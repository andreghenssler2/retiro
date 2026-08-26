<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';
require_once dirname(__DIR__, 2) . '/config/ModoManutencao.php';

DeployUtil::exigirCli();

$args = DeployUtil::args($argv);

$zipPath = (string) ($args['zip'] ?? '');
$backupDir = (string) ($args['backup-dir'] ?? '');
$dbRotated = strtoupper(
    trim(
        (string) (
            $args['db-rotated']
            ?? ''
        )
    )
);
$dbBackup = (string) ($args['db-backup'] ?? '');
$dbBackupConfirm = strtoupper(
    trim(
        (string) (
            $args['db-backup-confirm']
            ?? ''
        )
    )
);
$migrar = isset($args['migrate']);
$executar = isset($args['execute']);
$confirm = (string) ($args['confirm'] ?? '');

if (
    $zipPath === ''
    || $backupDir === ''
    || $dbRotated !== 'SIM'
    || (
        $dbBackup === ''
        && $dbBackupConfirm !== 'CPANEL'
    )
) {
    DeployUtil::erro(
        'Uso: php tools/deploy/deploy-producao.php '
        . '--zip=/caminho/release.zip '
        . '--backup-dir=/caminho/fora/do/site '
        . '--db-rotated=SIM '
        . '[--db-backup=/caminho/backup.sql.gz '
        . '| --db-backup-confirm=CPANEL] '
        . '[--migrate] '
        . '[--execute --confirm=DEPLOY-PRODUCAO]'
    );
}

$raiz = DeployUtil::raiz();

/**
 * @return array{codigo:int,saida:array<int,string>}
 */
$rodarPhp = static function (
    string $arquivo,
    array $argumentos = []
): array {
    $cmd =
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($arquivo);

    foreach ($argumentos as $argumento) {
        $cmd .= ' '
            . escapeshellarg(
                (string) $argumento
            );
    }

    $saida = [];
    $codigo = 0;

    exec(
        $cmd . ' 2>&1',
        $saida,
        $codigo
    );

    foreach ($saida as $linha) {
        echo $linha . PHP_EOL;
    }

    return [
        'codigo' => $codigo,
        'saida' => $saida
    ];
};

try {
    $manifest = DeployUtil::verificarRelease(
        $zipPath
    );
} catch (Throwable $erro) {
    DeployUtil::erro(
        'Release inválida: '
        . $erro->getMessage()
    );
}

echo "======================================" . PHP_EOL;
echo "DEPLOY DE PRODUÇÃO CONTROLADO" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo '[INFO] Versão: '
    . (string) ($manifest['version'] ?? 'desconhecida')
    . PHP_EOL;
echo '[INFO] Build: '
    . (int) ($manifest['build'] ?? 0)
    . PHP_EOL;
echo '[INFO] Commit: '
    . (string) ($manifest['commit'] ?? 'não informado')
    . PHP_EOL;
echo '[INFO] Migrations: '
    . ($migrar ? 'SIM' : 'NÃO')
    . PHP_EOL;
echo '[INFO] Modo: '
    . ($executar ? 'EXECUÇÃO' : 'PLANO')
    . PHP_EOL;
echo PHP_EOL;

/*
|--------------------------------------------------------------------------
| Gate obrigatório de prontidão
|--------------------------------------------------------------------------
*/

echo '[INFO] Executando gate de prontidão...' . PHP_EOL;

$prontidaoArgs = [
    '--zip=' . $zipPath,
    '--backup-dir=' . $backupDir,
    '--db-rotated=SIM'
];

if ($dbBackup !== '') {
    $prontidaoArgs[] =
        '--db-backup=' . $dbBackup;
} else {
    $prontidaoArgs[] =
        '--db-backup-confirm=CPANEL';
}

$prontidao = $rodarPhp(
    $raiz
    . '/tools/deploy/prontidao-producao.php',
    $prontidaoArgs
);

if ($prontidao['codigo'] !== 0) {
    DeployUtil::erro(
        'Gate de prontidão falhou. Deploy bloqueado.'
    );
}

/*
|--------------------------------------------------------------------------
| Conferência explícita de migrations antes de qualquer execução
|--------------------------------------------------------------------------
*/

echo '[INFO] Conferindo migrations atuais...' . PHP_EOL;

$statusMigration = $rodarPhp(
    $raiz . '/database/migrate.php',
    ['status']
);

if ($statusMigration['codigo'] !== 0) {
    DeployUtil::erro(
        'Não foi possível consultar migrations.'
    );
}

$pendente = false;
$alterada = false;

foreach ($statusMigration['saida'] as $linha) {
    if (
        str_contains(
            (string) $linha,
            '[PENDENTE]'
        )
    ) {
        $pendente = true;
    }

    if (
        str_contains(
            (string) $linha,
            '[ALTERADA]'
        )
    ) {
        $alterada = true;
    }
}

if ($alterada) {
    DeployUtil::erro(
        'Existe migration aplicada com checksum alterado.'
    );
}

if ($pendente && !$migrar) {
    DeployUtil::erro(
        'Há migration pendente. Refaça o plano com --migrate '
        . 'e evidência válida de backup do banco.'
    );
}

echo PHP_EOL;
echo "======================================" . PHP_EOL;
echo "PLANO APROVADO" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo '[OK] Release validada.' . PHP_EOL;
echo '[OK] Prontidão validada.' . PHP_EOL;
echo '[OK] Estado de migrations validado.' . PHP_EOL;
echo '[OK] Backup do banco informado/confirmado.' . PHP_EOL;
echo '[OK] Diretório de backup de código informado.' . PHP_EOL;

if (!$executar) {
    echo PHP_EOL;
    echo '[PLANO] Nenhum arquivo de produção foi alterado.'
        . PHP_EOL;
    echo '[PLANO] Para executar de fato, repita o comando '
        . 'com --execute --confirm=DEPLOY-PRODUCAO.'
        . PHP_EOL;
    exit(0);
}

if ($confirm !== 'DEPLOY-PRODUCAO') {
    DeployUtil::erro(
        'Execução exige --confirm=DEPLOY-PRODUCAO.'
    );
}

/*
|--------------------------------------------------------------------------
| Execução controlada
|--------------------------------------------------------------------------
*/

echo PHP_EOL;
echo '[EXECUÇÃO] Iniciando aplicar-release com manutenção preservada...'
    . PHP_EOL;

$applyArgs = [
    '--zip=' . $zipPath,
    '--backup-dir=' . $backupDir,
    '--confirm=DEPLOY',
    '--keep-maintenance'
];

if ($migrar) {
    $applyArgs[] = '--migrate';

    if ($dbBackup !== '') {
        $applyArgs[] =
            '--db-backup=' . $dbBackup;
    } else {
        $applyArgs[] =
            '--db-backup-confirm=CPANEL';
    }
}

$aplicar = $rodarPhp(
    $raiz
    . '/tools/deploy/aplicar-release.php',
    $applyArgs
);

if ($aplicar['codigo'] !== 0) {
    echo PHP_EOL;
    echo '[BLOQUEADO] aplicar-release falhou.'
        . PHP_EOL;
    echo '[ATENÇÃO] Não desligue manutenção sem diagnosticar a falha.'
        . PHP_EOL;
    exit(1);
}

/*
|--------------------------------------------------------------------------
| Descobrir backup de código gerado para orientar rollback manual
|--------------------------------------------------------------------------
*/

$backupCodigo = null;

foreach ($aplicar['saida'] as $linha) {
    $prefixo =
        '[OK] Backup de código: ';

    if (
        str_starts_with(
            (string) $linha,
            $prefixo
        )
    ) {
        $backupCodigo = trim(
            substr(
                (string) $linha,
                strlen($prefixo)
            )
        );
    }
}

/*
|--------------------------------------------------------------------------
| Validação final ainda com manutenção ativa
|--------------------------------------------------------------------------
*/

echo PHP_EOL;
echo '[INFO] Executando validação final com manutenção ainda ativa...'
    . PHP_EOL;

$pos = $rodarPhp(
    $raiz
    . '/tools/deploy/validar-pos-deploy.php',
    [
        '--expect-version='
            . (string) (
                $manifest['version']
                ?? ''
            ),
        '--expect-commit='
            . (string) (
                $manifest['commit']
                ?? ''
            ),
        '--expect-maintenance=on'
    ]
);

if ($pos['codigo'] !== 0) {
    try {
        $status =
            ModoManutencao::status($raiz);

        if (!$status['ativo']) {
            ModoManutencao::ativar(
                $raiz,
                'Validação pós-deploy falhou.',
                null
            );
        }
    } catch (Throwable $erro) {
        echo '[ERRO] Falha ao garantir manutenção ativa: '
            . $erro->getMessage()
            . PHP_EOL;
    }

    echo PHP_EOL;
    echo '[BLOQUEADO] Validação pós-deploy falhou.'
        . PHP_EOL;
    echo '[ATENÇÃO] Manutenção deve permanecer ATIVA.'
        . PHP_EOL;
    echo '[ATENÇÃO] Rollback de código NÃO restaura migrations.'
        . PHP_EOL;

    if (
        is_string($backupCodigo)
        && $backupCodigo !== ''
    ) {
        echo '[INFO] Rollback de código disponível:'
            . PHP_EOL;
        echo 'php tools/deploy/rollback-codigo.php --backup='
            . escapeshellarg($backupCodigo)
            . ' --confirm=ROLLBACK'
            . PHP_EOL;
    }

    exit(1);
}

/*
|--------------------------------------------------------------------------
| Único ponto em que o wrapper reabre o site
|--------------------------------------------------------------------------
*/

try {
    ModoManutencao::desativar($raiz);
} catch (Throwable $erro) {
    DeployUtil::erro(
        'Tudo validado, mas não foi possível desativar manutenção: '
        . $erro->getMessage()
    );
}

echo PHP_EOL;
echo "======================================" . PHP_EOL;
echo "DEPLOY CONCLUÍDO" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo '[OK] Release aplicada.' . PHP_EOL;
echo '[OK] Migrations consistentes.' . PHP_EOL;
echo '[OK] Smoke test passou.' . PHP_EOL;
echo '[OK] Dados persistentes auditados.' . PHP_EOL;
echo '[OK] Validação final passou.' . PHP_EOL;
echo '[OK] Modo de manutenção desativado.' . PHP_EOL;

if (
    is_string($backupCodigo)
    && $backupCodigo !== ''
) {
    echo '[INFO] Backup de código: '
        . $backupCodigo
        . PHP_EOL;
}

echo '[INFO] Monitore login, eventos, inscrições, financeiro, '
    . 'webhook e cron após o deploy.'
    . PHP_EOL;
