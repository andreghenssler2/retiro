<?php

declare(strict_types=1);

require_once "../config/settings.php";

Session::start();

if (Auth::check()) {
    try {
        AtividadeUsuario::registrarAcaoAtual(
            $db,
            "Encerramento da sessão",
            "O usuário encerrou a sessão."
        );
    } catch (Throwable $erro) {
        error_log(
            "Falha ao registrar logout: "
            . $erro->getMessage()
        );
    }
}

Auth::logout();

header("Location: " . BASE_URL . "login");
exit;
