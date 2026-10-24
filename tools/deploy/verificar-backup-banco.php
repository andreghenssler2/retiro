<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';
require_once __DIR__ . '/DatabaseBackupValidator.php';

DeployUtil::exigirCli();

$args = DeployUtil::args($argv);
$backup = (string) ($args['backup'] ?? '');

if ($backup === '') {
    DeployUtil::erro(
        'Uso: php tools/deploy/verificar-backup-banco.php '
        . '--backup=/caminho/backup.sql[.gz]'
    );
}

try {
    $resultado =
        DatabaseBackupValidator::verificar(
            DeployUtil::raiz(),
            $backup
        );
} catch (Throwable $erro) {
    DeployUtil::erro(
        $erro->getMessage()
    );
}

echo "======================================" . PHP_EOL;
echo "VERIFICAR BACKUP DE BANCO" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo '[OK] Arquivo: '
    . $resultado['path']
    . PHP_EOL;
echo '[OK] Tamanho: '
    . number_format(
        $resultado['size'],
        0,
        ',',
        '.'
    )
    . ' bytes'
    . PHP_EOL;
echo '[OK] SHA-256: '
    . $resultado['sha256']
    . PHP_EOL;

if ($resultado['sidecar']) {
    echo '[OK] Arquivo .sha256 conferido.'
        . PHP_EOL;
} else {
    echo '[AVISO] Não há .sha256 ao lado do backup; '
        . 'o hash acima foi calculado agora.'
        . PHP_EOL;
}

echo '[OK] Backup está fora da raiz pública do projeto.'
    . PHP_EOL;
