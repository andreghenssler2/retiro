<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/settings.php";

Session::start();
Auth::requireLogin();

if (!in_array(Auth::tipo(), [2, 3], true)) {
    http_response_code(403);
    exit("Acesso negado.");
}

$idUsuario = (int) (Auth::id() ?? 0);
$idCertificado = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
) ?: 0;

if ($idCertificado <= 0) {
    http_response_code(400);
    exit("Certificado inválido.");
}

$certificado = new Certificado();
$registro = $certificado->buscarEmitido($idCertificado);

if (
    !$registro
    || (int) ($registro["idUsuario"] ?? 0) !== $idUsuario
) {
    http_response_code(404);
    exit("Certificado não encontrado.");
}

if ((string) ($registro["status"] ?? "") === "Revogado") {
    http_response_code(403);
    exit("Este certificado foi revogado.");
}

$service = new CertificadoService($certificado);
$arquivo = $service->arquivoAbsolutoDeRegistro($registro);

if (
    !$arquivo
    || !is_file($arquivo)
    || !is_readable($arquivo)
) {
    http_response_code(404);
    exit(
        "A cópia do certificado não foi localizada no servidor."
    );
}

$nome = preg_replace(
    "/[^a-zA-Z0-9._-]+/",
    "-",
    (string) $registro["codigo"]
) . ".pdf";

header("Content-Type: application/pdf");
header(
    'Content-Disposition: attachment; filename="'
    . $nome
    . '"'
);
header("Content-Length: " . filesize($arquivo));
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, no-store, max-age=0");

readfile($arquivo);
exit;
