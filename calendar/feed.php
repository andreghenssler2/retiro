<?php

declare(strict_types=1);

ob_start();

require_once __DIR__ . "/../config/settings.php";
require_once __DIR__ . "/CalendarExport.php";

AtividadeUsuario::ignorarRequisicaoAtual();

header("X-Content-Type-Options: nosniff");
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");

try {
    $token = strtolower(trim((string) ($_GET["token"] ?? "")));
    $usuario = CalendarExport::buscarUsuarioPorToken($db, $token);

    if (!is_array($usuario)) {
        if (ob_get_length() !== false) {
            ob_clean();
        }

        http_response_code(404);
        header("Content-Type: text/plain; charset=UTF-8");
        echo "Calendário não encontrado ou URL revogada.";
        exit;
    }

    $idUsuario = (int) ($usuario["id"] ?? 0);
    $administrador = (int) ($usuario["tipo"] ?? 0) === 1;

    $eventos = CalendarExport::listarEventos(
        $db,
        $idUsuario,
        $administrador
    );

    $nomeCalendario = $administrador
        ? "Todos os eventos - " . (string) $usuario["nome"]
        : "Meus eventos - " . (string) $usuario["nome"];

    $ics = CalendarExport::gerarIcs(
        $eventos,
        $nomeCalendario,
        $idUsuario
    );

    if (ob_get_length() !== false) {
        ob_clean();
    }

    header("Content-Type: text/calendar; charset=UTF-8");
    header(
        'Content-Disposition: inline; filename="calendario-eventos.ics"'
    );
    header("Content-Length: " . strlen($ics));

    echo $ics;
} catch (Throwable $erro) {
    if (ob_get_length() !== false) {
        ob_clean();
    }

    http_response_code(500);
    header("Content-Type: text/plain; charset=UTF-8");

    error_log(
        "Erro no feed do calendário"
        . " | erro="
        . $erro->getMessage()
    );

    echo "Não foi possível gerar o calendário.";
}
