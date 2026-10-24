<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/settings.php";

Session::start();
Auth::requireLogin();

$pageStyles = [
    THEME_CSS . "eventos/listagem.css?v=" . VERSION
];

$eventoModel = new Evento();
$eventos = $eventoModel->listarDisponiveis();

function eventosListaEscapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function eventosListaUrl(array $evento): string
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

function eventosListaImagem(array $evento): string
{
    $imagem = trim((string) ($evento["imagem"] ?? ""));

    return $imagem !== ""
        ? BASE_URL
            . "uploads/eventos/"
            . rawurlencode(basename($imagem))
        : THEME_IMG . "sem-imagem.png";
}

require_once __DIR__
    . "/../admin/includes/header.php";
require_once __DIR__
    . "/../admin/includes/navbar.php";
require_once __DIR__
    . "/../includes/sidebar.php";
?>

<div class="content" id="content">
    <div class="container-fluid">

        <div class="mb-4">
            <h2 class="fw-bold mb-1">
                <i
                    class="fa-solid
                        fa-calendar-days
                        text-primary me-2"
                ></i>
                Eventos
            </h2>

            <p class="text-muted mb-0">
                Confira todos os eventos disponíveis
                para inscrição.
            </p>
        </div>

        <?php if ($eventos === []): ?>
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

                    <h4>Nenhum evento disponível</h4>
                </div>
            </div>
        <?php else: ?>

            <div class="row g-4">
                <?php foreach ($eventos as $evento): ?>
                    <?php
                    $valor = (float) (
                        $evento["valor_inscricao"]
                        ?? $evento["valor"]
                        ?? 0
                    );

                    $hora = trim(
                        (string) (
                            $evento["hora_inicio"]
                            ?? ""
                        )
                    );
                    ?>

                    <div
                        class="col-12
                            col-md-6 col-xl-4"
                    >
                        <a
                            href="<?= eventosListaEscapar(
                                eventosListaUrl($evento)
                            ); ?>"
                            class="text-decoration-none
                                text-reset"
                        >
                            <article
                                class="card border-0
                                    shadow-sm h-100
                                    overflow-hidden"
                            >
                                <img
                                    src="<?= eventosListaEscapar(
                                        eventosListaImagem($evento)
                                    ); ?>"
                                    alt="<?= eventosListaEscapar(
                                        (string) (
                                            $evento["titulo"]
                                            ?? "Evento"
                                        )
                                    ); ?>"
                                    style="
                                        width:100%;
                                        height:220px;
                                        object-fit:cover;
                                    "
                                >

                                <div class="card-body p-4">
                                    <span
                                        class="badge
                                            text-bg-primary
                                            mb-2"
                                    >
                                        <?= eventosListaEscapar(
                                            (string) (
                                                $evento["tipo"]
                                                ?? "Evento"
                                            )
                                        ); ?>
                                    </span>

                                    <h3 class="h5 fw-bold">
                                        <?= eventosListaEscapar(
                                            (string) (
                                                $evento["titulo"]
                                                ?? ""
                                            )
                                        ); ?>
                                    </h3>

                                    <?php if (
                                        !empty(
                                            $evento[
                                                "descricao_curta"
                                            ]
                                        )
                                    ): ?>
                                        <p class="text-muted">
                                            <?= eventosListaEscapar(
                                                (string) $evento[
                                                    "descricao_curta"
                                                ]
                                            ); ?>
                                        </p>
                                    <?php endif; ?>

                                    <div
                                        class="small
                                            text-body-secondary"
                                    >
                                        <p class="mb-2">
                                            <i
                                                class="fa-regular
                                                    fa-calendar
                                                    me-1"
                                            ></i>

                                            <?= !empty(
                                                $evento[
                                                    "data_inicio"
                                                ]
                                            )
                                                ? date(
                                                    "d/m/Y",
                                                    strtotime(
                                                        (string) $evento[
                                                            "data_inicio"
                                                        ]
                                                    )
                                                )
                                                : "A definir"; ?>

                                            <?= $hora !== ""
                                                ? " às "
                                                    . substr(
                                                        $hora,
                                                        0,
                                                        5
                                                    )
                                                : ""; ?>
                                        </p>

                                        <p class="mb-2">
                                            <i
                                                class="fa-solid
                                                    fa-location-dot
                                                    me-1"
                                            ></i>

                                            <?= eventosListaEscapar(
                                                trim(
                                                    (string) (
                                                        $evento["local"]
                                                        ?? ""
                                                    )
                                                    . (
                                                        !empty(
                                                            $evento["cidade"]
                                                        )
                                                            ? " — "
                                                                . $evento["cidade"]
                                                            : ""
                                                    )
                                                )
                                            ); ?>
                                        </p>

                                        <p class="mb-0 fw-semibold">
                                            <i
                                                class="fa-solid
                                                    fa-ticket me-1"
                                            ></i>

                                            <?= $valor > 0
                                                ? "R$ "
                                                    . number_format(
                                                        $valor,
                                                        2,
                                                        ",",
                                                        "."
                                                    )
                                                : "Gratuito"; ?>
                                        </p>
                                    </div>

                                    <div class="mt-4">
                                        <span
                                            class="btn
                                                btn-primary w-100"
                                        >
                                            Ver detalhes
                                        </span>
                                    </div>
                                </div>
                            </article>
                        </a>
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
