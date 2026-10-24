<?php

declare(strict_types=1);

require_once '../../config/settings.php';

Middleware::moderador();

$idModelo = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;

try {
    $service = new CertificadoService();
    $pdf = $service->gerarPreview($idModelo);

    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="previa-certificado.pdf"');
    header('Content-Length: ' . strlen($pdf));
    echo $pdf;
} catch (Throwable $erro) {
    http_response_code(500);
    echo htmlspecialchars($erro->getMessage(), ENT_QUOTES, 'UTF-8');
}
