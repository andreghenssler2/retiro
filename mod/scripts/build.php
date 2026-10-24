<?php

$arquivo = __DIR__ . '/../version.php';
date_default_timezone_set('America/Sao_Paulo');

if (!file_exists($arquivo)) {
    exit("Arquivo version.php não encontrado.\n");
}

$config = require $arquivo;

// Incrementa versão (PATCH)
$versao = explode('.', $config['version']);

$major = (int) ($versao[0] ?? 0);
$minor = (int) ($versao[1] ?? 0);
$patch = (int) ($versao[2] ?? 0);

$patch++;

$config['version'] = sprintf(
    '%d.%d.%03d',
    $major,
    $minor,
    $patch
);

// Incrementa Build
$config['build'] = ((int) $config['build']) + 1;

// Atualiza informações
$config['date'] = date('Y-m-d H:i:s');
$config['commit'] = trim(shell_exec('git rev-parse --short HEAD'));
$config['branch'] = trim(shell_exec('git rev-parse --abbrev-ref HEAD'));

// Salva arquivo
$conteudo = "<?php\n\nreturn " . var_export($config, true) . ";\n";

file_put_contents($arquivo, $conteudo);

echo "=============================\n";
echo "Versão : {$config['version']}\n";
echo "Build  : {$config['build']}\n";
echo "Branch : {$config['branch']}\n";
echo "Commit : {$config['commit']}\n";
echo "=============================\n";