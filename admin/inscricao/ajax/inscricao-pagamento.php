<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json; charset=utf-8");
http_response_code(405);

echo json_encode(
    [
        "status" => false,
        "msg" => "O pagamento deve ser confirmado ou alterado em Financeiro > Pagamentos."
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
