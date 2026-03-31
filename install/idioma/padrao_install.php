<?php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit;
}

$dados = [
    
    '$lang'      => $_POST['idioma'],
    '$data_p'      => date('Y-m-d H:i:s'),
    '$host'      => '',

];

$conteudo = "<?php \n";
foreach ($dados as $chave => $valor) {
  
    $conteudo .= "{$chave}= '{$valor}';" . PHP_EOL;
   
}
 $conteudo .= "?>";

$arquivo = "../padroes.php";

// SOBRESCREVE o arquivo a cada clique
file_put_contents($arquivo, $conteudo);
echo "Padroes alterados com sucesso.";
