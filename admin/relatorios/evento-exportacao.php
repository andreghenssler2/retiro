<?php

declare(strict_types=1);

require_once __DIR__
    . "/../../config/settings.php";

Session::start();
Middleware::moderador();

$eventos = [];
$erro = "";

try {
    $eventos = (new Evento())->listar();
} catch (Throwable $exception) {
    $erro = "Não foi possível carregar os eventos.";
}

function relEventoEscapar(
    mixed $valor
): string {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
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
            class="d-flex flex-column
                flex-lg-row
                justify-content-between
                align-items-lg-center
                gap-3 mb-4"
        >
            <div>
                <h2 class="fw-bold mb-1">
                    <i
                        class="fa-solid
                            fa-file-export
                            text-primary me-2"
                    ></i>
                    Exportação de eventos
                </h2>

                <p class="text-muted mb-0">
                    Gere listas, fichas individuais,
                    planilha Excel e relatório de
                    Saúde e Acessibilidade.
                </p>
            </div>

            <a
                href="<?= BASE_URL ?>admin/relatorios/"
                class="btn btn-outline-secondary"
            >
                <i
                    class="fa-solid
                        fa-arrow-left me-1"
                ></i>
                Central de relatórios
            </a>
        </div>

        <?php if ($erro !== ""): ?>
            <div class="alert alert-danger">
                <?= relEventoEscapar($erro); ?>
            </div>
        <?php endif; ?>

        <div
            class="card border-0
                shadow-sm mb-4"
        >
            <div class="card-body p-4">
                <form
                    method="get"
                    action="<?= BASE_URL ?>admin/relatorios/evento-exportar.php"
                    target="_blank"
                    id="formRelatorioEvento"
                >
                    <div class="row g-3">

                        <div class="col-12">
                            <label
                                for="idEvento"
                                class="form-label fw-semibold"
                            >
                                Evento
                            </label>

                            <select
                                class="form-select"
                                name="evento"
                                id="idEvento"
                                required
                            >
                                <option value="">
                                    Selecione o evento
                                </option>

                                <?php foreach (
                                    $eventos
                                    as $evento
                                ): ?>
                                    <option
                                        value="<?= (int) $evento[
                                            "idEvento"
                                        ]; ?>"
                                    >
                                        <?= relEventoEscapar(
                                            $evento["titulo"]
                                            ?? "Evento"
                                        ); ?>

                                        <?php if (!empty(
                                            $evento["data_inicio"]
                                        )): ?>
                                            —
                                            <?= date(
                                                "d/m/Y",
                                                strtotime(
                                                    (string) $evento[
                                                        "data_inicio"
                                                    ]
                                                )
                                            ); ?>
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <div class="alert alert-info mt-4 mb-4">
                        <i
                            class="fa-solid
                                fa-circle-info me-1"
                        ></i>

                        Nas listas, participantes que
                        não tiverem pago até o prazo
                        configurado no evento serão
                        destacados como
                        <strong>Inscrição não confirmada</strong>.
                    </div>

                    <div class="row g-3">

                        <div class="col-md-6 col-xl-3">
                            <button
                                type="submit"
                                name="modo"
                                value="lista_pdf"
                                class="btn
                                    btn-danger
                                    w-100 h-100
                                    p-4 text-start"
                            >
                                <i
                                    class="fa-solid
                                        fa-file-pdf
                                        fs-3 d-block mb-3"
                                ></i>

                                <strong class="d-block">
                                    PDF — Lista
                                </strong>

                                <small>
                                    Lista de todos os inscritos,
                                    pagamento e confirmação.
                                </small>
                            </button>
                        </div>

                        <div class="col-md-6 col-xl-3">
                            <button
                                type="submit"
                                name="modo"
                                value="fichas_pdf"
                                class="btn
                                    btn-outline-danger
                                    w-100 h-100
                                    p-4 text-start"
                            >
                                <i
                                    class="fa-solid
                                        fa-file-circle-user
                                        fs-3 d-block mb-3"
                                ></i>

                                <strong class="d-block">
                                    PDF — Uma folha por pessoa
                                </strong>

                                <small>
                                    Ficha completa de cada
                                    participante inscrito.
                                </small>
                            </button>
                        </div>

                        <div class="col-md-6 col-xl-3">
                            <button
                                type="submit"
                                name="modo"
                                value="xlsx"
                                class="btn
                                    btn-outline-success
                                    w-100 h-100
                                    p-4 text-start"
                            >
                                <i
                                    class="fa-solid
                                        fa-file-excel
                                        fs-3 d-block mb-3"
                                ></i>

                                <strong class="d-block">
                                    Excel (.xlsx)
                                </strong>

                                <small>
                                    Uma linha por inscrição,
                                    com dados completos.
                                </small>
                            </button>
                        </div>

                        <div class="col-md-6 col-xl-3">
                            <button
                                type="submit"
                                name="modo"
                                value="saude_pdf"
                                class="btn
                                    btn-outline-primary
                                    w-100 h-100
                                    p-4 text-start"
                            >
                                <i
                                    class="fa-solid
                                        fa-heart-pulse
                                        fs-3 d-block mb-3"
                                ></i>

                                <strong class="d-block">
                                    PDF — Saúde e Acessibilidade
                                </strong>

                                <small>
                                    Somente as perguntas de saúde
                                    habilitadas para o evento.
                                </small>
                            </button>
                        </div>

                    </div>
                </form>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-md-6">
                <div
                    class="card border-0
                        shadow-sm h-100"
                >
                    <div class="card-body">
                        <h5 class="fw-bold">
                            <i
                                class="fa-solid
                                    fa-list-check
                                    text-primary me-1"
                            ></i>
                            Situação da inscrição
                        </h5>

                        <div class="small text-muted">
                            <div class="mb-2">
                                <span
                                    class="badge
                                        text-bg-success"
                                >
                                    Confirmada
                                </span>
                                Pagamento realizado dentro
                                do prazo ou evento gratuito.
                            </div>

                            <div class="mb-2">
                                <span
                                    class="badge
                                        text-bg-danger"
                                >
                                    Inscrição não confirmada
                                </span>
                                Não houve pagamento válido
                                até o prazo do evento.
                            </div>

                            <div>
                                <span
                                    class="badge
                                        text-bg-warning"
                                >
                                    Aguardando pagamento
                                </span>
                                O prazo ainda não encerrou.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div
                    class="card border-0
                        shadow-sm h-100"
                >
                    <div class="card-body">
                        <h5 class="fw-bold">
                            <i
                                class="fa-solid
                                    fa-shield-heart
                                    text-danger me-1"
                            ></i>
                            Saúde e Acessibilidade
                        </h5>

                        <p class="text-muted mb-0">
                            O PDF de saúde exibe somente
                            as perguntas habilitadas na
                            configuração daquele evento e
                            as respostas de cada inscrito.
                        </p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
require_once __DIR__
    . "/../includes/footer.php";
?>
