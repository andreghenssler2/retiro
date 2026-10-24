<?php

declare(strict_types=1);

require_once '../../../config/settings.php';

Middleware::auth();

header('Content-Type: application/json; charset=utf-8');

$retorno = [
    'status' => false,
    'msg' => 'Não foi possível atualizar a presença.'
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
    $retorno['msg'] = 'Token de segurança inválido. Atualize a página.';
    echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
    exit;
}

$idInscricao = max(0, (int) ($_POST['id'] ?? 0));
$presente = (string) ($_POST['presente'] ?? '0') === '1';

if ($idInscricao <= 0) {
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
    $resultado = $credenciamento->registrarPresenca(
        $idInscricao,
        $presente,
        $idResponsavel
    );

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
        'Erro ao salvar credenciamento: '
        . $erro->getMessage()
        . ' | Linha: '
        . $erro->getLine()
    );

    http_response_code(500);
}

echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
