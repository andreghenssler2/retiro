<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';
require_once dirname(__DIR__, 2) . '/config/ModoManutencao.php';
DeployUtil::exigirCli();

$args = DeployUtil::args($argv);
$backup = (string) ($args['backup'] ?? '');
$confirm = (string) ($args['confirm'] ?? '');

if ($backup === '' || $confirm !== 'ROLLBACK') {
    DeployUtil::erro(
        'Uso: php tools/deploy/rollback-codigo.php '
        . '--backup=/caminho/retiro-codigo-....zip --confirm=ROLLBACK'
    );
}

if (!class_exists('ZipArchive')) {
    DeployUtil::erro('Extensão ZipArchive não disponível.');
}

$real = realpath($backup);

if ($real === false || !is_file($real)) {
    DeployUtil::erro('Backup não encontrado.');
}

$zip = new ZipArchive();

if ($zip->open($real) !== true) {
    DeployUtil::erro('Não foi possível abrir o backup.');
}

try {
    $raw = $zip->getFromName(DeployUtil::BACKUP_MANIFEST);
    $manifest = is_string($raw) ? json_decode($raw, true) : null;

    if (!is_array($manifest) || !isset($manifest['files']) || !is_array($manifest['files'])) {
        DeployUtil::erro('BACKUP-MANIFEST.json inválido.');
    }

    foreach ($manifest['files'] as $item) {
        if (!is_array($item) || !isset($item['path'], $item['sha256'], $item['size'])) {
            DeployUtil::erro('Entrada inválida no backup.');
        }

        $path = DeployUtil::normalizar((string) $item['path']);

        if (!DeployUtil::caminhoSeguro($path) || DeployUtil::protegido($path)) {
            DeployUtil::erro('Caminho proibido no backup: ' . $path);
        }

        $content = $zip->getFromName($path);

        if (!is_string($content)) {
            DeployUtil::erro('Arquivo ausente no backup: ' . $path);
        }

        if ((int) $item['size'] !== strlen($content)) {
            DeployUtil::erro('Tamanho inválido no backup: ' . $path);
        }

        if (!hash_equals((string) $item['sha256'], hash('sha256', $content))) {
            DeployUtil::erro('Checksum inválido no backup: ' . $path);
        }
    }

    $raiz = DeployUtil::raiz();

/* MODO_MANUTENCAO_ROLLBACK_V1 */
try {
    ModoManutencao::ativar(
        $raiz,
        'Rollback de código em andamento.',
        null
    );
} catch (Throwable $erro) {
    DeployUtil::erro(
        'Não foi possível ativar manutenção para rollback: '
        . $erro->getMessage()
    );
}
    $atuais = [];
    $manifestAtualPath = $raiz . '/' . DeployUtil::RELEASE_MANIFEST;

    if (is_file($manifestAtualPath)) {
        $atual = json_decode((string) file_get_contents($manifestAtualPath), true);

        if (is_array($atual) && isset($atual['files']) && is_array($atual['files'])) {
            foreach ($atual['files'] as $item) {
                if (is_array($item) && isset($item['path'])) {
                    $atuais[] = DeployUtil::normalizar((string) $item['path']);
                }
            }
            $atuais[] = DeployUtil::RELEASE_MANIFEST;
        }
    }

    $anteriores = [];

    foreach ($manifest['files'] as $item) {
        $anteriores[] = DeployUtil::normalizar((string) $item['path']);
    }

    foreach (array_diff(array_unique($atuais), array_unique($anteriores)) as $stale) {
        if (DeployUtil::protegido($stale) || !DeployUtil::caminhoSeguro($stale)) {
            continue;
        }

        $abs = $raiz . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $stale);
        if (is_file($abs) || is_link($abs)) {
            @unlink($abs);
        }
    }

    foreach ($manifest['files'] as $item) {
        $path = DeployUtil::normalizar((string) $item['path']);
        $content = $zip->getFromName($path);
        $dest = $raiz . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $dir = dirname($dest);

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            DeployUtil::erro('Falha ao criar diretório: ' . $dir);
        }

        if (!is_string($content) || file_put_contents($dest, $content) === false) {
            DeployUtil::erro('Falha ao restaurar: ' . $path);
        }
    }
} finally {
    $zip->close();
}

echo "======================================" . PHP_EOL;
echo "ROLLBACK DE CÓDIGO" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo '[OK] Código restaurado.' . PHP_EOL;
echo '[ATENÇÃO] Banco de dados NÃO foi restaurado.' . PHP_EOL;
echo 'Rode: php database/migrate.php status' . PHP_EOL;
echo 'Depois: php tools/smoke-test.php' . PHP_EOL;
echo '[ATENÇÃO] Manutenção continua ATIVA após rollback.' . PHP_EOL;
echo 'Após validar: php tools/deploy/manutencao.php off' . PHP_EOL;
