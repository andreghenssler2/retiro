<?php

declare(strict_types=1);

require_once '../../../config/settings.php';

Session::start();
Middleware::auth();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Método não permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Session::validateCsrf($_POST['_token'] ?? null)) {
    http_response_code(419);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Token de segurança inválido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $relatorio = (new RelatorioGeral($db))->gerar($_POST);
    echo json_encode(['sucesso' => true, 'relatorio' => $relatorio], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (InvalidArgumentException $erro) {
    http_response_code(422);
    echo json_encode(['sucesso' => false, 'mensagem' => $erro->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $erro) {
    error_log('Erro na central de relatórios: ' . $erro->getMessage());
    http_response_code(500);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Não foi possível gerar o relatório.'], JSON_UNESCAPED_UNICODE);
}
