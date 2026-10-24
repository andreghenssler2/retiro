<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/settings.php";

Middleware::admin();

$pageStyles = [
    THEME_CSS
    . "user/permissoes.css?v="
    . VERSION
];

$mensagemSucesso = Session::getFlash("success");
$mensagemErro = Session::getFlash("error");

$pesquisa = trim(
    (string) ($_GET["pesquisa"] ?? "")
);

$pagina = max(
    1,
    (int) ($_GET["pagina"] ?? 1)
);

$limite = 50;
$offset = ($pagina - 1) * $limite;

/**
 * Retorna o nome apresentado para cada tipo.
 */
function permissaoNomeTipo(int $tipo): string
{
    return match ($tipo) {
        1 => "Administrador",
        2 => "Moderador",
        3 => "Usuário",
        default => "Desconhecido"
    };
}

/**
 * Escapa valores para HTML.
 */
function permissaoEscapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

/**
 * Monta a URL mantendo pesquisa/página.
 */
function permissaoUrl(
    int $paginaDestino,
    string $pesquisa
): string {
    $parametros = [
        "pagina" => max(1, $paginaDestino)
    ];

    if ($pesquisa !== "") {
        $parametros["pesquisa"] = $pesquisa;
    }

    return "?"
        . http_build_query(
            $parametros,
            "",
            "&",
            PHP_QUERY_RFC3986
        );
}

/*
|--------------------------------------------------------------------------
| Alteração de permissão
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $paginaRetorno = max(
        1,
        (int) ($_POST["pagina"] ?? 1)
    );

    $pesquisaRetorno = trim(
        (string) ($_POST["pesquisa"] ?? "")
    );

    $urlRetorno = BASE_URL
        . "user/permissoes.php"
        . permissaoUrl(
            $paginaRetorno,
            $pesquisaRetorno
        );

    try {
        if (
            !Session::validateCsrf(
                $_POST["_token"] ?? ""
            )
        ) {
            throw new RuntimeException(
                "Token de segurança inválido. "
                . "Atualize a página e tente novamente."
            );
        }

        $idUsuario = (int) (
            $_POST["id_usuario"]
            ?? 0
        );

        $novoTipo = (int) (
            $_POST["tipo"]
            ?? 0
        );

        if ($idUsuario <= 0) {
            throw new InvalidArgumentException(
                "Usuário inválido."
            );
        }

        if (
            !in_array(
                $novoTipo,
                [1, 2, 3],
                true
            )
        ) {
            throw new InvalidArgumentException(
                "Permissão inválida."
            );
        }

        /*
         * Evita que o administrador altere a própria
         * permissão e perca o acesso durante a sessão.
         */
        if ($idUsuario === (int) Auth::id()) {
            throw new InvalidArgumentException(
                "Para sua segurança, você não pode "
                . "alterar a sua própria permissão nesta tela."
            );
        }

        $stmtUsuario = $db->prepare("
            SELECT
                id,
                nome,
                email,
                tipo,
                ativo
            FROM usuarios
            WHERE id = :id
            LIMIT 1
        ");

        $stmtUsuario->execute([
            ":id" => $idUsuario
        ]);

        $usuarioAtual = $stmtUsuario->fetch(
            PDO::FETCH_ASSOC
        );

        if (!is_array($usuarioAtual)) {
            throw new InvalidArgumentException(
                "Usuário não encontrado."
            );
        }

        $tipoAtual = (int) (
            $usuarioAtual["tipo"]
            ?? 0
        );

        if ($tipoAtual === $novoTipo) {
            Session::flash(
                "success",
                "A permissão de "
                . (string) $usuarioAtual["nome"]
                . " já está definida como "
                . permissaoNomeTipo($novoTipo)
                . "."
            );

            header(
                "Location: " . $urlRetorno
            );
            exit;
        }

        /*
         * Não permite remover o último administrador ativo.
         */
        if (
            $tipoAtual === 1
            && $novoTipo !== 1
            && (int) (
                $usuarioAtual["ativo"]
                ?? 0
            ) === 1
        ) {
            $totalAdministradoresAtivos =
                (int) $db->query("
                    SELECT COUNT(*)
                    FROM usuarios
                    WHERE tipo = 1
                      AND ativo = 1
                ")->fetchColumn();

            if ($totalAdministradoresAtivos <= 1) {
                throw new InvalidArgumentException(
                    "Não é possível remover a permissão "
                    . "do último administrador ativo."
                );
            }
        }

        $stmtAtualizar = $db->prepare("
            UPDATE usuarios
            SET tipo = :tipo
            WHERE id = :id
            LIMIT 1
        ");

        $stmtAtualizar->execute([
            ":tipo" => $novoTipo,
            ":id" => $idUsuario
        ]);

        $nomeUsuarioAlterado = trim(
            (string) (
                $usuarioAtual["nome"]
                ?? "Usuário"
            )
        );

        $emailUsuarioAlterado = trim(
            (string) (
                $usuarioAtual["email"]
                ?? ""
            )
        );

        $perfilAnterior = permissaoNomeTipo(
            $tipoAtual
        );

        $perfilNovo = permissaoNomeTipo(
            $novoTipo
        );

        Session::flash(
            "success",
            "Permissão de "
            . $nomeUsuarioAlterado
            . " alterada de "
            . $perfilAnterior
            . " para "
            . $perfilNovo
            . "."
        );

        /*
        |--------------------------------------------------------------------------
        | Notificação por e-mail
        |--------------------------------------------------------------------------
        |
        | O HTML fica separado em:
        | /mod/mail/templates/permissao_alterada.php
        |
        | A permissão já foi alterada neste ponto.
        | Se o e-mail falhar, a alteração não é desfeita.
        |
        */
        try {
            $nome = $nomeUsuarioAlterado;
            $email = $emailUsuarioAlterado;
            $nomeEmail = $nome;

            if (
                $email === ""
                || filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                throw new RuntimeException(
                    "O usuário não possui um e-mail válido."
                );
            }

            $administradorNome = trim(
                (string) (
                    Auth::nome()
                    ?? "Administrador"
                )
            );

            $dataAlteracao = date(
                "d/m/Y H:i:s"
            );

            ob_start();

            include __DIR__
                . "/../mod/mail/templates/permissao_alterada.php";

            $html = (string) ob_get_clean();

            if (trim($html) === "") {
                throw new RuntimeException(
                    "O template do e-mail não gerou conteúdo."
                );
            }

            $mail = new Mail();

            $emailEnviado = $mail->send(
                $email,
                $nomeEmail,
                "Sua permissão de acesso foi alterada",
                $html
            );

            if (!$emailEnviado) {
                throw new RuntimeException(
                    "O servidor de e-mail não confirmou o envio."
                );
            }

            Log::info(
                "Notificação de alteração de permissão enviada por e-mail",
                [
                    "idUsuario" => $idUsuario,
                    "email" => $email,
                    "tipoAnterior" => $tipoAtual,
                    "tipoNovo" => $novoTipo,
                    "administrador" => (int) Auth::id()
                ]
            );
        } catch (Throwable $mailErro) {
            Log::warning(
                "Permissão alterada, mas o e-mail de notificação falhou",
                [
                    "idUsuario" => $idUsuario,
                    "email" => $emailUsuarioAlterado,
                    "tipoAnterior" => $tipoAtual,
                    "tipoNovo" => $novoTipo,
                    "erro" => $mailErro->getMessage()
                ]
            );

            Session::flash(
                "error",
                "A permissão foi alterada, mas não foi possível "
                . "enviar a notificação por e-mail para "
                . (
                    $emailUsuarioAlterado !== ""
                    ? $emailUsuarioAlterado
                    : "o usuário"
                )
                . "."
            );
        }
    } catch (Throwable $erro) {
        error_log(
            "Erro ao alterar permissão de usuário: "
            . $erro->getMessage()
        );

        Session::flash(
            "error",
            $erro instanceof InvalidArgumentException
            || $erro instanceof RuntimeException
            ? $erro->getMessage()
            : "Não foi possível alterar a permissão."
        );
    }

    header(
        "Location: " . $urlRetorno
    );
    exit;
}

/*
|--------------------------------------------------------------------------
| Listagem
|--------------------------------------------------------------------------
*/
$where = [];
$params = [];

if ($pesquisa !== "") {
    $where[] = "(
        nome LIKE :pesquisa
        OR email LIKE :pesquisa
        OR cpf LIKE :pesquisa
    )";

    $params[":pesquisa"] =
        "%" . $pesquisa . "%";
}

$sqlWhere = $where !== []
    ? " WHERE " . implode(
        " AND ",
        $where
    )
    : "";

$stmtTotal = $db->prepare(
    "SELECT COUNT(*)
     FROM usuarios"
    . $sqlWhere
);

$stmtTotal->execute($params);

$total = (int) $stmtTotal->fetchColumn();

$totalPaginas = max(
    1,
    (int) ceil(
        $total / $limite
    )
);

if ($pagina > $totalPaginas) {
    $pagina = $totalPaginas;
    $offset = ($pagina - 1) * $limite;
}

$stmtUsuarios = $db->prepare(
    "SELECT
        id,
        nome,
        email,
        cpf,
        tipo,
        ativo,
        ultimo_login,
        created_at
     FROM usuarios"
    . $sqlWhere
    . " ORDER BY
            CASE tipo
                WHEN 1 THEN 1
                WHEN 2 THEN 2
                ELSE 3
            END,
            nome ASC,
            id ASC
        LIMIT :limite
        OFFSET :offset"
);

foreach ($params as $chave => $valor) {
    $stmtUsuarios->bindValue(
        $chave,
        $valor,
        PDO::PARAM_STR
    );
}

$stmtUsuarios->bindValue(
    ":limite",
    $limite,
    PDO::PARAM_INT
);

$stmtUsuarios->bindValue(
    ":offset",
    $offset,
    PDO::PARAM_INT
);

$stmtUsuarios->execute();

$usuarios = $stmtUsuarios->fetchAll(
    PDO::FETCH_ASSOC
);

$totalAdmin = 0;
$totalModerador = 0;
$totalUsuario = 0;

try {
    $contagens = $db->query("
        SELECT
            SUM(tipo = 1) AS administradores,
            SUM(tipo = 2) AS moderadores,
            SUM(tipo = 3) AS usuarios
        FROM usuarios
    ")->fetch(PDO::FETCH_ASSOC);

    if (is_array($contagens)) {
        $totalAdmin = (int) (
            $contagens["administradores"]
            ?? 0
        );

        $totalModerador = (int) (
            $contagens["moderadores"]
            ?? 0
        );

        $totalUsuario = (int) (
            $contagens["usuarios"]
            ?? 0
        );
    }
} catch (Throwable $erroContagem) {
    error_log(
        "Erro ao contar tipos de usuários: "
        . $erroContagem->getMessage()
    );
}

require_once __DIR__
    . "/../admin/includes/header.php";
require_once __DIR__
    . "/../admin/includes/navbar.php";
require_once __DIR__
    . "/../admin/includes/sidebar.php";
?>

<div class="content permissoes-page" id="content">
    <div class="container-fluid">

        <div class="d-flex flex-wrap
                justify-content-between
                align-items-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">
                    <i class="fa-solid
                            fa-user-shield
                            text-primary me-2"></i>
                    Permissões dos usuários
                </h2>

                <p class="text-muted mb-0">
                    Defina quem será Administrador,
                    Moderador ou Usuário.
                </p>
            </div>

            <span class="badge rounded-pill
                    text-bg-primary fs-6">
                <?= $total; ?>
                usuário<?= $total === 1
                    ? ""
                    : "s"; ?>
            </span>
        </div>

        <?php if ($mensagemSucesso): ?>
            <div class="alert alert-success
                    alert-dismissible fade show" role="alert">
                <i class="fa-solid
                        fa-circle-check me-1"></i>

                <?= permissaoEscapar(
                    (string) $mensagemSucesso
                ); ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>

        <?php if ($mensagemErro): ?>
            <div class="alert alert-danger
                    alert-dismissible fade show" role="alert">
                <i class="fa-solid
                        fa-circle-exclamation me-1"></i>

                <?= permissaoEscapar(
                    (string) $mensagemErro
                ); ?>

                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">

            <div class="col-md-4">
                <div class="card border-0
                        shadow-sm h-100">
                    <div class="card-body
                            d-flex align-items-center
                            gap-3">
                        <span class="permissoes-resumo-icone
                                bg-danger-subtle
                                text-danger">
                            <i class="fa-solid
                                    fa-user-shield"></i>
                        </span>

                        <div>
                            <div class="text-muted small">
                                Administradores
                            </div>

                            <div class="fs-4 fw-bold">
                                <?= $totalAdmin; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0
                        shadow-sm h-100">
                    <div class="card-body
                            d-flex align-items-center
                            gap-3">
                        <span class="permissoes-resumo-icone
                                bg-warning-subtle
                                text-warning-emphasis">
                            <i class="fa-solid
                                    fa-user-gear"></i>
                        </span>

                        <div>
                            <div class="text-muted small">
                                Moderadores
                            </div>

                            <div class="fs-4 fw-bold">
                                <?= $totalModerador; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card border-0
                        shadow-sm h-100">
                    <div class="card-body
                            d-flex align-items-center
                            gap-3">
                        <span class="permissoes-resumo-icone
                                bg-primary-subtle
                                text-primary">
                            <i class="fa-solid
                                    fa-user"></i>
                        </span>

                        <div>
                            <div class="text-muted small">
                                Usuários
                            </div>

                            <div class="fs-4 fw-bold">
                                <?= $totalUsuario; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div class="card border-0
                shadow-sm mb-4">
            <div class="card-header bg-white py-3">
                <h5 class="mb-0">
                    <i class="fa-solid
                            fa-magnifying-glass
                            text-primary me-1"></i>
                    Localizar usuário
                </h5>
            </div>

            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-md-10">
                        <label for="pesquisa" class="form-label">
                            Nome, e-mail ou CPF
                        </label>

                        <input type="search" class="form-control" id="pesquisa" name="pesquisa" value="<?= permissaoEscapar(
                            $pesquisa
                        ); ?>" placeholder="Pesquisar usuário...">
                    </div>

                    <div class="col-md-2 d-grid">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa-solid
                                    fa-magnifying-glass
                                    me-1"></i>
                            Pesquisar
                        </button>
                    </div>

                    <?php if ($pesquisa !== ""): ?>
                        <div class="col-12">
                            <a href="<?= BASE_URL ?>user/permissoes.php" class="btn btn-sm
                                    btn-outline-secondary">
                                <i class="fa-solid
                                        fa-eraser me-1"></i>
                                Limpar pesquisa
                            </a>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">

            <div class="card-header bg-white py-3
                    d-flex flex-wrap
                    justify-content-between
                    align-items-center gap-2">
                <h5 class="mb-0">
                    Usuários cadastrados
                </h5>

                <small class="text-muted">
                    Página
                    <?= $pagina; ?>
                    de
                    <?= $totalPaginas; ?>
                </small>
            </div>

            <div class="card-body p-0">
                <?php if ($usuarios === []): ?>
                    <div class="text-center
                            text-muted p-5">
                        <i class="fa-solid
                                fa-users-slash
                                fa-3x mb-3"></i>

                        <div>
                            Nenhum usuário encontrado.
                        </div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover
                                align-middle mb-0
                                permissoes-table">
                            <thead class="table-light">
                                <tr>
                                    <th>Usuário</th>
                                    <th>Status</th>
                                    <th class="text-center
                                            permissao-coluna">
                                        Administrador
                                    </th>
                                    <th class="text-center
                                            permissao-coluna">
                                        Moderador
                                    </th>
                                    <th class="text-center
                                            permissao-coluna">
                                        Usuário
                                    </th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach (
                                    $usuarios
                                    as $usuario
                                ): ?>
                                    <?php
                                    $usuarioId =
                                        (int) $usuario["id"];

                                    $tipoAtual =
                                        (int) $usuario["tipo"];

                                    $ehUsuarioLogado =
                                        $usuarioId
                                        === (int) Auth::id();

                                    $usuarioAtivo =
                                        (int) $usuario["ativo"]
                                        === 1;
                                    ?>

                                    <tr>
                                        <td>
                                            <div class="fw-semibold">
                                                <?= permissaoEscapar(
                                                    (string) $usuario["nome"]
                                                ); ?>

                                                <?php if (
                                                    $ehUsuarioLogado
                                                ): ?>
                                                    <span class="badge
                                                            text-bg-primary
                                                            ms-1">
                                                        Você
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <small class="text-muted
                                                    d-block">
                                                <?= permissaoEscapar(
                                                    (string) $usuario["email"]
                                                ); ?>
                                            </small>

                                            <small class="text-muted">
                                                ID <?= $usuarioId; ?>
                                                ·
                                                <?= permissaoEscapar(
                                                    permissaoNomeTipo(
                                                        $tipoAtual
                                                    )
                                                ); ?>
                                            </small>
                                        </td>

                                        <td>
                                            <span class="badge <?= $usuarioAtivo
                                                ? "text-bg-success"
                                                : "text-bg-secondary"; ?>">
                                                <?= $usuarioAtivo
                                                    ? "Ativo"
                                                    : "Inativo"; ?>
                                            </span>
                                        </td>

                                        <?php foreach (
                                            [1, 2, 3]
                                            as $tipoOpcao
                                        ): ?>
                                            <td class="text-center
                                                    permissao-coluna">
                                                <form method="post" class="permissao-form" data-permissao-form>
                                                    <input type="hidden" name="_token" value="<?= permissaoEscapar(
                                                        Session::csrf()
                                                    ); ?>">

                                                    <input type="hidden" name="id_usuario" value="<?= $usuarioId; ?>">

                                                    <input type="hidden" name="tipo" value="<?= $tipoAtual; ?>" data-tipo-input>

                                                    <input type="hidden" name="pagina" value="<?= $pagina; ?>">

                                                    <input type="hidden" name="pesquisa" value="<?= permissaoEscapar(
                                                        $pesquisa
                                                    ); ?>">

                                                    <div class="form-check
                                                            d-inline-flex
                                                            justify-content-center
                                                            m-0">
                                                        <input class="form-check-input
                                                                permissao-checkbox" type="checkbox"
                                                            value="<?= $tipoOpcao; ?>" data-tipo="<?= $tipoOpcao; ?>" aria-label="Definir <?= permissaoEscapar(
                                                                    permissaoNomeTipo(
                                                                        $tipoOpcao
                                                                    )
                                                                ); ?> para <?= permissaoEscapar(
                                                                      (string) $usuario["nome"]
                                                                  ); ?>" <?= $tipoAtual === $tipoOpcao
                                                                       ? "checked"
                                                                       : ""; ?>
                                                            <?= $ehUsuarioLogado
                                                                ? "disabled"
                                                                : ""; ?>>
                                                    </div>
                                                </form>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($totalPaginas > 1): ?>
                <div class="card-footer bg-white">
                    <nav aria-label="Paginação dos usuários">
                        <ul class="pagination
                                justify-content-center
                                flex-wrap mb-0">
                            <li class="page-item <?= $pagina <= 1
                                ? "disabled"
                                : ""; ?>">
                                <a class="page-link" href="<?= permissaoEscapar(
                                    permissaoUrl(
                                        1,
                                        $pesquisa
                                    )
                                ); ?>">
                                    Início
                                </a>
                            </li>

                            <li class="page-item <?= $pagina <= 1
                                ? "disabled"
                                : ""; ?>">
                                <a class="page-link" href="<?= permissaoEscapar(
                                    permissaoUrl(
                                        $pagina - 1,
                                        $pesquisa
                                    )
                                ); ?>">
                                    Anterior
                                </a>
                            </li>

                            <?php
                            if ($totalPaginas <= 5) {
                                $paginasExibir = range(
                                    1,
                                    $totalPaginas
                                );
                            } elseif ($pagina <= 3) {
                                $paginasExibir = [
                                    1,
                                    2,
                                    3,
                                    null,
                                    $totalPaginas
                                ];
                            } elseif (
                                $pagina
                                >= $totalPaginas - 2
                            ) {
                                $paginasExibir = [
                                    1,
                                    null,
                                    $totalPaginas - 2,
                                    $totalPaginas - 1,
                                    $totalPaginas
                                ];
                            } else {
                                $paginasExibir = [
                                    1,
                                    null,
                                    $pagina - 1,
                                    $pagina,
                                    $pagina + 1,
                                    null,
                                    $totalPaginas
                                ];
                            }
                            ?>

                            <?php foreach (
                                $paginasExibir
                                as $numeroPagina
                            ): ?>
                                <?php if (
                                    $numeroPagina === null
                                ): ?>
                                    <li class="page-item disabled">
                                        <span class="page-link">
                                            …
                                        </span>
                                    </li>
                                <?php else: ?>
                                    <li class="page-item <?= $numeroPagina === $pagina
                                        ? "active"
                                        : ""; ?>">
                                        <a class="page-link" href="<?= permissaoEscapar(
                                            permissaoUrl(
                                                $numeroPagina,
                                                $pesquisa
                                            )
                                        ); ?>" <?= $numeroPagina === $pagina
                                             ? 'aria-current="page"'
                                             : ""; ?>>
                                            <?= $numeroPagina; ?>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <li class="page-item <?= $pagina >= $totalPaginas
                                ? "disabled"
                                : ""; ?>">
                                <a class="page-link" href="<?= permissaoEscapar(
                                    permissaoUrl(
                                        $pagina + 1,
                                        $pesquisa
                                    )
                                ); ?>">
                                    Próxima
                                </a>
                            </li>

                            <li class="page-item <?= $pagina >= $totalPaginas
                                ? "disabled"
                                : ""; ?>">
                                <a class="page-link" href="<?= permissaoEscapar(
                                    permissaoUrl(
                                        $totalPaginas,
                                        $pesquisa
                                    )
                                ); ?>">
                                    Última
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>

        </div>

        <div class="alert alert-info mt-4 mb-0">
            <i class="fa-solid
                    fa-circle-info me-1"></i>

            Marque uma das opções para alterar
            imediatamente a permissão do usuário.
            Apenas uma permissão pode ficar marcada.
            O perfil <strong>Usuário</strong>
            corresponde ao tipo 3 do sistema.
        </div>

    </div>
</div>

<script>
    document.addEventListener(
        "DOMContentLoaded",
        function () {
            document
                .querySelectorAll(
                    "[data-permissao-form]"
                )
                .forEach(function (form) {
                    const checkbox =
                        form.querySelector(
                            ".permissao-checkbox"
                        );

                    const tipoInput =
                        form.querySelector(
                            "[data-tipo-input]"
                        );

                    if (!checkbox || !tipoInput) {
                        return;
                    }

                    checkbox.addEventListener(
                        "change",
                        function () {
                            /*
                             * A opção atual não pode ficar
                             * simplesmente desmarcada.
                             */
                            if (!checkbox.checked) {
                                checkbox.checked = true;
                                return;
                            }

                            const linha =
                                checkbox.closest("tr");

                            if (linha) {
                                linha
                                    .querySelectorAll(
                                        ".permissao-checkbox"
                                    )
                                    .forEach(function (item) {
                                        item.disabled = true;

                                        if (item !== checkbox) {
                                            item.checked = false;
                                        }
                                    });
                            }

                            tipoInput.value =
                                checkbox.dataset.tipo
                                || checkbox.value;

                            form.submit();
                        }
                    );
                });
        }
    );
</script>

<?php
require_once __DIR__
    . "/../admin/includes/footer.php";
?>