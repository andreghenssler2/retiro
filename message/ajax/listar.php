<?php

declare(strict_types=1);

require_once "../../config/settings.php";
require_once "../../mod/auth/Notificacao.php";

Session::start();
Auth::requireLogin();

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: no-store, no-cache, must-revalidate");

try {
    $idUsuario = (int) (Auth::id() ?? 0);
    $tipoUsuario = (int) (Auth::tipo() ?? 0);

    if ($idUsuario <= 0) {
        throw new RuntimeException(
            "Usuário autenticado não encontrado."
        );
    }

    $notificacao = new Notificacao($db);
    $notificacao->sincronizar();

    $resultado = $notificacao->listarResumo(
        $idUsuario,
        $tipoUsuario,
        8
    );

    echo json_encode(
        [
            "status" => true,
            "naoLidas" => $resultado["naoLidas"],
            "notificacoes" => $resultado["dados"]
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );
} catch (Throwable $erro) {
    error_log(
        "Erro ao listar notificações: "
        . $erro->getMessage()
    );

    http_response_code(500);

    $ip = (string) ($_SERVER["REMOTE_ADDR"] ?? "");
    $emLocalhost = in_array(
        $ip,
        ["127.0.0.1", "::1"],
        true
    );

    echo json_encode(
        [
            "status" => false,
            "msg" => $emLocalhost
                ? "Não foi possível carregar as notificações: "
                . $erro->getMessage()
                : "Não foi possível carregar as notificações.",
            "erro" => $emLocalhost
                ? $erro->getMessage()
                : null
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );
}