<?php

$arquivo = __DIR__ . '/../version.php';
date_default_timezone_set('America/Sao_Paulo');

$config = require $arquivo;

// Incrementa o build
$config['build']++;

// Atualiza a data
$config['date'] = date('Y-m-d H:i:s');

// A cada 100 builds incrementa o PATCH
if ($config['build'] % 100 == 0) {

    [$major, $minor, $patch] = explode('.', $config['version']);

    $patch++;

    $config['version'] = "{$major}.{$minor}.{$patch}";
}

$conteudo = "<?php\n\nreturn " . var_export($config, true) . ";\n";

file_put_contents($arquivo, $conteudo);

echo "Nova versão: {$config['version']} (Build {$config['build']})\n";