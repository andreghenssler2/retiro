<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . "/config/settings.php";

header("Content-Type: application/json; charset=utf-8");

function responderWebhookAsaas(int $status, array $dados): never
{
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function configuracaoWebhookAsaas(string $nome): string
{
    if (defined($nome)) {
        return trim((string) constant($nome));
    }

    $valor = getenv($nome);
    return $valor === false ? "" : trim((string) $valor);
}

function cabecalhoWebhookAsaas(string $nome): string
{
    $chaveServidor = "HTTP_" . strtoupper(str_replace("-", "_", $nome));

    // Forma padrão usada pelo Apache/PHP-FPM.
    $valor = $_SERVER[$chaveServidor] ?? null;

    // Alguns ambientes CGI/FastCGI adicionam o prefixo REDIRECT_.
    if (($valor === null || $valor === "") && isset($_SERVER["REDIRECT_" . $chaveServidor])) {
        $valor = $_SERVER["REDIRECT_" . $chaveServidor];
    }

    if (is_scalar($valor) && trim((string) $valor) !== "") {
        return trim((string) $valor);
    }

    // Fallback para hospedagens que não expõem o header em $_SERVER.
    if (function_exists("getallheaders")) {
        $cabecalhos = getallheaders();

        if (is_array($cabecalhos)) {
            foreach ($cabecalhos as $chave => $conteudo) {
                if (strcasecmp((string) $chave, $nome) === 0 && is_scalar($conteudo)) {
                    return trim((string) $conteudo);
                }
            }
        }
    }

    return "";
}

function statusLocalWebhookAsaas(string $evento, string $statusAsaas): ?string
{
    $evento = strtoupper(trim($evento));
    $statusAsaas = strtoupper(trim($statusAsaas));

    return match ($evento) {
        "PAYMENT_RECEIVED", "PAYMENT_CONFIRMED" => "Pago",
        "PAYMENT_OVERDUE", "PAYMENT_BANK_SLIP_CANCELLED" => "Vencido",
        "PAYMENT_REFUNDED" => "Estornado",
        "PAYMENT_DELETED" => "Cancelado",
        default => match ($statusAsaas) {
            "RECEIVED", "CONFIRMED", "RECEIVED_IN_CASH" => "Pago",
            "REFUNDED" => "Estornado",
            "DELETED" => "Cancelado",
            "OVERDUE" => "Vencido",
            "PENDING", "AWAITING_RISK_ANALYSIS" => "Pendente",
            default => null
        }
    };
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderWebhookAsaas(405, ["sucesso" => false, "mensagem" => "Método não permitido."]);
}

$configuracaoBancaria = new ConfiguracaoBancaria($db);
$ambienteWebhook = $configuracaoBancaria->ambiente();
$tokenConfigurado = $configuracaoBancaria->webhookToken($ambienteWebhook);
$tokenRecebido = cabecalhoWebhookAsaas("asaas-access-token");

if (!$configuracaoBancaria->ativo()) {
    responderWebhookAsaas(200, [
        "sucesso" => true,
        "ignorado" => true,
        "mensagem" => "A integração bancária está desativada."
    ]);
}

if (
    $tokenConfigurado === ""
    || $tokenRecebido === ""
    || !hash_equals($tokenConfigurado, $tokenRecebido)
) {
    // Não registra os tokens. O log informa somente presença e tamanho para diagnóstico.
    error_log(
        "Webhook Asaas recusado: "
        . "configurado=" . ($tokenConfigurado !== "" ? "sim" : "nao")
        . " (" . strlen($tokenConfigurado) . "), "
        . "recebido=" . ($tokenRecebido !== "" ? "sim" : "nao")
        . " (" . strlen($tokenRecebido) . ")"
    );

    responderWebhookAsaas(401, ["sucesso" => false, "mensagem" => "Token inválido."]);
}

$corpo = file_get_contents("php://input");

if (!is_string($corpo) || trim($corpo) === "") {
    responderWebhookAsaas(400, ["sucesso" => false, "mensagem" => "Payload vazio."]);
}

try {
    $payload = json_decode($corpo, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException) {
    responderWebhookAsaas(400, ["sucesso" => false, "mensagem" => "JSON inválido."]);
}

if (!is_array($payload)) {
    responderWebhookAsaas(400, ["sucesso" => false, "mensagem" => "Payload inválido."]);
}

$eventoId = trim((string) ($payload["id"] ?? ""));
$evento = trim((string) ($payload["event"] ?? ""));
$payment = $payload["payment"] ?? [];

if (!is_array($payment)) {
    $payment = [];
}

$asaasPaymentId = trim((string) ($payment["id"] ?? ""));
$asaasStatus = trim((string) ($payment["status"] ?? ""));

if ($eventoId === "" || $evento === "") {
    responderWebhookAsaas(400, ["sucesso" => false, "mensagem" => "Evento incompleto."]);
}

$iniciouTransacao = !$db->inTransaction();

try {
    if ($iniciouTransacao) {
        $db->beginTransaction();
    }

    $stmtEvento = $db->prepare("
        INSERT IGNORE INTO asaas_webhook_eventos (
            eventoId,
            evento,
            asaasPaymentId,
            payload
        ) VALUES (
            :eventoId,
            :evento,
            :asaasPaymentId,
            :payload
        )
    ");
    $stmtEvento->execute([
        ":eventoId" => $eventoId,
        ":evento" => $evento,
        ":asaasPaymentId" => $asaasPaymentId !== "" ? $asaasPaymentId : null,
        ":payload" => $corpo
    ]);

    if ($stmtEvento->rowCount() === 0) {
        if ($iniciouTransacao) {
            $db->commit();
        }

        responderWebhookAsaas(200, ["sucesso" => true, "duplicado" => true]);
    }

    $statusLocal = statusLocalWebhookAsaas($evento, $asaasStatus);

    if ($asaasPaymentId !== "" && $statusLocal !== null) {
        $dataPagamento = $payment["paymentDate"]
            ?? $payment["confirmedDate"]
            ?? $payment["clientPaymentDate"]
            ?? null;

        $pagamentoModel = new Pagamento($db);
        $pagamentoModel->atualizarStatusPeloAsaas(
            $asaasPaymentId,
            $statusLocal,
            $asaasStatus !== "" ? $asaasStatus : null,
            is_scalar($dataPagamento) ? (string) $dataPagamento : null,
            $corpo
        );
    }

    $stmtProcessado = $db->prepare("
        UPDATE asaas_webhook_eventos
        SET processadoEm = NOW(), erro = NULL
        WHERE eventoId = :eventoId
    ");
    $stmtProcessado->execute([":eventoId" => $eventoId]);

    if ($iniciouTransacao) {
        $db->commit();
    }

    
    // SAUDE_WEBHOOK_ASAAS_V1
    SaudeSistemaService::registrarExecucao(
        $db,
        "webhook.asaas",
        "ok",
        [
            "evento" => $evento
        ]
    );

    responderWebhookAsaas(200, ["sucesso" => true]);
} catch (Throwable $erro) {
    if ($iniciouTransacao && $db->inTransaction()) {
        $db->rollBack();
    }


    // SAUDE_WEBHOOK_ASAAS_V1
    try {
        if ($eventoId !== "") {
            $stmtFalha = $db->prepare("
                INSERT INTO asaas_webhook_eventos (
                    eventoId,
                    evento,
                    asaasPaymentId,
                    payload,
                    recebidoEm,
                    processadoEm,
                    erro
                ) VALUES (
                    :eventoId,
                    :evento,
                    :asaasPaymentId,
                    :payload,
                    NOW(),
                    NULL,
                    :erro
                )
                ON DUPLICATE KEY UPDATE
                    processadoEm = NULL,
                    erro = VALUES(erro)
            ");

            $stmtFalha->execute([
                ":eventoId" => $eventoId,
                ":evento" => $evento !== "" ? $evento : "DESCONHECIDO",
                ":asaasPaymentId" => $asaasPaymentId !== ""
                    ? $asaasPaymentId
                    : null,
                ":payload" => is_string($corpo) ? $corpo : "",
                ":erro" => mb_substr(
                    $erro->getMessage(),
                    0,
                    4000
                )
            ]);
        }
    } catch (Throwable $erroRegistro) {
        error_log(
            "Falha ao registrar erro do webhook Asaas: "
            . $erroRegistro->getMessage()
        );
    }

    SaudeSistemaService::registrarExecucao(
        $db,
        "webhook.asaas",
        "erro",
        $erro->getMessage()
    );

    error_log(
        "Erro no Webhook Asaas: "
        . $erro->getMessage()
        . " | Evento: "
        . $eventoId
    );

    responderWebhookAsaas(500, ["sucesso" => false, "mensagem" => "Erro interno."]);
}
