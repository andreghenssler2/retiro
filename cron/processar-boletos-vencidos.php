<?php

declare(strict_types=1);

require_once dirname(__DIR__) . "/config/settings.php";

set_time_limit(0);
date_default_timezone_set("America/Sao_Paulo");

$isCli = PHP_SAPI === "cli" || PHP_SAPI === "phpdbg";

if (!$isCli) {
    header("Content-Type: application/json; charset=utf-8");

    $tokenConfigurado = defined("CRON_BOLETOS_TOKEN")
        ? trim((string) CRON_BOLETOS_TOKEN)
        : trim((string) getenv("CRON_BOLETOS_TOKEN"));
    $tokenRecebido = trim((string) ($_GET["token"] ?? ""));

    if (
        $tokenConfigurado === ""
        || $tokenRecebido === ""
        || !hash_equals($tokenConfigurado, $tokenRecebido)
    ) {
        http_response_code(403);
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Execução não autorizada. Prefira executar este arquivo pelo Cron do servidor."
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

try {
    $servico = new BoletoVencidoService($db);
    $resultado = $servico->processar();
    $sucesso = count($resultado["erros"] ?? []) === 0;

    // SAUDE_CRON_BOLETOS_V1
    SaudeSistemaService::registrarExecucao(
        $db,
        "cron.boletos",
        $sucesso ? "ok" : "erro",
        [
            "erros" => count(
                $resultado["erros"] ?? []
            )
        ]
    );

    $saida = [
        "sucesso" => $sucesso,
        "executadoEm" => date(DATE_ATOM),
        "resultado" => $resultado
    ];

    if (!$isCli) {
        http_response_code($sucesso ? 200 : 207);
        echo json_encode($saida, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    fwrite(
        $sucesso ? STDOUT : STDERR,
        json_encode($saida, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . PHP_EOL
    );

    exit($sucesso ? 0 : 1);
} catch (Throwable $erro) {
    error_log("Falha geral no cron de boletos vencidos: " . $erro->getMessage());

    SaudeSistemaService::registrarExecucao(
        $db,
        "cron.boletos",
        "erro",
        $erro->getMessage()
    );

    $saida = [
        "sucesso" => false,
        "executadoEm" => date(DATE_ATOM),
        "mensagem" => $erro->getMessage()
    ];

    if (!$isCli) {
        http_response_code(500);
        echo json_encode($saida, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    fwrite(
        STDERR,
        json_encode($saida, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . PHP_EOL
    );
    exit(1);
}
