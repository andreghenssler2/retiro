<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';
require_once __DIR__ . '/DeployRecoveryValidator.php';
require_once __DIR__ . '/DatabaseBackupValidator.php';
require_once dirname(__DIR__, 2) . '/config/ModoManutencao.php';
DeployUtil::exigirCli();

$args = DeployUtil::args($argv);
$zipPath = (string) ($args['zip'] ?? '');
$backupDir = (string) ($args['backup-dir'] ?? '');
$confirm = (string) ($args['confirm'] ?? '');
$migrar = isset($args['migrate']);
$manterManutencao = isset($args['keep-maintenance']);
$dbBackup = (string) ($args['db-backup'] ?? '');
$dbBackupConfirm = strtoupper(
    trim(
        (string) (
            $args['db-backup-confirm']
            ?? ''
        )
    )
);

if ($zipPath === '' || $backupDir === '' || $confirm !== 'DEPLOY') {
    DeployUtil::erro(
        'Uso: php tools/deploy/aplicar-release.php '
        . '--zip=/caminho/release.zip '
        . '--backup-dir=/caminho/fora/do/site '
        . '--confirm=DEPLOY [--migrate '
        . '--db-backup=/caminho/backup.sql.gz '
        . '| --db-backup-confirm=CPANEL] '
        . '[--keep-maintenance]'
    );
}

$raiz = DeployUtil::raiz();

/* GATE_PRE_DEPLOY_FASE14 */
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

echo '[INFO] Executando preflight antes do deploy...' . PHP_EOL;
$preflight = $rodarPhp(
    $raiz . '/tools/deploy/preflight.php'
);

if ($preflight['codigo'] !== 0) {
    DeployUtil::erro(
        'Preflight falhou. Nenhum arquivo foi alterado.'
    );
}

echo '[INFO] Executando ensaio da release...' . PHP_EOL;
$ensaio = $rodarPhp(
    $raiz . '/tools/deploy/ensaio-release.php',
    [
        '--zip=' . $zipPath
    ]
);

if ($ensaio['codigo'] !== 0) {
    DeployUtil::erro(
        'Ensaio da release falhou. Nenhum arquivo foi alterado.'
    );
}

if ($migrar) {
    if ($dbBackup !== '') {
        try {
            $resultadoDb =
                DatabaseBackupValidator::verificar(
                    $raiz,
                    $dbBackup
                );
        } catch (Throwable $erro) {
            DeployUtil::erro(
                'Backup de banco inválido: '
                . $erro->getMessage()
            );
        }

        echo '[OK] Backup de banco verificado: '
            . $resultadoDb['path']
            . PHP_EOL;
        echo '[OK] SHA-256 banco: '
            . $resultadoDb['sha256']
            . PHP_EOL;
    } elseif ($dbBackupConfirm === 'CPANEL') {
        echo '[AVISO] Backup do banco confirmado via cPanel/phpMyAdmin; '
            . 'o arquivo não foi validado pelo PHP.'
            . PHP_EOL;
    } else {
        DeployUtil::erro(
            'Deploy com --migrate exige --db-backup=/caminho/arquivo '
            . 'ou --db-backup-confirm=CPANEL.'
        );
    }
}

$nova = DeployUtil::verificarRelease($zipPath);
$backup = DeployUtil::backupCodigo($backupDir);

try {
    $backupValidado =
        DeployRecoveryValidator::verificarBackupCodigo(
            $backup['zip']
        );
} catch (Throwable $erro) {
    DeployUtil::erro(
        'Backup de código recém-criado é inválido: '
        . $erro->getMessage()
    );
}

echo '[OK] Backup de código validado: '
    . $backupValidado['zipPath']
    . PHP_EOL;

/* MODO_MANUTENCAO_DEPLOY_V1 */
try {
    ModoManutencao::ativar(
        $raiz,
        'Deploy em andamento.',
        null
    );
} catch (Throwable $erro) {
    DeployUtil::erro(
        'Não foi possível ativar manutenção: '
        . $erro->getMessage()
    );
}
echo '[OK] Modo de manutenção ativado.' . PHP_EOL;

$temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'retiro-deploy-' . bin2hex(random_bytes(6));

if (!mkdir($temp, 0750, true)) {
    DeployUtil::erro('Não foi possível criar staging temporário.');
}

$removerTemp = static function (string $dir): void {
    if (!is_dir($dir)) {
        return;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $item) {
        if ($item->isDir() && !$item->isLink()) {
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($dir);
};

try {
    $zip = new ZipArchive();

    if ($zip->open($nova['zipPath']) !== true || !$zip->extractTo($temp)) {
        DeployUtil::erro('Falha ao extrair release para staging.');
    }

    $zip->close();

    $oldPaths = [];
    $oldManifestPath = $raiz . '/' . DeployUtil::RELEASE_MANIFEST;

    if (is_file($oldManifestPath)) {
        $old = json_decode((string) file_get_contents($oldManifestPath), true);

        if (is_array($old) && isset($old['files']) && is_array($old['files'])) {
            foreach ($old['files'] as $item) {
                if (is_array($item) && isset($item['path'])) {
                    $oldPaths[] = DeployUtil::normalizar((string) $item['path']);
                }
            }
        }
    }

    $newPaths = [];
    foreach ($nova['files'] as $item) {
        $newPaths[] = DeployUtil::normalizar((string) $item['path']);
    }

    if ($oldPaths !== []) {
        foreach (array_diff(array_unique($oldPaths), array_unique($newPaths)) as $stale) {
            if (!DeployUtil::caminhoSeguro($stale) || DeployUtil::protegido($stale)) {
                continue;
            }

            $abs = $raiz . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $stale);
            if (is_file($abs) || is_link($abs)) {
                @unlink($abs);
            }
        }
    }

    foreach ($nova['files'] as $item) {
        $path = DeployUtil::normalizar((string) $item['path']);

        if (DeployUtil::protegido($path)) {
            DeployUtil::erro('Release tentou escrever caminho protegido: ' . $path);
        }

        $origem = $temp . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $destino = $raiz . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        DeployUtil::copiar($origem, $destino);

        if (!hash_equals((string) $item['sha256'], hash_file('sha256', $destino))) {
            DeployUtil::erro('Falha de integridade após copiar: ' . $path);
        }
    }

    DeployUtil::copiar(
        $temp . DIRECTORY_SEPARATOR . DeployUtil::RELEASE_MANIFEST,
        $raiz . DIRECTORY_SEPARATOR . DeployUtil::RELEASE_MANIFEST
    );
} finally {
    $removerTemp($temp);
}

echo "======================================" . PHP_EOL;
echo "DEPLOY DE PRODUÇÃO" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo '[OK] Release aplicada: ' . (string) ($nova['version'] ?? 'desconhecida') . PHP_EOL;
echo '[OK] Backup de código: ' . $backup['zip'] . PHP_EOL;

if ($migrar) {
    echo '[INFO] Aplicando migrations...' . PHP_EOL;

    $migration = $rodarPhp(
        $raiz . '/database/migrate.php',
        ['migrate']
    );

    if ($migration['codigo'] !== 0) {
        echo '[ERRO] Migration falhou. '
            . 'Manutenção permanece ATIVA.'
            . PHP_EOL;
        exit(1);
    }

    echo '[OK] Migrations concluídas.' . PHP_EOL;
} else {
    /* STATUS_MIGRATIONS_FASE14 */
    echo '[INFO] Conferindo migrations após aplicar o código...' . PHP_EOL;

    $statusMigration = $rodarPhp(
        $raiz . '/database/migrate.php',
        ['status']
    );

    if ($statusMigration['codigo'] !== 0) {
        echo '[ERRO] Não foi possível consultar migrations. '
            . 'Manutenção permanece ATIVA.'
            . PHP_EOL;
        exit(1);
    }

    $pendente = false;
    $alterada = false;

    foreach ($statusMigration['saida'] as $linha) {
        if (str_contains((string) $linha, '[PENDENTE]')) {
            $pendente = true;
        }

        if (str_contains((string) $linha, '[ALTERADA]')) {
            $alterada = true;
        }
    }

    if ($alterada) {
        echo '[ERRO] Existe migration aplicada com checksum alterado. '
            . 'Manutenção permanece ATIVA.'
            . PHP_EOL;
        exit(1);
    }

    if ($pendente) {
        echo '[ERRO] A nova release possui migration pendente e '
            . '--migrate não foi informado.'
            . PHP_EOL;
        echo '[ATENÇÃO] Manutenção permanece ATIVA.'
            . PHP_EOL;
        echo 'Confirme o backup do banco antes de executar migrations.'
            . PHP_EOL;
        exit(1);
    }

    echo '[OK] Não há migrations pendentes.' . PHP_EOL;
}

echo '[INFO] Executando smoke test...' . PHP_EOL;

$smoke = $rodarPhp(
    $raiz . '/tools/smoke-test.php'
);

if ($smoke['codigo'] !== 0) {
    echo '[ERRO] Smoke test falhou após o deploy. '
        . 'Manutenção permanece ATIVA.'
        . PHP_EOL;
    exit(1);
}

echo '[OK] Smoke test concluído.' . PHP_EOL;
/* KEEP_MAINTENANCE_FASE15 */
if ($manterManutencao) {
    echo '[OK] Validações internas concluídas. '
        . 'Manutenção permanece ATIVA para validação final.'
        . PHP_EOL;
} else {
    try {
        ModoManutencao::desativar($raiz);
    } catch (Throwable $erro) {
        DeployUtil::erro(
            'Deploy aplicado, mas não foi possível desativar manutenção: '
            . $erro->getMessage()
        );
    }

    echo '[OK] Modo de manutenção desativado.'
        . PHP_EOL;
}
echo '[INFO] Rollback de código, se necessário:' . PHP_EOL;
echo 'php tools/deploy/rollback-codigo.php --backup=' . escapeshellarg($backup['zip']) . ' --confirm=ROLLBACK' . PHP_EOL;
