<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json; charset=utf-8");

$texto = trim((string) ($_GET["q"] ?? ""));

if (mb_strlen($texto) < 2) {
    echo json_encode([], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $usuario = new Usuario();
    $lista = $usuario->pesquisar($texto);
    $retorno = [];

    foreach ($lista as $item) {
        $complementos = [];

        if (!empty($item["cpf"])) {
            $complementos[] = "CPF: " . $item["cpf"];
        }

        if (!empty($item["email"])) {
            $complementos[] = $item["email"];
        }

        $retorno[] = [
            "id" => (int) $item["id"],
            "text" => (string) $item["nome"]
                . ($complementos ? " | " . implode(" | ", $complementos) : "")
        ];
    }

    echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
} catch (Throwable $erro) {
    error_log("Erro em usuario-search.php: " . $erro->getMessage());
    http_response_code(500);
    echo json_encode([], JSON_UNESCAPED_UNICODE);
}
