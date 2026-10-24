<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';
DeployUtil::exigirCli();

$args = DeployUtil::args($argv);
$destino = (string) ($args['dest'] ?? '');

if ($destino === '') {
    DeployUtil::erro('Uso: php tools/deploy/backup-codigo.php --dest=/caminho/fora/do/site');
}

$r = DeployUtil::backupCodigo($destino);

echo "======================================" . PHP_EOL;
echo "BACKUP DE CÓDIGO" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo '[OK] Backup: ' . $r['zip'] . PHP_EOL;
echo '[OK] SHA-256: ' . $r['sha256'] . PHP_EOL;
echo '[OK] Arquivos: ' . $r['files'] . PHP_EOL;
echo '[OK] Credenciais e dados dinâmicos não foram incluídos.' . PHP_EOL;
