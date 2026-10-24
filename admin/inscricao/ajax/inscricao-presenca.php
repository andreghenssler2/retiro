<?php

declare(strict_types=1);

require_once '../../../config/settings.php';

Middleware::auth();

header('Content-Type: application/json; charset=utf-8');

$retorno = [
    'status' => false,
    'msg' => ''
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $retorno['msg'] = 'Método de requisição inválido.';
    echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
    exit;
}

$token = (string) ($_POST['_token'] ?? '');

if ($token === '' || !Session::validateCsrf($token)) {
    http_response_code(403);
    $retorno['msg'] = 'Token de segurança inválido. Atualize a página e tente novamente.';
    echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
    exit;
}

$id = max(0, (int) ($_POST['id'] ?? 0));

if ($id <= 0) {
    http_response_code(422);
    $retorno['msg'] = 'Inscrição inválida.';
    echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $idResponsavel = (int) (
        $_SESSION['user']['id']
        ?? $_SESSION['usuario_id']
        ?? 0
    );

    $credenciamento = new Credenciamento($db);
    $resultado = $credenciamento->alternarPresenca($id, $idResponsavel);

    $retorno = [
        'status' => true,
        'msg' => $resultado['msg'],
        'presenca' => $resultado['presenca'],
        'presencaStatus' => $resultado['presencaStatus']
    ];
} catch (InvalidArgumentException|RuntimeException $erro) {
    http_response_code(422);
    $retorno['msg'] = $erro->getMessage();
} catch (Throwable $erro) {
    error_log(
        'Erro em inscricao-presenca.php: '
        . $erro->getMessage()
        . ' | Linha: '
        . $erro->getLine()
    );

    http_response_code(500);
    $retorno['msg'] = 'Não foi possível alterar a presença.';
}

echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
