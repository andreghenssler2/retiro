<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';
require_once __DIR__ . '/DatabaseBackupValidator.php';
require_once dirname(__DIR__, 2) . '/config/ModoManutencao.php';

DeployUtil::exigirCli();

$args = DeployUtil::args($argv);

$zipPath = (string) ($args['zip'] ?? '');
$backupDir = (string) ($args['backup-dir'] ?? '');
$dbBackup = (string) ($args['db-backup'] ?? '');
$dbBackupConfirm = strtoupper(
    trim(
        (string) (
            $args['db-backup-confirm']
            ?? ''
        )
    )
);
$dbRotated = strtoupper(
    trim(
        (string) (
            $args['db-rotated']
            ?? ''
        )
    )
);

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
        'Uso: php tools/deploy/prontidao-producao.php '
        . '--zip=/caminho/release.zip '
        . '--backup-dir=/caminho/fora/do/site '
        . '--db-rotated=SIM '
        . '[--db-backup=/caminho/backup.sql.gz '
        . '| --db-backup-confirm=CPANEL]'
    );
}

$raiz = DeployUtil::raiz();

$ok = [];
$avisos = [];
$erros = [];

$addOk = static function (
    string $mensagem
) use (&$ok): void {
    $ok[] = $mensagem;
};

$addAviso = static function (
    string $mensagem
) use (&$avisos): void {
    $avisos[] = $mensagem;
};

$addErro = static function (
    string $mensagem
) use (&$erros): void {
    $erros[] = $mensagem;
};

$executarPhp = static function (
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

    return [
        'codigo' => $codigo,
        'saida' => $saida
    ];
};

$addOk(
    'Rotação da credencial de banco confirmada pelo operador'
);

foreach (
    [
        'config/conn.php',
        'config/integracoes.php',
        'config/.bancario.key',
        'lib/vendor/autoload.php',
        'tools/deploy/aplicar-release.php',
        'tools/deploy/rollback-codigo.php',
        'tools/deploy/ensaio-release.php',
        'tools/deploy/verificar-backup-codigo.php'
    ]
    as $relativo
) {
    $arquivo =
        $raiz
        . DIRECTORY_SEPARATOR
        . str_replace(
            '/',
            DIRECTORY_SEPARATOR,
            $relativo
        );

    if (is_file($arquivo)) {
        $addOk(
            'Presente: ' . $relativo
        );
    } else {
        $addErro(
            'Arquivo obrigatório ausente: '
            . $relativo
        );
    }
}

foreach (
    [
        'json',
        'pdo',
        'pdo_mysql',
        'zip',
        'fileinfo',
        'openssl'
    ]
    as $ext
) {
    if (extension_loaded($ext)) {
        $addOk(
            'Extensão PHP: ' . $ext
        );
    } else {
        $addErro(
            'Extensão PHP ausente: ' . $ext
        );
    }
}

$statusManutencao =
    ModoManutencao::status($raiz);

if ($statusManutencao['ativo']) {
    $addErro(
        'Modo de manutenção já está ativo antes do deploy'
    );
} else {
    $addOk(
        'Modo de manutenção está inativo antes do deploy'
    );
}

try {
    $destinoBackup =
        DeployUtil::destinoExterno(
            $backupDir
        );

    $addOk(
        'Diretório de backup de código é externo: '
        . $destinoBackup
    );
} catch (Throwable $erro) {
    $addErro(
        'Diretório de backup inválido: '
        . $erro->getMessage()
    );
}

if ($dbBackup !== '') {
    try {
        $resultadoDb =
            DatabaseBackupValidator::verificar(
                $raiz,
                $dbBackup
            );

        $addOk(
            'Backup de banco verificado: '
            . $resultadoDb['path']
        );

        $addOk(
            'SHA-256 do backup de banco: '
            . $resultadoDb['sha256']
        );

        if (!$resultadoDb['sidecar']) {
            $addAviso(
                'Backup de banco não possui sidecar .sha256'
            );
        }
    } catch (Throwable $erro) {
        $addErro(
            'Backup de banco inválido: '
            . $erro->getMessage()
        );
    }
} else {
    $addOk(
        'Backup externo do banco confirmado via cPanel/phpMyAdmin'
    );

    $addAviso(
        'O arquivo do backup de banco não pôde ser validado automaticamente'
    );
}

$resultadoPreflight =
    $executarPhp(
        $raiz
        . '/tools/deploy/preflight.php'
    );

if ($resultadoPreflight['codigo'] === 0) {
    $addOk(
        'Preflight de produção passou'
    );
} else {
    $addErro(
        'Preflight falhou: '
        . implode(
            ' | ',
            $resultadoPreflight['saida']
        )
    );
}

$resultadoEnsaio =
    $executarPhp(
        $raiz
        . '/tools/deploy/ensaio-release.php',
        [
            '--zip=' . $zipPath
        ]
    );

if ($resultadoEnsaio['codigo'] === 0) {
    $addOk(
        'Ensaio da release passou no servidor atual'
    );
} else {
    $addErro(
        'Ensaio da release falhou: '
        . implode(
            ' | ',
            $resultadoEnsaio['saida']
        )
    );
}

echo "======================================" . PHP_EOL;
echo "PRONTIDÃO PARA PRODUÇÃO" . PHP_EOL;
echo "======================================" . PHP_EOL;

foreach ($ok as $mensagem) {
    echo '[OK] ' . $mensagem . PHP_EOL;
}

foreach ($avisos as $mensagem) {
    echo '[AVISO] ' . $mensagem . PHP_EOL;
}

foreach ($erros as $mensagem) {
    echo '[ERRO] ' . $mensagem . PHP_EOL;
}

echo PHP_EOL;
echo 'OK: ' . count($ok) . PHP_EOL;
echo 'AVISOS: ' . count($avisos) . PHP_EOL;
echo 'ERROS: ' . count($erros) . PHP_EOL;

if ($erros !== []) {
    echo PHP_EOL;
    echo '[BLOQUEADO] Não execute o deploy.'
        . PHP_EOL;
    exit(1);
}

echo PHP_EOL;
echo '[PRONTO] Gates técnicos concluídos.'
    . PHP_EOL;
echo '[INFO] Este comando NÃO executou deploy nem migration.'
    . PHP_EOL;
echo '[INFO] O aplicar-release executará novamente '
    . 'preflight e ensaio antes de alterar o código.'
    . PHP_EOL;

exit(0);
