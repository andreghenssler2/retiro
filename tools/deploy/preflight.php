<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';
DeployUtil::exigirCli();

$raiz = DeployUtil::raiz();
$ok = [];
$avisos = [];
$erros = [];

$addOk = static function (string $m) use (&$ok): void { $ok[] = $m; };
$addAviso = static function (string $m) use (&$avisos): void { $avisos[] = $m; };
$addErro = static function (string $m) use (&$erros): void { $erros[] = $m; };

if (PHP_VERSION_ID >= 80200) {
    $addOk('PHP ' . PHP_VERSION);
} else {
    $addErro('PHP ' . PHP_VERSION . '; requer PHP 8.2+.');
}

foreach (['json', 'pdo', 'pdo_mysql', 'zip'] as $ext) {
    extension_loaded($ext)
        ? $addOk('Extensão ' . $ext)
        : $addErro('Extensão ausente: ' . $ext);
}

is_file($raiz . '/config/conn.php')
    ? $addOk('config/conn.php presente')
    : $addErro('config/conn.php ausente');

is_file($raiz . '/config/integracoes.php')
    ? $addOk('config/integracoes.php presente')
    : $addAviso('config/integracoes.php ausente');

is_file($raiz . '/config/.bancario.key')
    ? $addOk('config/.bancario.key presente')
    : $addAviso('config/.bancario.key ausente');

is_file($raiz . '/lib/vendor/autoload.php')
    ? $addOk('Composer vendor presente')
    : $addErro('lib/vendor/autoload.php ausente');

foreach (['logs', 'storage/certificados', 'uploads/certificados/modelos'] as $dir) {
    $path = $raiz . '/' . $dir;

    if (!is_dir($path)) {
        $addAviso($dir . ' não existe');
    } elseif (!is_writable($path)) {
        $addErro($dir . ' sem permissão de escrita');
    } else {
        $addOk($dir . ' gravável');
    }
}

$cmd = escapeshellarg(PHP_BINARY)
    . ' '
    . escapeshellarg($raiz . '/database/migrate.php')
    . ' status 2>&1';
$out = [];
$code = 0;
exec($cmd, $out, $code);

if ($code !== 0) {
    $addErro('Falha ao consultar migrations: ' . implode(' | ', $out));
} else {
    $addOk('Consulta de migrations executada');

    foreach ($out as $line) {
        if (str_contains((string) $line, '[ALTERADA]')) {
            $addErro('Existe migration aplicada com checksum alterado.');
        }

        if (str_contains((string) $line, '[PENDENTE]')) {
            $addAviso('Há migration pendente para o deploy.');
        }
    }
}

echo "======================================" . PHP_EOL;
echo "PREFLIGHT DE PRODUÇÃO" . PHP_EOL;
echo "======================================" . PHP_EOL;

foreach ($ok as $m) { echo '[OK] ' . $m . PHP_EOL; }
foreach ($avisos as $m) { echo '[AVISO] ' . $m . PHP_EOL; }
foreach ($erros as $m) { echo '[ERRO] ' . $m . PHP_EOL; }

echo PHP_EOL . 'OK: ' . count($ok) . PHP_EOL;
echo 'AVISOS: ' . count($avisos) . PHP_EOL;
echo 'ERROS: ' . count($erros) . PHP_EOL;

exit($erros === [] ? 0 : 1);
