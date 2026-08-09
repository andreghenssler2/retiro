<!-- <?php

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

-->
<!--
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

?> -->
