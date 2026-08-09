<?php

declare(strict_types=1);

require_once "../../config/settings.php";
require_once "../../mod/auth/Notificacao.php";

Session::start();
Auth::requireLogin();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store");

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);

    echo json_encode([
        "status" => false,
        "msg" => "Método não permitido."
    ]);
    exit;
}

if (!Session::validateCsrf($_POST["_token"] ?? "")) {
    http_response_code(419);

    echo json_encode([
        "status" => false,
        "msg" => "Token de segurança inválido."
    ]);
    exit;
}

try {
    $idUsuario = (int) (Auth::id() ?? 0);
    $tipoUsuario = (int) (Auth::tipo() ?? 0);

    $notificacao = new Notificacao($db);
    $notificacao->marcarTodasComoLidas(
        $idUsuario,
        $tipoUsuario
    );

    echo json_encode([
        "status" => true,
        "naoLidas" => 0,
        "msg" => "Todas as notificações foram marcadas como lidas."
    ]);
} catch (Throwable $erro) {
    error_log(
        "Erro ao marcar notificações como lidas: "
        . $erro->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "msg" => "Não foi possível atualizar as notificações."
    ]);
}
