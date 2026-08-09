<?php

declare(strict_types=1);

require_once __DIR__
    . "/../../config/settings.php";

Session::start();
Auth::requireLogin();

if (!Auth::isAdmin()) {
    http_response_code(403);
    exit("Acesso negado.");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Método não permitido.");
}

if (
    !Session::validateCsrf(
        $_POST["_token"] ?? ""
    )
) {
    http_response_code(419);
    exit("Token de segurança inválido.");
}

$idSolicitacao = filter_input(
    INPUT_POST,
    "idSolicitacao",
    FILTER_VALIDATE_INT
) ?: 0;

$decisao = trim(
    (string) ($_POST["decisao"] ?? "")
);

$observacao = trim(
    (string) (
        $_POST["observacao_admin"]
        ?? ""
    )
);

try {
    $service =
        new SolicitacaoCancelamentoInscricao(
            $db
        );

    $resultado = $service->analisar(
        $idSolicitacao,
        (int) Auth::id(),
        $decisao,
        $observacao
    );

    $mensagem =
        $decisao === "Aprovada"
            ? "Cancelamento aprovado."
            : "Solicitação rejeitada.";

    if (
        !empty(
            $resultado["pagamentoPago"]
        )
    ) {
        if (
            !empty(
                $resultado[
                    "estornoConcluido"
                ]
            )
        ) {
            $mensagem .=
                " O pagamento foi estornado "
                . "automaticamente no Asaas.";
        } elseif (
            !empty(
                $resultado[
                    "estornoBoletoUrl"
                ]
            )
        ) {
            $mensagem .=
                " O estorno do boleto foi iniciado. "
                . "O pagador precisa preencher os "
                . "dados bancários no link retornado "
                . "pelo Asaas.";
        } elseif (
            !empty(
                $resultado[
                    "estornoSolicitado"
                ]
            )
        ) {
            $mensagem .=
                " O estorno foi solicitado ao Asaas "
                . "e está aguardando conclusão.";
        }
    }

    Session::flash(
        "success",
        $mensagem
    );
} catch (Throwable $erro) {
    Session::flash(
        "error",
        $erro->getMessage()
    );
}

header(
    "Location: "
    . BASE_URL
    . "admin/inscricao/cancelamentos.php"
);
exit;
