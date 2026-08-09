<?php

declare(strict_types=1);

require_once __DIR__
    . "/../../config/settings.php";

Session::start();
Auth::requireLogin();

if (!Auth::isAdmin()) {
    http_response_code(403);
    exit("Acesso negado.");
}

$title = "Solicitações de cancelamento";

$statusFiltro = trim(
    (string) ($_GET["status"] ?? "Pendente")
);

if (
    !in_array(
        $statusFiltro,
        [
            "",
            "Pendente",
            "Aprovada",
            "Rejeitada"
        ],
        true
    )
) {
    $statusFiltro = "Pendente";
}

$service =
    new SolicitacaoCancelamentoInscricao(
        $db
    );

$solicitacoes = $service->listar(
    $statusFiltro
);

$totalPendentes =
    $service->contarPendentes();

$mensagemSucesso =
    Session::getFlash("success");

$mensagemErro =
    Session::getFlash("error");

function cancelarAdminEscapar(
    string $valor
): string {
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function cancelarAdminData(
    mixed $valor
): string {
    $texto = trim(
        (string) ($valor ?? "")
    );

    if ($texto === "") {
        return "-";
    }

    $ts = strtotime($texto);

    return $ts !== false
        ? date("d/m/Y H:i", $ts)
        : $texto;
}

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
            class="d-flex flex-wrap
                justify-content-between
                align-items-center gap-3 mb-4"
        >
            <div>
                <h2 class="fw-bold mb-1">
                    <i
                        class="fa-solid
                            fa-ban text-danger
                            me-2"
                    ></i>
                    Solicitações de cancelamento
                </h2>

                <p class="text-muted mb-0">
                    Analise os pedidos enviados
                    pelos participantes.
                </p>
            </div>

            <a
                href="<?= BASE_URL ?>admin/inscricao/inscricoes.php"
                class="btn btn-outline-secondary"
            >
                <i
                    class="fa-solid
                        fa-arrow-left me-1"
                ></i>
                Inscrições
            </a>
        </div>

        <?php if ($mensagemSucesso): ?>
            <div class="alert alert-success">
                <?= cancelarAdminEscapar(
                    (string) $mensagemSucesso
                ); ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagemErro): ?>
            <div class="alert alert-danger">
                <?= cancelarAdminEscapar(
                    (string) $mensagemErro
                ); ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div
                    class="card border-0
                        shadow-sm h-100"
                >
                    <div class="card-body">
                        <small class="text-muted">
                            Aguardando análise
                        </small>

                        <div
                            class="display-6
                                fw-bold text-warning"
                        >
                            <?= $totalPendentes; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div
            class="card border-0 shadow-sm"
        >
            <div
                class="card-header bg-white
                    d-flex flex-wrap
                    justify-content-between
                    align-items-center gap-3"
            >
                <strong>Solicitações</strong>

                <form
                    method="get"
                    class="d-flex gap-2"
                >
                    <select
                        name="status"
                        class="form-select"
                        onchange="this.form.submit()"
                    >
                        <option
                            value="Pendente"
                            <?= $statusFiltro === "Pendente"
                                ? "selected"
                                : ""; ?>
                        >
                            Pendentes
                        </option>

                        <option
                            value="Aprovada"
                            <?= $statusFiltro === "Aprovada"
                                ? "selected"
                                : ""; ?>
                        >
                            Aprovadas
                        </option>

                        <option
                            value="Rejeitada"
                            <?= $statusFiltro === "Rejeitada"
                                ? "selected"
                                : ""; ?>
                        >
                            Rejeitadas
                        </option>

                        <option
                            value=""
                            <?= $statusFiltro === ""
                                ? "selected"
                                : ""; ?>
                        >
                            Todas
                        </option>
                    </select>
                </form>
            </div>

            <div class="card-body">

                <?php if ($solicitacoes === []): ?>
                    <div
                        class="text-center
                            text-muted py-5"
                    >
                        <i
                            class="fa-regular
                                fa-circle-check
                                fa-3x mb-3"
                        ></i>

                        <p class="mb-0">
                            Nenhuma solicitação encontrada.
                        </p>
                    </div>
                <?php else: ?>

                    <div class="row g-4">
                        <?php foreach (
                            $solicitacoes
                            as $solicitacao
                        ): ?>
                            <?php
                            $statusSolicitacao =
                                (string) (
                                    $solicitacao[
                                        "status"
                                    ]
                                    ?? ""
                                );

                            $statusPagamento =
                                (string) (
                                    $solicitacao[
                                        "statusPagamento"
                                    ]
                                    ?? ""
                                );

                            $classeStatus = match (
                                $statusSolicitacao
                            ) {
                                "Aprovada" => "success",
                                "Rejeitada" => "danger",
                                default => "warning"
                            };
                            ?>

                            <div class="col-12">
                                <div
                                    class="card
                                        border shadow-sm"
                                >
                                    <div
                                        class="card-body p-4"
                                    >
                                        <div
                                            class="d-flex
                                                flex-wrap
                                                justify-content-between
                                                gap-3 mb-3"
                                        >
                                            <div>
                                                <h3
                                                    class="h5
                                                        fw-bold mb-1"
                                                >
                                                    <?= cancelarAdminEscapar(
                                                        (string) (
                                                            $solicitacao[
                                                                "evento"
                                                            ]
                                                            ?? ""
                                                        )
                                                    ); ?>
                                                </h3>

                                                <div
                                                    class="text-muted"
                                                >
                                                    <?= cancelarAdminEscapar(
                                                        (string) (
                                                            $solicitacao[
                                                                "usuario"
                                                            ]
                                                            ?? ""
                                                        )
                                                    ); ?>

                                                    · Inscrição
                                                    #<?= (int) (
                                                        $solicitacao[
                                                            "idInscricao"
                                                        ]
                                                        ?? 0
                                                    ); ?>
                                                </div>
                                            </div>

                                            <span
                                                class="badge
                                                    text-bg-<?= $classeStatus; ?>
                                                    align-self-start"
                                            >
                                                <?= cancelarAdminEscapar(
                                                    $statusSolicitacao
                                                ); ?>
                                            </span>
                                        </div>

                                        <div class="row g-3 mb-3">
                                            <div class="col-md-4">
                                                <small
                                                    class="text-muted
                                                        d-block"
                                                >
                                                    Solicitado em
                                                </small>

                                                <strong>
                                                    <?= cancelarAdminData(
                                                        $solicitacao[
                                                            "criado_em"
                                                        ]
                                                        ?? ""
                                                    ); ?>
                                                </strong>
                                            </div>

                                            <div class="col-md-4">
                                                <small
                                                    class="text-muted
                                                        d-block"
                                                >
                                                    Pagamento
                                                </small>

                                                <strong>
                                                    <?= $statusPagamento !== ""
                                                        ? cancelarAdminEscapar(
                                                            $statusPagamento
                                                        )
                                                        : "Sem cobrança"; ?>
                                                </strong>

                                                <?php if (
                                                    !empty(
                                                        $solicitacao[
                                                            "formaPagamento"
                                                        ]
                                                    )
                                                ): ?>
                                                    <span
                                                        class="text-muted
                                                            d-block"
                                                    >
                                                        <?= cancelarAdminEscapar(
                                                            (string) $solicitacao[
                                                                "formaPagamento"
                                                            ]
                                                        ); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>

                                            <div class="col-md-4">
                                                <small
                                                    class="text-muted
                                                        d-block"
                                                >
                                                    Encerramento das inscrições
                                                </small>

                                                <strong>
                                                    <?= cancelarAdminData(
                                                        $solicitacao[
                                                            "inscricao_fim"
                                                        ]
                                                        ?? ""
                                                    ); ?>
                                                </strong>
                                            </div>
                                        </div>

                                        <?php if (
                                            $statusPagamento === "Pago"
                                            && $statusSolicitacao === "Pendente"
                                        ): ?>
                                            <div
                                                class="alert
                                                    alert-warning"
                                            >
                                                <i
                                                    class="fa-solid
                                                        fa-triangle-exclamation
                                                        me-1"
                                                ></i>
                                                Esta inscrição possui
                                                pagamento confirmado.
                                                Aprovar o cancelamento
                                                <strong>não fará estorno
                                                automático</strong>.
                                            </div>
                                        <?php endif; ?>

                                        <div
                                            class="bg-body-tertiary
                                                rounded p-3 mb-3"
                                        >
                                            <small
                                                class="text-muted
                                                    d-block mb-1"
                                            >
                                                Motivo informado pelo usuário
                                            </small>

                                            <div>
                                                <?= nl2br(
                                                    cancelarAdminEscapar(
                                                        (string) (
                                                            $solicitacao[
                                                                "motivo"
                                                            ]
                                                            ?? ""
                                                        )
                                                    )
                                                ); ?>
                                            </div>
                                        </div>

                                        <?php if (
                                            $statusSolicitacao === "Pendente"
                                        ): ?>
                                            <form
                                                method="post"
                                                action="<?= BASE_URL ?>admin/inscricao/analisar-cancelamento.php"
                                            >
                                                <input
                                                    type="hidden"
                                                    name="_token"
                                                    value="<?= cancelarAdminEscapar(
                                                        Session::csrf()
                                                    ); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="idSolicitacao"
                                                    value="<?= (int) (
                                                        $solicitacao[
                                                            "idSolicitacao"
                                                        ]
                                                        ?? 0
                                                    ); ?>"
                                                >

                                                <div class="mb-3">
                                                    <label
                                                        class="form-label"
                                                        for="obs-<?= (int) $solicitacao["idSolicitacao"]; ?>"
                                                    >
                                                        Observação da análise
                                                    </label>

                                                    <textarea
                                                        class="form-control"
                                                        id="obs-<?= (int) $solicitacao["idSolicitacao"]; ?>"
                                                        name="observacao_admin"
                                                        rows="3"
                                                        maxlength="2000"
                                                        placeholder="Opcional para aprovação. Obrigatória ao rejeitar."
                                                    ></textarea>
                                                </div>

                                                <div
                                                    class="d-flex
                                                        flex-wrap gap-2"
                                                >
                                                    <button
                                                        type="submit"
                                                        name="decisao"
                                                        value="Aprovada"
                                                        class="btn
                                                            btn-success"
                                                        onclick="return confirm('Aprovar o cancelamento desta inscrição?');"
                                                    >
                                                        <i
                                                            class="fa-solid
                                                                fa-check me-1"
                                                        ></i>
                                                        Aprovar cancelamento
                                                    </button>

                                                    <button
                                                        type="submit"
                                                        name="decisao"
                                                        value="Rejeitada"
                                                        class="btn
                                                            btn-outline-danger"
                                                    >
                                                        <i
                                                            class="fa-solid
                                                                fa-xmark me-1"
                                                        ></i>
                                                        Rejeitar
                                                    </button>
                                                </div>
                                            </form>
                                        <?php else: ?>

                                            <div
                                                class="border-top pt-3"
                                            >
                                                <small
                                                    class="text-muted
                                                        d-block"
                                                >
                                                    Analisado em
                                                    <?= cancelarAdminData(
                                                        $solicitacao[
                                                            "analisado_em"
                                                        ]
                                                        ?? ""
                                                    ); ?>

                                                    <?php if (
                                                        !empty(
                                                            $solicitacao[
                                                                "administrador"
                                                            ]
                                                        )
                                                    ): ?>
                                                        por
                                                        <?= cancelarAdminEscapar(
                                                            (string) $solicitacao[
                                                                "administrador"
                                                            ]
                                                        ); ?>
                                                    <?php endif; ?>
                                                </small>

                                                <?php if (
                                                    !empty(
                                                        $solicitacao[
                                                            "observacao_admin"
                                                        ]
                                                    )
                                                ): ?>
                                                    <p class="mb-0 mt-2">
                                                        <?= nl2br(
                                                            cancelarAdminEscapar(
                                                                (string) $solicitacao[
                                                                    "observacao_admin"
                                                                ]
                                                            )
                                                        ); ?>
                                                    </p>
                                                <?php endif; ?>
                                            </div>

                                        <?php endif; ?>

                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<?php
require_once __DIR__
    . "/../includes/footer.php";
?>
