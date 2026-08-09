<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();
header("Content-Type: application/json; charset=utf-8");

function responderEstornoPagamento(
    bool $sucesso,
    string $mensagem,
    array $dados = [],
    int $http = 200
): never {
    http_response_code($http);
    echo json_encode(
        array_merge(["sucesso" => $sucesso, "mensagem" => $mensagem], $dados),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderEstornoPagamento(false, "Método de requisição inválido.", [], 405);
}

if (!Session::validateCsrf((string) ($_POST["_token"] ?? ""))) {
    responderEstornoPagamento(
        false,
        "Token de segurança inválido. Atualize a página.",
        [],
        419
    );
}

$idPagamento = (int) ($_POST["idPagamento"] ?? 0);
$motivo = trim((string) ($_POST["motivo"] ?? ""));

if ($idPagamento <= 0) {
    responderEstornoPagamento(false, "Pagamento inválido.", [], 422);
}

if (function_exists("mb_substr")) {
    $motivo = mb_substr($motivo, 0, 500, "UTF-8");
} else {
    $motivo = substr($motivo, 0, 500);
}

if ($motivo === "") {
    $motivo = "Estorno integral solicitado pelo administrador do sistema.";
}

try {
    $pagamentoModel = new Pagamento($db);
    $pagamento = $pagamentoModel->buscar($idPagamento);

    if (!$pagamento || (int) ($pagamento["idInscricao"] ?? 0) <= 0) {
        responderEstornoPagamento(false, "Pagamento não encontrado.", [], 404);
    }

    $statusLocal = trim((string) ($pagamento["status"] ?? "Pendente"));

    if ($statusLocal === "Estornado") {
        responderEstornoPagamento(
            true,
            "O pagamento já estava estornado.",
            ["pagamento" => $pagamento]
        );
    }

    if ($statusLocal !== "Pago") {
        responderEstornoPagamento(
            false,
            "Somente pagamentos confirmados como Pago podem ser estornados.",
            [],
            409
        );
    }

    $formaPagamento = trim((string) ($pagamento["formaPagamento"] ?? "NaoDefinido"));
    $asaasPaymentId = trim((string) ($pagamento["asaasPaymentId"] ?? ""));
    $integracaoAsaas = (string) ($pagamento["integracao"] ?? "Manual") === "Asaas"
        || $asaasPaymentId !== "";

    if ($integracaoAsaas) {
        if ($asaasPaymentId === "") {
            throw new RuntimeException("O pagamento não possui o identificador da cobrança no Asaas.");
        }

        if (!in_array($formaPagamento, ["PIX", "Cartao"], true)) {
            responderEstornoPagamento(
                false,
                "O estorno automático pelo Asaas está disponível para PIX e cartão de crédito. Para boleto, realize o procedimento no painel do Asaas e depois consulte a situação.",
                [],
                422
            );
        }

        $asaas = new AsaasService();
        $cobranca = $asaas->consultarCobrancaOuNull($asaasPaymentId);

        if ($cobranca === null) {
            throw new RuntimeException("A cobrança não foi encontrada na conta Asaas selecionada.");
        }

        $statusAsaas = strtoupper(trim((string) ($cobranca["status"] ?? "")));

        if ($statusAsaas === "REFUNDED") {
            $pagamentoModel->atualizarStatusPeloAsaas(
                $asaasPaymentId,
                "Estornado",
                "REFUNDED",
                null,
                json_encode($cobranca, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        } else {
            if (!in_array($statusAsaas, ["RECEIVED", "CONFIRMED", "RECEIVED_IN_CASH"], true)) {
                responderEstornoPagamento(
                    false,
                    "A cobrança não está confirmada como recebida no Asaas. Situação atual: "
                        . ($statusAsaas !== "" ? $statusAsaas : "não informada")
                        . ".",
                    [],
                    409
                );
            }

            $respostaEstorno = $asaas->estornarCobranca($asaasPaymentId, $motivo);
            $statusRetornado = strtoupper(trim((string) ($respostaEstorno["status"] ?? "REFUNDED")));

            if ($statusRetornado === "") {
                $statusRetornado = "REFUNDED";
            }

            $pagamentoModel->atualizarStatusPeloAsaas(
                $asaasPaymentId,
                "Estornado",
                $statusRetornado,
                null,
                json_encode($respostaEstorno, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }
    } else {
        $pagamentoModel->alterarStatus($idPagamento, "Estornado");
    }

    $registro = "[" . date("d/m/Y H:i") . "] Pagamento estornado integralmente. Motivo: " . $motivo;
    $pagamentoModel->adicionarObservacao($idPagamento, $registro);
    $atualizado = $pagamentoModel->buscar($idPagamento);

    responderEstornoPagamento(
        true,
        $integracaoAsaas
            ? "Estorno solicitado ao Asaas. A inscrição e a presença foram canceladas."
            : "Pagamento manual marcado como Estornado. A inscrição e a presença foram canceladas.",
        ["pagamento" => $atualizado]
    );
} catch (InvalidArgumentException | RuntimeException $erro) {
    responderEstornoPagamento(false, $erro->getMessage(), [], 422);
} catch (Throwable $erro) {
    error_log(
        "Erro ao estornar pagamento #{$idPagamento}: "
        . $erro->getMessage()
        . " | Linha: "
        . $erro->getLine()
    );

    responderEstornoPagamento(
        false,
        "Não foi possível estornar o pagamento.",
        [],
        500
    );
}
