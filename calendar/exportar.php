<?php

declare(strict_types=1);

ob_start();

require_once __DIR__ . "/../config/settings.php";
require_once __DIR__ . "/CalendarExport.php";

Middleware::auth();
AtividadeUsuario::ignorarRequisicaoAtual();

try {
    $idUsuario = (int) (Auth::id() ?? 0);

    if ($idUsuario <= 0) {
        throw new RuntimeException(
            "Usuário autenticado não identificado."
        );
    }

    $periodo = trim((string) ($_GET["periodo"] ?? "60dias"));
    $inicioPersonalizado = trim(
        (string) ($_GET["data_inicio"] ?? "")
    );
    $fimPersonalizado = trim(
        (string) ($_GET["data_fim"] ?? "")
    );

    $intervalo = CalendarExport::resolverPeriodo(
        $periodo,
        $inicioPersonalizado,
        $fimPersonalizado
    );

    $administrador = Auth::isAdmin();
    $eventos = CalendarExport::listarEventos(
        $db,
        $idUsuario,
        $administrador,
        $intervalo["inicio"],
        $intervalo["fim"]
    );

    $nomeUsuario = trim((string) (Auth::nome() ?? "Usuário"));
    $nomeCalendario = $administrador
        ? "Todos os eventos - " . $nomeUsuario
        : "Meus eventos - " . $nomeUsuario;

    $ics = CalendarExport::gerarIcs(
        $eventos,
        $nomeCalendario,
        $idUsuario
    );

    $nomeArquivo = "calendario-eventos-"
        . date("Y-m-d-His")
        . ".ics";

    if (ob_get_length() !== false) {
        ob_clean();
    }

    header("Content-Type: text/calendar; charset=UTF-8");
    header("X-Content-Type-Options: nosniff");
    header("Cache-Control: private, no-store, no-cache, must-revalidate");
    header(
        'Content-Disposition: attachment; filename="'
        . $nomeArquivo
        . '"'
    );
    header("Content-Length: " . strlen($ics));

    echo $ics;
} catch (InvalidArgumentException $erro) {
    if (ob_get_length() !== false) {
        ob_clean();
    }

    http_response_code(422);
    header("Content-Type: text/plain; charset=UTF-8");
    echo $erro->getMessage();
} catch (Throwable $erro) {
    if (ob_get_length() !== false) {
        ob_clean();
    }

    http_response_code(500);
    header("Content-Type: text/plain; charset=UTF-8");

    error_log(
        "Erro ao exportar calendário"
        . " | usuario="
        . (int) (Auth::id() ?? 0)
        . " | erro="
        . $erro->getMessage()
    );

    echo "Não foi possível exportar o calendário.";
}
