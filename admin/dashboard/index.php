<?php

declare(strict_types=1);

require_once "../../config/settings.php";

Middleware::auth();

$title = "Dashboard";

$pageStyles = [
    THEME_CSS . "admin/dashboard.css?v=1.1.0"
];

$pageScripts = [
    "https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js",
    THEME_JS . "admin/dashboard/dashboard.js?v=1.1.0"
];

$filtroAno = (int) ($_GET['ano'] ?? 0);
$filtroMes = (int) ($_GET['mes'] ?? 0);
$filtroEvento = max(0, (int) ($_GET['evento'] ?? 0));

if ($filtroAno < 2000 || $filtroAno > 2100) {
    $filtroAno = 0;
}

if ($filtroMes < 1 || $filtroMes > 12) {
    $filtroMes = 0;
}

if ($filtroEvento > 0) {
    $filtroAno = 0;
    $filtroMes = 0;
}

$eventos = (new Evento())->listar();
$anos = [];

foreach ($eventos as $evento) {
    $dataInicio = trim((string) ($evento['data_inicio'] ?? ''));

    if ($dataInicio === '') {
        continue;
    }

    $anoEvento = (int) substr($dataInicio, 0, 4);

    if ($anoEvento >= 2000 && $anoEvento <= 2100) {
        $anos[$anoEvento] = $anoEvento;
    }
}

krsort($anos);

$meses = [
    1 => 'Janeiro',
    2 => 'Fevereiro',
    3 => 'Março',
    4 => 'Abril',
    5 => 'Maio',
    6 => 'Junho',
    7 => 'Julho',
    8 => 'Agosto',
    9 => 'Setembro',
    10 => 'Outubro',
    11 => 'Novembro',
    12 => 'Dezembro'
];

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

?>

<div class="content dashboard-page" id="content">

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
        <div>
            <h2 class="mb-1 fw-bold">
                <i class="fa-solid fa-chart-line me-2 text-primary"></i>
                Dashboard
            </h2>

            <p class="text-muted mb-0">
                Visão geral de eventos, inscrições e recebimentos.
            </p>
        </div>

        <div class="d-flex align-items-center gap-3">
            <small class="text-muted" id="dashboardAtualizadoEm">
                Aguardando atualização...
            </small>

            <button type="button" class="btn btn-outline-primary" id="btnAtualizarDashboard">
                <i class="fa-solid fa-rotate me-1"></i>
                Atualizar
            </button>
        </div>
    </div>

    <div class="alert alert-danger d-none" id="dashboardErro" role="alert"></div>

    <div class="card dashboard-filter-card mb-4">
        <div class="card-body">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h5 class="mb-1">
                        <i class="fa-solid fa-filter me-2 text-primary"></i>
                        Filtros do dashboard
                    </h5>
                    <small class="text-muted">
                        Filtre pela data de início dos eventos ou escolha um evento específico.
                    </small>
                </div>

                <span class="badge text-bg-primary dashboard-filter-summary" id="dashboardFiltroResumo">
                    Todos os dados
                </span>
            </div>

            <div class="row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label for="filtroDashboardAno" class="form-label">Ano</label>
                    <select class="form-select" id="filtroDashboardAno" <?= $filtroEvento > 0 ? 'disabled' : '' ?>>
                        <option value="0">Todos os anos</option>
                        <?php foreach ($anos as $ano): ?>
                            <option value="<?= $ano ?>" <?= $filtroAno === $ano ? 'selected' : '' ?>>
                                <?= $ano ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-3">
                    <label for="filtroDashboardMes" class="form-label">Mês</label>
                    <select class="form-select" id="filtroDashboardMes" <?= $filtroEvento > 0 ? 'disabled' : '' ?>>
                        <option value="0">Todos os meses</option>
                        <?php foreach ($meses as $numeroMes => $nomeMes): ?>
                            <option value="<?= $numeroMes ?>" <?= $filtroMes === $numeroMes ? 'selected' : '' ?>>
                                <?= htmlspecialchars($nomeMes, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-4">
                    <label for="filtroDashboardEvento" class="form-label">Evento</label>
                    <select class="form-select" id="filtroDashboardEvento">
                        <option value="0">Todos os eventos</option>
                        <?php foreach ($eventos as $evento): ?>
                            <?php
                            $idEvento = (int) ($evento['idEvento'] ?? 0);
                            $tituloEvento = (string) ($evento['titulo'] ?? 'Evento sem título');
                            $dataEvento = trim((string) ($evento['data_inicio'] ?? ''));
                            $dataEventoFormatada = $dataEvento !== ''
                                ? date('d/m/Y', strtotime($dataEvento))
                                : 'Sem data';
                            ?>
                            <option value="<?= $idEvento ?>" <?= $filtroEvento === $idEvento ? 'selected' : '' ?>>
                                <?= htmlspecialchars($tituloEvento . ' — ' . $dataEventoFormatada, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-12 col-md-2">
                    <div class="d-grid gap-2 d-sm-flex d-md-grid">
                        <button type="button" class="btn btn-primary" id="btnAplicarFiltroDashboard">
                            <i class="fa-solid fa-magnifying-glass me-1"></i>
                            Aplicar
                        </button>

                        <button type="button" class="btn btn-outline-secondary" id="btnLimparFiltroDashboard">
                            <i class="fa-solid fa-eraser me-1"></i>
                            Limpar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <a href="<?= BASE_URL ?>admin/event/eventos.php" class="dashboard-card-link">
                <div class="card dashboard-stat-card h-100">
                    <div class="card-body">
                        <div class="dashboard-stat-icon bg-primary-subtle text-primary">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>
                        <div>
                            <span class="dashboard-stat-label">Eventos ativos</span>
                            <strong class="dashboard-stat-value" id="cardEventos">0</strong>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="<?= BASE_URL ?>admin/inscricao/inscricoes.php" class="dashboard-card-link">
                <div class="card dashboard-stat-card h-100">
                    <div class="card-body">
                        <div class="dashboard-stat-icon bg-info-subtle text-info">
                            <i class="fa-solid fa-clipboard-list"></i>
                        </div>
                        <div>
                            <span class="dashboard-stat-label">Inscrições</span>
                            <strong class="dashboard-stat-value" id="cardInscritos">0</strong>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card dashboard-stat-card h-100">
                <div class="card-body">
                    <div class="dashboard-stat-icon bg-success-subtle text-success">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                    <div>
                        <span class="dashboard-stat-label">Confirmadas</span>
                        <strong class="dashboard-stat-value" id="cardConfirmados">0</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card dashboard-stat-card h-100">
                <div class="card-body">
                    <div class="dashboard-stat-icon bg-warning-subtle text-warning-emphasis">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                    <div>
                        <span class="dashboard-stat-label">Pendentes</span>
                        <strong class="dashboard-stat-value" id="cardPendentes">0</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="<?= BASE_URL ?>admin/financeiro/pagamentos.php" class="dashboard-card-link">
                <div class="card dashboard-stat-card h-100">
                    <div class="card-body">
                        <div class="dashboard-stat-icon bg-success-subtle text-success">
                            <i class="fa-solid fa-money-bill-transfer"></i>
                        </div>
                        <div>
                            <span class="dashboard-stat-label">Recebido</span>
                            <strong class="dashboard-stat-value dashboard-money" id="cardRecebido">R$ 0,00</strong>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <a href="<?= BASE_URL ?>admin/financeiro/pagamentos.php" class="dashboard-card-link">
                <div class="card dashboard-stat-card h-100">
                    <div class="card-body">
                        <div class="dashboard-stat-icon bg-warning-subtle text-warning-emphasis">
                            <i class="fa-solid fa-hourglass-half"></i>
                        </div>
                        <div>
                            <span class="dashboard-stat-label">A receber</span>
                            <strong class="dashboard-stat-value dashboard-money" id="cardAReceber">R$ 0,00</strong>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card dashboard-stat-card h-100">
                <div class="card-body">
                    <div class="dashboard-stat-icon bg-danger-subtle text-danger">
                        <i class="fa-solid fa-ban"></i>
                    </div>
                    <div>
                        <span class="dashboard-stat-label">Canceladas</span>
                        <strong class="dashboard-stat-value" id="cardCanceladas">0</strong>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card dashboard-stat-card h-100">
                <div class="card-body">
                    <div class="dashboard-stat-icon bg-secondary-subtle text-secondary">
                        <i class="fa-solid fa-person-circle-check"></i>
                    </div>
                    <div>
                        <span class="dashboard-stat-label">Presenças</span>
                        <strong class="dashboard-stat-value" id="cardPresencas">0</strong>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-8">
            <div class="card dashboard-panel h-100">
                <div class="card-header bg-white">
                    <div>
                        <h5 class="mb-1">Inscrições por mês</h5>
                        <small class="text-muted dashboard-subtitle">Últimos 12 meses</small>
                    </div>
                </div>
                <div class="card-body dashboard-chart-large">
                    <canvas id="graficoInscricoes"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card dashboard-panel h-100">
                <div class="card-header bg-white">
                    <div>
                        <h5 class="mb-1">Situação dos pagamentos</h5>
                        <small class="text-muted dashboard-subtitle">Quantidade por status</small>
                    </div>
                </div>
                <div class="card-body dashboard-chart-small">
                    <canvas id="graficoPagamentos"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-8">
            <div class="card dashboard-panel h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">Últimas inscrições</h5>
                        <small class="text-muted dashboard-subtitle">Registros mais recentes</small>
                    </div>

                    <a href="<?= BASE_URL ?>admin/inscricao/inscricoes.php" class="btn btn-sm btn-outline-primary">
                        Ver todas
                    </a>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>#</th>
                                    <th>Participante</th>
                                    <th>Evento</th>
                                    <th>Inscrição</th>
                                    <th>Pagamento</th>
                                    <th>Data</th>
                                </tr>
                            </thead>
                            <tbody id="listaInscricoes">
                                <tr>
                                    <td colspan="6" class="text-center py-5">
                                        <div class="spinner-border spinner-border-sm text-primary"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card dashboard-panel h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-1">Pagamentos pendentes</h5>
                        <small class="text-muted dashboard-subtitle">Recebimentos que aguardam confirmação</small>
                    </div>

                    <a href="<?= BASE_URL ?>admin/financeiro/pagamentos.php" class="btn btn-sm btn-outline-primary">
                        Ver todos
                    </a>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Participante</th>
                                    <th>Valor</th>
                                    <th>Vencimento</th>
                                </tr>
                            </thead>
                            <tbody id="listaFinanceiro">
                                <tr>
                                    <td colspan="3" class="text-center py-5">
                                        <div class="spinner-border spinner-border-sm text-primary"></div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xl-6">
            <div class="card dashboard-panel h-100">
                <div class="card-header bg-white">
                    <div>
                        <h5 class="mb-1">Recebimentos por mês</h5>
                        <small class="text-muted dashboard-subtitle">Valores pagos e pendentes</small>
                    </div>
                </div>
                <div class="card-body dashboard-chart-medium">
                    <canvas id="graficoFinanceiro"></canvas>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card dashboard-panel h-100">
                <div class="card-header bg-white">
                    <div>
                        <h5 class="mb-1">Camisetas</h5>
                        <small class="text-muted dashboard-subtitle">Somente inscrições não canceladas</small>
                    </div>
                </div>
                <div class="card-body dashboard-chart-medium">
                    <canvas id="graficoCamisetas"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once "../includes/footer.php"; ?>
