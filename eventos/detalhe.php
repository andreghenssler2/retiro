<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/settings.php";

Session::start();

$eventoModel = new Evento();
$inscricaoModel = new Inscricao($db);
$pagamentoModel = new Pagamento($db);

$slug = trim((string) ($_GET["slug"] ?? ""));
$idEvento = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
) ?: 0;

$evento = false;

if ($slug !== "") {
    $evento = $eventoModel->buscarPorSlug($slug);
} elseif ($idEvento > 0) {
    $evento = $eventoModel->buscar($idEvento);
}

if (!$evento || (int) ($evento["ativo"] ?? 0) !== 1) {
    http_response_code(404);
    exit("Evento não encontrado.");
}

$idEvento = (int) $evento["idEvento"];

/*
 * EVENTO_URL_CANONICA_V1
 *
 * Se alguém acessar diretamente:
 * detalhe.php?slug=...
 *
 * redireciona para:
 * /eventos/slug
 *
 * Na reescrita interna, REQUEST_URI continua
 * sendo /eventos/slug e não ocorre loop.
 */
$requestUri = (string) (
    $_SERVER["REQUEST_URI"]
    ?? ""
);

if (
    $slug !== ""
    && str_contains(
        $requestUri,
        "/eventos/detalhe.php"
    )
) {
    header(
        "Location: "
        . BASE_URL
        . "eventos/"
        . rawurlencode($slug),
        true,
        301
    );

    exit;
}
$idUsuario = (int) (Auth::id() ?? 0);

$inscricaoUsuario = false;
$pagamentoUsuario = null;
$cancelamentoUsuario = null;
$regraCancelamento = null;

$cancelamentoService =
    new SolicitacaoCancelamentoInscricao(
        $db
    );

if ($idUsuario > 0) {
    $inscricaoUsuario = $inscricaoModel->buscarDoUsuarioNoEvento(
        $idEvento,
        $idUsuario
    );

    if ($inscricaoUsuario) {
        $idInscricaoUsuario =
            (int) $inscricaoUsuario[
                "idInscricao"
            ];

        $pagamentoUsuario =
            $pagamentoModel->buscarPorInscricao(
                $idInscricaoUsuario
            );

        $cancelamentoUsuario =
            $cancelamentoService
                ->buscarUltimaPorInscricao(
                    $idInscricaoUsuario,
                    $idUsuario
                );

        $regraCancelamento =
            $cancelamentoService
                ->podeSolicitar(
                    $idInscricaoUsuario,
                    $idUsuario
                );
    }
}

$agora = new DateTimeImmutable(
    "now",
    new DateTimeZone("America/Sao_Paulo")
);

$inscricaoAberta =
    (int) ($evento["inscricao_aberta"] ?? 0) === 1;

$motivoInscricaoFechada = "";

$inicioInscricao = trim(
    (string) ($evento["inscricao_inicio"] ?? "")
);

if ($inscricaoAberta && $inicioInscricao !== "") {
    try {
        $inicio = new DateTimeImmutable(
            $inicioInscricao,
            new DateTimeZone("America/Sao_Paulo")
        );

        if ($agora < $inicio) {
            $inscricaoAberta = false;
            $motivoInscricaoFechada =
                "As inscrições iniciam em "
                . $inicio->format("d/m/Y H:i")
                . ".";
        }
    } catch (Throwable) {
        $inscricaoAberta = false;
        $motivoInscricaoFechada =
            "O período de inscrição ainda não está disponível.";
    }
}

$fimInscricao = trim(
    (string) ($evento["inscricao_fim"] ?? "")
);

if ($inscricaoAberta && $fimInscricao !== "") {
    try {
        $fim = new DateTimeImmutable(
            $fimInscricao,
            new DateTimeZone("America/Sao_Paulo")
        );

        if ($agora > $fim) {
            $inscricaoAberta = false;
            $motivoInscricaoFechada =
                "As inscrições foram encerradas em "
                . $fim->format("d/m/Y H:i")
                . ".";
        }
    } catch (Throwable) {
        $inscricaoAberta = false;
        $motivoInscricaoFechada =
            "O período de inscrição foi encerrado.";
    }
}

$vagasDisponiveis = $inscricaoModel->vagasDisponiveis(
    $idEvento
);

if ($vagasDisponiveis === 0) {
    $inscricaoAberta = false;
    $motivoInscricaoFechada =
        "As vagas para este evento estão esgotadas.";
}

$valor = (float) (
    $evento["valor_inscricao"]
    ?? $evento["valor"]
    ?? 0
);

$pagamentoObrigatorio =
    (int) ($evento["pagamento_obrigatorio"] ?? 1) === 1
    && $valor > 0;

$mensagemSucesso = Session::getFlash("success");
$mensagemErro = Session::getFlash("error");

function eventoDetalheEscapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function eventoDetalheData(mixed $valor): string
{
    $texto = trim((string) ($valor ?? ""));

    if ($texto === "") {
        return "A definir";
    }

    $timestamp = strtotime($texto);

    return $timestamp !== false
        ? date("d/m/Y", $timestamp)
        : $texto;
}

function eventoDetalheHora(mixed $valor): string
{
    $texto = trim((string) ($valor ?? ""));

    return $texto === ""
        ? ""
        : substr($texto, 0, 5);
}

function eventoDetalheImagem(array $evento): string
{
    $imagem = trim((string) ($evento["imagem"] ?? ""));

    if ($imagem === "") {
        return THEME_IMG . "sem-imagem.png";
    }

    return BASE_URL
        . "uploads/eventos/"
        . rawurlencode(basename($imagem));
}

function eventoDetalheUrl(array $evento): string
{
    $slug = trim(
        (string) ($evento["slug"] ?? "")
    );

    if ($slug !== "") {
        return BASE_URL
            . "eventos/"
            . rawurlencode($slug);
    }

    return BASE_URL
        . "eventos/detalhe.php?id="
        . (int) (
            $evento["idEvento"]
            ?? 0
        );
}

$pageStyles = [
    THEME_CSS
    . "eventos/detalhe.css?v="
    . VERSION
];

if (Auth::check()) {
    require_once __DIR__
        . "/../admin/includes/header.php";

    require_once __DIR__
        . "/../admin/includes/navbar.php";

    require_once __DIR__
        . "/../includes/sidebar.php";
} else {
    ?>
    <!doctype html>
    <html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta
            name="viewport"
            content="width=device-width, initial-scale=1"
        >

        <title>
            <?= eventoDetalheEscapar(
                (string) ($evento["titulo"] ?? "Evento")
            ); ?>
        </title>

        <link
            href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
            rel="stylesheet"
        >

        <link
            rel="stylesheet"
            href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
        >

        <link
            rel="stylesheet"
            href="<?= THEME_CSS ?>eventos/detalhe.css?v=<?= VERSION ?>"
        >
    </head>
    <body class="evento-publico-detalhe">
    <?php
}
?>

<?php if (Auth::check()): ?>
<div class="content evento-detalhe-page" id="content">
<?php else: ?>
<div class="evento-detalhe-page">
<?php endif; ?>

    <div class="container py-4 py-lg-5">

        <?php if ($mensagemSucesso): ?>
            <div class="alert alert-success">
                <i class="fa-solid fa-circle-check me-1"></i>
                <?= eventoDetalheEscapar(
                    (string) $mensagemSucesso
                ); ?>
            </div>
        <?php endif; ?>

        <?php if ($mensagemErro): ?>
            <div class="alert alert-danger">
                <i class="fa-solid fa-circle-exclamation me-1"></i>
                <?= eventoDetalheEscapar(
                    (string) $mensagemErro
                ); ?>
            </div>
        <?php endif; ?>

        <div class="row g-4 g-xl-5">

            <div class="col-lg-7">

                <a
                    href="<?= Auth::check()
                        ? BASE_URL . "eventos/"
                        : BASE_URL; ?>"
                    class="btn btn-sm btn-outline-secondary mb-3"
                >
                    <i class="fa-solid fa-arrow-left me-1"></i>
                    Voltar aos eventos
                </a>

                <img
                    src="<?= eventoDetalheEscapar(
                        eventoDetalheImagem($evento)
                    ); ?>"
                    class="evento-detalhe-imagem"
                    alt="<?= eventoDetalheEscapar(
                        (string) $evento["titulo"]
                    ); ?>"
                >

                <div class="mt-4">
                    <div class="d-flex flex-wrap gap-2 mb-3">
                        <span class="badge text-bg-primary">
                            <?= eventoDetalheEscapar(
                                (string) ($evento["tipo"] ?? "Evento")
                            ); ?>
                        </span>

                        <?php if ($inscricaoAberta): ?>
                            <span class="badge text-bg-success">
                                Inscrições abertas
                            </span>
                        <?php else: ?>
                            <span class="badge text-bg-secondary">
                                Inscrições fechadas
                            </span>
                        <?php endif; ?>
                    </div>

                    <h1 class="evento-detalhe-titulo">
                        <?= eventoDetalheEscapar(
                            (string) $evento["titulo"]
                        ); ?>
                    </h1>

                    <?php if (
                        trim(
                            (string) (
                                $evento["descricao_curta"]
                                ?? ""
                            )
                        ) !== ""
                    ): ?>
                        <p class="lead text-muted">
                            <?= eventoDetalheEscapar(
                                (string) $evento["descricao_curta"]
                            ); ?>
                        </p>
                    <?php endif; ?>
                </div>

                <section class="evento-detalhe-descricao mt-4">
                    <h2 class="h4 fw-bold">
                        Sobre o evento
                    </h2>

                    <?php if (
                        trim(
                            (string) ($evento["descricao"] ?? "")
                        ) !== ""
                    ): ?>
                        <div class="text-body-secondary">
                            <?= nl2br(
                                eventoDetalheEscapar(
                                    (string) $evento["descricao"]
                                )
                            ); ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">
                            Nenhuma descrição adicional foi informada.
                        </p>
                    <?php endif; ?>
                </section>
            </div>

            <div class="col-lg-5">

                <div
                    class="card border-0 shadow-sm
                        evento-detalhe-resumo"
                >
                    <div class="card-body p-4">

                        <h2 class="h5 fw-bold mb-4">
                            Informações
                        </h2>

                        <div class="evento-detalhe-info">

                            <div>
                                <span class="evento-detalhe-icone">
                                    <i
                                        class="fa-regular
                                            fa-calendar"
                                    ></i>
                                </span>

                                <div>
                                    <small>Data</small>
                                    <strong>
                                        <?= eventoDetalheData(
                                            $evento["data_inicio"]
                                                ?? ""
                                        ); ?>
                                    </strong>

                                    <?php
                                    $horaInicio = eventoDetalheHora(
                                        $evento["hora_inicio"] ?? ""
                                    );
                                    ?>
                                    <?php if ($horaInicio !== ""): ?>
                                        <span>
                                            <?= eventoDetalheEscapar(
                                                $horaInicio
                                            ); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div>
                                <span class="evento-detalhe-icone">
                                    <i
                                        class="fa-solid
                                            fa-location-dot"
                                    ></i>
                                </span>

                                <div>
                                    <small>Local</small>
                                    <strong>
                                        <?= eventoDetalheEscapar(
                                            (string) (
                                                $evento["local"]
                                                ?: "A definir"
                                            )
                                        ); ?>
                                    </strong>

                                    <span>
                                        <?= eventoDetalheEscapar(
                                            trim(
                                                (string) (
                                                    $evento["cidade"]
                                                    ?? ""
                                                )
                                                . (
                                                    !empty($evento["estado"])
                                                        ? "/"
                                                            . $evento["estado"]
                                                        : ""
                                                )
                                            )
                                        ); ?>
                                    </span>
                                </div>
                            </div>

                            <div>
                                <span class="evento-detalhe-icone">
                                    <i
                                        class="fa-solid
                                            fa-ticket"
                                    ></i>
                                </span>

                                <div>
                                    <small>Inscrição</small>
                                    <strong>
                                        <?= $valor > 0
                                            ? "R$ "
                                                . number_format(
                                                    $valor,
                                                    2,
                                                    ",",
                                                    "."
                                                )
                                            : "Gratuita"; ?>
                                    </strong>

                                    <?php if (
                                        $vagasDisponiveis
                                        !== PHP_INT_MAX
                                    ): ?>
                                        <span>
                                            <?= $vagasDisponiveis; ?>
                                            vaga<?= $vagasDisponiveis === 1
                                                ? ""
                                                : "s"; ?>
                                            disponível<?= $vagasDisponiveis === 1
                                                ? ""
                                                : "is"; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                        </div>

                        <hr class="my-4">

                        <?php if (
                            Auth::check()
                            && $inscricaoUsuario
                        ): ?>
                            <?php
                            $statusPagamento = (
                                string
                            ) (
                                $pagamentoUsuario["status"]
                                ?? $inscricaoUsuario["pagamento"]
                                ?? ""
                            );

                            $cancelamentoPendente =
                                $cancelamentoUsuario
                                && (string) (
                                    $cancelamentoUsuario[
                                        "status"
                                    ]
                                    ?? ""
                                ) === "Pendente";

                            $cancelamentoRejeitado =
                                $cancelamentoUsuario
                                && (string) (
                                    $cancelamentoUsuario[
                                        "status"
                                    ]
                                    ?? ""
                                ) === "Rejeitada";
                            ?>

                            <?php if (
                                $statusPagamento === "Pago"
                                || !$pagamentoObrigatorio
                            ): ?>
                                <div class="alert alert-success">
                                    <i
                                        class="fa-solid
                                            fa-circle-check me-1"
                                    ></i>
                                    Sua inscrição está confirmada.
                                </div>
                            <?php elseif (
                                $pagamentoUsuario
                                && (int) (
                                    $pagamentoUsuario["idPagamento"]
                                    ?? 0
                                ) > 0
                            ): ?>
                                <div class="alert alert-warning">
                                    Sua inscrição foi realizada,
                                    mas o pagamento ainda está pendente.
                                </div>

                                <a
                                    href="<?= BASE_URL ?>eventos/pagamento.php?id=<?= (int) $pagamentoUsuario["idPagamento"]; ?>"
                                    class="btn btn-primary
                                        btn-lg w-100 mb-3"
                                >
                                    <i
                                        class="fa-solid
                                            fa-credit-card me-1"
                                    ></i>
                                    Continuar pagamento
                                </a>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    Você já possui uma inscrição
                                    neste evento.
                                </div>
                            <?php endif; ?>

                            <?php if ($cancelamentoPendente): ?>
                                <div class="alert alert-warning mb-0">
                                    <i
                                        class="fa-regular
                                            fa-clock me-1"
                                    ></i>
                                    Sua solicitação de cancelamento
                                    está aguardando análise do
                                    Administrador.

                                    <hr>

                                    <strong>Motivo:</strong><br>
                                    <?= nl2br(
                                        eventoDetalheEscapar(
                                            (string) (
                                                $cancelamentoUsuario[
                                                    "motivo"
                                                ]
                                                ?? ""
                                            )
                                        )
                                    ); ?>
                                </div>

                            <?php else: ?>

                                <?php if ($cancelamentoRejeitado): ?>
                                    <div class="alert alert-danger">
                                        <strong>
                                            Solicitação anterior
                                            rejeitada.
                                        </strong>

                                        <?php if (
                                            !empty(
                                                $cancelamentoUsuario[
                                                    "observacao_admin"
                                                ]
                                            )
                                        ): ?>
                                            <div class="mt-2">
                                                <?= nl2br(
                                                    eventoDetalheEscapar(
                                                        (string) $cancelamentoUsuario[
                                                            "observacao_admin"
                                                        ]
                                                    )
                                                ); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (
                                    !empty(
                                        $regraCancelamento[
                                            "permitido"
                                        ]
                                    )
                                ): ?>
                                    <button
                                        type="button"
                                        class="btn
                                            btn-outline-danger
                                            w-100"
                                        data-bs-toggle="modal"
                                        data-bs-target="#modalCancelarInscricao"
                                    >
                                        <i
                                            class="fa-solid
                                                fa-ban me-1"
                                        ></i>
                                        Cancelar inscrição
                                    </button>

                                    <small
                                        class="text-muted
                                            d-block mt-2"
                                    >
                                        A solicitação pode ser
                                        enviada até
                                        <strong>
                                            <?= eventoDetalheEscapar(
                                                (string) (
                                                    $regraCancelamento[
                                                        "limite_formatado"
                                                    ]
                                                    ?? ""
                                                )
                                            ); ?>
                                        </strong>.
                                        O cancelamento só será
                                        efetivado após análise
                                        do Administrador.
                                    </small>
                                <?php else: ?>
                                    <div
                                        class="alert
                                            alert-secondary mb-0"
                                    >
                                        <i
                                            class="fa-solid
                                                fa-circle-info me-1"
                                        ></i>
                                        <?= eventoDetalheEscapar(
                                            (string) (
                                                $regraCancelamento[
                                                    "mensagem"
                                                ]
                                                ?? "O cancelamento não está disponível."
                                            )
                                        ); ?>
                                    </div>
                                <?php endif; ?>

                            <?php endif; ?>

                        <?php elseif (!$inscricaoAberta): ?>
                            <div class="alert alert-secondary mb-0">
                                <i
                                    class="fa-solid
                                        fa-circle-info me-1"
                                ></i>

                                <?= eventoDetalheEscapar(
                                    $motivoInscricaoFechada
                                        !== ""
                                        ? $motivoInscricaoFechada
                                        : "As inscrições estão fechadas."
                                ); ?>
                            </div>

                        <?php elseif (!Auth::check()): ?>
                            <a
                                href="<?= BASE_URL ?>inscricao/?evento=<?= $idEvento; ?>"
                                class="btn btn-primary btn-lg w-100"
                            >
                                <i
                                    class="fa-solid
                                        fa-right-to-bracket me-1"
                                ></i>
                                Inscrever-se
                            </a>

                        <?php else: ?>

                            <form
                                method="post"
                                action="<?= BASE_URL ?>inscricao/"
                            >
                                <input
                                    type="hidden"
                                    name="_token"
                                    value="<?= eventoDetalheEscapar(
                                        Session::csrf()
                                    ); ?>"
                                >

                                <input
                                    type="hidden"
                                    name="idEvento"
                                    value="<?= $idEvento; ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn btn-primary
                                        btn-lg w-100"
                                >
                                    <i
                                        class="fa-solid
                                            fa-clipboard-check me-1"
                                    ></i>
                                    Inscrever-se
                                </button>
                            </form>

                            <?php if ($pagamentoObrigatorio): ?>
                                <small
                                    class="text-muted d-block
                                        text-center mt-2"
                                >
                                    Após a inscrição você escolherá
                                    a forma de pagamento.
                                </small>
                            <?php endif; ?>

                        <?php endif; ?>

                    </div>
                </div>

            </div>

        </div>

    </div>
</div>

<?php if (
    Auth::check()
    && $inscricaoUsuario
    && !empty(
        $regraCancelamento[
            "permitido"
        ]
    )
): ?>
    <div
        class="modal fade"
        id="modalCancelarInscricao"
        tabindex="-1"
        aria-labelledby="modalCancelarInscricaoLabel"
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
                            id="modalCancelarInscricaoLabel"
                        >
                            Solicitar cancelamento
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
                                    fa-circle-info me-1"
                            ></i>
                            A inscrição não será
                            cancelada imediatamente.
                            O Administrador analisará
                            sua solicitação.
                        </div>

                        <input
                            type="hidden"
                            name="_token"
                            value="<?= eventoDetalheEscapar(
                                Session::csrf()
                            ); ?>"
                        >

                        <input
                            type="hidden"
                            name="idInscricao"
                            value="<?= (int) (
                                $inscricaoUsuario[
                                    "idInscricao"
                                ]
                                ?? 0
                            ); ?>"
                        >

                        <label
                            class="form-label"
                            for="motivoCancelamento"
                        >
                            Por qual motivo deseja
                            cancelar sua inscrição?
                        </label>

                        <textarea
                            class="form-control"
                            id="motivoCancelamento"
                            name="motivo"
                            rows="5"
                            minlength="10"
                            maxlength="2000"
                            required
                            placeholder="Explique o motivo do cancelamento."
                        ></textarea>

                        <small
                            class="text-muted
                                d-block mt-2"
                        >
                            Prazo para solicitação:
                            <?= eventoDetalheEscapar(
                                (string) (
                                    $regraCancelamento[
                                        "limite_formatado"
                                    ]
                                    ?? ""
                                )
                            ); ?>
                        </small>

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
                            class="btn btn-danger"
                        >
                            <i
                                class="fa-solid
                                    fa-paper-plane me-1"
                            ></i>
                            Enviar solicitação
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
<?php endif; ?>

<?php if (Auth::check()): ?>
    <?php
    require_once __DIR__
        . "/../admin/includes/footer.php";
    ?>
<?php else: ?>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
    ></script>
    </body>
    </html>
<?php endif; ?>
