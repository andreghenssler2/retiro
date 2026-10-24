<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store");

try {
    $dashboard = new Dashboard();

    echo json_encode([
        "status" => true,
        "eventos" => $dashboard->totalEventos(),
        "inscritos" => $dashboard->totalInscritos(),
        "confirmados" => $dashboard->totalConfirmados(),
        "pendentes" => $dashboard->totalPendentes(),
        "canceladas" => $dashboard->totalCanceladas(),
        "presencas" => $dashboard->totalPresencas(),
        "recebido" => $dashboard->totalReceitas(),
        "aReceber" => $dashboard->totalPendenteFinanceiro()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $erro) {
    error_log("Erro em dashboard-cards.php: " . $erro->getMessage());
    http_response_code(500);

    echo json_encode([
        "status" => false,
        "msg" => "Não foi possível carregar os indicadores."
    ], JSON_UNESCAPED_UNICODE);
}
