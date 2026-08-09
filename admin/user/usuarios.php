<?php

require_once "../../config/settings.php";

Middleware::auth();

$db = Config::getDB();
$usuario = new Usuario();

/*
|--------------------------------------------------------------------------
| Estatísticas
|--------------------------------------------------------------------------
*/

$totalUsuarios = 0;
$totalAdmins = 0;
$totalAtivos = 0;
$totalInativos = 0;

try {

    $totalUsuarios = (int) $db->query("
        SELECT COUNT(*)
        FROM usuarios
    ")->fetchColumn();

    $totalAdmins = (int) $db->query("
        SELECT COUNT(*)
        FROM usuarios
        WHERE tipo=1
           OR tipo='admin'
    ")->fetchColumn();

    $totalAtivos = (int) $db->query("
        SELECT COUNT(*)
        FROM usuarios
        WHERE ativo=1
    ")->fetchColumn();

    $totalInativos = (int) $db->query("
        SELECT COUNT(*)
        FROM usuarios
        WHERE ativo=0
    ")->fetchColumn();

} catch (PDOException $e) {

    $totalUsuarios = 0;
    $totalAdmins = 0;
    $totalAtivos = 0;
    $totalInativos = 0;

}

/*
|--------------------------------------------------------------------------
| Listagem
|--------------------------------------------------------------------------
*/

$usuarios = [];

try {

    $usuarios = $usuario->listar();

} catch (Exception $e) {

    $usuarios = [];

}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";
?>

<div class="content" id="content">

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold mb-1">

                    <i class="fa fa-users"></i>

                    Usuários

                </h2>

                <p class="text-muted mb-0">

                    Gerenciamento de usuários do sistema.

                </p>

            </div>

            <div>

                <a href="usuario.php" class="btn btn-primary">

                    <i class="fa fa-plus"></i>

                    Novo Usuário

                </a>

            </div>

        </div>

        <!-- Cards -->

        <div class="row g-3 mb-4">

            <div class="col-xl-3 col-md-6">

                <div class="card shadow-sm border-0">

                    <div class="card-body">

                        <div class="small text-muted mb-2">

                            <i class="fa fa-users"></i>

                            Total Usuários

                        </div>

                        <h2 class="mb-0">

                            <?= $totalUsuarios ?>

                        </h2>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-md-6">

                <div class="card shadow-sm border-0">

                    <div class="card-body">

                        <div class="small text-muted mb-2">

                            <i class="fa fa-user-shield"></i>

                            Administradores

                        </div>

                        <h2 class="mb-0">

                            <?= $totalAdmins ?>

                        </h2>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-md-6">

                <div class="card shadow-sm border-0">

                    <div class="card-body">

                        <div class="small text-muted mb-2">

                            <i class="fa fa-user-check"></i>

                            Ativos

                        </div>

                        <h2 class="mb-0 text-success">

                            <?= $totalAtivos ?>

                        </h2>

                    </div>

                </div>

            </div>

            <div class="col-xl-3 col-md-6">

                <div class="card shadow-sm border-0">

                    <div class="card-body">

                        <div class="small text-muted mb-2">

                            <i class="fa fa-user-slash"></i>

                            Inativos

                        </div>

                        <h2 class="mb-0 text-danger">

                            <?= $totalInativos ?>

                        </h2>

                    </div>

                </div>

            </div>

        </div>

        <!-- Pesquisa -->

        <div class="card shadow-sm border-0 mb-4">

            <div class="card-body">

                <div class="row g-3">

                    <div class="col-lg-6">

                        <label class="form-label">

                            Pesquisar

                        </label>

                        <input type="text" id="pesquisar" class="form-control" placeholder="Nome ou e-mail">

                    </div>

                    <div class="col-lg-3">

                        <label class="form-label">

                            Perfil

                        </label>

                        <select class="form-select" id="filtroPerfil">

                            <option value="">Todos</option>

                            <option value="Administrador">
                                Administrador
                            </option>
                            <option value="Moderador">
                                Moderador
                            </option>

                            <option value="Usuário">
                                Usuário
                            </option>

                        </select>

                    </div>

                    <div class="col-lg-3">

                        <label class="form-label">

                            Status

                        </label>

                        <select class="form-select" id="filtroStatus">

                            <option value="">Todos</option>

                            <option value="Ativo">

                                Ativo

                            </option>

                            <option value="Inativo">

                                Inativo

                            </option>

                        </select>

                    </div>

                </div>

            </div>

        </div>

        <!-- Tabela -->

        <div class="card shadow-sm border-0">

            <div class="table-responsive">

                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">

                        <tr>

                            <th width="70">#</th>

                            <th>Usuário</th>

                            <th width="170">Perfil</th>

                            <th width="190">Último Login</th>

                            <th width="120">Status</th>

                            <th width="170">Ações</th>

                        </tr>

                    </thead>

                    <tbody id="tbodyUsuarios">
                        <?php if (!empty($usuarios)): ?>

                            <?php foreach ($usuarios as $u): ?>

                                <?php

                                $perfil = ($u["tipo"] == 1 || $u["tipo"] == "admin")
                                    ? "Administrador"
                                    : "Usuário";

                                $status = $u["ativo"]
                                    ? "Ativo"
                                    : "Inativo";

                                $classePerfil = ($perfil == "Administrador")
                                    ? "danger"
                                    : "secondary";

                                $classeStatus = $u["ativo"]
                                    ? "success"
                                    : "danger";

                                $foto = !empty($u["foto"])
                                    ? BASE_URL . "uploads/usuarios/" . $u["foto"]
                                    : THEME_IMG . "user.png";

                                ?>

                                <tr>

                                    <td>

                                        <strong>
                                            <?= $u["id"] ?>
                                        </strong>

                                    </td>

                                    <td>

                                        <div class="d-flex align-items-center">

                                            <img src="<?= $foto ?>" class="rounded-circle border shadow-sm me-3" style="
                            width:50px;
                            height:50px;
                            object-fit:cover;
                        ">

                                            <div>

                                                <strong>

                                                    <?= htmlspecialchars($u["nome"]) ?>

                                                </strong>

                                                <br>

                                                <small class="text-muted">

                                                    <?= htmlspecialchars($u["email"]) ?>

                                                </small>

                                            </div>

                                        </div>

                                    </td>

                                    <td>

                                        <span class="badge bg-<?= $classePerfil ?>">

                                            <?= $perfil ?>

                                        </span>

                                    </td>

                                    <td>

                                        <?php if (!empty($u["ultimo_login"])): ?>

                                            <?= date("d/m/Y H:i", strtotime($u["ultimo_login"])) ?>

                                        <?php else: ?>

                                            <span class="text-muted">

                                                Nunca acessou

                                            </span>

                                        <?php endif; ?>

                                    </td>

                                    <td>

                                        <span class="badge bg-<?= $classeStatus ?>">

                                            <?= $status ?>

                                        </span>

                                    </td>

                                    <td>

                                        <div class="btn-group">

                                            <!-- <a href="usuario.php?id=<?= $u["id"] ?>" class="btn btn-sm btn-outline-primary"
                                                title="Editar">

                                                <i class="fa fa-pencil"></i>

                                            </a> -->

                                            <button class="btn btn-sm btn-outline-primary text-white editar-user-admin"
                                                data-id="<?= $u['id'] ?>" title="Editar">
                                                <i class="fa fa-pencil"></i>
                                            </button>

                                            <button class="btn btn-sm btn-outline-info text-white view-visivel"
                                                data-id-visivel="<?= $u['id'] ?>" title="Visualizar">
                                                <i class="fa fa-eye"></i>
                                            </button>

                                            <button class="btn btn-sm btn-outline-danger btnExcluir" data-id="<?= $u["id"] ?>"
                                                title="Excluir">
                                                <i class="fa fa-trash"></i>
                                            </button>

                                        </div>

                                    </td>

                                </tr>

                            <?php endforeach; ?>

                        <?php else: ?>

                            <tr>

                                <td colspan="6">

                                    <div class="text-center py-5">

                                        <i class="fa fa-users text-secondary mb-3" style="font-size:48px;">
                                        </i>

                                        <h5>

                                            Nenhum usuário encontrado.

                                        </h5>

                                        <p class="text-muted mb-0">

                                            Clique em <strong>Novo Usuário</strong> para cadastrar o primeiro.

                                        </p>

                                    </div>

                                </td>

                            </tr>

                        <?php endif; ?>

                    </tbody>

                </table>
                <div class="datatable-footer">

                    <div id="datatableInfo"></div>

                    <div id="datatablePaginacao"></div>

                </div>

            </div>

        </div>

        <!-- Barra do DataTable -->

        <div class="card border-0 shadow-sm mt-3">

            <div class="card-body">

                <div class="row align-items-center">

                    <div class="col-md-4">

                        <div class="d-flex align-items-center">

                            <label class="me-2 mb-0">

                                Mostrar

                            </label>

                            <select id="dt-length" class="form-select form-select-sm" style="width:90px;">

                                <option value="10">10</option>
                                <option value="25" selected>25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>

                            </select>

                            <span class="ms-2">

                                registros

                            </span>

                        </div>

                    </div>

                    <div class="col-md-4 text-center">

                        <small class="text-muted" id="dt-info">

                            Carregando...

                        </small>

                    </div>

                    <div class="col-md-4">

                        <nav>

                            <ul id="dt-pagination" class="pagination pagination-sm justify-content-end mb-0">

                            </ul>

                        </nav>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>
<script>

    $(function () {
        $(document).on("click", ".editar-user-admin", function () {

            let id = $(this).data("id");

            window.location = "usuario.php?id=" + id

        });
        $(document).on("click", ".view-visivel", function () {

            let id = $(this).data("idVisivel");

            window.location = "usuario-view.php?id=" + id;

        });
        /*
        |--------------------------------------------------------------------------
        | Excluir Usuário
        |--------------------------------------------------------------------------
        */

        $(document).on("click", ".btnExcluir", function () {

            let id = $(this).data("id");

            Swal.fire({

                title: "Excluir usuário?",

                text: "Esta ação não poderá ser desfeita.",

                icon: "warning",

                showCancelButton: true,

                confirmButtonText: "Excluir",

                cancelButtonText: "Cancelar",

                confirmButtonColor: "#dc3545"

            }).then((result) => {

                if (result.isConfirmed) {

                    window.location =
                        "usuario-delete.php?id=" + id;

                }

            });

        });

    });
</script>

<!-- DataTable Próprio -->

<script src="<?= THEME_JS ?>admin/user/datatable.js?v=1.0.0"></script>

<script>

    $(function () {

        /*
        |--------------------------------------------------------------------------
        | Inicializa o DataTable próprio
        |--------------------------------------------------------------------------
        */

        if (typeof AdminDataTable !== "undefined") {

            new AdminDataTable({

                table: "#tbodyUsuarios",

                search: "#pesquisar",

                pageSize: "#dt-length",

                pagination: "#dt-pagination",

                info: "#dt-info",

                filters: {

                    perfil: "#filtroPerfil",

                    status: "#filtroStatus"

                }

            });

        }

    });
</script>

<?php

require_once "../includes/footer.php";

?>