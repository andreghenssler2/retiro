<?php

declare(strict_types=1);

require_once __DIR__
    . "/../../config/settings.php";

Session::start();
Middleware::admin();

$comunidades =
    new Comunidade($db);

function comunidadeEscapar(
    mixed $valor
): string {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES
        | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function comunidadeSalvarImagem(
    string $campo,
    ?string $imagemAtual = null
): ?string {
    if (
        !isset($_FILES[$campo])
        || !is_array(
            $_FILES[$campo]
        )
        || (int) (
            $_FILES[$campo]["error"]
            ?? UPLOAD_ERR_NO_FILE
        ) === UPLOAD_ERR_NO_FILE
    ) {
        return $imagemAtual;
    }

    $arquivo =
        $_FILES[$campo];

    $erro =
        (int) (
            $arquivo["error"]
            ?? UPLOAD_ERR_NO_FILE
        );

    if ($erro !== UPLOAD_ERR_OK) {
        throw new RuntimeException(
            "Não foi possível enviar "
            . "a imagem."
        );
    }

    $tmp =
        (string) (
            $arquivo["tmp_name"]
            ?? ""
        );

    if (
        $tmp === ""
        || !is_uploaded_file($tmp)
    ) {
        throw new RuntimeException(
            "Arquivo de imagem inválido."
        );
    }

    $tamanho =
        (int) (
            $arquivo["size"]
            ?? 0
        );

    if (
        $tamanho <= 0
        || $tamanho
            > 3 * 1024 * 1024
    ) {
        throw new InvalidArgumentException(
            "A imagem deve possuir "
            . "no máximo 3 MB."
        );
    }

    $mime =
        (new finfo(
            FILEINFO_MIME_TYPE
        ))->file($tmp);

    $formatos = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];

    if (
        !isset(
            $formatos[$mime]
        )
    ) {
        throw new InvalidArgumentException(
            "Use uma imagem JPG, PNG "
            . "ou WEBP."
        );
    }

    $diretorio =
        ROOT_PATH
        . "/uploads/comunidades";

    if (
        !is_dir($diretorio)
        && !mkdir(
            $diretorio,
            0755,
            true
        )
        && !is_dir($diretorio)
    ) {
        throw new RuntimeException(
            "Não foi possível criar "
            . "uploads/comunidades."
        );
    }

    $nome =
        "comunidade-"
        . bin2hex(
            random_bytes(8)
        )
        . "."
        . $formatos[$mime];

    $destino =
        $diretorio
        . "/"
        . $nome;

    if (
        !move_uploaded_file(
            $tmp,
            $destino
        )
    ) {
        throw new RuntimeException(
            "Não foi possível salvar "
            . "a imagem."
        );
    }

    @chmod(
        $destino,
        0644
    );

    if (
        $imagemAtual
        && basename(
            $imagemAtual
        ) === $imagemAtual
    ) {
        $antiga =
            $diretorio
            . "/"
            . $imagemAtual;

        if (
            is_file($antiga)
            && $antiga !== $destino
        ) {
            @unlink($antiga);
        }
    }

    return $nome;
}

function comunidadeExcluirImagem(
    ?string $imagem
): void {
    $imagem =
        trim(
            (string) $imagem
        );

    if (
        $imagem === ""
        || basename($imagem)
            !== $imagem
    ) {
        return;
    }

    $arquivo =
        ROOT_PATH
        . "/uploads/comunidades/"
        . $imagem;

    if (is_file($arquivo)) {
        @unlink($arquivo);
    }
}

if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {
    if (
        !Session::validateCsrf(
            $_POST["_token"]
            ?? ""
        )
    ) {
        Session::flash(
            "error",
            "Token de segurança inválido."
        );

        header(
            "Location: "
            . BASE_URL
            . "admin/configuracoes/"
            . "comunidades.php"
        );
        exit;
    }

    $acao =
        trim(
            (string) (
                $_POST["acao"]
                ?? ""
            )
        );

    $id =
        max(
            0,
            (int) (
                $_POST["id"]
                ?? 0
            )
        );

    try {
        if ($acao === "salvar") {
            $registroAtual =
                $id > 0
                    ? $comunidades
                        ->buscar($id)
                    : null;

            if (
                $id > 0
                && !$registroAtual
            ) {
                throw new RuntimeException(
                    "Comunidade não encontrada."
                );
            }

            $imagemAtual =
                is_array($registroAtual)
                    ? (
                        $registroAtual["imagem"]
                        ?? null
                    )
                    : null;

            $removerImagem =
                !empty(
                    $_POST[
                        "remover_imagem"
                    ]
                );

            if ($removerImagem) {
                comunidadeExcluirImagem(
                    is_string($imagemAtual)
                        ? $imagemAtual
                        : null
                );

                $imagemAtual = null;
            }

            $imagem =
                comunidadeSalvarImagem(
                    "imagem",
                    is_string($imagemAtual)
                        ? $imagemAtual
                        : null
                );

            $comunidades->salvar([
                "id" => $id,
                "nome_comunidade" =>
                    $_POST[
                        "nome_comunidade"
                    ]
                    ?? "",
                "descricao" =>
                    $_POST[
                        "descricao"
                    ]
                    ?? "",
                "imagem" => $imagem,
                "ativo" =>
                    !empty(
                        $_POST["ativo"]
                    )
            ]);

            Session::flash(
                "success",
                $id > 0
                    ? "Comunidade atualizada "
                        . "com sucesso."
                    : "Comunidade cadastrada "
                        . "com sucesso."
            );
        } elseif (
            $acao === "ativar"
            || $acao === "desativar"
        ) {
            $comunidades
                ->alterarStatus(
                    $id,
                    $acao === "ativar"
                );

            Session::flash(
                "success",
                $acao === "ativar"
                    ? "Comunidade ativada."
                    : "Comunidade desativada."
            );
        } elseif (
            $acao === "excluir"
        ) {
            $registro =
                $comunidades
                    ->buscar($id);

            if (!$registro) {
                throw new RuntimeException(
                    "Comunidade não encontrada."
                );
            }

            $comunidades
                ->excluir($id);

            comunidadeExcluirImagem(
                isset(
                    $registro["imagem"]
                )
                    ? (string)
                        $registro["imagem"]
                    : null
            );

            Session::flash(
                "success",
                "Comunidade excluída "
                . "com sucesso."
            );
        } else {
            throw new InvalidArgumentException(
                "Ação inválida."
            );
        }
    } catch (Throwable $erro) {
        Session::flash(
            "error",
            $erro->getMessage()
        );
    }

    header(
        "Location: "
        . BASE_URL
        . "admin/configuracoes/"
        . "comunidades.php"
    );
    exit;
}

$pesquisa =
    trim(
        (string) (
            $_GET["q"]
            ?? ""
        )
    );

$lista =
    $comunidades
        ->listar(
            $pesquisa
        );

$total =
    count($lista);

$ativos = 0;
$inativos = 0;

foreach ($lista as $item) {
    if (
        (int) (
            $item["ativo"]
            ?? 0
        ) === 1
    ) {
        $ativos++;
    } else {
        $inativos++;
    }
}

$mensagemSucesso =
    Session::getFlash(
        "success"
    );

$mensagemErro =
    Session::getFlash(
        "error"
    );

require_once __DIR__
    . "/../includes/header.php";

require_once __DIR__
    . "/../includes/navbar.php";

require_once __DIR__
    . "/../includes/sidebar.php";
?>

<div
    class="content"
    id="content"
>
    <div class="container-fluid">

        <div
            class="d-flex flex-column
                flex-md-row
                justify-content-between
                align-items-md-center
                gap-3 mb-4"
        >
            <div>
                <h2
                    class="fw-bold mb-1"
                >
                    <i
                        class="fa-solid
                            fa-church
                            text-primary
                            me-2"
                    ></i>
                    Comunidades
                </h2>

                <p
                    class="text-muted mb-0"
                >
                    Cadastre e gerencie
                    as comunidades/paróquias
                    disponíveis no sistema.
                </p>
            </div>

            <button
                type="button"
                class="btn btn-primary"
                data-bs-toggle="modal"
                data-bs-target="#modalComunidade"
                id="btnNovaComunidade"
            >
                <i
                    class="fa-solid
                        fa-plus me-1"
                ></i>
                Nova comunidade
            </button>
        </div>

        <?php if (
            $mensagemSucesso
        ): ?>
            <div
                class="alert
                    alert-success
                    alert-dismissible
                    fade show"
            >
                <i
                    class="fa-solid
                        fa-circle-check
                        me-1"
                ></i>

                <?= comunidadeEscapar(
                    $mensagemSucesso
                ); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>
            </div>
        <?php endif; ?>

        <?php if (
            $mensagemErro
        ): ?>
            <div
                class="alert
                    alert-danger
                    alert-dismissible
                    fade show"
            >
                <i
                    class="fa-solid
                        fa-circle-exclamation
                        me-1"
                ></i>

                <?= comunidadeEscapar(
                    $mensagemErro
                ); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                ></button>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">

            <div class="col-md-4">
                <div
                    class="card
                        shadow-sm
                        border-0 h-100"
                >
                    <div class="card-body">
                        <div
                            class="text-muted small"
                        >
                            Comunidades
                        </div>

                        <div
                            class="fs-3 fw-bold"
                        >
                            <?= $total; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div
                    class="card
                        shadow-sm
                        border-0 h-100"
                >
                    <div class="card-body">
                        <div
                            class="text-muted small"
                        >
                            Ativas
                        </div>

                        <div
                            class="fs-3
                                fw-bold
                                text-success"
                        >
                            <?= $ativos; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div
                    class="card
                        shadow-sm
                        border-0 h-100"
                >
                    <div class="card-body">
                        <div
                            class="text-muted small"
                        >
                            Inativas
                        </div>

                        <div
                            class="fs-3
                                fw-bold
                                text-secondary"
                        >
                            <?= $inativos; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <div
            class="card
                shadow-sm
                border-0"
        >
            <div
                class="card-header
                    bg-white py-3"
            >
                <div
                    class="d-flex flex-column
                        flex-lg-row
                        justify-content-between
                        align-items-lg-center
                        gap-3"
                >
                    <strong>
                        Comunidades cadastradas
                    </strong>

                    <form
                        method="get"
                        class="d-flex gap-2"
                    >
                        <input
                            type="search"
                            class="form-control"
                            name="q"
                            value="<?= comunidadeEscapar(
                                $pesquisa
                            ); ?>"
                            placeholder="Pesquisar comunidade"
                        >

                        <button
                            class="btn
                                btn-outline-primary"
                            type="submit"
                        >
                            <i
                                class="fa-solid
                                    fa-magnifying-glass"
                            ></i>
                        </button>

                        <?php if (
                            $pesquisa !== ""
                        ): ?>
                            <a
                                class="btn
                                    btn-outline-secondary"
                                href="<?= BASE_URL ?>admin/configuracoes/comunidades.php"
                            >
                                Limpar
                            </a>
                        <?php endif; ?>
                    </form>
                </div>
            </div>

            <div class="table-responsive">
                <table
                    class="table
                        table-hover
                        align-middle
                        mb-0"
                >
                    <thead
                        class="table-light"
                    >
                        <tr>
                            <th style="width:70px">
                                #
                            </th>
                            <th style="width:80px">
                                Imagem
                            </th>
                            <th>
                                Comunidade
                            </th>
                            <th>
                                Descrição
                            </th>
                            <th class="text-center">
                                Usuários
                            </th>
                            <th class="text-center">
                                Status
                            </th>
                            <th>
                                Cadastro
                            </th>
                            <th
                                class="text-end"
                                style="width:190px"
                            >
                                Ações
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (
                        $lista === []
                    ): ?>
                        <tr>
                            <td
                                colspan="8"
                                class="text-center
                                    text-muted py-5"
                            >
                                Nenhuma comunidade
                                encontrada.
                            </td>
                        </tr>
                    <?php else: ?>

                        <?php foreach (
                            $lista
                            as $item
                        ): ?>
                            <?php
                            $imagem =
                                trim(
                                    (string) (
                                        $item["imagem"]
                                        ?? ""
                                    )
                                );

                            $imagemUrl =
                                $imagem !== ""
                                    ? BASE_URL
                                        . "uploads/comunidades/"
                                        . rawurlencode(
                                            basename(
                                                $imagem
                                            )
                                        )
                                    : "";
                            ?>

                            <tr>
                                <td>
                                    <?= (int)
                                        $item["id"]; ?>
                                </td>

                                <td>
                                    <?php if (
                                        $imagemUrl !== ""
                                    ): ?>
                                        <img
                                            src="<?= comunidadeEscapar(
                                                $imagemUrl
                                            ); ?>"
                                            alt=""
                                            class="rounded"
                                            style="
                                                width:48px;
                                                height:48px;
                                                object-fit:cover;
                                            "
                                        >
                                    <?php else: ?>
                                        <div
                                            class="rounded
                                                bg-light
                                                d-flex
                                                align-items-center
                                                justify-content-center"
                                            style="
                                                width:48px;
                                                height:48px;
                                            "
                                        >
                                            <i
                                                class="fa-solid
                                                    fa-church
                                                    text-secondary"
                                            ></i>
                                        </div>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= comunidadeEscapar(
                                            $item[
                                                "nome_comunidade"
                                            ]
                                        ); ?>
                                    </strong>
                                </td>

                                <td>
                                    <?php
                                    $descricao =
                                        trim(
                                            (string) (
                                                $item[
                                                    "descricao"
                                                ]
                                                ?? ""
                                            )
                                        );
                                    ?>

                                    <?= $descricao !== ""
                                        ? comunidadeEscapar(
                                            $descricao
                                        )
                                        : '<span class="text-muted">—</span>'; ?>
                                </td>

                                <td class="text-center">
                                    <span
                                        class="badge
                                            text-bg-light
                                            border"
                                    >
                                        <?= (int) (
                                            $item[
                                                "total_usuarios"
                                            ]
                                            ?? 0
                                        ); ?>
                                    </span>
                                </td>

                                <td class="text-center">
                                    <?php if (
                                        (int) (
                                            $item["ativo"]
                                            ?? 0
                                        ) === 1
                                    ): ?>
                                        <span
                                            class="badge
                                                text-bg-success"
                                        >
                                            Ativa
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="badge
                                                text-bg-secondary"
                                        >
                                            Inativa
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= !empty(
                                        $item["criado_em"]
                                    )
                                        ? date(
                                            "d/m/Y H:i",
                                            strtotime(
                                                (string)
                                                $item[
                                                    "criado_em"
                                                ]
                                            )
                                        )
                                        : "—"; ?>
                                </td>

                                <td class="text-end">
                                    <button
                                        type="button"
                                        class="btn
                                            btn-sm
                                            btn-outline-primary
                                            btn-editar-comunidade"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalComunidade"
                                        data-id="<?= (int)
                                            $item["id"]; ?>"
                                        data-nome="<?= comunidadeEscapar(
                                            $item[
                                                "nome_comunidade"
                                            ]
                                        ); ?>"
                                        data-descricao="<?= comunidadeEscapar(
                                            $descricao
                                        ); ?>"
                                        data-imagem="<?= comunidadeEscapar(
                                            $imagem
                                        ); ?>"
                                        data-ativo="<?= (int) (
                                            $item["ativo"]
                                            ?? 0
                                        ); ?>"
                                    >
                                        <i
                                            class="fa-solid
                                                fa-pen"
                                        ></i>
                                    </button>

                                    <form
                                        method="post"
                                        class="d-inline"
                                    >
                                        <input
                                            type="hidden"
                                            name="_token"
                                            value="<?= comunidadeEscapar(
                                                Session::csrf()
                                            ); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)
                                                $item["id"]; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="acao"
                                            value="<?= (int) (
                                                $item["ativo"]
                                                ?? 0
                                            ) === 1
                                                ? "desativar"
                                                : "ativar"; ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn
                                                btn-sm
                                                <?= (int) (
                                                    $item["ativo"]
                                                    ?? 0
                                                ) === 1
                                                    ? "btn-outline-warning"
                                                    : "btn-outline-success"; ?>"
                                            title="<?= (int) (
                                                $item["ativo"]
                                                ?? 0
                                            ) === 1
                                                ? "Desativar"
                                                : "Ativar"; ?>"
                                        >
                                            <i
                                                class="fa-solid
                                                    <?= (int) (
                                                        $item["ativo"]
                                                        ?? 0
                                                    ) === 1
                                                        ? "fa-ban"
                                                        : "fa-check"; ?>"
                                            ></i>
                                        </button>
                                    </form>

                                    <form
                                        method="post"
                                        class="d-inline"
                                        onsubmit="
                                            return confirm(
                                                'Deseja realmente excluir esta comunidade?'
                                            );
                                        "
                                    >
                                        <input
                                            type="hidden"
                                            name="_token"
                                            value="<?= comunidadeEscapar(
                                                Session::csrf()
                                            ); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int)
                                                $item["id"]; ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="acao"
                                            value="excluir"
                                        >

                                        <button
                                            type="submit"
                                            class="btn
                                                btn-sm
                                                btn-outline-danger"
                                            <?= (int) (
                                                $item[
                                                    "total_usuarios"
                                                ]
                                                ?? 0
                                            ) > 0
                                                ? "disabled"
                                                : ""; ?>
                                            title="<?= (int) (
                                                $item[
                                                    "total_usuarios"
                                                ]
                                                ?? 0
                                            ) > 0
                                                ? "Há usuários vinculados"
                                                : "Excluir"; ?>"
                                        >
                                            <i
                                                class="fa-solid
                                                    fa-trash"
                                            ></i>
                                        </button>
                                    </form>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<div
    class="modal fade"
    id="modalComunidade"
    tabindex="-1"
    aria-hidden="true"
>
    <div
        class="modal-dialog
            modal-lg
            modal-dialog-centered"
    >
        <div class="modal-content">

            <form
                method="post"
                enctype="multipart/form-data"
                autocomplete="off"
                id="formComunidade"
            >
                <div class="modal-header">
                    <h5
                        class="modal-title"
                        id="tituloModalComunidade"
                    >
                        Nova comunidade
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                    ></button>
                </div>

                <div class="modal-body">

                    <input
                        type="hidden"
                        name="_token"
                        value="<?= comunidadeEscapar(
                            Session::csrf()
                        ); ?>"
                    >

                    <input
                        type="hidden"
                        name="acao"
                        value="salvar"
                    >

                    <input
                        type="hidden"
                        name="id"
                        id="comunidadeId"
                        value="0"
                    >

                    <div class="mb-3">
                        <label
                            for="comunidadeNome"
                            class="form-label
                                fw-semibold"
                        >
                            Nome da comunidade
                            <span
                                class="text-danger"
                            >*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="comunidadeNome"
                            name="nome_comunidade"
                            maxlength="150"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label
                            for="comunidadeDescricao"
                            class="form-label
                                fw-semibold"
                        >
                            Descrição
                        </label>

                        <textarea
                            class="form-control"
                            id="comunidadeDescricao"
                            name="descricao"
                            rows="4"
                        ></textarea>
                    </div>

                    <div class="mb-3">
                        <label
                            for="comunidadeImagem"
                            class="form-label
                                fw-semibold"
                        >
                            Imagem
                        </label>

                        <input
                            type="file"
                            class="form-control"
                            id="comunidadeImagem"
                            name="imagem"
                            accept="
                                image/jpeg,
                                image/png,
                                image/webp
                            "
                        >

                        <div
                            class="form-text"
                        >
                            JPG, PNG ou WEBP,
                            máximo 3 MB.
                        </div>

                        <div
                            id="imagemAtualArea"
                            class="mt-2"
                            hidden
                        >
                            <div
                                class="small
                                    text-muted
                                    mb-1"
                            >
                                Imagem atual:
                            </div>

                            <img
                                id="imagemAtualPreview"
                                src=""
                                alt=""
                                class="rounded border"
                                style="
                                    width:90px;
                                    height:90px;
                                    object-fit:cover;
                                "
                            >

                            <div
                                class="form-check mt-2"
                            >
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="remover_imagem"
                                    value="1"
                                    id="removerImagem"
                                >

                                <label
                                    class="form-check-label"
                                    for="removerImagem"
                                >
                                    Remover imagem atual
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="form-check">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="ativo"
                            value="1"
                            id="comunidadeAtiva"
                            checked
                        >

                        <label
                            class="form-check-label"
                            for="comunidadeAtiva"
                        >
                            Comunidade ativa
                        </label>
                    </div>

                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn
                            btn-outline-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancelar
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        <i
                            class="fa-solid
                                fa-floppy-disk
                                me-1"
                        ></i>
                        Salvar
                    </button>
                </div>

            </form>

        </div>
    </div>
</div>

<script>
(() => {
    "use strict";

    const baseUrl =
        <?= json_encode(
            BASE_URL,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
        ); ?>;

    const id =
        document.getElementById(
            "comunidadeId"
        );

    const nome =
        document.getElementById(
            "comunidadeNome"
        );

    const descricao =
        document.getElementById(
            "comunidadeDescricao"
        );

    const ativo =
        document.getElementById(
            "comunidadeAtiva"
        );

    const titulo =
        document.getElementById(
            "tituloModalComunidade"
        );

    const areaImagem =
        document.getElementById(
            "imagemAtualArea"
        );

    const preview =
        document.getElementById(
            "imagemAtualPreview"
        );

    const removerImagem =
        document.getElementById(
            "removerImagem"
        );

    const arquivoImagem =
        document.getElementById(
            "comunidadeImagem"
        );

    const resetar = () => {
        id.value = "0";
        nome.value = "";
        descricao.value = "";
        ativo.checked = true;
        titulo.textContent =
            "Nova comunidade";

        areaImagem.hidden = true;
        preview.src = "";
        removerImagem.checked = false;
        arquivoImagem.value = "";
    };

    document
        .getElementById(
            "btnNovaComunidade"
        )
        ?.addEventListener(
            "click",
            resetar
        );

    document
        .querySelectorAll(
            ".btn-editar-comunidade"
        )
        .forEach((button) => {
            button.addEventListener(
                "click",
                () => {
                    id.value =
                        button.dataset.id
                        || "0";

                    nome.value =
                        button.dataset.nome
                        || "";

                    descricao.value =
                        button.dataset.descricao
                        || "";

                    ativo.checked =
                        button.dataset.ativo
                        === "1";

                    titulo.textContent =
                        "Editar comunidade";

                    removerImagem.checked =
                        false;

                    arquivoImagem.value =
                        "";

                    const imagem =
                        button.dataset.imagem
                        || "";

                    if (imagem) {
                        preview.src =
                            baseUrl
                            + "uploads/comunidades/"
                            + encodeURIComponent(
                                imagem
                            );

                        areaImagem.hidden =
                            false;
                    } else {
                        preview.src = "";
                        areaImagem.hidden =
                            true;
                    }
                }
            );
        });
})();
</script>

<?php
require_once __DIR__
    . "/../includes/footer.php";
?>
