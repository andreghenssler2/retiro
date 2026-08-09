<?php

declare(strict_types=1);

require_once __DIR__
    . "/../../config/settings.php";

Session::start();
Auth::requireLogin();

header(
    "Content-Type: application/json; charset=UTF-8"
);

header(
    "Cache-Control: no-store, no-cache, "
    . "must-revalidate, max-age=0"
);

header(
    "X-Retiro-Checkout-Version: 2026-08-08-05"
);

const RETIRO_CHECKOUT_VERSION = "2026-08-08-05";

function checkoutResponder(
    bool $status,
    string $mensagem,
    array $dados = [],
    int $http = 200
): never {
    http_response_code($http);

    echo json_encode(
        [
            "status" => $status,
            "mensagem" => $mensagem,
            "dados" => $dados,
            "versao" => RETIRO_CHECKOUT_VERSION
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    exit;
}


/**
 * Mantém mensagens técnicas úteis no Sandbox sem permitir que
 * um possível número completo de cartão apareça na resposta/log.
 */
function checkoutSanitizarErroCartao(string $mensagem): string
{
    $mensagem = preg_replace(
        '/(?<!\d)\d{13,19}(?!\d)/',
        '[número do cartão oculto]',
        $mensagem
    ) ?? $mensagem;

    return trim($mensagem);
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    checkoutResponder(
        false,
        "Método não permitido.",
        [],
        405
    );
}

if (!Session::validateCsrf($_POST["_token"] ?? "")) {
    checkoutResponder(
        false,
        "Token de segurança inválido.",
        [],
        419
    );
}

$idPagamento = filter_input(
    INPUT_POST,
    "idPagamento",
    FILTER_VALIDATE_INT
) ?: 0;

$forma = trim(
    (string) ($_POST["forma"] ?? "")
);

if (
    $idPagamento <= 0
    || !in_array(
        $forma,
        ["PIX", "Boleto", "Cartao"],
        true
    )
) {
    checkoutResponder(
        false,
        "Dados do pagamento inválidos.",
        [],
        422
    );
}

$pagamentoModel = new Pagamento($db);
$pagamento = $pagamentoModel->buscar($idPagamento);

$idUsuario = (int) (Auth::id() ?? 0);

if (
    !$pagamento
    || (int) ($pagamento["idUsuario"] ?? 0) !== $idUsuario
) {
    checkoutResponder(
        false,
        "Pagamento não encontrado.",
        [],
        404
    );
}

if ((string) ($pagamento["status"] ?? "") === "Pago") {
    checkoutResponder(
        true,
        "Este pagamento já foi confirmado.",
        [
            "statusPagamento" => "Pago"
        ]
    );
}

if (
    in_array(
        (string) ($pagamento["status"] ?? ""),
        ["Cancelado", "Estornado"],
        true
    )
) {
    checkoutResponder(
        false,
        "Este pagamento não pode mais ser processado.",
        [],
        409
    );
}

$service = new AsaasPagamentoService(
    $db,
    $pagamentoModel
);

if (!$service->estaConfigurado()) {
    checkoutResponder(
        false,
        "O pagamento online ainda não está configurado.",
        [
            "ambiente" => $service->ambiente()
        ],
        503
    );
}

$ambienteAsaas = $service->ambiente();

try {
    if ($forma === "PIX") {
        $resultado = $service->gerarOuRecuperarCobranca(
            $idPagamento,
            "PIX"
        );

        checkoutResponder(
            true,
            "PIX gerado com sucesso.",
            [
                "statusPagamento" =>
                    (string) ($resultado["status"] ?? "Pendente"),
                "qrCode" =>
                    (string) ($resultado["pixQrCode"] ?? ""),
                "copiaCola" =>
                    (string) ($resultado["pixCopiaCola"] ?? ""),
                "expiracao" =>
                    (string) ($resultado["pixExpiracao"] ?? "")
            ]
        );
    }

    if ($forma === "Boleto") {
        $resultado = $service->gerarOuRecuperarCobranca(
            $idPagamento,
            "Boleto"
        );

        checkoutResponder(
            true,
            "Boleto gerado com sucesso.",
            [
                "statusPagamento" =>
                    (string) ($resultado["status"] ?? "Pendente"),
                "linhaDigitavel" =>
                    (string) (
                        $resultado["boletoLinhaDigitavel"]
                        ?? ""
                    ),
                "boletoUrl" =>
                    (string) (
                        $resultado["bankSlipUrl"]
                        ?? $resultado["invoiceUrl"]
                        ?? ""
                    )
            ]
        );
    }

    /*
     * CARTÃO
     *
     * IMPORTANTE:
     * - os campos abaixo existem somente nesta requisição;
     * - nenhum deles é salvo no banco;
     * - nenhum deles é incluído em logs;
     * - a resposta ao navegador não contém número, CVV ou token.
     */
    $numero = preg_replace(
        "/\D+/",
        "",
        (string) ($_POST["cardNumber"] ?? "")
    ) ?? "";

    if (
        $ambienteAsaas !== "sandbox"
        && $numero === "4444444444444444"
    ) {
        checkoutResponder(
            false,
            "O cartão 4444 4444 4444 4444 é exclusivo "
            . "do Sandbox do Asaas. Altere o ambiente bancário "
            . "para Sandbox antes de continuar o teste.",
            [
                "ambiente" => $ambienteAsaas
            ],
            422
        );
    }

    $ccv = preg_replace(
        "/\D+/",
        "",
        (string) ($_POST["cardCcv"] ?? "")
    ) ?? "";

    $mes = str_pad(
        preg_replace(
            "/\D+/",
            "",
            (string) ($_POST["cardExpiryMonth"] ?? "")
        ) ?? "",
        2,
        "0",
        STR_PAD_LEFT
    );

    $ano = preg_replace(
        "/\D+/",
        "",
        (string) ($_POST["cardExpiryYear"] ?? "")
    ) ?? "";

    $nomeCartao = trim(
        (string) ($_POST["cardHolderName"] ?? "")
    );

    $titularNome = trim(
        (string) ($_POST["holderName"] ?? "")
    );

    $titularEmail = trim(
        (string) ($_POST["holderEmail"] ?? "")
    );

    $titularCpf = preg_replace(
        "/\D+/",
        "",
        (string) ($_POST["holderCpf"] ?? "")
    ) ?? "";

    $titularCep = preg_replace(
        "/\D+/",
        "",
        (string) ($_POST["holderPostalCode"] ?? "")
    ) ?? "";

    $titularNumero = trim(
        (string) ($_POST["holderAddressNumber"] ?? "")
    );

    $titularComplemento = trim(
        (string) ($_POST["holderComplement"] ?? "")
    );

    $titularTelefone = preg_replace(
        "/\D+/",
        "",
        (string) ($_POST["holderPhone"] ?? "")
    ) ?? "";

    if (
        strlen($numero) < 13
        || strlen($numero) > 19
        || !in_array(strlen($ccv), [3, 4], true)
        || !preg_match("/^(0[1-9]|1[0-2])$/", $mes)
        || !preg_match("/^\d{4}$/", $ano)
        || (int) $ano < (int) date("Y")
        || $nomeCartao === ""
    ) {
        checkoutResponder(
            false,
            "Confira os dados do cartão.",
            [],
            422
        );
    }

    if (
        $titularNome === ""
        || !filter_var(
            $titularEmail,
            FILTER_VALIDATE_EMAIL
        )
        || !in_array(
            strlen($titularCpf),
            [11, 14],
            true
        )
        || strlen($titularCep) !== 8
        || $titularNumero === ""
        || strlen($titularTelefone) < 10
    ) {
        checkoutResponder(
            false,
            "Confira os dados do titular do cartão.",
            [],
            422
        );
    }

    $resultado = $service->pagarCobrancaCartao(
        $idPagamento,
        [
            "holderName" => $nomeCartao,
            "number" => $numero,
            "expiryMonth" => $mes,
            "expiryYear" => $ano,
            "ccv" => $ccv
        ],
        [
            "name" => $titularNome,
            "email" => $titularEmail,
            "cpfCnpj" => $titularCpf,
            "postalCode" => $titularCep,
            "addressNumber" => $titularNumero,
            "addressComplement" =>
                $titularComplemento !== ""
                    ? $titularComplemento
                    : null,
            "phone" => $titularTelefone,
            "mobilePhone" => $titularTelefone
        ]
    );

    /*
     * Remove as referências locais o quanto antes.
     * Isso não é garantia de limpeza física da memória,
     * mas evita uso acidental posterior no código.
     */
    unset(
        $numero,
        $ccv,
        $mes,
        $ano,
        $nomeCartao
    );

    $statusFinal = (string) (
        $resultado["status"]
        ?? "Pendente"
    );

    checkoutResponder(
        true,
        $statusFinal === "Pago"
            ? "Pagamento aprovado."
            : "Pagamento enviado para processamento.",
        [
            "statusPagamento" => $statusFinal
        ]
    );
} catch (Throwable $erro) {
    /*
     * Nunca registrar $_POST nem os dados do cartão.
     */
    $erroSeguro = checkoutSanitizarErroCartao(
        $erro->getMessage()
    );

    error_log(
        "Falha no checkout"
        . " | pagamento=" . $idPagamento
        . " | usuario=" . $idUsuario
        . " | forma=" . $forma
        . " | ambiente=" . $ambienteAsaas
        . " | erro=" . $erroSeguro
    );

    $mensagemRetorno = $erroSeguro;

    if (
        $forma === "Cartao"
        && $ambienteAsaas !== "sandbox"
    ) {
        $mensagemRetorno =
            "Não foi possível processar o cartão. "
            . "Confira os dados ou tente outra forma de pagamento.";
    }

    checkoutResponder(
        false,
        $mensagemRetorno,
        [
            "ambiente" => $ambienteAsaas,
            "etapa" => "asaas"
        ],
        400
    );
}
