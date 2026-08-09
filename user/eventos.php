<?php

declare(strict_types=1);

require_once __DIR__
    . "/../config/settings.php";

Session::start();
Auth::requireLogin();

/*
 * Administrador gerencia inscrições pela área administrativa.
 */
if (Auth::isAdmin()) {
    header(
        "Location: "
        . BASE_URL
        . "admin/inscricao/inscricoes.php"
    );
    exit;
}

$idUsuario = (int) (Auth::id() ?? 0);

$inscricaoModel = new Inscricao($db);

$inscricoes =
    $inscricaoModel->listarPorUsuario(
        $idUsuario
    );

$cancelamentoService = null;

if (
    class_exists(
        "SolicitacaoCancelamentoInscricao"
    )
) {
    $cancelamentoService =
        new SolicitacaoCancelamentoInscricao(
            $db
        );
}

$total = count($inscricoes);

$totalAtivas = 0;
$totalPendentesPagamento = 0;
$totalCanceladas = 0;

foreach ($inscricoes as $item) {
    $statusInscricao = (string) (
        $item["statusInscricao"]
        ?? ""
    );

    $statusPagamento = (string) (
        $item["statusPagamento"]
        ?? $item["pagamentoInscricao"]
        ?? ""
    );

    if ($statusInscricao === "Cancelada") {
        $totalCanceladas++;
        continue;
    }

    $totalAtivas++;

    if (
        $statusPagamento !== "Pago"
        && (float) (
            $item["valor"]
            ?? 0
        ) > 0
    ) {
        $totalPendentesPagamento++;
    }
}

function meusEventosEscapar(
    string $valor
): string {
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function meusEventosData(
    mixed $valor
): string {
    $texto = trim(
        (string) ($valor ?? "")
    );

    if ($texto === "") {
        return "A definir";
    }

    $timestamp = strtotime($texto);

    return $timestamp !== false
        ? date("d/m/Y", $timestamp)
        : $texto;
}

function meusEventosDataHora(
    mixed $valor
): string {
    $texto = trim(
        (string) ($valor ?? "")
    );

    if ($texto === "") {
        return "-";
    }

    $timestamp = strtotime($texto);

    return $timestamp !== false
        ? date("d/m/Y H:i", $timestamp)
        : $texto;
}

function meusEventosHora(
    mixed $valor
): string {
    $texto = trim(
        (string) ($valor ?? "")
    );

    return $texto === ""
        ? ""
        : substr($texto, 0, 5);
}

function meusEventosImagem(
    array $item
): string {
    $imagem = trim(
        (string) ($item["imagem"] ?? "")
    );

    if ($imagem === "") {
        return THEME_IMG . "sem-imagem.png";
    }

    return BASE_URL
        . "uploads/eventos/"
        . rawurlencode(
            basename($imagem)
        );
}

function meusEventosUrl(
    array $item
): string {
    $slug = trim(
        (string) ($item["slug"] ?? "")
    );

    return BASE_URL
        . "eventos/detalhe.php"
        . (
            $slug !== ""
                ? "?slug="
                    . rawurlencode($slug)
                : "?id="
                    . (int) (
                        $item["idEvento"]
                        ?? 0
                    )
        );
}

function meusEventosStatusClasse(
    string $status
): string {
    return match ($status) {
        "Confirmada" => "success",
        "Cancelada" => "danger",
        default => "warning"
    };
}

function meusEventosPagamentoClasse(
    string $status
): string {
    return match ($status) {
        "Pago" => "success",
        "Cancelado",
        "Estornado",
        "Vencido" => "danger",
        default => "warning"
    };
}

require_once __DIR__
    . "/../admin/includes/header.php";

require_once __DIR__
    . "/../admin/includes/navbar.php";

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
                align-items-end gap-3 mb-4"
        >
            <div>
                <h2 class="fw-bold mb-1">
                    <i
                        class="fa-solid
                            fa-clipboard-check
                            text-primary me-2"
                    ></i>
                    Meus Eventos
                </h2>

                <p class="text-muted mb-0">
                    Consulte os eventos em que
                    você realizou inscrição.
                </p>
            </div>

            <a
                href="<?= BASE_URL ?>eventos/"
                class="btn btn-primary"
            >
                <i
                    class="fa-solid
                        fa-plus me-1"
                ></i>
                Ver eventos disponíveis
            </a>
        </div>

        <div class="row g-3 mb-4">

            <div class="col-sm-6 col-xl-3">
                <div
                    class="card border-0
                        shadow-sm h-100"
                >
                    <div class="card-body">
                        <small class="text-muted">
                            Total de inscrições
                        </small>

                        <div class="display-6 fw-bold">
                            <?= $total; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div
                    class="card border-0
                        shadow-sm h-100"
                >
                    <div class="card-body">
                        <small class="text-muted">
                            Inscrições ativas
                        </small>

                        <div
                            class="display-6
                                fw-bold text-success"
                        >
                            <?= $totalAtivas; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div
                    class="card border-0
                        shadow-sm h-100"
                >
                    <div class="card-body">
                        <small class="text-muted">
                            Pagamentos pendentes
                        </small>

                        <div
                            class="display-6
                                fw-bold text-warning"
                        >
                            <?= $totalPendentesPagamento; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div
                    class="card border-0
                        shadow-sm h-100"
                >
                    <div class="card-body">
                        <small class="text-muted">
                            Canceladas
                        </small>

                        <div
                            class="display-6
                                fw-bold text-danger"
                        >
                            <?= $totalCanceladas; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <?php if ($inscricoes === []): ?>

            <div
                class="card border-0
                    shadow-sm text-center"
            >
                <div class="card-body p-5">

                    <i
                        class="fa-regular
                            fa-calendar-xmark
                            fa-4x text-muted mb-3"
                    ></i>

                    <h3 class="h4">
                        Você ainda não está
                        inscrito em nenhum evento
                    </h3>

                    <p class="text-muted">
                        Consulte os eventos disponíveis
                        e escolha um para participar.
                    </p>

                    <a
                        href="<?= BASE_URL ?>eventos/"
                        class="btn btn-primary"
                    >
                        Ver eventos
                    </a>

                </div>
            </div>

        <?php else: ?>

            <div class="row g-4">

                <?php foreach (
                    $inscricoes
                    as $item
                ): ?>
                    <?php
                    $idInscricao = (int) (
                        $item["idInscricao"]
                        ?? 0
                    );

                    $statusInscricao = (string) (
                        $item["statusInscricao"]
                        ?? "Pendente"
                    );

                    $statusPagamento = (string) (
                        $item["statusPagamento"]
                        ?? $item["pagamentoInscricao"]
                        ?? "Pendente"
                    );

                    $idPagamento = (int) (
                        $item["idPagamento"]
                        ?? 0
                    );

                    $valor = (float) (
                        $item["valor"]
                        ?? 0
                    );

                    $hora = meusEventosHora(
                        $item["hora_inicio"]
                        ?? ""
                    );

                    $solicitacaoCancelamento =
                        $cancelamentoService
                            ? $cancelamentoService
                                ->buscarUltimaPorInscricao(
                                    $idInscricao,
                                    $idUsuario
                                )
                            : null;

                    $statusCancelamento = trim(
                        (string) (
                            $solicitacaoCancelamento[
                                "status"
                            ]
                            ?? ""
                        )
                    );

                    $regraCancelamento = null;

                    if (
                        $cancelamentoService
                        && $statusInscricao !== "Cancelada"
                        && $statusCancelamento !== "Pendente"
                    ) {
                        $regraCancelamento =
                            $cancelamentoService
                                ->podeSolicitar(
                                    $idInscricao,
                                    $idUsuario
                                );
                    }

                    $podeCancelar = !empty(
                        $regraCancelamento[
                            "permitido"
                        ]
                    );

                    $modalCancelamentoId =
                        "modalCancelarInscricao"
                        . $idInscricao;
                    ?>

                    <div
                        class="col-12
                            col-md-6 col-xl-4"
                    >
                        <article
                            class="card border-0
                                shadow-sm h-100
                                overflow-hidden"
                        >
                            <img
                                src="<?= meusEventosEscapar(
                                    meusEventosImagem(
                                        $item
                                    )
                                ); ?>"
                                alt="<?= meusEventosEscapar(
                                    (string) (
                                        $item["titulo"]
                                        ?? "Evento"
                                    )
                                ); ?>"
                                style="
                                    width: 100%;
                                    height: 210px;
                                    object-fit: cover;
                                "
                            >

                            <div
                                class="card-body
                                    d-flex flex-column p-4"
                            >
                                <div
                                    class="d-flex flex-wrap
                                        gap-2 mb-3"
                                >
                                    <span
                                        class="badge
                                            text-bg-<?= meusEventosStatusClasse(
                                                $statusInscricao
                                            ); ?>"
                                    >
                                        Inscrição:
                                        <?= meusEventosEscapar(
                                            $statusInscricao
                                        ); ?>
                                    </span>

                                    <?php if ($valor > 0): ?>
                                        <span
                                            class="badge
                                                text-bg-<?= meusEventosPagamentoClasse(
                                                    $statusPagamento
                                                ); ?>"
                                        >
                                            Pagamento:
                                            <?= meusEventosEscapar(
                                                $statusPagamento
                                            ); ?>
                                        </span>
                                    <?php else: ?>
                                        <span
                                            class="badge
                                                text-bg-success"
                                        >
                                            Gratuito
                                        </span>
                                    <?php endif; ?>

                                    <?php if (
                                        $statusCancelamento ===
                                        "Pendente"
                                    ): ?>
                                        <span
                                            class="badge
                                                text-bg-warning"
                                        >
                                            Cancelamento em análise
                                        </span>
                                    <?php elseif (
                                        $statusCancelamento ===
                                        "Rejeitada"
                                    ): ?>
                                        <span
                                            class="badge
                                                text-bg-danger"
                                        >
                                            Cancelamento rejeitado
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <h3
                                    class="h5 fw-bold mb-2"
                                >
                                    <?= meusEventosEscapar(
                                        (string) (
                                            $item["titulo"]
                                            ?? ""
                                        )
                                    ); ?>
                                </h3>

                                <div
                                    class="small
                                        text-body-secondary mb-3"
                                >
                                    <p class="mb-2">
                                        <i
                                            class="fa-regular
                                                fa-calendar
                                                me-1"
                                        ></i>

                                        <?= meusEventosData(
                                            $item[
                                                "data_inicio"
                                            ]
                                            ?? ""
                                        ); ?>

                                        <?php if (
                                            $hora !== ""
                                        ): ?>
                                            às
                                            <?= meusEventosEscapar(
                                                $hora
                                            ); ?>
                                        <?php endif; ?>
                                    </p>

                                    <?php if (
                                        trim(
                                            (string) (
                                                $item["local"]
                                                ?? ""
                                            )
                                        ) !== ""
                                        || trim(
                                            (string) (
                                                $item["cidade"]
                                                ?? ""
                                            )
                                        ) !== ""
                                    ): ?>
                                        <p class="mb-2">
                                            <i
                                                class="fa-solid
                                                    fa-location-dot
                                                    me-1"
                                            ></i>

                                            <?= meusEventosEscapar(
                                                trim(
                                                    (string) (
                                                        $item[
                                                            "local"
                                                        ]
                                                        ?? ""
                                                    )
                                                    . (
                                                        !empty(
                                                            $item[
                                                                "cidade"
                                                            ]
                                                        )
                                                            ? " — "
                                                                . $item[
                                                                    "cidade"
                                                                ]
                                                            : ""
                                                    )
                                                )
                                            ); ?>
                                        </p>
                                    <?php endif; ?>

                                    <p class="mb-0">
                                        <i
                                            class="fa-solid
                                                fa-receipt me-1"
                                        ></i>
                                        Inscrição
                                        #<?= $idInscricao; ?>
                                        realizada em
                                        <?= meusEventosDataHora(
                                            $item[
                                                "inscritoEm"
                                            ]
                                            ?? ""
                                        ); ?>
                                    </p>
                                </div>

                                <?php if (
                                    $statusCancelamento ===
                                    "Pendente"
                                ): ?>
                                    <div
                                        class="alert
                                            alert-warning
                                            small py-2"
                                    >
                                        Sua solicitação de
                                        cancelamento está
                                        aguardando análise.
                                    </div>
                                <?php elseif (
                                    $statusCancelamento ===
                                    "Rejeitada"
                                    && !empty(
                                        $solicitacaoCancelamento[
                                            "observacao_admin"
                                        ]
                                    )
                                ): ?>
                                    <div
                                        class="alert
                                            alert-danger
                                            small py-2"
                                    >
                                        <?= nl2br(
                                            meusEventosEscapar(
                                                (string) $solicitacaoCancelamento[
                                                    "observacao_admin"
                                                ]
                                            )
                                        ); ?>
                                    </div>
                                <?php endif; ?>

                                <div
                                    class="mt-auto
                                        d-grid gap-2"
                                >
                                    <a
                                        href="<?= meusEventosEscapar(
                                            meusEventosUrl(
                                                $item
                                            )
                                        ); ?>"
                                        class="btn
                                            btn-outline-primary"
                                    >
                                        <i
                                            class="fa-solid
                                                fa-eye me-1"
                                        ></i>
                                        Ver detalhes
                                    </a>

                                    <?php if (
                                        $statusInscricao
                                            !== "Cancelada"
                                        && $valor > 0
                                        && $statusPagamento
                                            !== "Pago"
                                        && $idPagamento > 0
                                    ): ?>
                                        <a
                                            href="<?= BASE_URL ?>eventos/pagamento.php?id=<?= $idPagamento; ?>"
                                            class="btn
                                                btn-primary"
                                        >
                                            <i
                                                class="fa-solid
                                                    fa-credit-card
                                                    me-1"
                                            ></i>
                                            Continuar pagamento
                                        </a>
                                    <?php endif; ?>

                                    <?php if (
                                        $statusInscricao !== "Cancelada"
                                        && $statusCancelamento !== "Pendente"
                                        && $podeCancelar
                                    ): ?>
                                        <button
                                            type="button"
                                            class="btn
                                                btn-outline-danger"
                                            data-bs-toggle="modal"
                                            data-bs-target="#<?= $modalCancelamentoId; ?>"
                                        >
                                            <i
                                                class="fa-solid
                                                    fa-ban me-1"
                                            ></i>
                                            Cancelar inscrição
                                        </button>

                                    <?php elseif (
                                        $statusInscricao !== "Cancelada"
                                        && $statusCancelamento !== "Pendente"
                                        && $regraCancelamento
                                    ): ?>
                                        <button
                                            type="button"
                                            class="btn
                                                btn-outline-secondary"
                                            disabled
                                            title="<?= meusEventosEscapar(
                                                (string) (
                                                    $regraCancelamento[
                                                        "mensagem"
                                                    ]
                                                    ?? ""
                                                )
                                            ); ?>"
                                        >
                                            <i
                                                class="fa-solid
                                                    fa-ban me-1"
                                            ></i>
                                            Cancelamento indisponível
                                        </button>

                                        <small
                                            class="text-muted"
                                        >
                                            <?= meusEventosEscapar(
                                                (string) (
                                                    $regraCancelamento[
                                                        "mensagem"
                                                    ]
                                                    ?? ""
                                                )
                                            ); ?>
                                        </small>
                                    <?php endif; ?>

                                </div>

                            </div>
                        </article>

                        <?php if (
                            $statusInscricao !== "Cancelada"
                            && $statusCancelamento !== "Pendente"
                            && $podeCancelar
                        ): ?>
                            <div
                                class="modal fade"
                                id="<?= $modalCancelamentoId; ?>"
                                tabindex="-1"
                                aria-labelledby="<?= $modalCancelamentoId; ?>Label"
                                aria-hidden="true"
                            >
                                <div
                                    class="modal-dialog
                                        modal-dialog-centered"
                                >
                                    <div class="modal-content">

                                        <form
                                            method="post"
                                            action="<?= BASE_URL ?>eventos/solicitar-cancelamento.php"
                                        >
                                            <div class="modal-header">
                                                <h5
                                                    class="modal-title"
                                                    id="<?= $modalCancelamentoId; ?>Label"
                                                >
                                                    Cancelar inscrição
                                                </h5>

                                                <button
                                                    type="button"
                                                    class="btn-close"
                                                    data-bs-dismiss="modal"
                                                    aria-label="Fechar"
                                                ></button>
                                            </div>

                                            <div class="modal-body">

                                                <div
                                                    class="alert
                                                        alert-warning"
                                                >
                                                    <i
                                                        class="fa-solid
                                                            fa-circle-info
                                                            me-1"
                                                    ></i>

                                                    O cancelamento
                                                    <strong>
                                                        não será imediato
                                                    </strong>.
                                                    Sua solicitação será
                                                    analisada pelo
                                                    Administrador.
                                                </div>

                                                <p class="mb-3">
                                                    <strong>
                                                        <?= meusEventosEscapar(
                                                            (string) (
                                                                $item["titulo"]
                                                                ?? "Evento"
                                                            )
                                                        ); ?>
                                                    </strong>
                                                </p>

                                                <input
                                                    type="hidden"
                                                    name="_token"
                                                    value="<?= meusEventosEscapar(
                                                        Session::csrf()
                                                    ); ?>"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="idInscricao"
                                                    value="<?= $idInscricao; ?>"
                                                >

                                                <div class="mb-3">
                                                    <label
                                                        class="form-label"
                                                        for="motivoCancelamento<?= $idInscricao; ?>"
                                                    >
                                                        Por qual motivo
                                                        deseja cancelar
                                                        sua inscrição?
                                                    </label>

                                                    <textarea
                                                        class="form-control"
                                                        id="motivoCancelamento<?= $idInscricao; ?>"
                                                        name="motivo"
                                                        rows="5"
                                                        minlength="10"
                                                        maxlength="2000"
                                                        required
                                                        placeholder="Informe o motivo do cancelamento."
                                                    ></textarea>

                                                    <div
                                                        class="form-text"
                                                    >
                                                        Informe pelo menos
                                                        10 caracteres.
                                                    </div>
                                                </div>

                                                <?php if (
                                                    !empty(
                                                        $regraCancelamento[
                                                            "limite_formatado"
                                                        ]
                                                    )
                                                ): ?>
                                                    <div
                                                        class="small
                                                            text-muted"
                                                    >
                                                        Prazo para solicitar:
                                                        <strong>
                                                            <?= meusEventosEscapar(
                                                                (string) $regraCancelamento[
                                                                    "limite_formatado"
                                                                ]
                                                            ); ?>
                                                        </strong>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (
                                                    $statusPagamento === "Pago"
                                                ): ?>
                                                    <div
                                                        class="alert
                                                            alert-info
                                                            mt-3 mb-0"
                                                    >
                                                        <i
                                                            class="fa-solid
                                                                fa-money-bill-transfer
                                                                me-1"
                                                        ></i>

                                                        Seu pagamento está
                                                        confirmado. A análise
                                                        de eventual reembolso
                                                        será feita pelo
                                                        Administrador.
                                                    </div>
                                                <?php endif; ?>

                                            </div>

                                            <div class="modal-footer">
                                                <button
                                                    type="button"
                                                    class="btn
                                                        btn-secondary"
                                                    data-bs-dismiss="modal"
                                                >
                                                    Voltar
                                                </button>

                                                <button
                                                    type="submit"
                                                    class="btn
                                                        btn-danger"
                                                >
                                                    <i
                                                        class="fa-solid
                                                            fa-paper-plane
                                                            me-1"
                                                    ></i>
                                                    Enviar solicitação
                                                </button>
                                            </div>

                                        </form>

                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>
</div>

<?php
require_once __DIR__
    . "/../admin/includes/footer.php";
?>
