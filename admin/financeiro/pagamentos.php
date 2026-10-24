<?php

declare(strict_types=1);

require_once "../../config/settings.php";

Middleware::auth();

$title = "Pagamentos";

$pagamento = new Pagamento($db);
$evento = new Evento();

$totalRecebido = 0.0;
$totalPendente = 0.0;
$totalVencido = 0.0;
$totalCancelado = 0.0;
$listaEventos = [];

try {
    $totalRecebido = $pagamento->totalRecebido();
    $totalPendente = $pagamento->totalPendente();
    $totalVencido = $pagamento->totalVencido();
    $totalCancelado = $pagamento->totalCancelado();
    $listaEventos = $evento->listar();
} catch (Throwable $erro) {
    error_log("Erro ao carregar pagamentos: " . $erro->getMessage());
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

$csrfToken = Session::csrf();

function moedaPagamentoPagina(float $valor): string
{
    return "R$ " . number_format($valor, 2, ",", ".");
}
?>

<input type="hidden" id="_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, "UTF-8") ?>">

<div class="content" id="content">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-money-bill-wave text-success me-1"></i>
                Pagamentos
            </h2>
            <p class="text-muted mb-0">
                Os pagamentos são gerados pelas inscrições. PIX, boleto e cartão usam o Asaas; dinheiro e transferência são registrados manualmente.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="<?= BASE_URL ?>admin/financeiro/" class="btn btn-outline-primary">
                <i class="fa-solid fa-chart-column me-1"></i>
                Relatório financeiro
            </a>
            <button type="button" id="btnAtualizar" class="btn btn-outline-secondary">
                <i class="fa fa-rotate me-1"></i>
                Atualizar
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-lg-3">
            <div class="card border-success shadow-sm h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Total recebido</small>
                        <h2 id="cardRecebido" class="text-success mb-0">
                            <?= moedaPagamentoPagina($totalRecebido) ?>
                        </h2>
                    </div>
                    <i class="fa fa-circle-check fs-1 text-success opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card border-warning shadow-sm h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Total pendente</small>
                        <h2 id="cardPendente" class="text-warning mb-0">
                            <?= moedaPagamentoPagina($totalPendente) ?>
                        </h2>
                    </div>
                    <i class="fa fa-clock fs-1 text-warning opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card border-danger shadow-sm h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Total vencido</small>
                        <h2 id="cardVencido" class="text-danger mb-0">
                            <?= moedaPagamentoPagina($totalVencido) ?>
                        </h2>
                    </div>
                    <i class="fa fa-triangle-exclamation fs-1 text-danger opacity-50"></i>
                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card border-secondary shadow-sm h-100">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted">Total cancelado</small>
                        <h2 id="cardCancelado" class="text-secondary mb-0">
                            <?= moedaPagamentoPagina($totalCancelado) ?>
                        </h2>
                    </div>
                    <i class="fa fa-circle-xmark fs-1 text-secondary opacity-50"></i>
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white">
            <form id="formFiltrosPagamento" autocomplete="off">
                <div class="row g-3 align-items-end">
                    <div class="col-lg-3">
                        <label for="pesquisa" class="form-label">Pesquisa</label>
                        <input type="search" id="pesquisa" class="form-control"
                            placeholder="Participante, código ou descrição" maxlength="150">
                    </div>

                    <div class="col-lg-3">
                        <label for="evento" class="form-label">Evento</label>
                        <select id="evento" class="form-select">
                            <option value="0">Todos os eventos</option>
                            <?php foreach ($listaEventos as $itemEvento): ?>
                                <?php
                                $idEvento = (int) ($itemEvento["idEvento"] ?? 0);
                                $tituloEvento = trim((string) ($itemEvento["titulo"] ?? ""));
                                if ($idEvento <= 0 || $tituloEvento === "") {
                                    continue;
                                }
                                ?>
                                <option value="<?= $idEvento ?>">
                                    <?= htmlspecialchars($tituloEvento, ENT_QUOTES, "UTF-8") ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <label for="status" class="form-label">Status</label>
                        <select id="status" class="form-select">
                            <option value="">Todos</option>
                            <option value="Pendente">Pendente</option>
                            <option value="Vencido">Vencido</option>
                            <option value="Pago">Pago</option>
                            <option value="Cancelado">Cancelado</option>
                            <option value="Estornado">Estornado</option>
                        </select>
                    </div>

                    <div class="col-lg-2">
                        <label for="forma" class="form-label">Forma</label>
                        <select id="forma" class="form-select">
                            <option value="">Todas</option>
                            <option value="NaoDefinido">A definir</option>
                            <option value="PIX">PIX</option>
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Cartao">Cartão</option>
                            <option value="Boleto">Boleto</option>
                            <option value="Transferencia">Transferência</option>
                        </select>
                    </div>

                    <div class="col-lg-2 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-search me-1"></i>
                            Pesquisar
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <div class="card-body p-0">
            <div id="loaderPagamentos" class="text-center py-5">
                <div class="spinner-border text-primary"></div>
                <div class="mt-2 text-muted">Carregando pagamentos...</div>
            </div>

            <div id="listaPagamentos" class="table-responsive" style="display:none"></div>
        </div>

        <div class="card-footer bg-white">
            <div class="row align-items-center g-2">
                <div class="col-md-6">
                    <small id="textoPaginacao" class="text-muted">Nenhum pagamento encontrado.</small>
                </div>
                <div class="col-md-6">
                    <ul id="paginacao" class="pagination pagination-sm justify-content-md-end mb-0"></ul>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalView" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fa fa-eye text-primary me-2"></i>
                    Detalhes do pagamento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" id="modalConteudo"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalPagamento" tabindex="-1" aria-hidden="true"
    data-bs-backdrop="static" data-bs-keyboard="false">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="tituloModalPagamento">
                    <i class="fa fa-hand-holding-dollar me-2"></i>
                    Atualizar recebimento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body p-0" id="conteudoModalPagamento"></div>
        </div>
    </div>
</div>

<?php
$pageScripts = [
    THEME_JS . "admin/financeiro/pagamentos.js?v=" . VERSION
];
require_once "../includes/footer.php";
?>
