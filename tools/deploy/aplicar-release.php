<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';
DeployUtil::exigirCli();

$args = DeployUtil::args($argv);
$zipPath = (string) ($args['zip'] ?? '');
$backupDir = (string) ($args['backup-dir'] ?? '');
$confirm = (string) ($args['confirm'] ?? '');
$migrar = isset($args['migrate']);

if ($zipPath === '' || $backupDir === '' || $confirm !== 'DEPLOY') {
    DeployUtil::erro(
        'Uso: php tools/deploy/aplicar-release.php '
        . '--zip=/caminho/release.zip '
        . '--backup-dir=/caminho/fora/do/site '
        . '--confirm=DEPLOY [--migrate]'
    );
}

$raiz = DeployUtil::raiz();
$nova = DeployUtil::verificarRelease($zipPath);
$backup = DeployUtil::backupCodigo($backupDir);

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
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($raiz . '/database/migrate.php') . ' migrate 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    foreach ($out as $line) { echo $line . PHP_EOL; }

    if ($code !== 0) {
        echo '[ERRO] Migration falhou. Não restaure banco sem o backup SQL correspondente.' . PHP_EOL;
        exit(1);
    }

    echo '[INFO] Executando smoke test...' . PHP_EOL;
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($raiz . '/tools/smoke-test.php') . ' 2>&1';
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    foreach ($out as $line) { echo $line . PHP_EOL; }

    if ($code !== 0) {
        echo '[ERRO] Smoke test falhou após o deploy.' . PHP_EOL;
        exit(1);
    }

    echo '[OK] Migrations e smoke test concluídos.' . PHP_EOL;
} else {
    echo '[INFO] Migrations NÃO foram executadas.' . PHP_EOL;
    echo 'Rode: php database/migrate.php status' . PHP_EOL;
    echo 'Depois do backup do banco: php database/migrate.php migrate' . PHP_EOL;
    echo 'E: php tools/smoke-test.php' . PHP_EOL;
}

echo '[INFO] Rollback de código, se necessário:' . PHP_EOL;
echo 'php tools/deploy/rollback-codigo.php --backup=' . escapeshellarg($backup['zip']) . ' --confirm=ROLLBACK' . PHP_EOL;
