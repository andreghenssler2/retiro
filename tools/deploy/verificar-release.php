<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';
DeployUtil::exigirCli();

$args = DeployUtil::args($argv);
$zip = (string) ($args['zip'] ?? '');

if ($zip === '') {
    DeployUtil::erro('Uso: php tools/deploy/verificar-release.php --zip=/caminho/release.zip');
}

$m = DeployUtil::verificarRelease($zip);

echo "======================================" . PHP_EOL;
echo "VERIFICAR RELEASE" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo '[OK] Versão: ' . (string) ($m['version'] ?? 'desconhecida') . PHP_EOL;
echo '[OK] Build: ' . (int) ($m['build'] ?? 0) . PHP_EOL;
echo '[OK] Commit: ' . (string) ($m['commit'] ?? 'não informado') . PHP_EOL;
echo '[OK] Arquivos: ' . (int) ($m['fileCount'] ?? 0) . PHP_EOL;
echo '[OK] SHA-256: ' . (string) ($m['zipSha256'] ?? '') . PHP_EOL;
echo '[OK] Manifesto, checksums e segurança validados.' . PHP_EOL;
