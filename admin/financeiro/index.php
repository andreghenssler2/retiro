<?php

declare(strict_types=1);

require_once '../../config/settings.php';

Middleware::auth();

$title = 'Relatório Financeiro';

$hoje = new DateTimeImmutable('today');
$dataInicio = $hoje->modify('first day of this month')->format('Y-m-d');
$dataFim = $hoje->modify('last day of this month')->format('Y-m-d');
$eventos = [];

try {
    $eventos = (new Evento())->listar();
} catch (Throwable $erro) {
    error_log('Erro ao carregar eventos no relatório financeiro: ' . $erro->getMessage());
}

$pageStyles = [
    THEME_CSS . 'admin/financeiro/relatorio.css?v=' . VERSION
];

$pageScripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js',
    THEME_JS . 'admin/financeiro/relatorio.js?v=' . VERSION
];

require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '../includes/sidebar.php';

$token = Session::csrf();
?>

<input type="hidden" id="_token" value="<?= htmlspecialchars($token, ENT_QUOTES, 'UTF-8') ?>">

<div class="content financeiro-relatorio" id="content">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-chart-column text-primary me-2"></i>
                Relatório financeiro
            </h2>
            <p class="text-muted mb-0">
                Consulte recebimentos, pendências e cancelamentos por período.
                Pagamentos pendentes e vencidos são considerados pela data de vencimento.
            </p>
        </div>

        <div class="d-flex flex-wrap gap-2">
            <a href="pagamentos.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-credit-card me-1"></i>
                Pagamentos
            </a>
            <button type="button" class="btn btn-danger" id="btnExportarPdf">
                <i class="fa-solid fa-file-pdf me-1"></i>
                Exportar PDF
            </button>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <form id="formRelatorioFinanceiro" autocomplete="off">
                <div class="row g-3 align-items-end">
                    <div class="col-sm-6 col-lg-3">
                        <label for="dataInicio" class="form-label">Data inicial</label>
                        <input type="date" class="form-control" id="dataInicio" name="dataInicio"
                            value="<?= htmlspecialchars($dataInicio, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="col-sm-6 col-lg-3">
                        <label for="dataFim" class="form-label">Data final</label>
                        <input type="date" class="form-control" id="dataFim" name="dataFim"
                            value="<?= htmlspecialchars($dataFim, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="col-lg-4">
                        <label for="idEvento" class="form-label">Evento</label>
                        <select class="form-select" id="idEvento" name="idEvento">
                            <option value="0">Todos os eventos</option>
                            <?php foreach ($eventos as $item): ?>
                                <?php
                                $idEvento = (int) ($item['idEvento'] ?? 0);
                                $tituloEvento = trim((string) ($item['titulo'] ?? ''));
                                if ($idEvento <= 0 || $tituloEvento === '') {
                                    continue;
                                }
                                ?>
                                <option value="<?= $idEvento ?>">
                                    <?= htmlspecialchars($tituloEvento, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-lg-2 d-grid">
                        <button type="submit" class="btn btn-primary" id="btnConsultar">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>
                            Consultar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="alert alert-danger d-none" id="alertaRelatorio" role="alert"></div>

    <div class="row g-3 mb-4" id="cardsFinanceiro">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card financeiro-card h-100">
                <div class="card-body">
                    <div class="financeiro-card-icon bg-primary-subtle text-primary">
                        <i class="fa-solid fa-sack-dollar"></i>
                    </div>
                    <div>
                        <span class="financeiro-card-label">Previsto</span>
                        <strong class="financeiro-card-value" id="totalPrevisto">R$ 0,00</strong>
                        <small class="text-muted" id="quantidadeTotal">0 pagamentos</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card financeiro-card h-100">
                <div class="card-body">
                    <div class="financeiro-card-icon bg-success-subtle text-success">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <span class="financeiro-card-label">Recebido</span>
                        <strong class="financeiro-card-value text-success" id="totalRecebido">R$ 0,00</strong>
                        <small class="text-muted" id="quantidadePago">0 recebimentos</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card financeiro-card h-100">
                <div class="card-body">
                    <div class="financeiro-card-icon bg-warning-subtle text-warning-emphasis">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div>
                        <span class="financeiro-card-label">Pendente/vencido</span>
                        <strong class="financeiro-card-value text-warning-emphasis" id="totalPendente">R$ 0,00</strong>
                        <small class="text-muted" id="quantidadePendente">0 pendências</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card financeiro-card h-100">
                <div class="card-body">
                    <div class="financeiro-card-icon bg-danger-subtle text-danger">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                    <div>
                        <span class="financeiro-card-label">Cancelado/estornado</span>
                        <strong class="financeiro-card-value text-danger" id="totalCancelado">R$ 0,00</strong>
                        <small class="text-muted" id="quantidadeCancelado">0 registros</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card financeiro-painel mb-4">
        <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between gap-2">
            <div>
                <h5 class="mb-1">Evolução financeira</h5>
                <small class="text-muted" id="textoPeriodoGrafico">Recebido e pendente no período.</small>
            </div>
            <div class="financeiro-legenda">
                <span><i class="legenda-cor recebido"></i> Recebido</span>
                <span><i class="legenda-cor pendente"></i> Pendente/vencido</span>
            </div>
        </div>
        <div class="card-body financeiro-grafico">
            <div class="financeiro-loader" id="loaderGrafico">
                <div class="spinner-border text-primary"></div>
                <span>Carregando relatório...</span>
            </div>
            <canvas id="graficoFinanceiro" aria-label="Gráfico financeiro por período"></canvas>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-5">
            <div class="card financeiro-painel h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-1">Recebimentos por forma</h5>
                    <small class="text-muted">Somente pagamentos com status Pago.</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Forma</th>
                                    <th class="text-center">Qtd.</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaFormas"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-7">
            <div class="card financeiro-painel h-100">
                <div class="card-header bg-white">
                    <h5 class="mb-1">Resumo por evento</h5>
                    <small class="text-muted">Valores agrupados por evento.</small>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Evento</th>
                                    <th class="text-center">Qtd.</th>
                                    <th class="text-end">Recebido</th>
                                    <th class="text-end">Pendente/vencido</th>
                                </tr>
                            </thead>
                            <tbody id="tabelaEventos"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card financeiro-painel">
        <div class="card-header bg-white d-flex flex-column flex-md-row justify-content-between gap-2">
            <div>
                <h5 class="mb-1">Movimentações do período</h5>
                <small class="text-muted">Pagamentos encontrados conforme a data de referência financeira.</small>
            </div>
            <span class="badge text-bg-light align-self-md-center" id="totalMovimentos">0 registros</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Data</th>
                            <th>Código</th>
                            <th>Participante</th>
                            <th>Evento</th>
                            <th>Forma</th>
                            <th>Status</th>
                            <th class="text-end">Valor</th>
                        </tr>
                    </thead>
                    <tbody id="tabelaMovimentos"></tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
