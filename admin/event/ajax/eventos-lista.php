<?php

require_once "../../../config/settings.php";


Middleware::auth();

$evento = new Evento();

$pesquisa = trim($_GET["pesquisa"] ?? "");
$tipo = trim($_GET["tipo"] ?? "");
$status = trim($_GET["status"] ?? "");

$paginaAtual = max(1, (int) ($_GET["pagina"] ?? 1));
$limite = 15;

$lista = $evento->listarPaginado(
    $pesquisa,
    $tipo,
    $status,
    "data_inicio",
    "DESC",
    $paginaAtual,
    $limite
);

$eventos = $lista["dados"];
$total = $lista["total"];
$totalPaginas = max(1, ceil($total / $limite));

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

<div class="table-responsive">

    <table class="table table-hover align-middle mb-0">

        <thead class="table-light">

            <tr>

                <th width="70">ID</th>

                <th>Título</th>

                <th>Tipo</th>

                <th>Data</th>

                <th>Local</th>

                <th>Valor</th>

                <th>Vagas</th>

                <th>Status</th>

                <th width="180" class="text-center">Ações</th>

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

                            <strong>#<?= $e["idEvento"] ?></strong>

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

                            <span class="badge bg-<?= $cores[$e["tipo"]] ?? "secondary" ?>">

                                <?= $e["tipo"] ?>

                            </span>

                        </td>

                        <td>

                            <?= date("d/m/Y", strtotime($e["data_inicio"])) ?>

                            <?php if (!empty($e["hora_inicio"])): ?>

                                <br>

                                <small>

                                    <?= substr($e["hora_inicio"], 0, 5) ?>

                                </small>

                            <?php endif; ?>

                        </td>

                        <td>

                            <?= htmlspecialchars($e["cidade"]) ?>

                        </td>

                        <td>

                            R$ <?= number_format($e["valor"], 2, ",", ".") ?>

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
                            <button class="btn btn-sm btn-outline-primary btn-editar" data-id="<?= $e["idEvento"] ?>"
                                title="Editar">

                                <i class="fa fa-pencil"></i>

                            </button>
                            <!-- <a href="evento.php?id=<?= $e["idEvento"] ?>" class="btn btn-sm btn-outline-primary" title="Editar">
                            </a> -->

                            <button class="btn btn-sm btn-outline-info btn-visualizar" data-id="<?= $e["idEvento"] ?>"
                                title="Visualizar">

                                <i class="fa fa-eye"></i>

                            </button>

                            <button class="btn btn-sm btn-outline-warning btn-status" data-token="<?= Session::csrf() ?>"
                                data-id="<?= $e["idEvento"] ?>" data-status="<?= $e["ativo"] ?>" title="Ativar/Inativar">

                                <i class="fa fa-power-off"></i>

                            </button>

                            <button class="btn btn-sm btn-outline-danger btn-excluir" data-id="<?= $e["idEvento"] ?>"
                                data-titulo="<?= htmlspecialchars($e["titulo"]) ?>" title="Excluir">

                                <i class="fa fa-trash"></i>

                            </button>

                        </td>

                    </tr>

                <?php endforeach; ?>

            <?php endif; ?>

        </tbody>

    </table>

</div>

<?php if ($totalPaginas > 1): ?>

    <nav class="mt-4">

        <ul class="pagination justify-content-center">

            <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>

                <li class="page-item <?= $paginaAtual == $i ? "active" : "" ?>">

                    <a href="#" class="page-link pagina-evento" data-pagina="<?= $i ?>">

                        <?= $i ?>

                    </a>

                </li>

            <?php endfor; ?>

        </ul>

    </nav>

<?php endif; ?>