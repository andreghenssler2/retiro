<?php

declare(strict_types=1);

require_once '../../config/settings.php';

Middleware::moderador();

$idCertificado = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$certificado = new Certificado();
$registro = $certificado->buscarEmitido($idCertificado);

if (!$registro) {
    http_response_code(404);
    exit('Certificado não encontrado.');
}

$service = new CertificadoService($certificado);
$arquivo = $service->arquivoAbsolutoDeRegistro($registro);

if (!$arquivo || !is_file($arquivo) || !is_readable($arquivo)) {
    http_response_code(404);
    exit('A cópia do certificado não foi localizada no servidor.');
}

$nome = preg_replace('/[^a-zA-Z0-9._-]+/', '-', (string) $registro['codigo']) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $nome . '"');
header('Content-Length: ' . filesize($arquivo));
header('X-Content-Type-Options: nosniff');
readfile($arquivo);
