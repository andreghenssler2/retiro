<?php

declare(strict_types=1);

require_once '../../../config/settings.php';

header('Content-Type: application/json; charset=UTF-8');

Middleware::moderador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'msg' => 'Método não permitido.']);
    exit;
}

if (!Session::validateCsrf($_POST['_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['status' => false, 'msg' => 'Token de segurança inválido.']);
    exit;
}

try {
    $idModelo = max(0, (int) ($_POST['id'] ?? 0));
    $certificado = new Certificado();

    if (!$certificado->excluirModelo($idModelo)) {
        throw new RuntimeException('Modelo não encontrado.');
    }

    echo json_encode([
        'status' => true,
        'msg' => 'Modelo excluído com sucesso.'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $erro) {
    http_response_code(422);
    echo json_encode([
        'status' => false,
        'msg' => $erro->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
