<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';
require_once __DIR__ . '/DeployRecoveryValidator.php';

DeployUtil::exigirCli();

$args = DeployUtil::args($argv);
$backup = (string) ($args['backup'] ?? '');

if ($backup === '') {
    DeployUtil::erro(
        'Uso: php tools/deploy/verificar-backup-codigo.php '
        . '--backup=/caminho/retiro-codigo-....zip'
    );
}

try {
    $resultado =
        DeployRecoveryValidator::verificarBackupCodigo(
            $backup
        );
} catch (Throwable $erro) {
    DeployUtil::erro($erro->getMessage());
}

echo "======================================" . PHP_EOL;
echo "VERIFICAR BACKUP DE CÓDIGO" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo '[OK] Arquivo: '
    . $resultado['zipPath']
    . PHP_EOL;
echo '[OK] Arquivos: '
    . $resultado['fileCount']
    . PHP_EOL;
echo '[OK] SHA-256: '
    . $resultado['zipSha256']
    . PHP_EOL;

if ($resultado['createdAt'] !== null) {
    echo '[OK] Criado em: '
        . $resultado['createdAt']
        . PHP_EOL;
}

echo '[OK] Manifesto, caminhos, tamanhos e checksums validados.'
    . PHP_EOL;
echo '[OK] Nenhum arquivo protegido foi incluído.'
    . PHP_EOL;
