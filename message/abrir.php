<?php

declare(strict_types=1);

require_once "../config/settings.php";
require_once "../mod/auth/Notificacao.php";

Session::start();
Auth::requireLogin();

$idNotificacao = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
) ?: 0;

$idUsuario = (int) (Auth::id() ?? 0);
$tipoUsuario = (int) (Auth::tipo() ?? 0);

$notificacao = new Notificacao($db);
$registro = $notificacao->buscarParaUsuario(
    $idNotificacao,
    $idUsuario,
    $tipoUsuario
);

if (!$registro) {
    http_response_code(404);
    exit("Notificação não encontrada.");
}

$notificacao->marcarComoLida(
    $idNotificacao,
    $idUsuario,
    $tipoUsuario
);

$url = trim((string) ($registro["url"] ?? ""));

/*
 * Permite somente caminhos internos do sistema.
 */
if (
    $url === ""
    || str_contains($url, "://")
    || str_starts_with($url, "//")
    || str_contains($url, "\0")
) {
    $url = "message/";
}

header(
    "Location: "
    . BASE_URL
    . ltrim($url, "/")
);
exit;
