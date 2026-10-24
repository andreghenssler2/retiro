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
        "camisetas" => $dashboard->camisetas(),
        "cidades" => $dashboard->cidades(),
        "financeiro" => $dashboard->financeiroMensal(),
        "inscricoes" => $dashboard->inscricoesMensal(),
        "pagamentosStatus" => $dashboard->pagamentosPorStatus()
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $erro) {
    error_log("Erro em dashboard-graficos.php: " . $erro->getMessage());
    http_response_code(500);

    echo json_encode([
        "status" => false,
        "msg" => "Não foi possível carregar os gráficos."
    ], JSON_UNESCAPED_UNICODE);
}
