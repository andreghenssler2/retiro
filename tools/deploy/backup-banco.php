<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';
DeployUtil::exigirCli();

$args = DeployUtil::args($argv);
$destino = (string) ($args['dest'] ?? '');

if ($destino === '') {
    DeployUtil::erro('Uso: php tools/deploy/backup-banco.php --dest=/caminho/fora/do/site');
}

$destino = DeployUtil::destinoExterno($destino);
$raiz = DeployUtil::raiz();
$configPath = $raiz . '/config/conn.php';

if (!is_file($configPath)) {
    DeployUtil::erro('config/conn.php não encontrado.');
}

$config = require $configPath;

if (!is_array($config)) {
    DeployUtil::erro('config/conn.php inválido.');
}

foreach (['host', 'database', 'username', 'password'] as $campo) {
    if (!array_key_exists($campo, $config)) {
        DeployUtil::erro('Campo ausente em config/conn.php: ' . $campo);
    }
}

$find = PHP_OS_FAMILY === 'Windows' ? 'where mysqldump' : 'command -v mysqldump';
$out = [];
$code = 0;
exec($find . ' 2>&1', $out, $code);

if ($code !== 0 || $out === []) {
    DeployUtil::erro('mysqldump não encontrado. Gere o backup pelo cPanel/phpMyAdmin.');
}

$mysqldump = trim((string) $out[0]);
$temp = tempnam(sys_get_temp_dir(), 'retiro-mysql-');

if ($temp === false) {
    DeployUtil::erro('Não foi possível criar configuração temporária do MySQL.');
}

$quote = static function (string $v): string {
    return '"' . str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', ''], $v) . '"';
};

$charset = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($config['charset'] ?? 'utf8mb4'));
$cfg = "[client]\n"
    . 'host=' . $quote((string) $config['host']) . "\n"
    . 'user=' . $quote((string) $config['username']) . "\n"
    . 'password=' . $quote((string) $config['password']) . "\n"
    . 'default-character-set=' . ($charset !== '' ? $charset : 'utf8mb4') . "\n";

if (file_put_contents($temp, $cfg) === false) {
    @unlink($temp);
    DeployUtil::erro('Falha ao criar configuração temporária.');
}

if (PHP_OS_FAMILY !== 'Windows') {
    @chmod($temp, 0600);
}

$sql = $destino . DIRECTORY_SEPARATOR . 'retiro-banco-' . gmdate('Ymd-His') . '.sql';
$cmd = escapeshellarg($mysqldump)
    . ' --defaults-extra-file=' . escapeshellarg($temp)
    . ' --single-transaction --quick --triggers --hex-blob --skip-lock-tables '
    . escapeshellarg((string) $config['database'])
    . ' > ' . escapeshellarg($sql);

try {
    $out = [];
    $code = 0;
    exec($cmd . ' 2>&1', $out, $code);

    if ($code !== 0 || !is_file($sql) || filesize($sql) === 0) {
        @unlink($sql);
        DeployUtil::erro('mysqldump falhou: ' . implode(' | ', $out));
    }
} finally {
    @unlink($temp);
}

$final = $sql;

if (function_exists('gzencode')) {
    $raw = file_get_contents($sql);

    if (is_string($raw)) {
        $gz = gzencode($raw, 6);

        if (is_string($gz) && file_put_contents($sql . '.gz', $gz) !== false) {
            @unlink($sql);
            $final = $sql . '.gz';
        }
    }
}

$sha = hash_file('sha256', $final);
file_put_contents($final . '.sha256', $sha . '  ' . basename($final) . PHP_EOL);

echo "======================================" . PHP_EOL;
echo "BACKUP DE BANCO" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo '[OK] Arquivo: ' . $final . PHP_EOL;
echo '[OK] SHA-256: ' . $sha . PHP_EOL;
echo '[ATENÇÃO] O dump contém dados sensíveis. Mantenha fora da pasta pública.' . PHP_EOL;
