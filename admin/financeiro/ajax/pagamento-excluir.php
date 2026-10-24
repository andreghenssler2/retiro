<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json; charset=utf-8");

http_response_code(405);

echo json_encode(
    [
        "sucesso" => false,
        "mensagem" => "Pagamentos gerados por inscrições não podem ser excluídos manualmente. Cancele ou estorne o recebimento."
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
