<?php

declare(strict_types=1);

require_once '../../config/settings.php';

Middleware::auth();

$title = 'Relatórios';
$hoje = new DateTimeImmutable('today');
$dataInicio = $hoje->modify('first day of this month')->format('Y-m-d');
$dataFim = $hoje->modify('last day of this month')->format('Y-m-d');
$eventos = [];

try {
    $eventos = (new Evento())->listar();
} catch (Throwable $erro) {
    error_log('Erro ao carregar eventos nos relatórios: ' . $erro->getMessage());
}

$pageStyles = [
    THEME_CSS . 'admin/relatorios/relatorios.css?v=' . VERSION,
];

$pageScripts = [
    'https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js',
    THEME_JS . 'admin/relatorios/relatorios.js?v=' . VERSION,
];

require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '../includes/sidebar.php';
?>

<input type="hidden" id="_token" value="<?= htmlspecialchars(Session::csrf(), ENT_QUOTES, 'UTF-8') ?>">

<div class="content relatorios-page" id="content">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-chart-pie text-primary me-2"></i>
                Central de relatórios
            </h2>
            <p class="text-muted mb-0">
                Consulte dados financeiros, pagamentos, eventos, usuários e inscrições.
            </p>
        </div>
                <a
            href="<?= BASE_URL ?>admin/relatorios/evento-exportacao.php"
            class="btn btn-primary"
        >
            <i class="fa-solid fa-file-export me-1"></i>
            Exportar evento
        </a>
<button type="button" class="btn btn-danger" id="btnExportarPdf">
            <i class="fa-solid fa-file-pdf me-1"></i>
            Exportar PDF
        </button>
    </div>

    <div class="row g-3 mb-4 relatorio-tipos" role="group" aria-label="Tipo de relatório">
        <?php
        $tipos = [
            ['financeiro', 'fa-sack-dollar', 'Financeiro', 'Resumo de valores por evento'],
            ['pagamentos', 'fa-credit-card', 'Pagamentos', 'Cobranças e recebimentos'],
            ['eventos', 'fa-calendar-days', 'Eventos', 'Criados, em andamento e finalizados'],
            ['usuarios', 'fa-users', 'Usuários', 'Ativos, inativos e perfis'],
            ['inscricoes', 'fa-clipboard-check', 'Inscrições', 'Situação e presença'],
        ];
        foreach ($tipos as [$valor, $icone, $nome, $descricao]):
        ?>
            <div class="col-12 col-sm-6 col-xl">
                <button type="button" class="relatorio-tipo-card <?= $valor === 'financeiro' ? 'active' : '' ?>"
                    data-tipo="<?= $valor ?>">
                    <span class="relatorio-tipo-icon"><i class="fa-solid <?= $icone ?>"></i></span>
                    <span>
                        <strong><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></strong>
                        <small><?= htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8') ?></small>
                    </span>
                </button>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white">
            <strong><i class="fa-solid fa-filter me-1 text-primary"></i> Filtros</strong>
        </div>
        <div class="card-body">
            <form id="formRelatorios" autocomplete="off">
                <input type="hidden" name="tipo" id="tipoRelatorio" value="financeiro">

                <div class="row g-3 align-items-end">
                    <div class="col-sm-6 col-xl-2">
                        <label for="dataInicio" class="form-label">Data inicial</label>
                        <input type="date" class="form-control" id="dataInicio" name="dataInicio"
                            value="<?= htmlspecialchars($dataInicio, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="col-sm-6 col-xl-2">
                        <label for="dataFim" class="form-label">Data final</label>
                        <input type="date" class="form-control" id="dataFim" name="dataFim"
                            value="<?= htmlspecialchars($dataFim, ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>

                    <div class="col-md-6 col-xl-3 filtro-campo" data-tipos="financeiro pagamentos eventos inscricoes">
                        <label for="idEvento" class="form-label">Evento</label>
                        <select class="form-select" id="idEvento" name="idEvento">
                            <option value="0">Todos os eventos</option>
                            <?php foreach ($eventos as $evento): ?>
                                <?php if ((int) ($evento['idEvento'] ?? 0) <= 0) continue; ?>
                                <option value="<?= (int) $evento['idEvento'] ?>">
                                    <?= htmlspecialchars((string) ($evento['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 col-xl-2 filtro-campo" data-tipos="financeiro pagamentos inscricoes">
                        <label for="status" class="form-label">Situação</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todas</option>
                        </select>
                    </div>

                    <div class="col-md-6 col-xl-2 filtro-campo" data-tipos="inscricoes">
                        <label for="statusPagamento" class="form-label">Pagamento</label>
                        <select class="form-select" id="statusPagamento" name="statusPagamento">
                            <option value="">Todos</option>
                            <option>Pendente</option>
                            <option>Vencido</option>
                            <option>Pago</option>
                            <option>Cancelado</option>
                            <option>Estornado</option>
                        </select>
                    </div>

                    <div class="col-md-6 col-xl-2 filtro-campo" data-tipos="financeiro pagamentos">
                        <label for="formaPagamento" class="form-label">Forma</label>
                        <select class="form-select" id="formaPagamento" name="formaPagamento">
                            <option value="">Todas</option>
                            <option value="NaoDefinido">Não definido</option>
                            <option value="PIX">PIX</option>
                            <option value="Cartao">Cartão</option>
                            <option value="Boleto">Boleto</option>
                            <option value="Dinheiro">Dinheiro</option>
                            <option value="Transferencia">Transferência</option>
                        </select>
                    </div>

                    <div class="col-md-6 col-xl-2 filtro-campo" data-tipos="financeiro pagamentos">
                        <label for="integracao" class="form-label">Integração</label>
                        <select class="form-select" id="integracao" name="integracao">
                            <option value="">Todas</option>
                            <option value="Asaas">Asaas</option>
                            <option value="Manual">Manual</option>
                        </select>
                    </div>

                    <div class="col-md-6 col-xl-2 filtro-campo" data-tipos="eventos">
                        <label for="situacaoEvento" class="form-label">Situação do evento</label>
                        <select class="form-select" id="situacaoEvento" name="situacaoEvento">
                            <option value="">Todas</option>
                            <option value="Criados">Criados</option>
                            <option value="EmAndamento">Em andamento</option>
                            <option value="Cancelados">Cancelados</option>
                            <option value="Executados">Executados</option>
                        </select>
                    </div>

                    <div class="col-md-6 col-xl-2 filtro-campo" data-tipos="eventos">
                        <label for="tipoEvento" class="form-label">Tipo de evento</label>
                        <select class="form-select" id="tipoEvento" name="tipoEvento">
                            <option value="">Todos</option>
                            <option>Retiro</option><option>Congresso</option><option>Acampamento</option>
                            <option>Curso</option><option>Encontro</option><option>Culto</option><option>Outro</option>
                        </select>
                    </div>

                    <div class="col-md-6 col-xl-2 filtro-campo" data-tipos="usuarios">
                        <label for="situacaoUsuario" class="form-label">Situação do usuário</label>
                        <select class="form-select" id="situacaoUsuario" name="situacaoUsuario">
                            <option value="">Todos</option>
                            <option value="Ativos">Ativos</option>
                            <option value="Inativos">Inativos</option>
                        </select>
                    </div>

                    <div class="col-md-6 col-xl-2 filtro-campo" data-tipos="usuarios">
                        <label for="perfilUsuario" class="form-label">Perfil</label>
                        <select class="form-select" id="perfilUsuario" name="perfilUsuario">
                            <option value="0">Todos</option>
                            <option value="1">Administrador</option>
                            <option value="2">Moderador</option>
                            <option value="3">Usuário normal</option>
                        </select>
                    </div>

                    <div class="col-md-6 col-xl-2 filtro-campo" data-tipos="inscricoes">
                        <label for="presenca" class="form-label">Presença</label>
                        <select class="form-select" id="presenca" name="presenca">
                            <option value="">Todas</option>
                            <option value="Sim">Presente</option>
                            <option value="Nao">Não presente</option>
                        </select>
                    </div>

                    <div class="col-md-8 col-xl-3">
                        <label for="pesquisa" class="form-label">Pesquisar</label>
                        <input type="search" class="form-control" id="pesquisa" name="pesquisa"
                            placeholder="Nome, código, e-mail ou evento">
                    </div>

                    <div class="col-md-4 col-xl-2">
                        <label for="limite" class="form-label">Registros na tela</label>
                        <select class="form-select" id="limite" name="limite">
                            <option>25</option><option>50</option><option selected>100</option><option>250</option><option>500</option>
                        </select>
                    </div>

                    <div class="col-xl-auto d-flex gap-2">
                        <button type="submit" class="btn btn-primary" id="btnConsultar">
                            <i class="fa-solid fa-magnifying-glass me-1"></i> Consultar
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="btnLimpar">
                            <i class="fa-solid fa-eraser me-1"></i> Limpar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="alert alert-danger d-none" id="alertaRelatorio" role="alert"></div>

    <div class="row g-3 mb-4" id="cardsRelatorio"></div>

    <div class="row g-4 mb-4">
        <div class="col-12 col-xl-4">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white">
                    <strong id="tituloGrafico">Resumo</strong>
                </div>
                <div class="card-body relatorio-grafico-wrap">
                    <canvas id="graficoRelatorio" aria-label="Gráfico do relatório"></canvas>
                    <div class="text-center text-muted py-5 d-none" id="graficoVazio">Sem dados para o gráfico.</div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-8">
            <div class="card shadow-sm border-0 h-100">
                <div class="card-header bg-white d-flex flex-wrap justify-content-between gap-2">
                    <div>
                        <strong id="tituloResultado">Resultado</strong>
                        <small class="text-muted d-block" id="descricaoResultado"></small>
                    </div>
                    <span class="badge text-bg-light align-self-center" id="totalResultado">0 registros</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive relatorio-tabela-wrap">
                        <table class="table table-hover align-middle mb-0" id="tabelaRelatorio">
                            <thead class="table-light"><tr><th>Carregando...</th></tr></thead>
                            <tbody><tr><td class="text-center py-5 text-muted">Aguarde.</td></tr></tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white d-none" id="observacaoRelatorio"></div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/footer.php'; ?>
