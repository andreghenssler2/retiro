<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json; charset=utf-8");

$retorno = ["status" => false, "msg" => ""];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    $retorno["msg"] = "Método de requisição inválido.";
    echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
    exit;
}

if (!Session::validateCsrf((string) ($_POST["_token"] ?? ""))) {
    http_response_code(419);
    $retorno["msg"] = "Token de segurança inválido. Atualize a página.";
    echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
    exit;
}

$id = (int) ($_POST["id"] ?? 0);

if ($id <= 0) {
    http_response_code(422);
    $retorno["msg"] = "Inscrição inválida.";
    echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $inscricao = new Inscricao($db);

    if (!$inscricao->buscar($id)) {
        http_response_code(404);
        $retorno["msg"] = "Inscrição não encontrada.";
    } elseif ($inscricao->alterarCertificado($id)) {
        $retorno["status"] = true;
        $retorno["msg"] = "Situação do certificado atualizada.";
    } else {
        $retorno["msg"] = "Não foi possível alterar o certificado.";
    }
} catch (Throwable $erro) {
    error_log("Erro em inscricao-certificado.php: " . $erro->getMessage());
    http_response_code(500);
    $retorno["msg"] = "Não foi possível alterar o certificado.";
}

echo json_encode($retorno, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
