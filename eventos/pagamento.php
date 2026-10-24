<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/settings.php";

Session::start();
Auth::requireLogin();

header(
    "Cache-Control: no-store, no-cache, "
    . "must-revalidate, max-age=0"
);

$idPagamento = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
) ?: 0;

$pagamentoModel = new Pagamento($db);
$pagamento = $pagamentoModel->buscar($idPagamento);

$idUsuario = (int) (Auth::id() ?? 0);

if (
    !$pagamento
    || (int) ($pagamento["idUsuario"] ?? 0) !== $idUsuario
) {
    http_response_code(404);
    exit("Pagamento não encontrado.");
}

$asaasPagamento = new AsaasPagamentoService(
    $db,
    $pagamentoModel
);

/*
 * Ao reabrir a página, sincroniza a cobrança já criada.
 * Isso atualiza PIX, boleto e cartão sem gerar nova cobrança.
 */
if (
    trim(
        (string) (
            $pagamento["asaasPaymentId"]
            ?? ""
        )
    ) !== ""
    && !in_array(
        (string) ($pagamento["status"] ?? ""),
        ["Cancelado", "Estornado"],
        true
    )
) {
    try {
        $pagamento = $asaasPagamento->sincronizarCobranca(
            $idPagamento
        );
    } catch (Throwable $erro) {
        error_log(
            "Falha ao sincronizar pagamento do usuário"
            . " | pagamento=" . $idPagamento
            . " | erro=" . $erro->getMessage()
        );
    }
}

$usuarioModel = new Usuario();
$usuario = $usuarioModel->buscar($idUsuario) ?: [];

$pageStyles = [
    THEME_CSS
    . "eventos/detalhe.css?v="
    . VERSION
];

$pageScripts = [
    THEME_JS
    . "eventos/pagamento.js?v="
    . VERSION
];

function pagamentoUserEscapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

$valorCobrado = (float) (
    $pagamento["valorCobrancaAsaas"]
    ?? $pagamento["valor"]
    ?? 0
);

$statusPagamento = (string) (
    $pagamento["status"]
    ?? "Pendente"
);

$formaAtual = (string) (
    $pagamento["formaPagamento"]
    ?? "NaoDefinido"
);

$pixQr = trim(
    (string) (
        $pagamento["pixQrCode"]
        ?? ""
    )
);

$pixCopiaCola = trim(
    (string) (
        $pagamento["pixCopiaCola"]
        ?? ""
    )
);

$linhaDigitavel = trim(
    (string) (
        $pagamento["boletoLinhaDigitavel"]
        ?? ""
    )
);

$bankSlipUrl = trim(
    (string) (
        $pagamento["bankSlipUrl"]
        ?? ""
    )
);

require_once __DIR__
    . "/../admin/includes/header.php";

require_once __DIR__
    . "/../admin/includes/navbar.php";

require_once __DIR__
    . "/../includes/sidebar.php";
?>

<div
    class="content evento-pagamento-page"
    id="content"
>
    <div class="container-fluid">

        <div class="mb-4">
            <a
                href="<?= BASE_URL ?>eventos/"
                class="btn btn-sm
                    btn-outline-secondary mb-3"
            >
                <i class="fa-solid fa-arrow-left me-1"></i>
                Voltar aos eventos
            </a>

            <h2 class="fw-bold mb-1">
                <i
                    class="fa-solid
                        fa-credit-card
                        text-primary me-2"
                ></i>
                Pagamento da inscrição
            </h2>

            <p class="text-muted mb-0">
                <?= pagamentoUserEscapar(
                    (string) (
                        $pagamento["tituloEvento"]
                        ?? "Evento"
                    )
                ); ?>
            </p>

            <?php if ($asaasPagamento->ambiente() === "sandbox"): ?>
                <small class="text-muted">
                    Checkout v2026-08-08-03
                </small>
            <?php endif; ?>
        </div>

        <?php if ($statusPagamento === "Pago"): ?>

            <div
                class="card border-0
                    shadow-sm text-center"
            >
                <div class="card-body p-5">
                    <i
                        class="fa-solid
                            fa-circle-check
                            text-success fa-4x mb-3"
                    ></i>

                    <h3 class="h4">
                        Pagamento confirmado
                    </h3>

                    <p class="text-muted">
                        Sua inscrição foi confirmada.
                    </p>

                    <a
                        href="<?= BASE_URL ?>eventos/"
                        class="btn btn-primary"
                    >
                        Voltar aos eventos
                    </a>
                </div>
            </div>

        <?php elseif (
            in_array(
                $statusPagamento,
                ["Cancelado", "Estornado"],
                true
            )
        ): ?>

            <div class="alert alert-danger">
                Este pagamento não está mais disponível.
            </div>

        <?php elseif (!$asaasPagamento->estaConfigurado()): ?>

            <div class="alert alert-warning">
                <i
                    class="fa-solid
                        fa-triangle-exclamation me-1"
                ></i>
                O pagamento online ainda não está
                configurado. Entre em contato com
                a organização do evento.
            </div>

        <?php else: ?>

            <div class="row g-4">

                <div class="col-lg-7">

                    <div
                        class="card border-0
                            shadow-sm"
                    >
                        <div class="card-body p-4">

                            <div
                                class="d-flex
                                    justify-content-between
                                    align-items-center
                                    gap-3 mb-4"
                            >
                                <div>
                                    <small
                                        class="text-muted
                                            d-block"
                                    >
                                        Valor da inscrição
                                    </small>

                                    <strong
                                        class="fs-3"
                                        id="checkoutValor"
                                    >
                                        R$
                                        <?= number_format(
                                            $valorCobrado,
                                            2,
                                            ",",
                                            "."
                                        ); ?>
                                    </strong>
                                </div>

                                <span
                                    class="badge
                                        text-bg-warning"
                                    id="checkoutStatusBadge"
                                >
                                    <?= pagamentoUserEscapar(
                                        $statusPagamento
                                    ); ?>
                                </span>

                                <span
                                    class="badge
                                        <?= $asaasPagamento->ambiente() === "sandbox"
                                            ? "text-bg-info"
                                            : "text-bg-danger"; ?>"
                                >
                                    Asaas:
                                    <?= $asaasPagamento->ambiente() === "sandbox"
                                        ? "Sandbox"
                                        : "Produção"; ?>
                                </span>
                            </div>

                            <h3 class="h5 fw-bold mb-3">
                                Escolha como pagar
                            </h3>

                            <div
                                class="checkout-formas"
                                data-checkout-formas
                            >
                                <button
                                    type="button"
                                    class="checkout-forma"
                                    data-forma="PIX"
                                >
                                    <span>
                                        <i
                                            class="fa-brands
                                                fa-pix"
                                        ></i>
                                    </span>

                                    <strong>PIX</strong>
                                    <small>
                                        QR Code e Copia e Cola
                                    </small>
                                </button>

                                <button
                                    type="button"
                                    class="checkout-forma"
                                    data-forma="Boleto"
                                >
                                    <span>
                                        <i
                                            class="fa-solid
                                                fa-barcode"
                                        ></i>
                                    </span>

                                    <strong>Boleto</strong>
                                    <small>
                                        Linha digitável e PDF
                                    </small>
                                </button>

                                <button
                                    type="button"
                                    class="checkout-forma"
                                    data-forma="Cartao"
                                >
                                    <span>
                                        <i
                                            class="fa-regular
                                                fa-credit-card"
                                        ></i>
                                    </span>

                                    <strong>
                                        Cartão de crédito
                                    </strong>

                                    <small>
                                        Pagamento dentro do site
                                    </small>
                                </button>
                            </div>

                            <div
                                class="alert d-none mt-4"
                                id="checkoutFeedback"
                                role="alert"
                            ></div>

                            <section
                                class="checkout-painel d-none"
                                id="checkoutPix"
                            >
                                <h4 class="h5 fw-bold">
                                    Pagamento por PIX
                                </h4>

                                <p class="text-muted">
                                    Confirme para gerar o QR Code
                                    desta inscrição.
                                </p>

                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    data-confirmar-forma="PIX"
                                >
                                    Gerar PIX
                                </button>

                                <div
                                    class="checkout-resultado
                                        d-none mt-4"
                                    id="checkoutPixResultado"
                                >
                                    <img
                                        src=""
                                        alt="QR Code PIX"
                                        id="checkoutPixQr"
                                        class="checkout-pix-qr"
                                    >

                                    <label
                                        class="form-label
                                            fw-semibold mt-3"
                                        for="checkoutPixCodigo"
                                    >
                                        PIX Copia e Cola
                                    </label>

                                    <textarea
                                        class="form-control
                                            font-monospace"
                                        id="checkoutPixCodigo"
                                        rows="4"
                                        readonly
                                    ></textarea>
                                </div>
                            </section>

                            <section
                                class="checkout-painel d-none"
                                id="checkoutBoleto"
                            >
                                <h4 class="h5 fw-bold">
                                    Pagamento por boleto
                                </h4>

                                <p class="text-muted">
                                    Confirme para gerar o boleto.
                                </p>

                                <button
                                    type="button"
                                    class="btn btn-primary"
                                    data-confirmar-forma="Boleto"
                                >
                                    Gerar boleto
                                </button>

                                <div
                                    class="checkout-resultado
                                        d-none mt-4"
                                    id="checkoutBoletoResultado"
                                >
                                    <label
                                        class="form-label
                                            fw-semibold"
                                        for="checkoutBoletoLinha"
                                    >
                                        Linha digitável
                                    </label>

                                    <textarea
                                        class="form-control
                                            font-monospace"
                                        id="checkoutBoletoLinha"
                                        rows="3"
                                        readonly
                                    ></textarea>

                                    <a
                                        href="#"
                                        target="_blank"
                                        rel="noopener"
                                        class="btn
                                            btn-outline-primary
                                            mt-3"
                                        id="checkoutBoletoAbrir"
                                    >
                                        <i
                                            class="fa-solid
                                                fa-file-pdf me-1"
                                        ></i>
                                        Abrir boleto
                                    </a>
                                </div>
                            </section>

                            <section
                                class="checkout-painel d-none"
                                id="checkoutCartao"
                            >
                                <h4 class="h5 fw-bold">
                                    Cartão de crédito
                                </h4>

                                <div
                                    class="alert
                                        alert-secondary
                                        small"
                                >
                                    <i
                                        class="fa-solid
                                            fa-shield-halved me-1"
                                    ></i>
                                    Os dados do cartão são
                                    enviados somente para
                                    processamento e não são
                                    armazenados pelo sistema.
                                </div>

                                <form
                                    id="checkoutCartaoForm"
                                    autocomplete="off"
                                >
                                    <div class="row g-3">

                                        <div class="col-12">
                                            <label
                                                class="form-label"
                                                for="cardHolderName"
                                            >
                                                Nome impresso
                                                no cartão
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="cardHolderName"
                                                name="cardHolderName"
                                                autocomplete="cc-name"
                                                maxlength="80"
                                                required
                                            >
                                        </div>

                                        <div class="col-12">
                                            <label
                                                class="form-label"
                                                for="cardNumber"
                                            >
                                                Número do cartão
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control
                                                    font-monospace"
                                                id="cardNumber"
                                                name="cardNumber"
                                                inputmode="numeric"
                                                autocomplete="cc-number"
                                                maxlength="23"
                                                required
                                            >
                                        </div>

                                        <div class="col-4">
                                            <label
                                                class="form-label"
                                                for="cardExpiryMonth"
                                            >
                                                Mês
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="cardExpiryMonth"
                                                name="cardExpiryMonth"
                                                inputmode="numeric"
                                                autocomplete="cc-exp-month"
                                                maxlength="2"
                                                placeholder="MM"
                                                required
                                            >
                                        </div>

                                        <div class="col-4">
                                            <label
                                                class="form-label"
                                                for="cardExpiryYear"
                                            >
                                                Ano
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="cardExpiryYear"
                                                name="cardExpiryYear"
                                                inputmode="numeric"
                                                autocomplete="cc-exp-year"
                                                maxlength="4"
                                                placeholder="AAAA"
                                                required
                                            >
                                        </div>

                                        <div class="col-4">
                                            <label
                                                class="form-label"
                                                for="cardCcv"
                                            >
                                                CVV
                                            </label>

                                            <input
                                                type="password"
                                                class="form-control"
                                                id="cardCcv"
                                                name="cardCcv"
                                                inputmode="numeric"
                                                autocomplete="cc-csc"
                                                maxlength="4"
                                                required
                                            >
                                        </div>

                                        <div class="col-12">
                                            <hr>
                                            <h5 class="h6 fw-bold">
                                                Dados do titular
                                            </h5>
                                        </div>

                                        <div class="col-md-6">
                                            <label
                                                class="form-label"
                                                for="holderName"
                                            >
                                                Nome completo
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="holderName"
                                                name="holderName"
                                                value="<?= pagamentoUserEscapar(
                                                    (string) (
                                                        $usuario["nome"]
                                                        ?? ""
                                                    )
                                                ); ?>"
                                                required
                                            >
                                        </div>

                                        <div class="col-md-6">
                                            <label
                                                class="form-label"
                                                for="holderCpf"
                                            >
                                                CPF
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="holderCpf"
                                                name="holderCpf"
                                                value="<?= pagamentoUserEscapar(
                                                    (string) (
                                                        $usuario["cpf"]
                                                        ?? ""
                                                    )
                                                ); ?>"
                                                inputmode="numeric"
                                                required
                                            >
                                        </div>

                                        <div class="col-md-6">
                                            <label
                                                class="form-label"
                                                for="holderEmail"
                                            >
                                                E-mail
                                            </label>

                                            <input
                                                type="email"
                                                class="form-control"
                                                id="holderEmail"
                                                name="holderEmail"
                                                value="<?= pagamentoUserEscapar(
                                                    (string) (
                                                        $usuario["email"]
                                                        ?? ""
                                                    )
                                                ); ?>"
                                                required
                                            >
                                        </div>

                                        <div class="col-md-6">
                                            <label
                                                class="form-label"
                                                for="holderPhone"
                                            >
                                                Telefone
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="holderPhone"
                                                name="holderPhone"
                                                value="<?= pagamentoUserEscapar(
                                                    (string) (
                                                        $usuario["telefone"]
                                                        ?? ""
                                                    )
                                                ); ?>"
                                                inputmode="tel"
                                                required
                                            >
                                        </div>

                                        <div class="col-md-4">
                                            <label
                                                class="form-label"
                                                for="holderPostalCode"
                                            >
                                                CEP
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="holderPostalCode"
                                                name="holderPostalCode"
                                                inputmode="numeric"
                                                maxlength="9"
                                                required
                                            >
                                        </div>

                                        <div class="col-md-4">
                                            <label
                                                class="form-label"
                                                for="holderAddressNumber"
                                            >
                                                Número
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="holderAddressNumber"
                                                name="holderAddressNumber"
                                                value="<?= pagamentoUserEscapar(
                                                    (string) (
                                                        $usuario["numero"]
                                                        ?? ""
                                                    )
                                                ); ?>"
                                                maxlength="20"
                                                required
                                            >
                                        </div>

                                        <div class="col-md-4">
                                            <label
                                                class="form-label"
                                                for="holderComplement"
                                            >
                                                Complemento
                                            </label>

                                            <input
                                                type="text"
                                                class="form-control"
                                                id="holderComplement"
                                                name="holderComplement"
                                                maxlength="80"
                                            >
                                        </div>

                                        <div class="col-12">
                                            <button
                                                type="submit"
                                                class="btn
                                                    btn-primary
                                                    btn-lg w-100"
                                                id="checkoutCartaoEnviar"
                                            >
                                                <i
                                                    class="fa-solid
                                                        fa-lock me-1"
                                                ></i>
                                                Confirmar pagamento
                                            </button>
                                        </div>

                                    </div>
                                </form>
                            </section>

                        </div>
                    </div>

                </div>

                <div class="col-lg-5">

                    <div
                        class="card border-0
                            shadow-sm"
                    >
                        <div class="card-body p-4">
                            <h3 class="h5 fw-bold">
                                Resumo
                            </h3>

                            <dl class="checkout-resumo">
                                <div>
                                    <dt>Evento</dt>
                                    <dd>
                                        <?= pagamentoUserEscapar(
                                            (string) (
                                                $pagamento["tituloEvento"]
                                                ?? ""
                                            )
                                        ); ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>Inscrição</dt>
                                    <dd>
                                        #<?= (int) (
                                            $pagamento["idInscricao"]
                                            ?? 0
                                        ); ?>
                                    </dd>
                                </div>

                                <div>
                                    <dt>Pagamento</dt>
                                    <dd>
                                        <?= pagamentoUserEscapar(
                                            (string) (
                                                $pagamento["codigo"]
                                                ?? ""
                                            )
                                        ); ?>
                                    </dd>
                                </div>
                            </dl>

                            <div
                                class="alert alert-info
                                    small mb-0"
                            >
                                PIX e boleto são atualizados
                                automaticamente conforme a
                                confirmação do provedor.
                            </div>
                        </div>
                    </div>

                </div>

            </div>

            <script>
            window.RETIRO_CHECKOUT = <?= json_encode(
                [
                    "url" => BASE_URL
                        . "eventos/ajax/processar-pagamento.php",
                    "idPagamento" => $idPagamento,
                    "csrf" => Session::csrf(),
                    "formaAtual" => $formaAtual,
                    "pixQr" => $pixQr,
                    "pixCopiaCola" => $pixCopiaCola,
                    "boletoLinha" => $linhaDigitavel,
                    "boletoUrl" => $bankSlipUrl
                ],
                JSON_UNESCAPED_UNICODE
                | JSON_UNESCAPED_SLASHES
                | JSON_HEX_TAG
                | JSON_HEX_AMP
                | JSON_HEX_APOS
                | JSON_HEX_QUOT
            ); ?>;
            </script>

        <?php endif; ?>

    </div>
</div>

<?php
require_once __DIR__
    . "/../admin/includes/footer.php";
?>
