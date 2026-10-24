<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json; charset=utf-8");

$retorno = [
    "status" => false,
    "msg" => "",
    "dados" => []
];

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {
    http_response_code(422);
    $retorno["msg"] = "Usuário inválido.";
    echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $usuario = new Usuario();
    $dados = $usuario->buscarInscricao($id);

    if (!$dados) {
        http_response_code(404);
        $retorno["msg"] = "Usuário não encontrado ou inativo.";
    } else {
        $retorno["status"] = true;
        $retorno["dados"] = $dados;
    }
} catch (Throwable $erro) {
    error_log("Erro em usuario-get.php: " . $erro->getMessage());
    http_response_code(500);
    $retorno["msg"] = "Não foi possível carregar o usuário.";
}

echo json_encode(
    $retorno,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
