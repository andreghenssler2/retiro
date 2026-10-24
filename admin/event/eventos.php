<?php

require_once "../../config/settings.php";

Middleware::auth();

$evento = new Evento();

/*
|--------------------------------------------------------------------------
| Filtros
|--------------------------------------------------------------------------
*/

$pesquisa = trim($_GET["pesquisa"] ?? "");

$tipo = trim($_GET["tipo"] ?? "");

$status = trim($_GET["status"] ?? "");

$paginaA = max(1, (int) ($_GET["pagina"] ?? 1));

$limite = 15;

$lista = $evento->listarPaginado(

    $pesquisa,

    $tipo,

    $status,

    "data_inicio",

    "DESC",

    $paginaA,

    $limite

);

$eventos = $lista["dados"];

$total = $lista["total"];

$totalPaginas = max(1, ceil($total / $limite));

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";
?>

<div class="content" id="content">

    <div class="row mb-4">

        <div class="col-md-6">

            <h2 class="fw-bold">

                <i class="fa fa-calendar-days text-primary"></i>

                Eventos

            </h2>

            <small class="text-muted">

                Cadastro e gerenciamento de eventos

            </small>

        </div>

        <div class="col-md-6 text-end">

            <a href="evento.php" class="btn btn-success">

                <i class="fa fa-plus"></i>

                Novo Evento

            </a>

        </div>

    </div>

    <div class="card shadow-sm mb-4">

        <div class="card-header bg-light">

            <strong>

                <i class="fa fa-filter"></i>

                Filtros

            </strong>

        </div>

        <div class="card-body">

            <form method="GET" id="formFiltroEventos">

                <div class="row">

                    <div class="col-md-4">

                        <label class="form-label">

                            Pesquisa

                        </label>

                        <input type="text" id="pesquisa" name="pesquisa" class="form-control"
                            value="<?= htmlspecialchars($pesquisa) ?>" placeholder="Título, cidade, local...">

                    </div>

                    <div class="col-md-3">

                        <label class="form-label">

                            Tipo

                        </label>

                        <select name="tipo" id="tipo" class="form-select">

                            <option value="">

                                Todos

                            </option>

                            <?php

                            $tipos = [

                                "Retiro",

                                "Congresso",

                                "Acampamento",

                                "Curso",

                                "Encontro",

                                "Culto",

                                "Outro"

                            ];

                            foreach ($tipos as $t):

                                ?>

                                <option value="<?= $t ?>" <?= $tipo == $t ? "selected" : "" ?>>

                                    <?= $t ?>

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>

                    <div class="col-md-2">

                        <label class="form-label">

                            Status

                        </label>

                        <select name="status" id="status" class="form-select">

                            <option value="">

                                Todos

                            </option>

                            <option value="1" <?= $status === "1" ? "selected" : "" ?>>

                                Ativos

                            </option>

                            <option value="0" <?= $status === "0" ? "selected" : "" ?>>

                                Inativos

                            </option>

                        </select>

                    </div>



                </div>

            </form>

        </div>

    </div>

    <div class="row mb-4">

        <div class="col-md-3">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <h6 class="text-muted">

                        Total de Eventos

                    </h6>

                    <h2>

                        <?= $total ?>

                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <h6 class="text-muted">

                        Página Atual

                    </h6>

                    <h2>

                        <?= $paginaA ?>

                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <h6 class="text-muted">

                        Registros

                    </h6>

                    <h2>

                        <?= count($eventos) ?>

                    </h2>

                </div>

            </div>

        </div>

        <div class="col-md-3">

            <div class="card border-0 shadow-sm">

                <div class="card-body">

                    <h6 class="text-muted">

                        Total de Páginas

                    </h6>

                    <h2>

                        <?= $totalPaginas ?>

                    </h2>

                </div>

            </div>

        </div>

    </div>

    <div class="card shadow-sm">

        <div class="card-header bg-white">

            <strong>

                Lista de Eventos

            </strong>

        </div>

        <div class="card-body p-0">
            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0" id="listatabela">

                    <thead class="table-light">

                        <tr>

                            <th width="80">ID</th>

                            <th>Título</th>

                            <th>Tipo</th>

                            <th>Data</th>

                            <th>Cidade</th>

                            <th>Valor</th>

                            <th>Vagas</th>

                            <th>Status</th>

                            <th width="220" class="text-center">Ações</th>

                        </tr>

                    </thead>

                    <tbody>

                        <?php if (empty($eventos)): ?>

                            <tr>

                                <td colspan="9" class="text-center p-5">

                                    <i class="fa fa-calendar-xmark fa-3x text-muted mb-3"></i>

                                    <br>

                                    Nenhum evento encontrado.

                                </td>

                            </tr>

                        <?php else: ?>

                            <?php foreach ($eventos as $e): ?>

                                <tr>

                                    <td>

                                        <strong>

                                            #<?= $e["idEvento"] ?>

                                        </strong>

                                    </td>

                                    <td>

                                        <strong>

                                            <?= htmlspecialchars($e["titulo"]) ?>

                                        </strong>

                                        <?php if (!empty($e["descricao_curta"])): ?>

                                            <br>

                                            <small class="text-muted">

                                                <?= htmlspecialchars($e["descricao_curta"]) ?>

                                            </small>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?php

                                        $cores = [

                                            "Retiro" => "primary",

                                            "Congresso" => "success",

                                            "Acampamento" => "warning",

                                            "Curso" => "info",

                                            "Encontro" => "secondary",

                                            "Culto" => "dark",

                                            "Outro" => "dark"

                                        ];

                                        ?>

                                        <span class="badge bg-<?= $cores[$e["tipo"]] ?? "secondary" ?>">

                                            <?= $e["tipo"] ?>

                                        </span>

                                    </td>

                                    <td>

                                        <?= date("d/m/Y", strtotime($e["data_inicio"])) ?>

                                        <?php if (!empty($e["hora_inicio"])): ?>

                                            <br>

                                            <small class="text-muted">

                                                <?= substr($e["hora_inicio"], 0, 5) ?>

                                            </small>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <?= htmlspecialchars($e["cidade"]) ?>

                                    </td>

                                    <td>

                                        <?= number_format($e["valor"], 2, ",", ".") ?>

                                    </td>

                                    <td>

                                        <?= $e["vagas"] ?: "-" ?>

                                    </td>

                                    <td>

                                        <?php if ($e["ativo"]): ?>

                                            <span class="badge bg-success">

                                                Ativo

                                            </span>

                                        <?php else: ?>

                                            <span class="badge bg-danger">

                                                Inativo

                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td class="text-center">

                                        <a href="evento.php?id=<?= $e["idEvento"] ?>" class="btn btn-sm btn-outline-primary"
                                            title="Editar">

                                            <i class="fa fa-pencil"></i>

                                        </a>

                                        <button class="btn btn-sm btn-outline-info btn-visualizar"
                                            data-id="<?= $e["idEvento"] ?>" title="Visualizar">

                                            <i class="fa fa-eye"></i>

                                        </button>
                                        <!-- <input type="hidden" id="_token" name="_token" value="<?= Session::csrf() ?>"> -->
                                        <button class="btn btn-sm btn-outline-warning btn-status"  data-token="<?= Session::csrf() ?>" data-id="<?= $e["idEvento"] ?>" data-status="<?= $e["ativo"] ?>"
                                            title="Ativar/Inativar">

                                            <i class="fa fa-power-off"></i>

                                        </button>

                                        <button class="btn btn-sm btn-outline-danger btn-excluir"
                                            data-id="<?= $e["idEvento"] ?>" data-titulo="<?= htmlspecialchars($e["titulo"]) ?>"
                                            title="Excluir">

                                            <i class="fa fa-trash"></i>

                                        </button>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>
                <div id="listaEventos">

                    <?php # include "ajax/eventos-lista.php"; ?>

                </div>

            </div>
        </div>

    </div>

    <?php if ($totalPaginas > 1): ?>

        <div class="mt-4">

            <nav>

                <ul class="pagination justify-content-center">

                    <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>

                        <li class="page-item <?= $paginaA == $i ? 'active' : '' ?>">

                            <a class="page-link" href="?pagina=<?= $i ?>

                               &pesquisa=<?= urlencode($pesquisa) ?>

                               &tipo=<?= urlencode($tipo) ?>

                               &status=<?= urlencode($status) ?>">

                                <?= $i ?>

                            </a>

                        </li>

                    <?php endfor; ?>

                </ul>

            </nav>

        </div>

    <?php endif; ?>

</div>

<!-- Modal Exclusão -->

<div class="modal fade" id="modalExcluir" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog">

        <div class="modal-content">

            <div class="modal-header bg-danger text-white">

                <h5 class="modal-title">

                    Excluir Evento

                </h5>

                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal">

                </button>

            </div>

            <div class="modal-body">

                <p>

                    Deseja realmente excluir o evento

                    <strong id="nomeEventoExcluir"></strong>?

                </p>

            </div>

            <div class="modal-footer">

                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">

                    Cancelar

                </button>

                <button type="button" class="btn btn-danger" id="confirmarExcluirEvento">

                    Excluir

                </button>

            </div>

        </div>

    </div>

</div>

<!-- Modal Visualização -->

<div class="modal fade" id="modalVisualizar" tabindex="-1" aria-hidden="true">

    <div class="modal-dialog modal-xl">

        <div class="modal-content">

            <div class="modal-header">

                <h5 class="modal-title">

                    Visualizar Evento

                </h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal">

                </button>

            </div>

            <div class="modal-body" id="conteudoEvento">

                <div class="text-center p-5">

                    <div class="spinner-border text-primary"></div>

                </div>

            </div>

        </div>

    </div>

</div>
<script>
    const BASE_URL = "<?= BASE_URL ?>";
</script>

<?php include "../includes/footer.php"; ?>

<script src="<?= THEME_JS ?>admin/event/js/eventos.js?v=<?= time(); ?>"></script>
<script>
    $(function () {

        

        

    });
</script>