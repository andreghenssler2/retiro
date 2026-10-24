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
    $idInscricao = max(0, (int) ($_POST['id'] ?? $_POST['idInscricao'] ?? 0));

    if ($idInscricao <= 0) {
        throw new InvalidArgumentException('Inscrição inválida.');
    }

    $service = new CertificadoService();
    $resultado = $service->emitirPorInscricao(
        $idInscricao,
        Auth::id() ?? 0
    );

    echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
} catch (Throwable $erro) {
    error_log('Erro ao emitir certificado: ' . $erro->getMessage());
    http_response_code($erro instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode([
        'status' => false,
        'msg' => $erro->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
