<?php

require_once "../../config/settings.php";

Middleware::auth();

$title = "Inscrições";

$pageStyles = [
    THEME_CSS . "admin/inscricao/inscricoes.css?v=" . VERSION
];

$inscricao = new Inscricao();

$evento = new Evento();

/*
|--------------------------------------------------------------------------
| Filtros
|--------------------------------------------------------------------------
*/

$pesquisa = trim($_GET["pesquisa"] ?? "");

$idEvento = (int) ($_GET["evento"] ?? 0);

$status = trim($_GET["status"] ?? "");

$pagamento = trim($_GET["pagamento"] ?? "");

$paginaAtual = max(1, (int) ($_GET["pagina"] ?? 1));

$limite = 15;

/*
|--------------------------------------------------------------------------
| Cards Dashboard
|--------------------------------------------------------------------------
*/

$totalInscricoes = count($inscricao->listar());

$totalConfirmadas = count($inscricao->listarConfirmadas());

$totalPendentes = count($inscricao->listarPendentes());

$totalPagas = count($inscricao->listarPagas());

$cancelamentoService = new SolicitacaoCancelamentoInscricao($db);

$totalCancelamentosPendentes = $cancelamentoService->contarPendentes();

$listaEventos = $evento->listar();


require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";
?>
<!-- <input type="hidden" name="_token" id="_token"> -->
<input type="hidden" name="_token" value="<?= Session::csrf(); ?>" id="_token" data-token="<?= Session::csrf(); ?>">

<div class="content inscricoes-page" id="content">

    <div class="row mb-4">

        <div class="col-md-3">

            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <small class="text-muted">

                        Total Inscrições

                    </small>

                    <h2 class="mb-0">

                        <?= $totalInscricoes ?>

                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <small class="text-muted">

                        Confirmadas

                    </small>

                    <h2 class="text-success mb-0">

                        <?= $totalConfirmadas ?>

                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <small class="text-muted">

                        Pendentes

                    </small>

                    <h2 class="text-warning mb-0">

                        <?= $totalPendentes ?>

                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card shadow-sm border-0">

                <div class="card-body">

                    <small class="text-muted">

                        Pagas

                    </small>

                    <h2 class="text-primary mb-0">

                        <?= $totalPagas ?>

                    </h2>

                </div>

            </div>

        </div>

    </div>

    <div class="card shadow-sm">

        <div class="card-header bg-white d-flex justify-content-between align-items-center">

            <h5 class="mb-0">

                Inscrições

            </h5>

            <div class="d-flex gap-2">
                <a
                    href="cancelamentos.php"
                    class="btn btn-outline-danger"
                >
                    <i class="fa fa-ban me-1"></i>
                    Cancelamentos

                    <?php if (
                        $totalCancelamentosPendentes > 0
                    ): ?>
                        <span
                            class="badge
                                text-bg-danger ms-1"
                        >
                            <?= $totalCancelamentosPendentes; ?>
                        </span>
                    <?php endif; ?>
                </a>
                <a href="../financeiro/pagamentos.php" class="btn btn-outline-success">
                    <i class="fa fa-hand-holding-dollar me-1"></i>
                    Confirmar pagamentos
                </a>

                <a href="inscricao.php" class="btn btn-success">
                    <i class="fa fa-plus me-1"></i>
                    Nova inscrição
                </a>
            </div>

        </div>

        <div class="card-body">

            <div class="alert alert-info">
                <i class="fa fa-circle-info me-1"></i>
                A inscrição relaciona um usuário a um evento. Quando o evento exige pagamento, ela permanece pendente até a confirmação do recebimento no módulo financeiro. O certificado fica disponível depois da confirmação da presença.
            </div>

            <form id="formFiltroInscricoes">

                <div class="row">

                    <div class="col-md-4 mb-3">

                        <label>

                            Pesquisa

                        </label>

                        <input type="text" id="pesquisa" name="pesquisa" class="form-control"
                            value="<?= htmlspecialchars($pesquisa) ?>" placeholder="Nome, CPF ou Email">

                    </div>

                    <div class="col-md-3 mb-3">

                        <label>

                            Evento

                        </label>

                        <select id="evento" name="evento" class="form-select">

                            <option value="0">

                                Todos

                            </option>

                            <?php foreach ($listaEventos as $e): ?>

                                <option value="<?= $e["idEvento"] ?>" <?= $idEvento == $e["idEvento"] ? 'selected' : '' ?>>

                                    <?= htmlspecialchars((string) $e["titulo"], ENT_QUOTES, "UTF-8") ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-2 mb-3">

                        <label>

                            Status

                        </label>

                        <select id="status" name="status" class="form-select">

                            <option value="">Todos</option>

                            <option value="Pendente">Pendente</option>

                            <option value="Confirmada">Confirmada</option>

                            <option value="Cancelada">Cancelada</option>

                        </select>

                    </div>

                    <div class="col-md-3 mb-3">

                        <label>

                            Pagamento

                        </label>

                        <select id="pagamento" name="pagamento" class="form-select">

                            <option value="">Todos</option>

                            <option value="Pendente">Pendente</option>

                            <option value="Pago">Pago</option>

                            <option value="Cancelado">Cancelado</option>
                            <option value="Estornado">Estornado</option>

                        </select>

                    </div>

                </div>

            </form>

            <div id="listaInscricoes">

                <!-- AJAX -->

            </div>

        </div>

    </div>

</div>
<div class="modal fade" id="modalView" tabindex="-1" aria-labelledby="modalViewLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title" id="modalViewLabel">
                    Dados da Inscrição
                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>

            </div>

            <div class="modal-body" id="modalConteudo"></div>

            <div class="modal-footer">

                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Fechar
                </button>

            </div>

        </div>

    </div>
</div>
<?php
$pageScripts = [
    THEME_JS . "admin/inscricao/inscricoes.js?v=" . VERSION
];
require_once "../includes/footer.php";
?>