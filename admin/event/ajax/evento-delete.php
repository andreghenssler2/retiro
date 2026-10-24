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

$id = (int) ($_POST["id"] ?? 0);

if ($id <= 0) {

    $ret["msg"] = "Evento inválido.";

    echo json_encode($ret);

    exit;

}

$evento = new Evento();

$dados = $evento->buscar($id);

if (!$dados) {

    $ret["msg"] = "Evento não encontrado.";

    echo json_encode($ret);

    exit;

}

/*
|--------------------------------------------------------------------------
| Remove imagem
|--------------------------------------------------------------------------
*/

if (!empty($dados["imagem"])) {

    $arquivo = ROOT_PATH . "/uploads/eventos/" . $dados["imagem"];

    if (file_exists($arquivo)) {

        @unlink($arquivo);

    }

}

if ($evento->excluir($id)) {

    $ret["status"] = true;

    $ret["msg"] = "Evento excluído com sucesso.";

} else {

    $ret["msg"] = "Erro ao excluir.";

}

echo json_encode($ret);

exit;