<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/settings.php";
require_once __DIR__ . "/../mod/auth/Notificacao.php";

Session::start();
Auth::requireLogin();

$pageStyles = [
    THEME_CSS
    . "message/message.css?v="
    . VERSION
];

$idUsuario = (int) (Auth::id() ?? 0);
$tipoUsuario = (int) Auth::tipo();

$tipo = trim(
    (string) ($_GET["tipo"] ?? "")
);
$tiposPermitidos = [
    "",
    "usuario",
    "inscricao",
    "pagamento"
];

if (!in_array($tipo, $tiposPermitidos, true)) {
    $tipo = "";
}

$statusFiltro = trim(
    (string) ($_GET["status"] ?? "")
);
$statusPermitidos = [
    "",
    "nao_lidas",
    "lidas"
];

if (!in_array($statusFiltro, $statusPermitidos, true)) {
    $statusFiltro = "";
}

$pagina = max(
    1,
    (int) ($_GET["pagina"] ?? 1)
);

$notificacao = new Notificacao($db);
$erro = "";

$resultado = [
    "dados" => [],
    "total" => 0,
    "pagina" => 1,
    "paginas" => 1,
    "naoLidas" => 0
];

try {
    $notificacao->sincronizar();

    $resultado = $notificacao->listarTodos(
        $idUsuario,
        $tipoUsuario,
        $tipo,
        $statusFiltro,
        $pagina,
        30
    );
} catch (Throwable $excecao) {
    $erro =
        "Não foi possível consultar as notificações. "
        . "Confirme se a migração foi executada.";

    error_log(
        "Erro em /message: "
        . $excecao->getMessage()
    );
}

function messageEscapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function messageIcone(string $tipo): string
{
    return match ($tipo) {
        "usuario" => "fa-user-plus",
        "inscricao" => "fa-clipboard-check",
        "pagamento" => "fa-circle-dollar-to-slot",
        default => "fa-bell"
    };
}

function messageClasse(string $tipo): string
{
    return match ($tipo) {
        "usuario" => "message-icone-usuario",
        "inscricao" => "message-icone-inscricao",
        "pagamento" => "message-icone-pagamento",
        default => ""
    };
}

function messageUrlPagina(
    int $paginaDestino,
    string $tipo,
    string $status
): string {
    $parametros = [
        "pagina" => max(1, $paginaDestino)
    ];

    if ($tipo !== "") {
        $parametros["tipo"] = $tipo;
    }

    if ($status !== "") {
        $parametros["status"] = $status;
    }

    return "?"
        . http_build_query(
            $parametros,
            "",
            "&",
            PHP_QUERY_RFC3986
        );
}

require_once __DIR__ . "/../admin/includes/header.php";
require_once __DIR__ . "/../admin/includes/navbar.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<div class="content message-page" id="content">
    <div class="container-fluid">

        <div
            class="d-flex flex-wrap justify-content-between
                align-items-center gap-3 mb-4"
        >
            <div>
                <h2 class="fw-bold mb-1">
                    <i
                        class="fa-solid fa-bell
                            text-primary me-2"
                    ></i>
                    Notificações
                </h2>

                <p class="text-muted mb-0">
                    Consulte as atualizações disponíveis
                    para sua conta.
                </p>
            </div>

            <div
                class="d-flex flex-wrap
                    align-items-center gap-2"
            >
                <span
                    class="badge rounded-pill
                        text-bg-primary fs-6"
                >
                    <?= (int) $resultado["total"]; ?>
                    registro<?= (int) $resultado["total"] === 1
                        ? ""
                        : "s"; ?>
                </span>

                <button
                    type="button"
                    class="btn btn-outline-primary"
                    id="btnMarcarTodasPagina"
                    <?= (int) $resultado["naoLidas"] === 0
                        ? "disabled"
                        : ""; ?>
                >
                    <i
                        class="fa-solid
                            fa-check-double me-1"
                    ></i>
                    Marcar todas como lidas
                </button>
            </div>
        </div>

        <?php if ($erro !== ""): ?>
            <div class="alert alert-danger">
                <i
                    class="fa-solid
                        fa-circle-exclamation me-1"
                ></i>
                <?= messageEscapar($erro); ?>
            </div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i
                        class="fa-solid
                            fa-filter text-primary me-1"
                    ></i>
                    Filtros
                </h5>
            </div>

            <div class="card-body">
                <form
                    method="get"
                    class="row g-3 align-items-end"
                >
                    <div class="col-md-5">
                        <label
                            class="form-label"
                            for="tipoNotificacao"
                        >
                            Tipo
                        </label>

                        <select
                            class="form-select"
                            id="tipoNotificacao"
                            name="tipo"
                        >
                            <option value="">
                                Todas
                            </option>

                            <option
                                value="usuario"
                                <?= $tipo === "usuario"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Cadastro
                            </option>

                            <option
                                value="inscricao"
                                <?= $tipo === "inscricao"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Inscrições
                            </option>

                            <option
                                value="pagamento"
                                <?= $tipo === "pagamento"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Pagamentos
                            </option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label
                            class="form-label"
                            for="statusNotificacao"
                        >
                            Situação
                        </label>

                        <select
                            class="form-select"
                            id="statusNotificacao"
                            name="status"
                        >
                            <option value="">
                                Todas
                            </option>

                            <option
                                value="nao_lidas"
                                <?= $statusFiltro === "nao_lidas"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Não lidas
                            </option>

                            <option
                                value="lidas"
                                <?= $statusFiltro === "lidas"
                                    ? "selected"
                                    : ""; ?>
                            >
                                Lidas
                            </option>
                        </select>
                    </div>

                    <div class="col-md-2 d-grid">
                        <button
                            type="submit"
                            class="btn btn-primary"
                        >
                            <i
                                class="fa-solid
                                    fa-filter me-1"
                            ></i>
                            Filtrar
                        </button>
                    </div>

                    <?php if (
                        $tipo !== ""
                        || $statusFiltro !== ""
                    ): ?>
                        <div class="col-md-1 d-grid">
                            <a
                                href="<?= BASE_URL ?>message/"
                                class="btn btn-outline-secondary"
                                title="Limpar filtros"
                                aria-label="Limpar filtros"
                            >
                                <i
                                    class="fa-solid
                                        fa-eraser"
                                ></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div
                class="card-header bg-white py-3
                    d-flex flex-wrap justify-content-between
                    align-items-center gap-2"
            >
                <h5 class="mb-0">
                    Registros
                </h5>

                <small class="text-muted">
                    Página
                    <?= (int) $resultado["pagina"]; ?>
                    de
                    <?= (int) $resultado["paginas"]; ?>
                </small>
            </div>

            <div class="card-body p-0">
                <?php if ($resultado["dados"] === []): ?>
                    <div
                        class="text-center
                            text-muted p-5"
                    >
                        <i
                            class="fa-regular
                                fa-bell-slash fa-3x mb-3"
                        ></i>

                        <div>
                            Nenhuma notificação encontrada.
                        </div>
                    </div>
                <?php else: ?>
                    <div
                        class="list-group
                            list-group-flush"
                    >
                        <?php foreach (
                            $resultado["dados"]
                            as $registro
                        ): ?>
                            <?php
                            $lida =
                                (int) $registro["lida"]
                                === 1;
                            ?>

                            <a
                                href="<?= BASE_URL ?>message/abrir.php?id=<?= (int) $registro["idNotificacao"]; ?>"
                                class="list-group-item
                                    list-group-item-action
                                    message-pagina-item
                                    <?= !$lida
                                        ? "nao-lida"
                                        : ""; ?>"
                            >
                                <span
                                    class="message-icone
                                        <?= messageClasse(
                                            (string) $registro["tipo"]
                                        ); ?>"
                                >
                                    <i
                                        class="fa-solid
                                            <?= messageIcone(
                                                (string) $registro["tipo"]
                                            ); ?>"
                                    ></i>
                                </span>

                                <span
                                    class="message-pagina-conteudo"
                                >
                                    <span
                                        class="d-flex flex-wrap
                                            justify-content-between
                                            gap-2"
                                    >
                                        <strong>
                                            <?= messageEscapar(
                                                (string) $registro["titulo"]
                                            ); ?>
                                        </strong>

                                        <small
                                            class="text-muted
                                                text-nowrap"
                                        >
                                            <?= date(
                                                "d/m/Y H:i",
                                                strtotime(
                                                    (string) $registro["criadoEm"]
                                                )
                                            ); ?>
                                        </small>
                                    </span>

                                    <span
                                        class="d-block
                                            text-muted mt-1"
                                    >
                                        <?= messageEscapar(
                                            (string) $registro["mensagem"]
                                        ); ?>
                                    </span>
                                </span>

                                <?php if (!$lida): ?>
                                    <span
                                        class="message-indicador"
                                        title="Não lida"
                                    ></span>
                                <?php else: ?>
                                    <span></span>
                                <?php endif; ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (
                (int) $resultado["paginas"] > 1
            ): ?>
                <div class="card-footer bg-white">
                    <nav
                        aria-label="Paginação das notificações"
                    >
                        <ul
                            class="pagination
                                justify-content-center
                                flex-wrap mb-0"
                        >
                            <li
                                class="page-item
                                    <?= (int) $resultado["pagina"] <= 1
                                        ? "disabled"
                                        : ""; ?>"
                            >
                                <a
                                    class="page-link"
                                    href="<?= messageEscapar(
                                        messageUrlPagina(
                                            (int) $resultado["pagina"] - 1,
                                            $tipo,
                                            $statusFiltro
                                        )
                                    ); ?>"
                                >
                                    Anterior
                                </a>
                            </li>

                            <?php
                            $inicioPagina = max(
                                1,
                                (int) $resultado["pagina"] - 2
                            );

                            $fimPagina = min(
                                (int) $resultado["paginas"],
                                (int) $resultado["pagina"] + 2
                            );
                            ?>

                            <?php for (
                                $numeroPagina = $inicioPagina;
                                $numeroPagina <= $fimPagina;
                                $numeroPagina++
                            ): ?>
                                <li
                                    class="page-item
                                        <?= $numeroPagina
                                            === (int) $resultado["pagina"]
                                            ? "active"
                                            : ""; ?>"
                                >
                                    <a
                                        class="page-link"
                                        href="<?= messageEscapar(
                                            messageUrlPagina(
                                                $numeroPagina,
                                                $tipo,
                                                $statusFiltro
                                            )
                                        ); ?>"
                                    >
                                        <?= $numeroPagina; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li
                                class="page-item
                                    <?= (int) $resultado["pagina"]
                                        >= (int) $resultado["paginas"]
                                        ? "disabled"
                                        : ""; ?>"
                            >
                                <a
                                    class="page-link"
                                    href="<?= messageEscapar(
                                        messageUrlPagina(
                                            (int) $resultado["pagina"] + 1,
                                            $tipo,
                                            $statusFiltro
                                        )
                                    ); ?>"
                                >
                                    Próxima
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>

        <div class="alert alert-info mt-4 mb-0">
            <i
                class="fa-solid
                    fa-circle-info me-1"
            ></i>
            As notificações não lidas ficam destacadas.
            Ao abrir uma notificação, ela será marcada
            automaticamente como lida.
        </div>

    </div>
</div>

<?php
require_once __DIR__ . "/../admin/includes/footer.php";
?>