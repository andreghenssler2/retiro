<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header(
    "Content-Type: application/json; charset=utf-8"
);

function responderCancelamentoPagamento(
    bool $sucesso,
    string $mensagem,
    array $dados = [],
    int $http = 200
): never {
    http_response_code($http);

    echo json_encode(
        array_merge(
            [
                "sucesso" => $sucesso,
                "mensagem" => $mensagem
            ],
            $dados
        ),
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderCancelamentoPagamento(
        false,
        "Método de requisição inválido.",
        [],
        405
    );
}

if (
    !Session::validateCsrf(
        (string) ($_POST["_token"] ?? "")
    )
) {
    responderCancelamentoPagamento(
        false,
        "Token de segurança inválido. Atualize a página.",
        [],
        419
    );
}

$idPagamento = (int) (
    $_POST["idPagamento"]
    ?? 0
);

$motivo = trim(
    (string) (
        $_POST["motivo"]
        ?? ""
    )
);

if ($idPagamento <= 0) {
    responderCancelamentoPagamento(
        false,
        "Pagamento inválido.",
        [],
        422
    );
}

if (function_exists("mb_substr")) {
    $motivo = mb_substr(
        $motivo,
        0,
        500,
        "UTF-8"
    );
} else {
    $motivo = substr(
        $motivo,
        0,
        500
    );
}

try {
    $pagamentoModel = new Pagamento($db);

    $pagamento = $pagamentoModel->buscar(
        $idPagamento
    );

    if (
        !$pagamento
        || (int) (
            $pagamento["idInscricao"]
            ?? 0
        ) <= 0
    ) {
        responderCancelamentoPagamento(
            false,
            "Pagamento não encontrado.",
            [],
            404
        );
    }

    $statusLocal = trim(
        (string) (
            $pagamento["status"]
            ?? "Pendente"
        )
    );

    if ($statusLocal === "Cancelado") {
        responderCancelamentoPagamento(
            true,
            "O pagamento já estava cancelado.",
            [
                "pagamento" => $pagamento
            ]
        );
    }

    if ($statusLocal === "Pago") {
        responderCancelamentoPagamento(
            false,
            "O pagamento já foi confirmado. "
            . "Use a opção Estornar nos detalhes do pagamento.",
            [],
            409
        );
    }

    if ($statusLocal === "Estornado") {
        responderCancelamentoPagamento(
            false,
            "O pagamento já foi estornado "
            . "e não pode ser cancelado.",
            [],
            409
        );
    }

    $asaasPaymentId = trim(
        (string) (
            $pagamento["asaasPaymentId"]
            ?? ""
        )
    );

    $integracaoAsaas =
        (string) (
            $pagamento["integracao"]
            ?? "Manual"
        ) === "Asaas"
        || $asaasPaymentId !== "";

    if (
        $integracaoAsaas
        && $asaasPaymentId !== ""
    ) {
        $asaas = new AsaasService();

        $cobranca =
            $asaas->consultarCobrancaOuNull(
                $asaasPaymentId
            );

        $statusAsaas = strtoupper(
            trim(
                (string) (
                    $cobranca["status"]
                    ?? ""
                )
            )
        );

        if (
            in_array(
                $statusAsaas,
                [
                    "RECEIVED",
                    "CONFIRMED",
                    "RECEIVED_IN_CASH"
                ],
                true
            )
        ) {
            responderCancelamentoPagamento(
                false,
                "A cobrança já foi paga no Asaas. "
                . "Use a opção Estornar nos detalhes do pagamento.",
                [],
                409
            );
        }

        if ($statusAsaas === "REFUNDED") {
            $pagamentoModel
                ->atualizarStatusPeloAsaas(
                    $asaasPaymentId,
                    "Estornado",
                    "REFUNDED",
                    null,
                    json_encode(
                        $cobranca,
                        JSON_UNESCAPED_UNICODE
                        | JSON_UNESCAPED_SLASHES
                    )
                );

            /*
             * Como o status mudou para Estornado durante
             * esta requisição, tenta notificar imediatamente.
             */
            try {
                (new EmailNotificacaoService($db))
                    ->notificarStatusPagamentoPorId(
                        $idPagamento
                    );
            } catch (Throwable $mailErro) {
                Log::warning(
                    "Pagamento estornado, mas a notificação por e-mail falhou",
                    [
                        "idPagamento" => $idPagamento,
                        "erro" => $mailErro->getMessage()
                    ]
                );
            }

            responderCancelamentoPagamento(
                false,
                "A cobrança já foi estornada no Asaas. "
                . "O pagamento local foi sincronizado como Estornado.",
                [],
                409
            );
        }

        $respostaAsaas = $cobranca ?? [
            "id" => $asaasPaymentId,
            "status" => "NOT_FOUND"
        ];

        if (
            $cobranca !== null
            && $statusAsaas !== "DELETED"
        ) {
            $excluida = $asaas->excluirCobranca(
                $asaasPaymentId
            );

            if (!$excluida) {
                throw new RuntimeException(
                    "O Asaas não confirmou "
                    . "o cancelamento da cobrança."
                );
            }

            $respostaAsaas = [
                "id" => $asaasPaymentId,
                "deleted" => true,
                "statusAnterior" => $statusAsaas
            ];
        }

        $pagamentoModel
            ->atualizarStatusPeloAsaas(
                $asaasPaymentId,
                "Cancelado",
                "DELETED",
                null,
                json_encode(
                    $respostaAsaas,
                    JSON_UNESCAPED_UNICODE
                    | JSON_UNESCAPED_SLASHES
                )
            );
    } else {
        $pagamentoModel->alterarStatus(
            $idPagamento,
            "Cancelado"
        );
    }

    $registro = "["
        . date("d/m/Y H:i")
        . "] Pagamento cancelado";

    if ($motivo !== "") {
        $registro .= ". Motivo: "
            . $motivo;
    }

    $pagamentoModel->adicionarObservacao(
        $idPagamento,
        $registro
    );

    $atualizado = $pagamentoModel->buscar(
        $idPagamento
    );

    /*
    |--------------------------------------------------------------------------
    | Notificação imediata por e-mail
    |--------------------------------------------------------------------------
    |
    | Não depende somente do shutdown do settings.php.
    | O serviço verifica email_status_notificado, portanto
    | não envia novamente se este mesmo status já foi informado.
    |
    */
    $emailNotificado = false;

    try {
        $emailNotificado =
            (new EmailNotificacaoService($db))
                ->notificarStatusPagamentoPorId(
                    $idPagamento
                );
    } catch (Throwable $mailErro) {
        Log::warning(
            "Pagamento cancelado, mas a notificação por e-mail falhou",
            [
                "idPagamento" => $idPagamento,
                "erro" => $mailErro->getMessage()
            ]
        );
    }

    responderCancelamentoPagamento(
        true,
        $integracaoAsaas
            && $asaasPaymentId !== ""
            ? "Cobrança cancelada no Asaas. "
                . "A inscrição e a presença foram canceladas."
            : "Pagamento cancelado. "
                . "A inscrição e a presença foram canceladas.",
        [
            "pagamento" => $atualizado,
            "emailNotificado" => $emailNotificado
        ]
    );
} catch (
    InvalidArgumentException
    | RuntimeException $erro
) {
    responderCancelamentoPagamento(
        false,
        $erro->getMessage(),
        [],
        422
    );
} catch (Throwable $erro) {
    error_log(
        "Erro ao cancelar pagamento #{$idPagamento}: "
        . $erro->getMessage()
        . " | Linha: "
        . $erro->getLine()
    );

    responderCancelamentoPagamento(
        false,
        "Não foi possível cancelar o pagamento.",
        [],
        500
    );
}
