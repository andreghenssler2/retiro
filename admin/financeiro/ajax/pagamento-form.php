<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: text/html; charset=utf-8");

function escaparFormularioPagamento(mixed $valor): string
{
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

function dataFormularioPagamento(mixed $valor): string
{
    $texto = trim((string) ($valor ?? ""));

    if ($texto === "" || $texto === "0000-00-00 00:00:00") {
        return "";
    }

    try {
        return (new DateTime($texto))->format("Y-m-d\\TH:i");
    } catch (Throwable) {
        return "";
    }
}

function dataExibicaoFormularioPagamento(mixed $valor, bool $hora = false): string
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

$idPagamento = (int) ($_GET["id"] ?? 0);

if ($idPagamento <= 0): ?>
    <div class="alert alert-warning mb-0">Pagamento inválido.</div>
    <?php exit; ?>
<?php endif;

try {
    $pagamentoModel = new Pagamento($db);
    $pagamento = $pagamentoModel->buscar($idPagamento);
    $estruturaAsaasBanco = $pagamentoModel->estruturaAsaasDisponivel();

    if (!$pagamento): ?>
        <div class="alert alert-warning mb-0">Pagamento não encontrado.</div>
        <?php exit; ?>
    <?php endif;

    if ((int) ($pagamento["idInscricao"] ?? 0) <= 0): ?>
        <div class="alert alert-warning mb-0">
            Este registro não foi gerado por uma inscrição e não pode ser alterado nesta tela.
        </div>
        <?php exit; ?>
    <?php endif;

    $formas = [
        "NaoDefinido" => "Selecione o meio de pagamento",
        "PIX" => "PIX — gerar QR Code pelo Asaas",
        "Boleto" => "Boleto — gerar pelo Asaas",
        "Cartao" => "Cartão de crédito — pagar no Asaas",
        "Dinheiro" => "Dinheiro — lançamento manual",
        "Transferencia" => "Transferência — lançamento manual"
    ];

    $statusPermitidos = ["Pendente", "Vencido", "Pago", "Cancelado", "Estornado"];
    $formasAsaas = ["PIX", "Boleto", "Cartao"];
    $formasManuais = ["Dinheiro", "Transferencia"];
    $formaAtual = trim((string) ($pagamento["formaPagamento"] ?? "NaoDefinido"));

    if ($formaAtual === "") {
        $formaAtual = "NaoDefinido";
    }

    $statusAtualPagamento = trim((string) ($pagamento["status"] ?? "Pendente"));
    $pagamentoPago = $statusAtualPagamento === "Pago";
    $pagamentoVencido = $statusAtualPagamento === "Vencido";
    $pagamentoPendente = $statusAtualPagamento === "Pendente";
    $pagamentoBloqueado = in_array(
        $statusAtualPagamento,
        ["Vencido", "Pago", "Cancelado", "Estornado"],
        true
    );
    $formaPagamentoEscolhida = $formaAtual !== "NaoDefinido";
    $formaPagamentoBloqueada = $pagamentoBloqueado || $formaPagamentoEscolhida;
    $asaasPaymentId = trim((string) ($pagamento["asaasPaymentId"] ?? ""));
    $asaasStatus = trim((string) ($pagamento["asaasStatus"] ?? ""));
    $integracaoAsaas = (string) ($pagamento["integracao"] ?? "Manual") === "Asaas"
        || $asaasPaymentId !== "";

    $asaasService = new AsaasService();
    $asaasConfigurado = $asaasService->estaConfigurado()
        && $estruturaAsaasBanco;
    $asaasAmbiente = $asaasService->ambiente();

    $prazoIntegradoDisponivel = true;
    $mensagemPrazoPagamento = "O limite para pagamentos não foi informado.";
    $mensagemPrazoBoleto = "O boleto usará o limite de pagamento definido no evento.";
    $cobrancaIntegradaExistente = in_array($formaAtual, $formasAsaas, true)
        && $asaasPaymentId !== "";
    $dataEventoTexto = trim((string) ($pagamento["dataInicioEvento"] ?? ""));
    $limitePagamentoTexto = trim((string) ($pagamento["pagamentoFimEvento"] ?? ""));
    $fusoPagamento = new DateTimeZone("America/Sao_Paulo");
    $dataEvento = DateTimeImmutable::createFromFormat("!Y-m-d", $dataEventoTexto, $fusoPagamento);

    if (!$dataEvento instanceof DateTimeImmutable) {
        $prazoIntegradoDisponivel = false;
        $mensagemPrazoPagamento = "Não é possível gerar cobrança porque a data de início do evento é inválida.";
        $mensagemPrazoBoleto = $mensagemPrazoPagamento;
    } else {
        $limiteMaximo = $dataEvento
            ->modify("-1 day")
            ->setTime(23, 59, 59);

        if ($limitePagamentoTexto !== "") {
            $limitePagamento = DateTimeImmutable::createFromFormat(
                "!Y-m-d H:i:s",
                $limitePagamentoTexto,
                $fusoPagamento
            );
        } else {
            // Compatibilidade com eventos criados antes deste campo.
            $limitePagamento = $limiteMaximo;
        }

        if (!$limitePagamento instanceof DateTimeImmutable) {
            $prazoIntegradoDisponivel = false;
            $mensagemPrazoPagamento = "O limite de pagamento configurado no evento é inválido.";
            $mensagemPrazoBoleto = $mensagemPrazoPagamento;
        } elseif ($limitePagamento > $limiteMaximo) {
            $prazoIntegradoDisponivel = false;
            $mensagemPrazoPagamento = "O limite de pagamento precisa ser, no máximo, até "
                . $limiteMaximo->format("d/m/Y H:i")
                . ".";
            $mensagemPrazoBoleto = $mensagemPrazoPagamento;
        } else {
            $agoraPagamento = new DateTimeImmutable("now", $fusoPagamento);
            $prazoIntegradoDisponivel = $agoraPagamento <= $limitePagamento;

            if ($prazoIntegradoDisponivel) {
                $mensagemPrazoPagamento = "Os pagamentos integrados poderão ser gerados até "
                    . $limitePagamento->format("d/m/Y H:i")
                    . ".";
                $mensagemPrazoBoleto = "O boleto vencerá em "
                    . $limitePagamento->format("d/m/Y")
                    . ", conforme o limite definido no evento.";
            } else {
                $mensagemPrazoPagamento = "O prazo para pagamentos encerrou em "
                    . $limitePagamento->format("d/m/Y H:i")
                    . ".";
                $mensagemPrazoBoleto = $mensagemPrazoPagamento;
            }
        }
    }
    ?>

    <form id="formRecebimento" enctype="multipart/form-data" autocomplete="off"
        data-pagamento-pago="<?= $pagamentoPago ? "1" : "0" ?>"
        data-pagamento-bloqueado="<?= $pagamentoBloqueado ? "1" : "0" ?>"
        data-status-pagamento="<?= escaparFormularioPagamento($statusAtualPagamento) ?>"
        data-forma-pagamento-bloqueada="<?= $formaPagamentoBloqueada ? "1" : "0" ?>">
        <input type="hidden" name="_token" value="<?= escaparFormularioPagamento(Session::csrf()) ?>">
        <input type="hidden" name="idPagamento" value="<?= $idPagamento ?>">
        <input type="hidden" name="comprovanteAtual" value="<?= escaparFormularioPagamento($pagamento["comprovante"] ?? "") ?>">

        <div class="container-fluid py-3">
            <div class="alert alert-info">
                <i class="fa fa-circle-info me-1"></i>
                O pagamento nasceu da inscrição. Escolha o meio de pagamento para gerar a cobrança no Asaas ou registrar o recebimento manualmente.
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <label class="form-label">Participante</label>
                    <input type="text" class="form-control bg-light" readonly
                        value="<?= escaparFormularioPagamento($pagamento["participante"] ?? "") ?>">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Evento</label>
                    <input type="text" class="form-control bg-light" readonly
                        value="<?= escaparFormularioPagamento($pagamento["tituloEvento"] ?? "") ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Inscrição</label>
                    <input type="text" class="form-control bg-light" readonly
                        value="#<?= (int) ($pagamento["idInscricao"] ?? 0) ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Código interno</label>
                    <input type="text" class="form-control bg-light" readonly
                        value="<?= escaparFormularioPagamento($pagamento["codigo"] ?? "") ?>">
                </div>

                <div class="col-md-4">
                    <label class="form-label">Valor</label>
                    <input type="text" class="form-control bg-light fw-semibold" readonly
                        value="R$ <?= number_format((float) ($pagamento["valor"] ?? 0), 2, ",", ".") ?>">
                    <?php if ((int) ($pagamento["repassarTaxaAsaasEvento"] ?? 0) === 1): ?>
                        <div class="form-text text-primary">
                            Este evento repassa a tarifa do Asaas. O total será calculado conforme o meio de pagamento escolhido.
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card border-primary">
                <div class="card-header bg-primary text-white">
                    <strong>
                        <i class="fa fa-wallet me-1"></i>
                        Meio de pagamento
                    </strong>
                </div>

                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-7">
                            <label for="formaPagamento" class="form-label">Forma de pagamento</label>
                            <select class="form-select"
                                name="<?= $formaPagamentoBloqueada ? "formaPagamentoVisual" : "formaPagamento" ?>"
                                id="formaPagamento" required
                                <?= $formaPagamentoBloqueada ? 'disabled aria-disabled="true"' : "" ?>>
                                <?php foreach ($formas as $valor => $rotulo): ?>
                                    <?php
                                    $opcaoIntegrada = in_array($valor, $formasAsaas, true);
                                    $manterCobrancaExistente = $valor === $formaAtual
                                        && $cobrancaIntegradaExistente;
                                    $opcaoIntegradaBloqueada = $opcaoIntegrada
                                        && (!$prazoIntegradoDisponivel || !$estruturaAsaasBanco)
                                        && !$manterCobrancaExistente;
                                    ?>
                                    <option value="<?= escaparFormularioPagamento($valor) ?>"
                                        <?= $formaAtual === $valor ? "selected" : "" ?>
                                        <?= $opcaoIntegradaBloqueada ? "disabled" : "" ?>>
                                        <?= escaparFormularioPagamento($rotulo) ?>
                                        <?= $opcaoIntegradaBloqueada ? " — prazo encerrado" : "" ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($formaPagamentoBloqueada): ?>
                                <input type="hidden" name="formaPagamento"
                                    value="<?= escaparFormularioPagamento($formaAtual) ?>">
                            <?php endif; ?>
                            <div class="form-text <?= $prazoIntegradoDisponivel ? "text-muted" : "text-warning" ?>">
                                <i class="fa <?= $prazoIntegradoDisponivel ? "fa-clock" : "fa-triangle-exclamation" ?> me-1"></i>
                                <?= escaparFormularioPagamento($mensagemPrazoPagamento) ?>
                            </div>
                        </div>

                        <div class="col-md-5">
                            <label class="form-label">Situação atual</label>
                            <input type="text" class="form-control bg-light" readonly
                                value="<?= escaparFormularioPagamento($pagamento["status"] ?? "Pendente") ?>">
                        </div>
                    </div>

                    <?php if ($pagamentoPago): ?>
                        <div class="alert alert-success mt-3 mb-0">
                            <i class="fa fa-lock me-1"></i>
                            <strong>Pagamento confirmado.</strong>
                            A forma de pagamento e a geração de novas cobranças estão bloqueadas.
                        </div>
                    <?php elseif ($pagamentoVencido): ?>
                        <div class="alert alert-danger mt-3 mb-0">
                            <i class="fa fa-clock me-1"></i>
                            <strong>Boleto vencido.</strong>
                            Consulte os detalhes do pagamento para verificar o prazo de três dias úteis e a situação da inscrição.
                        </div>
                    <?php elseif (in_array($statusAtualPagamento, ["Cancelado", "Estornado"], true)): ?>
                        <div class="alert alert-secondary mt-3 mb-0">
                            <i class="fa fa-lock me-1"></i>
                            <strong>Pagamento encerrado.</strong>
                            Este recebimento não pode mais ser alterado.
                        </div>
                    <?php elseif ($formaPagamentoEscolhida): ?>
                        <div class="alert alert-info mt-3 mb-0">
                            <i class="fa fa-lock me-1"></i>
                            <strong>Meio de pagamento definido.</strong>
                            Após a primeira confirmação, o meio de pagamento não pode ser trocado.
                            <?php if (in_array($formaAtual, $formasManuais, true)): ?>
                                O status, a data, a observação e o comprovante ainda podem ser atualizados.
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <div id="blocoAsaas" class="mt-3" style="display:none">
                        <?php if (!$estruturaAsaasBanco): ?>
                            <div class="alert alert-warning mb-0">
                                <strong>Estrutura do Asaas incompleta no banco.</strong>
                                Execute a migração de reparo antes de gerar PIX, boleto ou cartão.
                                Os recebimentos em dinheiro e transferência continuam disponíveis.
                            </div>
                        <?php elseif ($asaasConfigurado): ?>
                            <div class="alert alert-primary mb-0">
                                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                                    <div>
                                        <strong><i class="fa fa-cloud me-1"></i> Cobrança integrada ao Asaas</strong>
                                        <div class="small mt-1" id="textoRegraAsaas"></div>
                                    </div>
                                    <span class="badge <?= $asaasAmbiente === "producao" ? "text-bg-success" : "text-bg-warning" ?>">
                                        <?= $asaasAmbiente === "producao" ? "Produção" : "Sandbox" ?>
                                    </span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger mb-0">
                                <strong>Integração Asaas não configurada para o ambiente selecionado.</strong>
                                Revise a API Key e o ambiente em
                                <a href="<?= BASE_URL ?>admin/configuracoes/bancario.php" class="alert-link">
                                    Configurações &gt; Bancário
                                </a>.
                            </div>
                        <?php endif; ?>

                        <?php if ($integracaoAsaas): ?>
                            <div class="border rounded p-3 mt-3 bg-light">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <small class="text-muted d-block">ID da cobrança no Asaas</small>
                                        <strong><?= escaparFormularioPagamento($asaasPaymentId ?: "Não informado") ?></strong>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted d-block">Status Asaas</small>
                                        <strong><?= escaparFormularioPagamento($asaasStatus ?: "PENDING") ?></strong>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted d-block">Vencimento</small>
                                        <strong><?= escaparFormularioPagamento(dataExibicaoFormularioPagamento($pagamento["dataVencimento"] ?? null)) ?></strong>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div id="blocoManual" class="mt-3" style="display:none">
                        <div class="alert alert-secondary">
                            <i class="fa fa-pen-to-square me-1"></i>
                            Dinheiro e transferência são registrados manualmente e não geram cobrança no Asaas.
                        </div>

                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="statusPagamento" class="form-label">Status</label>
                                <select class="form-select campo-manual" name="status" id="statusPagamento">
                                    <?php foreach ($statusPermitidos as $status): ?>
                                        <option value="<?= escaparFormularioPagamento($status) ?>"
                                            <?= ($pagamento["status"] ?? "Pendente") === $status ? "selected" : "" ?>>
                                            <?= escaparFormularioPagamento($status) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label for="dataPagamento" class="form-label">Data do recebimento</label>
                                <input type="datetime-local" class="form-control campo-manual" name="dataPagamento" id="dataPagamento"
                                    value="<?= escaparFormularioPagamento(dataFormularioPagamento($pagamento["dataPagamento"] ?? null)) ?>">
                                <div class="form-text">Se o status for Pago e a data ficar vazia, será usada a data atual.</div>
                            </div>

                            <div class="col-md-4">
                                <label for="comprovante" class="form-label">Comprovante</label>
                                <input type="file" class="form-control campo-manual" name="comprovante" id="comprovante"
                                    accept=".jpg,.jpeg,.png,.webp,.pdf">
                                <div class="form-text">JPG, PNG, WEBP ou PDF, com até 10 MB.</div>
                            </div>

                            <div class="col-12">
                                <label for="observacao" class="form-label">Observação</label>
                                <textarea class="form-control campo-manual" name="observacao" id="observacao" rows="3" maxlength="2000"><?= escaparFormularioPagamento($pagamento["observacao"] ?? "") ?></textarea>
                            </div>

                            <?php if (!empty($pagamento["comprovante"])): ?>
                                <div class="col-12">
                                    <a href="<?= escaparFormularioPagamento(BASE_URL . ltrim((string) $pagamento["comprovante"], "/")) ?>"
                                        target="_blank" rel="noopener" class="btn btn-sm btn-outline-primary">
                                        <i class="fa fa-file-arrow-down me-1"></i>
                                        Ver comprovante atual
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-flex flex-column flex-sm-row justify-content-between gap-2 mt-4">
                <div>
                    <?php if ($pagamentoPendente): ?>
                        <button type="button"
                            class="btn btn-outline-danger btn-cancelar-pagamento"
                            data-id="<?= $idPagamento ?>">
                            <i class="fa fa-ban me-1"></i>
                            Cancelar pagamento
                        </button>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button type="submit" class="btn btn-success" id="btnSalvarRecebimento"
                        <?= $pagamentoBloqueado ? "disabled" : "" ?>>
                        <i class="fa <?= $pagamentoBloqueado ? "fa-lock" : "fa-check" ?> me-1"></i>
                        <span id="textoBtnPagamento">
                            <?= $pagamentoBloqueado ? "Pagamento bloqueado" : "Confirmar meio de pagamento" ?>
                        </span>
                    </button>
                </div>
            </div>
        </div>
    </form>

    <script>
        $(function () {
            const formasAsaas = <?= json_encode($formasAsaas, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const asaasConfigurado = <?= $asaasConfigurado ? "true" : "false" ?>;
            const pagamentoBloqueado = <?= $pagamentoBloqueado ? "true" : "false" ?>;
            const formaPagamentoBloqueada = <?= $formaPagamentoBloqueada ? "true" : "false" ?>;
            const mensagemPrazoPagamento = <?= json_encode($mensagemPrazoPagamento, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const mensagemPrazoBoleto = <?= json_encode($mensagemPrazoBoleto, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
            const $forma = $("#formaPagamento");
            const $blocoAsaas = $("#blocoAsaas");
            const $blocoManual = $("#blocoManual");
            const $status = $("#statusPagamento");
            const $data = $("#dataPagamento");
            const $botao = $("#btnSalvarRecebimento");
            const $textoBotao = $("#textoBtnPagamento");

            function ajustarDataManual() {
                const pago = $status.val() === "Pago";
                $data.prop("disabled", !pago || !$blocoManual.is(":visible"));

                if (!pago) {
                    $data.val("");
                }
            }

            function atualizarTela() {
                const forma = String($forma.val() || "NaoDefinido");
                const integrado = formasAsaas.includes(forma);
                const manual = ["Dinheiro", "Transferencia"].includes(forma);

                $blocoAsaas.toggle(integrado);
                $blocoManual.toggle(manual);
                $(".campo-manual").prop("disabled", pagamentoBloqueado || !manual);

                if (integrado) {
                    const textos = {
                        PIX: "Será criada uma cobrança PIX com QR Code e código Copia e Cola. " + mensagemPrazoPagamento,
                        Boleto: mensagemPrazoBoleto,
                        Cartao: "Será criada uma cobrança de cartão na página segura do Asaas. " + mensagemPrazoPagamento
                    };
                    $("#textoRegraAsaas").text(textos[forma] || "");
                    $textoBotao.text("Gerar cobrança no Asaas");
                    $botao.prop("disabled", !asaasConfigurado);
                } else if (manual) {
                    $textoBotao.text("Salvar recebimento manual");
                    $botao.prop("disabled", false);
                } else {
                    $textoBotao.text("Confirmar meio de pagamento");
                    $botao.prop("disabled", true);
                }

                if (formaPagamentoBloqueada) {
                    $forma.prop("disabled", true);
                }

                if (pagamentoBloqueado) {
                    $(".campo-manual").prop("disabled", true);
                    $botao.prop("disabled", true);
                    $botao.find("i")
                        .removeClass("fa-check fa-cloud-arrow-up")
                        .addClass("fa-lock");
                    $textoBotao.text("Pagamento confirmado");
                }

                ajustarDataManual();

                if (pagamentoBloqueado) {
                    $data.prop("disabled", true);
                }
            }

            $forma.on("change", atualizarTela);
            $status.on("change", ajustarDataManual);
            atualizarTela();
        });
    </script>

<?php
} catch (Throwable $erro) {
    error_log(
        "Erro em pagamento-form.php: "
        . $erro->getMessage()
        . " | Linha: "
        . $erro->getLine()
    );
    $host = strtolower((string) ($_SERVER["HTTP_HOST"] ?? ""));
    $localhost = str_contains($host, "localhost")
        || str_contains($host, "127.0.0.1");
    ?>
    <div class="alert alert-danger mb-0">
        Não foi possível carregar o recebimento.
        <?php if ($localhost): ?>
            <hr>
            <strong>Detalhe local:</strong>
            <code><?= escaparFormularioPagamento($erro->getMessage()) ?></code>
        <?php endif; ?>
    </div>
    <?php
}
