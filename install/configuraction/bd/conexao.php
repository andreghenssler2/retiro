<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit;

}



$dados = [
    '$host'      => $_POST['host'],
    '$nomebanco' => $_POST['nomebanco'],
    '$user'      => $_POST['user'],
    '$senha'     => $_POST['senha'] ?? '',
    '$porta'     => $_POST['porta'] ?? '',
];

$conteudo = "<?php \n";

foreach ($dados as $chave => $valor) {

    $conteudo .= "{$chave}= '{$valor}';" . PHP_EOL;

}

$conteudo .= "?>";



$arquivo = "../../padroes.php";

$arquivo = fopen($arquivo ,'a');

// SOBRESCREVE o arquivo a cada clique

// file_put_contents($arquivo, $conteudo);

fwrite($arquivo, mb_convert_encoding($conteudo, 'UTF-8', 'auto'));

fclose($arquivo);

echo "Padroes alterados com sucesso.";

