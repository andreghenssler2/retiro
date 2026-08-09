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
    $idCertificado = max(0, (int) ($_POST['id'] ?? 0));
    $motivo = trim((string) ($_POST['motivo'] ?? ''));
    $service = new CertificadoService();

    if (!$service->revogar(
        $idCertificado,
        $motivo,
        Auth::id() ?? 0
    )) {
        throw new RuntimeException('Não foi possível revogar o certificado.');
    }

    echo json_encode([
        'status' => true,
        'msg' => 'Certificado revogado com sucesso.'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $erro) {
    http_response_code(422);
    echo json_encode([
        'status' => false,
        'msg' => $erro->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
