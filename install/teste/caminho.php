<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'Método inválido'
    ]);
    exit;
}

$caminho = trim($_POST['caminho'] ?? '');
$caminho_pasta = trim($_POST['caminho_pasta'] ?? '');

if ($caminho === '' || $caminho_pasta === '') {
    echo json_encode([
        'status' => 'error',
        'message' => 'Campos obrigatórios em branco'
    ]);
    exit;
}

/**
 * 🔒 Segurança básica: evita ../ e barras estranhas
 */
$caminho_pasta = basename($caminho_pasta);

/**
 * Caminho final: caminho_sistema/upload/docs/nome_pasta
 */
$baseDir   = rtrim($caminho, '/\\');
$diretorio = $baseDir . DIRECTORY_SEPARATOR . 'upload' . DIRECTORY_SEPARATOR . 'docs' . DIRECTORY_SEPARATOR . $caminho_pasta;
/**
 * Salva padrões no arquivo
 */
$conteudo  = "<?php\n";
$conteudo .= "\$caminho_sistema = '{$baseDir}';\n";
$conteudo .= "\$caminho_pastas = '{$caminho_pasta}';\n";
$conteudo .= "?>";

$arquivo = "../padroes.php";
$arquivo = fopen($arquivo ,'a');
fwrite($arquivo, mb_convert_encoding($conteudo, 'UTF-8', 'auto'));
fclose($arquivo);

/**
 * Cria a pasta se não existir
*/

if (!is_dir($diretorio)) {
    if (!mkdir($diretorio, 0755, true)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Erro ao criar a pasta no servidor'
        ]);
        exit;
    }
}



echo json_encode([
    'status'  => 'ok',
    'message' => 'Pasta criada com sucesso em: upload/docs/' . $caminho_pasta
    // 'message1' => 'caminho_pasta ' . $caminho_pasta,
    // 'caminho' => 'caminho: ' . $caminho
]);
