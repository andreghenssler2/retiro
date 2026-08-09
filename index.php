<?php

declare(strict_types=1);

require_once __DIR__ . "/config/settings.php";

Session::start();

$eventoModel = new Evento();
$eventos = $eventoModel->listarDisponiveis();

$titulo = Title::getAtual();

function eventoPublicoEscapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function eventoPublicoData(?string $data): string
{
    $data = trim((string) $data);

    if ($data === "") {
        return "Data a definir";
    }

    $timestamp = strtotime($data);

    return $timestamp !== false
        ? date("d/m/Y", $timestamp)
        : $data;
}

function eventoPublicoHora(?string $hora): string
{
    $hora = trim((string) $hora);

    if ($hora === "") {
        return "";
    }

    return substr($hora, 0, 5);
}

/**
 * @return array{texto:string,classe:string}
 */
function eventoPublicoStatusInscricao(array $evento): array
{
    if ((int) ($evento["inscricao_aberta"] ?? 0) !== 1) {
        return [
            "texto" => "Inscrições fechadas",
            "classe" => "secondary"
        ];
    }

    $agora = time();

    $inicio = trim(
        (string) ($evento["inscricao_inicio"] ?? "")
    );

    if ($inicio !== "") {
        $inicioTs = strtotime($inicio);

        if ($inicioTs !== false && $inicioTs > $agora) {
            return [
                "texto" => "Inscrições em breve",
                "classe" => "warning"
            ];
        }
    }

    $fim = trim(
        (string) ($evento["inscricao_fim"] ?? "")
    );

    if ($fim !== "") {
        $fimTs = strtotime($fim);

        if ($fimTs !== false && $fimTs < $agora) {
            return [
                "texto" => "Inscrições encerradas",
                "classe" => "secondary"
            ];
        }
    }

    return [
        "texto" => "Inscrições abertas",
        "classe" => "success"
    ];
}

function eventoPublicoImagem(array $evento): string
{
    $imagem = trim(
        (string) ($evento["imagem"] ?? "")
    );

    if ($imagem === "") {
        return THEME_IMG . "sem-imagem.png";
    }

    return BASE_URL
        . "uploads/eventos/"
        . rawurlencode(basename($imagem));
}

function eventoPublicoValor(array $evento): string
{
    $valor = (float) (
        $evento["valor_inscricao"]
        ?? $evento["valor"]
        ?? 0
    );

    if ($valor <= 0) {
        return "Gratuito";
    }

    return "R$ "
        . number_format(
            $valor,
            2,
            ",",
            "."
        );
}

function eventoPublicoUrl(array $evento): string
{
    $slug = trim(
        (string) ($evento["slug"] ?? "")
    );

    return BASE_URL
        . "eventos/detalhe.php"
        . (
            $slug !== ""
                ? "?slug=" . rawurlencode($slug)
                : "?id=" . (int) ($evento["idEvento"] ?? 0)
        );
}

$areaUsuarioUrl = BASE_URL . "login/";

if (Auth::check()) {
    $areaUsuarioUrl = Auth::isAdmin()
        ? BASE_URL . "admin/dashboard/"
        : BASE_URL . "my/";
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <?php if ($titulo): ?>
        <?php
        HeaderHTML::metaTags(
            $titulo->getNome(),
            $titulo->getDescricao(),
            $titulo->getKeyword(),
            $titulo->getFavicon()
        );
        ?>
    <?php else: ?>
        <title>Eventos</title>
    <?php endif; ?>

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
        href="<?= THEME_CSS ?>eventos/listagem.css?v=<?= VERSION ?>"
    >
</head>

<body class="eventos-publico-body">

<header class="eventos-publico-topo">
    <div class="container py-3">
        <div
            class="d-flex flex-wrap
                justify-content-between
                align-items-center gap-3"
        >
            <a
                href="<?= BASE_URL ?>"
                class="eventos-publico-marca"
            >
                <span class="eventos-publico-marca-icone">
                    <i class="fa-solid fa-calendar-days"></i>
                </span>

                <span>
                    <?= eventoPublicoEscapar(
                        $titulo
                            ? $titulo->getNome()
                            : "Eventos"
                    ); ?>
                </span>
            </a>

            <div class="d-flex flex-wrap gap-2">
                <?php if (Auth::check()): ?>
                    <a
                        href="<?= BASE_URL ?>eventos/"
                        class="btn btn-outline-primary"
                    >
                        <i class="fa-solid fa-calendar me-1"></i>
                        Eventos
                    </a>
                <?php endif; ?>

                <a
                    href="<?= $areaUsuarioUrl ?>"
                    class="btn btn-primary"
                >
                    <i
                        class="fa-solid
                            <?= Auth::check()
                                ? "fa-user"
                                : "fa-right-to-bracket"; ?>
                            me-1"
                    ></i>

                    <?= Auth::check()
                        ? "Minha área"
                        : "Entrar"; ?>
                </a>
            </div>
        </div>
    </div>
</header>

<main>
    <section class="eventos-publico-hero">
        <div class="container">
            <div class="eventos-publico-hero-conteudo">
                <span class="eventos-publico-kicker">
                    Próximos eventos
                </span>

                <h1>
                    Encontre o próximo evento
                    para participar
                </h1>

                <p>
                    Confira os eventos ativos cadastrados
                    no sistema, suas datas, locais e a
                    situação das inscrições.
                </p>
            </div>
        </div>
    </section>

    <section class="container py-5">

        <div
            class="d-flex flex-wrap
                justify-content-between
                align-items-end gap-3 mb-4"
        >
            <div>
                <h2 class="fw-bold mb-1">
                    Eventos disponíveis
                </h2>

                <p class="text-muted mb-0">
                    <?= count($eventos); ?>
                    evento<?= count($eventos) === 1
                        ? ""
                        : "s"; ?>
                    disponível<?= count($eventos) === 1
                        ? ""
                        : "is"; ?>.
                </p>
            </div>

            <?php if (Auth::check()): ?>
                <a
                    href="<?= BASE_URL ?>eventos/"
                    class="btn btn-outline-primary"
                >
                    Ver área de eventos
                    <i
                        class="fa-solid
                            fa-arrow-right ms-1"
                    ></i>
                </a>
            <?php endif; ?>
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

                    <h4>
                        Nenhum evento disponível
                    </h4>

                    <p class="text-muted mb-0">
                        Novos eventos aparecerão
                        aqui assim que forem
                        cadastrados e ativados.
                    </p>
                </div>
            </div>
        <?php else: ?>

            <div class="row g-4">
                <?php foreach ($eventos as $evento): ?>
                    <?php
                    $statusInscricao =
                        eventoPublicoStatusInscricao(
                            $evento
                        );

                    $horaInicio =
                        eventoPublicoHora(
                            $evento["hora_inicio"]
                                ?? ""
                        );

                    $local = trim(
                        (string) (
                            $evento["local"]
                            ?? ""
                        )
                    );

                    $cidade = trim(
                        (string) (
                            $evento["cidade"]
                            ?? ""
                        )
                    );

                    $estado = trim(
                        (string) (
                            $evento["estado"]
                            ?? ""
                        )
                    );
                    ?>

                    <div
                        class="col-12 col-md-6 col-xl-4"
                    >
                        <article
                            class="card
                                evento-publico-card
                                border-0 shadow-sm h-100"
                        >
                            <img
                                src="<?= eventoPublicoEscapar(
                                    eventoPublicoImagem(
                                        $evento
                                    )
                                ); ?>"
                                class="evento-publico-imagem"
                                alt="<?= eventoPublicoEscapar(
                                    (string) (
                                        $evento["titulo"]
                                        ?? "Evento"
                                    )
                                ); ?>"
                            >

                            <div
                                class="card-body
                                    d-flex flex-column p-4"
                            >
                                <div
                                    class="d-flex
                                        justify-content-between
                                        align-items-start
                                        gap-2 mb-3"
                                >
                                    <span
                                        class="badge
                                            text-bg-light
                                            border"
                                    >
                                        <?= eventoPublicoEscapar(
                                            (string) (
                                                $evento["tipo"]
                                                ?? "Evento"
                                            )
                                        ); ?>
                                    </span>

                                    <span
                                        class="badge
                                            text-bg-<?= eventoPublicoEscapar(
                                                $statusInscricao["classe"]
                                            ); ?>"
                                    >
                                        <?= eventoPublicoEscapar(
                                            $statusInscricao["texto"]
                                        ); ?>
                                    </span>
                                </div>

                                <h3
                                    class="h5 fw-bold mb-2"
                                >
                                    <?= eventoPublicoEscapar(
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
                                    <p
                                        class="text-muted
                                            evento-publico-descricao"
                                    >
                                        <?= eventoPublicoEscapar(
                                            (string) $evento[
                                                "descricao_curta"
                                            ]
                                        ); ?>
                                    </p>
                                <?php endif; ?>

                                <div
                                    class="evento-publico-detalhes"
                                >
                                    <div>
                                        <i
                                            class="fa-regular
                                                fa-calendar"
                                        ></i>

                                        <span>
                                            <?= eventoPublicoData(
                                                $evento[
                                                    "data_inicio"
                                                ] ?? ""
                                            ); ?>

                                            <?php if (
                                                $horaInicio !== ""
                                            ): ?>
                                                às
                                                <?= eventoPublicoEscapar(
                                                    $horaInicio
                                                ); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>

                                    <?php if (
                                        $local !== ""
                                        || $cidade !== ""
                                    ): ?>
                                        <div>
                                            <i
                                                class="fa-solid
                                                    fa-location-dot"
                                            ></i>

                                            <span>
                                                <?php if (
                                                    $local !== ""
                                                ): ?>
                                                    <?= eventoPublicoEscapar(
                                                        $local
                                                    ); ?>
                                                <?php endif; ?>

                                                <?php if (
                                                    $local !== ""
                                                    && $cidade !== ""
                                                ): ?>
                                                    —
                                                <?php endif; ?>

                                                <?= eventoPublicoEscapar(
                                                    $cidade
                                                ); ?>

                                                <?php if (
                                                    $estado !== ""
                                                ): ?>
                                                    /<?= eventoPublicoEscapar(
                                                        $estado
                                                    ); ?>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>

                                    <div>
                                        <i
                                            class="fa-solid
                                                fa-ticket"
                                        ></i>

                                        <span>
                                            <?= eventoPublicoEscapar(
                                                eventoPublicoValor(
                                                    $evento
                                                )
                                            ); ?>
                                        </span>
                                    </div>

                                    <?php if (
                                        !empty(
                                            $evento["vagas"]
                                        )
                                    ): ?>
                                        <div>
                                            <i
                                                class="fa-solid
                                                    fa-users"
                                            ></i>

                                            <span>
                                                <?= (int) $evento[
                                                    "vagas"
                                                ]; ?>
                                                vagas
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="mt-auto pt-4">
                                    <a
                                        href="<?= eventoPublicoEscapar(
                                            eventoPublicoUrl($evento)
                                        ); ?>"
                                        class="btn
                                            btn-primary w-100"
                                    >
                                        Ver detalhes
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>
            </div>

        <?php endif; ?>

    </section>
</main>

<footer class="eventos-publico-footer">
    <div class="container py-4 text-center">
        <?php FooterHTML::versao(); ?>
    </div>
</footer>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
></script>

</body>
</html>
