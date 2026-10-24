<?php

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json");

$ret = [
    "status" => false,
    "msg" => ""
];

if (!Session::validateCsrf($_POST["_token"] ?? "")) {

    $ret["msg"] = "Token inválido.";

    echo json_encode($ret);
    exit;

}

$id = (int)($_POST["id"] ?? 0);

$evento = new Evento();

if ($evento->alterarStatus($id)) {

    $ret["status"] = true;
    $ret["msg"] = "Status alterado com sucesso.";

} else {

    $ret["msg"] = "Não foi possível alterar o status.";

}

echo json_encode($ret);