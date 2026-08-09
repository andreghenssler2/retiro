<?php

declare(strict_types=1);

require_once __DIR__
    . "/../config/settings.php";

Session::start();
Auth::requireLogin();

$pageStyles = [
    THEME_CSS
    . "admin/configuracoes/atividades.css?v="
    . VERSION
];

$isAdmin = Auth::isAdmin();
$idUsuarioLogado = (int) (Auth::id() ?? 0);

$aba = (string) ($_GET["aba"] ?? "hoje");

$aba = in_array(
    $aba,
    ["hoje", "todos"],
    true
)
    ? $aba
    : "hoje";

$pesquisa = trim(
    (string) (
        $_GET["pesquisa"]
        ?? ""
    )
);

$dataInicio = trim(
    (string) (
        $_GET["data_inicio"]
        ?? ""
    )
);

$dataFim = trim(
    (string) (
        $_GET["data_fim"]
        ?? ""
    )
);

$pagina = max(
    1,
    (int) (
        $_GET["pagina"]
        ?? 1
    )
);

/*
 * SEGURANÇA:
 *
 * Administrador pode selecionar qualquer usuário.
 *
 * Moderador/Participante SEMPRE utiliza Auth::id().
 * O parâmetro ?usuario= é ignorado para eles.
 */
$idUsuario = $isAdmin
    ? max(
        0,
        (int) (
            $_GET["usuario"]
            ?? 0
        )
    )
    : $idUsuarioLogado;

$atividade =
    new AtividadeUsuario($db);

$usuarios = [];
$usuarioSelecionado = null;

$resultado = [
    "dados" => [],
    "total" => 0,
    "pagina" => 1,
    "paginas" => 1
];

$erro = "";

try {
    if ($isAdmin) {
        /*
         * Somente o Administrador recebe a lista
         * de usuários para o filtro.
         */
        $stmtUsuarios = $db->query("
            SELECT
                id,
                nome,
                email,
                tipo,
                ativo
            FROM usuarios
            ORDER BY
                nome ASC,
                id ASC
        ");

        $usuarios =
            $stmtUsuarios->fetchAll(
                PDO::FETCH_ASSOC
            );

        foreach (
            $usuarios
            as $usuario
        ) {
            if (
                (int) $usuario["id"]
                === $idUsuario
            ) {
                $usuarioSelecionado =
                    $usuario;

                break;
            }
        }
    } else {
        /*
         * O usuário não consulta dados de outras
         * contas para montar esta tela.
         */
        $usuarioSessao =
            Auth::user() ?? [];

        $usuarioSelecionado = [
            "id" => $idUsuarioLogado,
            "nome" => (string) (
                $usuarioSessao["nome"]
                ?? "Usuário"
            ),
            "email" => (string) (
                $usuarioSessao["email"]
                ?? ""
            ),
            "tipo" => (int) (
                $usuarioSessao["tipo"]
                ?? 0
            ),
            "ativo" => 1
        ];
    }

    if ($aba === "hoje") {
        $resultado =
            $atividade->listarHoje(
                $pesquisa,
                $pagina,
                50,
                $idUsuario
            );
    } else {
        $resultado =
            $atividade->listarTodos(
                $pesquisa,
                $dataInicio,
                $dataFim,
                $pagina,
                50,
                $idUsuario
            );
    }
} catch (Throwable $excecao) {
    $erro =
        "Não foi possível consultar "
        . "as atividades.";

    error_log(
        "Erro em user/atividades.php"
        . " | usuario="
        . $idUsuarioLogado
        . " | erro="
        . $excecao->getMessage()
    );
}

function atividadeUserEscapar(
    string $valor
): string {
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function atividadeUserPerfil(
    int $tipo
): string {
    return match ($tipo) {
        1 => "Administrador",
        2 => "Moderador",
        3 => "Participante",
        default => "Usuário"
    };
}

function atividadeUserDataHora(
    mixed $valor,
    bool $somenteHora = false
): string {
    $texto = trim(
        (string) (
            $valor
            ?? ""
        )
    );

    if ($texto === "") {
        return "-";
    }

    $timestamp = strtotime(
        $texto
    );

    if ($timestamp === false) {
        return $texto;
    }

    return $somenteHora
        ? date(
            "H:i:s",
            $timestamp
        )
        : date(
            "d/m/Y H:i:s",
            $timestamp
        );
}

function atividadeUserUrl(
    int $paginaDestino,
    string $aba,
    string $pesquisa,
    string $dataInicio,
    string $dataFim,
    int $idUsuario,
    bool $isAdmin
): string {
    $parametros = [
        "aba" => $aba,
        "pagina" => max(
            1,
            $paginaDestino
        )
    ];

    /*
     * Somente Admin envia o ID pela URL.
     */
    if (
        $isAdmin
        && $idUsuario > 0
    ) {
        $parametros["usuario"] =
            $idUsuario;
    }

    if ($pesquisa !== "") {
        $parametros["pesquisa"] =
            $pesquisa;
    }

    if (
        $aba === "todos"
        && $dataInicio !== ""
    ) {
        $parametros["data_inicio"] =
            $dataInicio;
    }

    if (
        $aba === "todos"
        && $dataFim !== ""
    ) {
        $parametros["data_fim"] =
            $dataFim;
    }

    return "?"
        . http_build_query(
            $parametros,
            "",
            "&",
            PHP_QUERY_RFC3986
        );
}

require_once __DIR__
    . "/../admin/includes/header.php";

require_once __DIR__
    . "/../admin/includes/navbar.php";

require_once __DIR__
    . "/../includes/sidebar.php";
?>

<div
    class="content atividades-page"
    id="content"
>
    <div class="container-fluid">

        <div
            class="d-flex flex-wrap
                justify-content-between
                align-items-center
                gap-3 mb-4"
        >
            <div>
                <h2 class="fw-bold mb-1">
                    <i
                        class="fa-solid
                            fa-clock-rotate-left
                            text-primary me-2"
                    ></i>

                    <?= $isAdmin
                        ? "Atividades dos usuários"
                        : "Minhas atividades"; ?>
                </h2>

                <p class="text-muted mb-0">
                    <?= $isAdmin
                        ? "Consulte os acessos e ações realizados pelos usuários autenticados."
                        : "Consulte seu histórico de acessos e atividades no sistema."; ?>
                </p>
            </div>

            <span
                class="badge rounded-pill
                    text-bg-primary fs-6"
            >
                <?= (int) $resultado[
                    "total"
                ]; ?>

                registro<?= (int) $resultado[
                    "total"
                ] === 1
                    ? ""
                    : "s"; ?>
            </span>
        </div>

        <?php if ($erro !== ""): ?>
            <div class="alert alert-danger">
                <i
                    class="fa-solid
                        fa-circle-exclamation
                        me-1"
                ></i>

                <?= atividadeUserEscapar(
                    $erro
                ); ?>
            </div>
        <?php endif; ?>

        <?php if (
            !$isAdmin
            && is_array(
                $usuarioSelecionado
            )
        ): ?>
            <div
                class="alert alert-primary
                    d-flex flex-wrap
                    align-items-center gap-2"
            >
                <i
                    class="fa-solid
                        fa-shield-halved"
                ></i>

                <span>
                    Por segurança, esta página
                    mostra somente as atividades
                    da sua conta.
                </span>
            </div>
        <?php elseif (
            $isAdmin
            && is_array(
                $usuarioSelecionado
            )
        ): ?>
            <div
                class="alert alert-primary
                    d-flex flex-wrap
                    align-items-center gap-2"
            >
                <i
                    class="fa-solid
                        fa-user-check"
                ></i>

                <span>
                    Exibindo atividades de
                    <strong>
                        <?= atividadeUserEscapar(
                            (string) $usuarioSelecionado[
                                "nome"
                            ]
                        ); ?>
                    </strong>

                    —

                    <?= atividadeUserEscapar(
                        atividadeUserPerfil(
                            (int) $usuarioSelecionado[
                                "tipo"
                            ]
                        )
                    ); ?>.
                </span>
            </div>
        <?php endif; ?>

        <div
            class="card border-0
                shadow-sm mb-4"
        >
            <div class="card-body pb-0">

                <ul
                    class="nav nav-tabs
                        atividades-tabs"
                >
                    <li class="nav-item">
                        <a
                            class="nav-link
                                <?= $aba === "hoje"
                                    ? "active"
                                    : ""; ?>"
                            href="<?= atividadeUserEscapar(
                                atividadeUserUrl(
                                    1,
                                    "hoje",
                                    $pesquisa,
                                    "",
                                    "",
                                    $idUsuario,
                                    $isAdmin
                                )
                            ); ?>"
                        >
                            <i
                                class="fa-solid
                                    fa-calendar-day
                                    me-1"
                            ></i>
                            Hoje
                        </a>
                    </li>

                    <li class="nav-item">
                        <a
                            class="nav-link
                                <?= $aba === "todos"
                                    ? "active"
                                    : ""; ?>"
                            href="<?= atividadeUserEscapar(
                                atividadeUserUrl(
                                    1,
                                    "todos",
                                    $pesquisa,
                                    $dataInicio,
                                    $dataFim,
                                    $idUsuario,
                                    $isAdmin
                                )
                            ); ?>"
                        >
                            <i
                                class="fa-solid
                                    fa-list me-1"
                            ></i>
                            Histórico completo
                        </a>
                    </li>
                </ul>

            </div>

            <div class="card-body">

                <form
                    method="get"
                    class="row g-3
                        align-items-end"
                >
                    <input
                        type="hidden"
                        name="aba"
                        value="<?= atividadeUserEscapar(
                            $aba
                        ); ?>"
                    >

                    <?php if ($isAdmin): ?>
                        <div
                            class="col-md-6
                                col-xl-3"
                        >
                            <label
                                class="form-label"
                                for="usuario"
                            >
                                Usuário
                            </label>

                            <select
                                class="form-select"
                                id="usuario"
                                name="usuario"
                            >
                                <option value="0">
                                    Todos os usuários
                                </option>

                                <?php foreach (
                                    $usuarios
                                    as $usuario
                                ): ?>
                                    <?php
                                    $usuarioId =
                                        (int) $usuario[
                                            "id"
                                        ];

                                    $usuarioAtivo =
                                        (int) $usuario[
                                            "ativo"
                                        ] === 1;
                                    ?>

                                    <option
                                        value="<?= $usuarioId; ?>"
                                        <?= $idUsuario
                                            === $usuarioId
                                                ? "selected"
                                                : ""; ?>
                                    >
                                        <?= atividadeUserEscapar(
                                            (string) $usuario[
                                                "nome"
                                            ]
                                        ); ?>

                                        —

                                        <?= atividadeUserEscapar(
                                            atividadeUserPerfil(
                                                (int) $usuario[
                                                    "tipo"
                                                ]
                                            )
                                        ); ?>

                                        <?= $usuarioAtivo
                                            ? ""
                                            : " (inativo)"; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>

                    <div
                        class="<?= $aba === "todos"
                            ? (
                                $isAdmin
                                    ? "col-md-6 col-xl-3"
                                    : "col-md-12 col-xl-5"
                            )
                            : (
                                $isAdmin
                                    ? "col-md-6 col-xl-7"
                                    : "col-md-9 col-xl-9"
                            ); ?>"
                    >
                        <label
                            class="form-label"
                            for="pesquisa"
                        >
                            Pesquisa
                        </label>

                        <input
                            type="search"
                            class="form-control"
                            id="pesquisa"
                            name="pesquisa"
                            value="<?= atividadeUserEscapar(
                                $pesquisa
                            ); ?>"
                            placeholder="Acesso, descrição, rota ou IP"
                        >
                    </div>

                    <?php if (
                        $aba === "todos"
                    ): ?>
                        <div
                            class="col-md-4
                                col-xl-2"
                        >
                            <label
                                class="form-label"
                                for="dataInicio"
                            >
                                Data inicial
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                id="dataInicio"
                                name="data_inicio"
                                value="<?= atividadeUserEscapar(
                                    $dataInicio
                                ); ?>"
                            >
                        </div>

                        <div
                            class="col-md-4
                                col-xl-2"
                        >
                            <label
                                class="form-label"
                                for="dataFim"
                            >
                                Data final
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                id="dataFim"
                                name="data_fim"
                                value="<?= atividadeUserEscapar(
                                    $dataFim
                                ); ?>"
                            >
                        </div>
                    <?php endif; ?>

                    <div
                        class="<?= $aba === "todos"
                            ? "col-md-4 col-xl-2"
                            : "col-md-3 col-xl-3"; ?>
                            d-grid"
                    >
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
                        $pesquisa !== ""
                        || $dataInicio !== ""
                        || $dataFim !== ""
                        || (
                            $isAdmin
                            && $idUsuario > 0
                        )
                    ): ?>
                        <div
                            class="col-md-4
                                col-xl-1 d-grid"
                        >
                            <a
                                href="?aba=<?= atividadeUserEscapar(
                                    $aba
                                ); ?>"
                                class="btn
                                    btn-outline-secondary"
                                title="Limpar filtros"
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

        <div
            class="card border-0
                shadow-sm"
        >
            <div
                class="card-header bg-white
                    py-3 d-flex flex-wrap
                    justify-content-between
                    align-items-center gap-2"
            >
                <h5 class="mb-0">
                    <?= $aba === "hoje"
                        ? (
                            $isAdmin
                                ? "Logs de hoje"
                                : "Meus acessos de hoje"
                        )
                        : (
                            $isAdmin
                                ? "Todos os registros"
                                : "Meu histórico de acessos"
                        ); ?>
                </h5>

                <small class="text-muted">
                    Página
                    <?= (int) $resultado[
                        "pagina"
                    ]; ?>

                    de
                    <?= (int) $resultado[
                        "paginas"
                    ]; ?>
                </small>
            </div>

            <div class="card-body p-0">

                <?php if (
                    $resultado["dados"]
                    === []
                ): ?>
                    <div
                        class="text-center
                            text-muted p-5"
                    >
                        <i
                            class="fa-solid
                                fa-clock-rotate-left
                                fa-3x mb-3"
                        ></i>

                        <div>
                            Nenhuma atividade
                            encontrada.
                        </div>
                    </div>

                <?php else: ?>

                    <div
                        class="table-responsive"
                    >
                        <table
                            class="table table-hover
                                align-middle mb-0
                                atividades-table"
                        >
                            <thead
                                class="table-light"
                            >
                                <tr>
                                    <th>
                                        <?= $aba === "hoje"
                                            ? "Hora"
                                            : "Data / Hora"; ?>
                                    </th>

                                    <?php if (
                                        $isAdmin
                                    ): ?>
                                        <th>Usuário</th>
                                    <?php endif; ?>

                                    <th>O que acessou</th>
                                    <th>Descrição</th>
                                    <th>Endereço IP</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach (
                                    $resultado["dados"]
                                    as $registro
                                ): ?>
                                    <tr>
                                        <td
                                            class="text-nowrap"
                                        >
                                            <?= atividadeUserEscapar(
                                                atividadeUserDataHora(
                                                    $registro[
                                                        "criadoEm"
                                                    ]
                                                    ?? "",
                                                    $aba === "hoje"
                                                )
                                            ); ?>
                                        </td>

                                        <?php if (
                                            $isAdmin
                                        ): ?>
                                            <td>
                                                <div
                                                    class="fw-semibold"
                                                >
                                                    <?= atividadeUserEscapar(
                                                        (string) (
                                                            $registro[
                                                                "nomeUsuario"
                                                            ]
                                                            ?? ""
                                                        )
                                                    ); ?>
                                                </div>

                                                <small
                                                    class="text-muted"
                                                >
                                                    ID
                                                    <?= (int) (
                                                        $registro[
                                                            "idUsuario"
                                                        ]
                                                        ?? 0
                                                    ); ?>
                                                </small>
                                            </td>
                                        <?php endif; ?>

                                        <td>
                                            <span
                                                class="badge
                                                    text-bg-light
                                                    border"
                                            >
                                                <?= atividadeUserEscapar(
                                                    (string) (
                                                        $registro[
                                                            "acesso"
                                                        ]
                                                        ?? ""
                                                    )
                                                ); ?>
                                            </span>

                                            <code
                                                class="atividade-rota
                                                    d-block mt-1"
                                            >
                                                <?= atividadeUserEscapar(
                                                    (string) (
                                                        $registro[
                                                            "rota"
                                                        ]
                                                        ?? ""
                                                    )
                                                ); ?>
                                            </code>
                                        </td>

                                        <td>
                                            <?= atividadeUserEscapar(
                                                (string) (
                                                    $registro[
                                                        "descricao"
                                                    ]
                                                    ?? ""
                                                )
                                            ); ?>

                                            <small
                                                class="d-block
                                                    text-muted
                                                    mt-1"
                                            >
                                                Método:
                                                <?= atividadeUserEscapar(
                                                    (string) (
                                                        $registro[
                                                            "metodo"
                                                        ]
                                                        ?? ""
                                                    )
                                                ); ?>
                                            </small>
                                        </td>

                                        <td
                                            class="text-nowrap"
                                        >
                                            <code>
                                                <?= atividadeUserEscapar(
                                                    (string) (
                                                        $registro[
                                                            "ip"
                                                        ]
                                                        ?? ""
                                                    )
                                                ); ?>
                                            </code>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

            </div>

            <?php if (
                (int) $resultado[
                    "paginas"
                ] > 1
            ): ?>
                <div
                    class="card-footer
                        bg-white"
                >
                    <nav
                        aria-label="Paginação das atividades"
                    >
                        <ul
                            class="pagination
                                justify-content-center
                                flex-wrap mb-0"
                        >
                            <?php
                            $paginaAtual = max(
                                1,
                                (int) $resultado[
                                    "pagina"
                                ]
                            );

                            $totalPaginas = max(
                                1,
                                (int) $resultado[
                                    "paginas"
                                ]
                            );

                            $inicioPaginas = max(
                                1,
                                $paginaAtual - 2
                            );

                            $fimPaginas = min(
                                $totalPaginas,
                                $paginaAtual + 2
                            );
                            ?>

                            <li
                                class="page-item
                                    <?= $paginaAtual <= 1
                                        ? "disabled"
                                        : ""; ?>"
                            >
                                <a
                                    class="page-link"
                                    href="<?= atividadeUserEscapar(
                                        atividadeUserUrl(
                                            $paginaAtual - 1,
                                            $aba,
                                            $pesquisa,
                                            $dataInicio,
                                            $dataFim,
                                            $idUsuario,
                                            $isAdmin
                                        )
                                    ); ?>"
                                >
                                    Anterior
                                </a>
                            </li>

                            <?php for (
                                $numeroPagina =
                                    $inicioPaginas;
                                $numeroPagina
                                    <= $fimPaginas;
                                $numeroPagina++
                            ): ?>
                                <li
                                    class="page-item
                                        <?= $numeroPagina
                                            === $paginaAtual
                                                ? "active"
                                                : ""; ?>"
                                >
                                    <a
                                        class="page-link"
                                        href="<?= atividadeUserEscapar(
                                            atividadeUserUrl(
                                                $numeroPagina,
                                                $aba,
                                                $pesquisa,
                                                $dataInicio,
                                                $dataFim,
                                                $idUsuario,
                                                $isAdmin
                                            )
                                        ); ?>"
                                    >
                                        <?= $numeroPagina; ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <li
                                class="page-item
                                    <?= $paginaAtual
                                        >= $totalPaginas
                                            ? "disabled"
                                            : ""; ?>"
                            >
                                <a
                                    class="page-link"
                                    href="<?= atividadeUserEscapar(
                                        atividadeUserUrl(
                                            $paginaAtual + 1,
                                            $aba,
                                            $pesquisa,
                                            $dataInicio,
                                            $dataFim,
                                            $idUsuario,
                                            $isAdmin
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

    </div>
</div>

<?php
require_once __DIR__
    . "/../admin/includes/footer.php";
?>
