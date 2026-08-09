<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json; charset=utf-8");

function responderVisualizacaoPagamento(
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

function escaparVisualizacaoPagamento(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

function dataVisualizacaoPagamento(mixed $valor, bool $hora = true): string
{
    $texto = trim((string) ($valor ?? ""));

    if ($texto === "" || str_starts_with($texto, "0000-00-00")) {
        return "Não informado";
    }

    try {
        return (new DateTime($texto))->format($hora ? "d/m/Y H:i" : "d/m/Y");
    } catch (Throwable) {
        return "Não informado";
    }
}

function urlVisualizacaoPagamento(mixed $valor): ?string
{
    $url = trim((string) ($valor ?? ""));

    if ($url === "" || !filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }

    $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($esquema, ["http", "https"], true) ? $url : null;
}

function qrVisualizacaoPagamento(mixed $valor): ?string
{
    $qr = trim((string) ($valor ?? ""));

    if (!preg_match('#^data:image/png;base64,[A-Za-z0-9+/=\r\n]+$#', $qr)) {
        return null;
    }

    return $qr;
}


function limiteVisualizacaoPagamento(array $pagamento): ?DateTimeImmutable
{
    $fuso = new DateTimeZone("America/Sao_Paulo");
    $limiteEvento = trim((string) ($pagamento["pagamentoFimEvento"] ?? ""));

    if ($limiteEvento !== "" && !str_starts_with($limiteEvento, "0000-00-00")) {
        try {
            return new DateTimeImmutable($limiteEvento, $fuso);
        } catch (Throwable) {
            // Continua para o vencimento do pagamento.
        }
    }

    $vencimento = trim((string) ($pagamento["dataVencimento"] ?? ""));

    if ($vencimento === "" || str_starts_with($vencimento, "0000-00-00")) {
        return null;
    }

    try {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $vencimento)) {
            $vencimento .= " 23:59:59";
        }

        return new DateTimeImmutable($vencimento, $fuso);
    } catch (Throwable) {
        return null;
    }
}

function vencimentoBoletoVisualizacao(array $pagamento): ?DateTimeImmutable
{
    $data = trim((string) ($pagamento["dataVencimento"] ?? ""));

    if ($data === "" || str_starts_with($data, "0000-00-00")) {
        return null;
    }

    try {
        return (new DateTimeImmutable(
            $data,
            new DateTimeZone("America/Sao_Paulo")
        ))->setTime(0, 0, 0);
    } catch (Throwable) {
        return null;
    }
}

function adicionarDiasUteisVisualizacao(
    DateTimeImmutable $data,
    int $quantidade
): DateTimeImmutable {
    $resultado = $data;
    $contados = 0;

    while ($contados < $quantidade) {
        $resultado = $resultado->modify("+1 day");

        if ((int) $resultado->format("N") <= 5) {
            $contados++;
        }
    }

    return $resultado->setTime(23, 59, 59);
}

/**
 * @return array{
 *     tipo:string,
 *     icone:string,
 *     titulo:string,
 *     mensagem:string,
 *     vencido:bool,
 *     podeAceitar:bool,
 *     limite:?DateTimeImmutable
 * }
 */
function situacaoVisualizacaoPagamento(array $pagamento): array
{
    $status = trim((string) ($pagamento["status"] ?? "Pendente"));
    $statusInscricao = trim((string) ($pagamento["statusInscricao"] ?? "Pendente"));
    $forma = trim((string) ($pagamento["formaPagamento"] ?? ""));
    $statusAsaas = strtoupper(trim((string) ($pagamento["asaasStatus"] ?? "")));
    $limite = limiteVisualizacaoPagamento($pagamento);
    $agora = new DateTimeImmutable(
        "now",
        new DateTimeZone("America/Sao_Paulo")
    );

    if ($status === "Pago") {
        return [
            "tipo" => "success",
            "icone" => "fa-circle-check",
            "titulo" => "Inscrição confirmada",
            "mensagem" => "O pagamento foi confirmado e a inscrição está confirmada para o evento.",
            "vencido" => false,
            "podeAceitar" => false,
            "limite" => $limite
        ];
    }

    if ($status === "Cancelado") {
        return [
            "tipo" => "danger",
            "icone" => "fa-circle-xmark",
            "titulo" => "Pagamento cancelado",
            "mensagem" => "A cobrança foi cancelada. Este pagamento não pode mais ser aceito e a inscrição permanece cancelada.",
            "vencido" => false,
            "podeAceitar" => false,
            "limite" => $limite
        ];
    }

    if ($status === "Estornado") {
        return [
            "tipo" => "secondary",
            "icone" => "fa-rotate-left",
            "titulo" => "Pagamento estornado",
            "mensagem" => "O valor foi estornado. Este pagamento não pode mais ser aceito e a inscrição foi cancelada.",
            "vencido" => false,
            "podeAceitar" => false,
            "limite" => $limite
        ];
    }

    $vencimentoBoleto = $forma === "Boleto"
        ? vencimentoBoletoVisualizacao($pagamento)
        : null;
    $boletoVencido = $forma === "Boleto"
        && (
            $status === "Vencido"
            || $statusAsaas === "OVERDUE"
            || (
                $vencimentoBoleto instanceof DateTimeImmutable
                && $agora > $vencimentoBoleto->setTime(23, 59, 59)
            )
        );

    if ($boletoVencido) {
        $limiteTolerancia = $vencimentoBoleto instanceof DateTimeImmutable
            ? adicionarDiasUteisVisualizacao(
                $vencimentoBoleto,
                BoletoVencidoService::DIAS_UTEIS_TOLERANCIA
            )
            : null;
        $inscricaoCancelada = $statusInscricao === "Cancelada";
        $toleranciaEncerrada = $limiteTolerancia instanceof DateTimeImmutable
            && $agora > $limiteTolerancia;

        if ($inscricaoCancelada || $toleranciaEncerrada) {
            $mensagem = "O boleto está vencido";

            if ($vencimentoBoleto instanceof DateTimeImmutable) {
                $mensagem .= " desde " . $vencimentoBoleto->format("d/m/Y");
            }

            if ($limiteTolerancia instanceof DateTimeImmutable) {
                $mensagem .= " e o prazo de "
                    . BoletoVencidoService::DIAS_UTEIS_TOLERANCIA
                    . " dias úteis terminou em "
                    . $limiteTolerancia->format("d/m/Y");
            }

            if ($inscricaoCancelada) {
                $mensagem .= ". A inscrição foi cancelada e este pagamento não pode mais ser aceito.";
            } else {
                $mensagem .= ". O pagamento não pode mais ser aceito e a inscrição será cancelada no próximo processamento automático.";
            }

            return [
                "tipo" => "danger",
                "icone" => "fa-calendar-xmark",
                "titulo" => $inscricaoCancelada
                    ? "Boleto vencido — inscrição cancelada"
                    : "Boleto vencido — tolerância encerrada",
                "mensagem" => $mensagem,
                "vencido" => true,
                "podeAceitar" => false,
                "limite" => $limiteTolerancia
            ];
        }

        $mensagem = "O boleto venceu";

        if ($vencimentoBoleto instanceof DateTimeImmutable) {
            $mensagem .= " em " . $vencimentoBoleto->format("d/m/Y");
        }

        if ($limiteTolerancia instanceof DateTimeImmutable) {
            $mensagem .= ", mas ainda poderá ser pago até "
                . $limiteTolerancia->format("d/m/Y")
                . " ao final do terceiro dia útil";
        }

        $mensagem .= ". Depois desse prazo, a inscrição será cancelada automaticamente.";

        return [
            "tipo" => "warning",
            "icone" => "fa-clock",
            "titulo" => "Boleto vencido — dentro da tolerância",
            "mensagem" => $mensagem,
            "vencido" => true,
            "podeAceitar" => true,
            "limite" => $limiteTolerancia
        ];
    }

    $vencido = $status === "Vencido"
        || (
            $status === "Pendente"
            && (
                $statusAsaas === "OVERDUE"
                || ($limite instanceof DateTimeImmutable && $agora > $limite)
            )
        );

    if ($vencido) {
        $mensagem = "O prazo deste pagamento terminou";

        if ($limite instanceof DateTimeImmutable) {
            $mensagem .= " em " . $limite->format("d/m/Y H:i");
        }

        $mensagem .= ". Este pagamento não pode mais ser aceito.";

        return [
            "tipo" => "danger",
            "icone" => "fa-clock",
            "titulo" => "Pagamento vencido",
            "mensagem" => $mensagem,
            "vencido" => true,
            "podeAceitar" => false,
            "limite" => $limite
        ];
    }

    $mensagem = "O pagamento ainda está pendente.";

    if ($limite instanceof DateTimeImmutable) {
        $mensagem .= " Ele poderá ser recebido até "
            . $limite->format("d/m/Y H:i")
            . ".";
    }

    return [
        "tipo" => "warning",
        "icone" => "fa-hourglass-half",
        "titulo" => "Aguardando pagamento",
        "mensagem" => $mensagem,
        "vencido" => false,
        "podeAceitar" => true,
        "limite" => $limite
    ];
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderVisualizacaoPagamento(false, "Método de requisição inválido.", [], 405);
}

if (!Session::validateCsrf((string) ($_POST["_token"] ?? ""))) {
    responderVisualizacaoPagamento(false, "Token de segurança inválido. Atualize a página.", [], 419);
}

$idPagamento = (int) ($_POST["idPagamento"] ?? 0);

if ($idPagamento <= 0) {
    responderVisualizacaoPagamento(false, "Pagamento inválido.", [], 422);
}

try {
    $pagamentoModel = new Pagamento($db);
    $pagamento = $pagamentoModel->buscar($idPagamento);

    if (!$pagamento || (int) ($pagamento["idInscricao"] ?? 0) <= 0) {
        responderVisualizacaoPagamento(false, "Pagamento não encontrado.", [], 404);
    }

    $status = (string) ($pagamento["status"] ?? "Pendente");
    $formaPagamentoAtual = trim((string) ($pagamento["formaPagamento"] ?? "NaoDefinido"));
    $classeStatus = match ($status) {
        "Pago" => "text-bg-success",
        "Pendente" => "text-bg-warning",
        "Vencido" => "text-bg-danger",
        "Cancelado" => "text-bg-dark",
        "Estornado" => "text-bg-secondary",
        default => "text-bg-light"
    };

    $forma = match ((string) ($pagamento["formaPagamento"] ?? "")) {
        "NaoDefinido" => "A definir",
        "Cartao" => "Cartão de crédito",
        "Transferencia" => "Transferência",
        default => (string) ($pagamento["formaPagamento"] ?? "Não informado")
    };

    $integracaoAsaas = (string) ($pagamento["integracao"] ?? "Manual") === "Asaas"
        || trim((string) ($pagamento["asaasPaymentId"] ?? "")) !== "";
    $asaasPaymentId = trim((string) ($pagamento["asaasPaymentId"] ?? ""));
    $estornoAutomaticoAsaas = $status === "Pago"
        && $integracaoAsaas
        && $asaasPaymentId !== ""
        && in_array($formaPagamentoAtual, ["PIX", "Cartao"], true);
    $estornoManualDisponivel = $status === "Pago" && !$integracaoAsaas;
    $podeEstornarPagamento = $estornoAutomaticoAsaas || $estornoManualDisponivel;
    $invoiceUrl = urlVisualizacaoPagamento($pagamento["invoiceUrl"] ?? null);
    $bankSlipUrl = urlVisualizacaoPagamento($pagamento["bankSlipUrl"] ?? null);
    $qrCode = qrVisualizacaoPagamento($pagamento["pixQrCode"] ?? null);
    $pixCopiaCola = trim((string) ($pagamento["pixCopiaCola"] ?? ""));
    $linhaDigitavel = trim((string) ($pagamento["boletoLinhaDigitavel"] ?? ""));
    $situacaoDetalhe = situacaoVisualizacaoPagamento($pagamento);
    $podeAceitarPagamento = (bool) $situacaoDetalhe["podeAceitar"];
    $statusInscricao = trim((string) ($pagamento["statusInscricao"] ?? "Pendente"));
    $valorBasePagamento = round((float) ($pagamento["valor"] ?? 0), 2);
    $valorCobrancaAsaas = round(
        (float) ($pagamento["valorCobrancaAsaas"] ?? $valorBasePagamento),
        2
    );
    $valorTaxaRepassada = max(
        0,
        round((float) ($pagamento["valorTaxaRepassada"] ?? ($valorCobrancaAsaas - $valorBasePagamento)), 2)
    );

    $classeStatusInscricao = match ($statusInscricao) {
        "Confirmada" => "text-bg-success",
        "Cancelada" => "text-bg-danger",
        default => "text-bg-warning"
    };

    ob_start();
    ?>
    <div class="container-fluid px-0">
        <div class="d-flex flex-column flex-md-row justify-content-between gap-3 mb-4">
            <div>
                <small class="text-muted d-block">Código interno</small>
                <h4 class="mb-1"><?= escaparVisualizacaoPagamento($pagamento["codigo"] ?? "") ?></h4>
                <?php if ($integracaoAsaas): ?>
                    <small class="text-muted">
                        Asaas: <?= escaparVisualizacaoPagamento($pagamento["asaasPaymentId"] ?? "Não informado") ?>
                    </small>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-start gap-2 flex-wrap">
                <?php if ($integracaoAsaas): ?>
                    <span class="badge text-bg-primary fs-6">Asaas</span>
                <?php else: ?>
                    <span class="badge text-bg-light fs-6">Manual</span>
                <?php endif; ?>
                <span class="badge <?= escaparVisualizacaoPagamento($classeStatus) ?> fs-6">
                    <?= escaparVisualizacaoPagamento($status) ?>
                </span>
            </div>
        </div>

        <div class="alert alert-<?= escaparVisualizacaoPagamento($situacaoDetalhe["tipo"]) ?> d-flex align-items-start gap-3 mb-3" role="alert">
            <i class="fa <?= escaparVisualizacaoPagamento($situacaoDetalhe["icone"]) ?> fs-4 mt-1"></i>
            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <strong class="fs-5"><?= escaparVisualizacaoPagamento($situacaoDetalhe["titulo"]) ?></strong>
                    <span class="badge <?= escaparVisualizacaoPagamento($classeStatusInscricao) ?>">
                        Inscrição: <?= escaparVisualizacaoPagamento($statusInscricao) ?>
                    </span>
                </div>
                <div><?= escaparVisualizacaoPagamento($situacaoDetalhe["mensagem"]) ?></div>
            </div>
        </div>

        <?php if ($status === "Pago"): ?>
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-4">
                <small class="text-muted">
                    O estorno é integral e cancela também a inscrição e qualquer presença já registrada.
                </small>

                <?php if ($podeEstornarPagamento): ?>
                    <button type="button"
                        class="btn btn-outline-danger btn-estornar-pagamento"
                        data-id="<?= $idPagamento ?>"
                        data-integracao="<?= $integracaoAsaas ? "asaas" : "manual" ?>">
                        <i class="fa fa-rotate-left me-1"></i>
                        <?= $integracaoAsaas ? "Estornar no Asaas" : "Marcar como estornado" ?>
                    </button>
                <?php elseif ($integracaoAsaas && $formaPagamentoAtual === "Boleto"): ?>
                    <span class="badge text-bg-warning">
                        Estorno de boleto deve ser realizado no painel do Asaas
                    </span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <div class="row g-3">
            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <small class="text-muted d-block">Participante</small>
                    <strong><?= escaparVisualizacaoPagamento($pagamento["participante"] ?? "Não informado") ?></strong>
                    <?php if (!empty($pagamento["email"])): ?>
                        <div class="small text-muted text-break"><?= escaparVisualizacaoPagamento($pagamento["email"]) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-6">
                <div class="border rounded p-3 h-100">
                    <small class="text-muted d-block">Evento</small>
                    <strong><?= escaparVisualizacaoPagamento($pagamento["tituloEvento"] ?? "Não informado") ?></strong>
                    <div class="small text-muted">Inscrição #<?= (int) ($pagamento["idInscricao"] ?? 0) ?></div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <small class="text-muted d-block">Valor da inscrição</small>
                    <strong class="fs-5">R$ <?= number_format($valorBasePagamento, 2, ",", ".") ?></strong>
                </div>
            </div>

            <?php if ($valorTaxaRepassada > 0): ?>
                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">Taxa Asaas repassada</small>
                        <strong class="fs-5">R$ <?= number_format($valorTaxaRepassada, 2, ",", ".") ?></strong>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="border rounded p-3 h-100">
                        <small class="text-muted d-block">Total cobrado</small>
                        <strong class="fs-5">R$ <?= number_format($valorCobrancaAsaas, 2, ",", ".") ?></strong>
                    </div>
                </div>
            <?php endif; ?>

            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <small class="text-muted d-block">Forma</small>
                    <strong><?= escaparVisualizacaoPagamento($forma) ?></strong>
                </div>
            </div>

            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <small class="text-muted d-block">Vencimento</small>
                    <strong><?= escaparVisualizacaoPagamento(dataVisualizacaoPagamento($pagamento["dataVencimento"] ?? null, false)) ?></strong>
                </div>
            </div>

            <div class="col-md-3">
                <div class="border rounded p-3 h-100">
                    <small class="text-muted d-block">Recebimento</small>
                    <strong><?= escaparVisualizacaoPagamento(dataVisualizacaoPagamento($pagamento["dataPagamento"] ?? null)) ?></strong>
                </div>
            </div>

            <?php if ($integracaoAsaas): ?>
                <div class="col-12">
                    <div class="card border-primary">
                        <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <strong><i class="fa fa-cloud me-1"></i> Cobrança Asaas</strong>
                            <button type="button" class="btn btn-sm btn-light btn-sincronizar-asaas" data-id="<?= $idPagamento ?>">
                                <i class="fa fa-rotate me-1"></i>
                                Consultar situação
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <small class="text-muted d-block">ID da cobrança</small>
                                    <code><?= escaparVisualizacaoPagamento($pagamento["asaasPaymentId"] ?? "Não informado") ?></code>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Status Asaas</small>
                                    <strong><?= escaparVisualizacaoPagamento($pagamento["asaasStatus"] ?? "Não informado") ?></strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Atualizado em</small>
                                    <strong><?= escaparVisualizacaoPagamento(dataVisualizacaoPagamento($pagamento["asaasAtualizadoEm"] ?? null)) ?></strong>
                                </div>
                            </div>

                            <?php if (!$podeAceitarPagamento): ?>
                                <div class="alert alert-<?= escaparVisualizacaoPagamento($situacaoDetalhe["tipo"]) ?> mb-0">
                                    <i class="fa fa-lock me-1"></i>
                                    <strong>Dados de pagamento bloqueados.</strong>
                                    <?= escaparVisualizacaoPagamento($situacaoDetalhe["mensagem"]) ?>

                                    <?php if ($status === "Pago" && $invoiceUrl !== null): ?>
                                        <div class="mt-3">
                                            <a href="<?= escaparVisualizacaoPagamento($invoiceUrl) ?>"
                                                target="_blank"
                                                rel="noopener"
                                                class="btn btn-outline-success">
                                                <i class="fa fa-up-right-from-square me-1"></i>
                                                Abrir registro da cobrança
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ((string) ($pagamento["formaPagamento"] ?? "") === "PIX"): ?>
                                <div class="row g-4 align-items-center">
                                    <?php if ($qrCode !== null): ?>
                                        <div class="col-md-4 text-center">
                                            <img src="<?= escaparVisualizacaoPagamento($qrCode) ?>" alt="QR Code PIX"
                                                class="img-fluid border rounded p-2 bg-white" style="max-width:280px">
                                        </div>
                                    <?php endif; ?>
                                    <div class="<?= $qrCode !== null ? "col-md-8" : "col-12" ?>">
                                        <label for="pixCopiaColaDetalhe" class="form-label fw-semibold">PIX Copia e Cola</label>
                                        <textarea id="pixCopiaColaDetalhe" class="form-control font-monospace" rows="5" readonly><?= escaparVisualizacaoPagamento($pixCopiaCola) ?></textarea>
                                        <div class="d-flex gap-2 mt-2 flex-wrap">
                                            <button type="button" class="btn btn-outline-primary btn-copiar-conteudo" data-alvo="#pixCopiaColaDetalhe">
                                                <i class="fa fa-copy me-1"></i> Copiar código PIX
                                            </button>
                                            <?php if ($invoiceUrl !== null): ?>
                                                <a href="<?= escaparVisualizacaoPagamento($invoiceUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary">
                                                    <i class="fa fa-up-right-from-square me-1"></i> Abrir cobrança
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (!empty($pagamento["pixExpiracao"])): ?>
                                            <small class="text-muted d-block mt-2">
                                                Expiração informada pelo Asaas: <?= escaparVisualizacaoPagamento(dataVisualizacaoPagamento($pagamento["pixExpiracao"])) ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php elseif ((string) ($pagamento["formaPagamento"] ?? "") === "Boleto"): ?>
                                <label for="linhaDigitavelDetalhe" class="form-label fw-semibold">Linha digitável</label>
                                <textarea id="linhaDigitavelDetalhe" class="form-control font-monospace" rows="2" readonly><?= escaparVisualizacaoPagamento($linhaDigitavel) ?></textarea>
                                <div class="d-flex gap-2 mt-3 flex-wrap">
                                    <?php if ($linhaDigitavel !== ""): ?>
                                        <button type="button" class="btn btn-outline-primary btn-copiar-conteudo" data-alvo="#linhaDigitavelDetalhe">
                                            <i class="fa fa-copy me-1"></i> Copiar linha digitável
                                        </button>
                                    <?php endif; ?>
                                    <?php if ($bankSlipUrl !== null): ?>
                                        <a href="<?= escaparVisualizacaoPagamento($bankSlipUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary">
                                            <i class="fa fa-barcode me-1"></i> Abrir boleto
                                        </a>
                                    <?php endif; ?>
                                    <?php if ($invoiceUrl !== null): ?>
                                        <a href="<?= escaparVisualizacaoPagamento($invoiceUrl) ?>" target="_blank" rel="noopener" class="btn btn-outline-primary">
                                            <i class="fa fa-up-right-from-square me-1"></i> Abrir fatura
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php elseif ((string) ($pagamento["formaPagamento"] ?? "") === "Cartao"): ?>
                                <div class="alert alert-info mb-3">
                                    Os dados do cartão são preenchidos diretamente na página segura do Asaas e não passam pelo sistema do <?php echo Title::getAtual()->getSigla(); ?>.
                                </div>
                                <?php if ($invoiceUrl !== null): ?>
                                    <a href="<?= escaparVisualizacaoPagamento($invoiceUrl) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-lg">
                                        <i class="fa fa-credit-card me-1"></i> Pagar com cartão no Asaas
                                    </a>
                                <?php else: ?>
                                    <div class="alert alert-warning mb-0">A URL da fatura ainda não foi retornada pelo Asaas.</div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($pagamento["descricao"])): ?>
                <div class="col-12">
                    <div class="border rounded p-3">
                        <small class="text-muted d-block">Descrição</small>
                        <?= nl2br(escaparVisualizacaoPagamento($pagamento["descricao"])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($pagamento["observacao"])): ?>
                <div class="col-12">
                    <div class="border rounded p-3">
                        <small class="text-muted d-block">Observação</small>
                        <?= nl2br(escaparVisualizacaoPagamento($pagamento["observacao"])) ?>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($pagamento["comprovante"])): ?>
                <div class="col-12">
                    <div class="border rounded p-3">
                        <small class="text-muted d-block mb-2">Comprovante manual</small>
                        <a href="<?= escaparVisualizacaoPagamento(BASE_URL . ltrim((string) $pagamento["comprovante"], "/")) ?>"
                            class="btn btn-outline-primary" target="_blank" rel="noopener">
                            <i class="fa fa-file-arrow-down me-1"></i> Abrir comprovante
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php
    $html = ob_get_clean();

    responderVisualizacaoPagamento(true, "Pagamento carregado.", ["html" => $html]);
} catch (Throwable $erro) {
    error_log(
        "Erro em pagamento-visualizar.php: "
        . $erro->getMessage()
        . " | Linha: "
        . $erro->getLine()
    );

    $host = strtolower((string) ($_SERVER["HTTP_HOST"] ?? ""));
    $localhost = str_contains($host, "localhost")
        || str_contains($host, "127.0.0.1");

    $mensagem = "Não foi possível carregar o pagamento.";

    if ($localhost) {
        $mensagem .= " Detalhe local: " . $erro->getMessage();
    }

    responderVisualizacaoPagamento(false, $mensagem, [], 500);
}
