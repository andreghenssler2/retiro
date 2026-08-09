<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/settings.php";
require_once __DIR__ . "/CalendarExport.php";

Middleware::auth();

$tipoUsuario = Auth::tipo();
$administrador = Auth::isAdmin();

$perfilNome = match ($tipoUsuario) {
    1 => "Administrador",
    2 => "Moderador",
    3 => "Participante",
    default => "Usuário"
};

$descricaoPagina = $administrador
    ? "Consulte todos os eventos cadastrados no sistema."
    : "Consulte os eventos nos quais você possui inscrição.";

$calendarExportErro = "";
$calendarMigracaoPendente = false;
$calendarToken = "";
$calendarFeedUrl = "";
$calendarWebcalUrl = "";

try {
    $calendarToken = CalendarExport::garantirToken(
        $db,
        (int) (Auth::id() ?? 0)
    );
    $calendarFeedUrl = BASE_URL
        . "calendar/feed.php?token="
        . rawurlencode($calendarToken);
    $calendarWebcalUrl = (string) preg_replace(
        '/^https?:\/\//i',
        'webcal://',
        $calendarFeedUrl
    );
} catch (Throwable $erroExportacao) {
    $calendarMigracaoPendente = str_contains(
        strtolower($erroExportacao->getMessage()),
        "migração"
    );
    $calendarExportErro = $calendarMigracaoPendente
        ? "A exportação por URL ainda precisa ser ativada no banco de dados."
        : "Não foi possível preparar a URL dinâmica do calendário.";

    error_log(
        "Erro ao preparar exportação do calendário"
        . " | usuario="
        . (int) (Auth::id() ?? 0)
        . " | erro="
        . $erroExportacao->getMessage()
    );
}

$calendarHoje = new DateTimeImmutable(
    "today",
    new DateTimeZone("America/Sao_Paulo")
);
$calendarDataInicioPadrao = $calendarHoje->format("Y-m-d");
$calendarDataFimPadrao = $calendarHoje
    ->modify("+365 days")
    ->format("Y-m-d");

$pageStyles = [
    THEME_CSS
    . "calendar/calendar.css?v="
    . VERSION
];

$pageScripts = [
    "https://cdn.jsdelivr.net/npm/fullcalendar@6.1.21/index.global.min.js",
    "https://cdn.jsdelivr.net/npm/@fullcalendar/core@6.1.21/locales-all.global.min.js",
    THEME_JS
    . "calendar/calendar.js?v="
    . VERSION
];

function calendarEscapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

require_once __DIR__ . "/../admin/includes/header.php";
require_once __DIR__ . "/../admin/includes/navbar.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<div class="content calendar-page" id="content">
    <div class="container-fluid">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">
                    <i class="fa-solid fa-calendar-days text-primary me-2"></i>
                    Calendário de eventos
                </h2>

                <p class="text-muted mb-0">
                    <?= calendarEscapar($descricaoPagina); ?>
                </p>
            </div>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <button
                    type="button"
                    class="btn btn-outline-primary"
                    data-bs-toggle="modal"
                    data-bs-target="#calendarExportModal"
                >
                    <i class="fa-solid fa-file-export me-1"></i>
                    Exportar calendário
                </button>

                <span class="badge rounded-pill text-bg-light border fs-6">
                    <i class="fa-solid fa-user-shield me-1"></i>
                    <?= calendarEscapar($perfilNome); ?>
                </span>

                <span
                    class="badge rounded-pill text-bg-primary fs-6"
                    id="calendarTotal"
                >
                    0 eventos
                </span>
            </div>
        </div>

        <div
            class="alert alert-danger d-none"
            id="calendarErro"
            role="alert"
        >
            <i class="fa-solid fa-circle-exclamation me-1"></i>
            <span>
                Não foi possível carregar os eventos.
            </span>
        </div>

        <div class="card border-0 shadow-sm calendar-card">
            <div class="card-header bg-white py-3 d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div>
                    <h5 class="mb-1">
                        <?= $administrador
                            ? "Todos os eventos"
                            : "Meus eventos"; ?>
                    </h5>

                    <small class="text-muted">
                        Clique em um evento para visualizar os detalhes.
                    </small>
                </div>

                <div class="calendar-legenda">
                    <?php if ($administrador): ?>
                        <span>
                            <i class="calendar-legenda-cor bg-primary"></i>
                            Evento ativo
                        </span>

                        <span>
                            <i class="calendar-legenda-cor bg-secondary"></i>
                            Evento inativo
                        </span>
                    <?php else: ?>
                        <span>
                            <i class="calendar-legenda-cor bg-success"></i>
                            Confirmada
                        </span>

                        <span>
                            <i class="calendar-legenda-cor bg-warning"></i>
                            Pendente
                        </span>

                        <span>
                            <i class="calendar-legenda-cor bg-danger"></i>
                            Cancelada
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card-body position-relative">
                <div
                    class="calendar-loading d-none"
                    id="calendarLoading"
                    aria-live="polite"
                >
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                </div>

                <div
                    id="calendar"
                    data-events-url="<?= calendarEscapar(
                        BASE_URL . "calendar/eventos.php"
                    ); ?>"
                    data-admin="<?= $administrador ? "1" : "0"; ?>"
                ></div>
            </div>
        </div>

        <div class="alert alert-info mt-4 mb-0">
            <i class="fa-solid fa-circle-info me-1"></i>

            <?php if ($administrador): ?>
                O administrador visualiza eventos ativos e inativos.
            <?php else: ?>
                São apresentados os eventos relacionados à sua inscrição,
                inclusive registros pendentes ou cancelados.
            <?php endif; ?>
        </div>

    </div>
</div>

<div
    class="modal fade"
    id="calendarEventoModal"
    tabindex="-1"
    aria-labelledby="calendarEventoModalTitulo"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                        <h5
                            class="modal-title mb-0"
                            id="calendarEventoModalTitulo"
                        >
                            Evento
                        </h5>

                        <span
                            class="badge text-bg-primary"
                            id="calendarModalTipo"
                        ></span>
                    </div>

                    <small
                        class="text-muted"
                        id="calendarModalPeriodo"
                    ></small>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Fechar"
                ></button>
            </div>

            <div class="modal-body">
                <div class="row g-4">
                    <div class="col-md-7">
                        <div class="calendar-detalhe">
                            <span class="calendar-detalhe-icone">
                                <i class="fa-solid fa-location-dot"></i>
                            </span>

                            <div>
                                <small>Local</small>
                                <strong id="calendarModalLocal">-</strong>
                                <span id="calendarModalEndereco"></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-5">
                        <div class="calendar-detalhe">
                            <span class="calendar-detalhe-icone">
                                <i class="fa-solid fa-toggle-on"></i>
                            </span>

                            <div>
                                <small>Status do evento</small>
                                <strong id="calendarModalStatusEvento">-</strong>
                                <span id="calendarModalInscricaoAberta"></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($administrador): ?>
                        <div class="col-md-5">
                            <div class="calendar-detalhe">
                                <span class="calendar-detalhe-icone">
                                    <i class="fa-solid fa-users"></i>
                                </span>

                                <div>
                                    <small>Inscrições ativas</small>
                                    <strong id="calendarModalInscritos">0</strong>
                                    <span>Não inclui inscrições canceladas.</span>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="col-md-4">
                            <div class="calendar-detalhe">
                                <span class="calendar-detalhe-icone">
                                    <i class="fa-solid fa-clipboard-check"></i>
                                </span>

                                <div>
                                    <small>Inscrição</small>
                                    <strong id="calendarModalInscricaoStatus">-</strong>
                                    <span id="calendarModalInscricaoNumero"></span>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="calendar-detalhe">
                                <span class="calendar-detalhe-icone">
                                    <i class="fa-solid fa-credit-card"></i>
                                </span>

                                <div>
                                    <small>Pagamento</small>
                                    <strong id="calendarModalPagamento">-</strong>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4">
                            <div class="calendar-detalhe">
                                <span class="calendar-detalhe-icone">
                                    <i class="fa-solid fa-user-check"></i>
                                </span>

                                <div>
                                    <small>Presença</small>
                                    <strong id="calendarModalPresenca">-</strong>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="col-12">
                        <div class="calendar-descricao">
                            <small>Descrição</small>
                            <p class="mb-0" id="calendarModalDescricao">
                                Nenhuma descrição informada.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    data-bs-dismiss="modal"
                >
                    Fechar
                </button>

                <?php if ($administrador): ?>
                    <a
                        href="#"
                        class="btn btn-primary d-none"
                        id="calendarModalEditar"
                    >
                        <i class="fa-solid fa-pen-to-square me-1"></i>
                        Administrar evento
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


<div
    class="modal fade"
    id="calendarExportModal"
    tabindex="-1"
    aria-labelledby="calendarExportModalTitulo"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header">
                <div>
                    <h5 class="modal-title" id="calendarExportModalTitulo">
                        <i class="fa-solid fa-file-export text-primary me-1"></i>
                        Exportar calendário
                    </h5>
                    <small class="text-muted">
                        Assine uma URL dinâmica ou baixe uma cópia em formato ICS.
                    </small>
                </div>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="modal"
                    aria-label="Fechar"
                ></button>
            </div>

            <div class="modal-body p-4">
                <div
                    class="alert d-none"
                    id="calendarExportFeedback"
                    role="alert"
                ></div>

                <section class="calendar-export-section">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="calendar-export-icon">
                            <i class="fa-solid fa-link"></i>
                        </span>

                        <div>
                            <h5 class="mb-1">URL do calendário</h5>
                            <p class="text-muted mb-0">
                                O URL do calendário fornece um link dinâmico
                                para importar eventos para outros calendários.
                                Todo evento novo, alterado ou excluído no
                                calendário de origem será refletido nos outros
                                calendários.
                            </p>
                        </div>
                    </div>

                    <?php if ($calendarExportErro !== ""): ?>
                        <div class="alert alert-warning mb-0">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i>
                            <?= calendarEscapar($calendarExportErro); ?>
                            <?php if ($calendarMigracaoPendente): ?>
                                Execute o arquivo
                                <code>calendar/migracao-calendar-token.sql</code>
                                no banco de dados.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <label class="form-label fw-semibold" for="calendarFeedUrl">
                            URL para assinatura
                        </label>

                        <div class="input-group calendar-url-group">
                            <input
                                type="text"
                                class="form-control font-monospace"
                                id="calendarFeedUrl"
                                value="<?= calendarEscapar($calendarFeedUrl); ?>"
                                readonly
                                aria-readonly="true"
                            >

                            <button
                                type="button"
                                class="btn btn-outline-primary"
                                id="calendarCopyUrl"
                            >
                                <i class="fa-regular fa-copy me-1"></i>
                                Copiar URL
                            </button>
                        </div>

                        <div class="d-flex flex-wrap gap-2 mt-3">
                            <a
                                href="<?= calendarEscapar($calendarWebcalUrl); ?>"
                                class="btn btn-primary"
                                id="calendarAssinarUrl"
                            >
                                <i class="fa-solid fa-calendar-plus me-1"></i>
                                Assinar calendário
                            </a>

                            <button
                                type="button"
                                class="btn btn-outline-danger"
                                id="calendarRegenerarUrl"
                                data-url="<?= calendarEscapar(
                                    BASE_URL . "calendar/token.php"
                                ); ?>"
                                data-csrf="<?= calendarEscapar(Session::csrf()); ?>"
                            >
                                <i class="fa-solid fa-rotate me-1"></i>
                                Gerar nova URL
                            </button>
                        </div>

                        <div class="form-text mt-2">
                            Esta URL é privada. Quem possuir o endereço poderá
                            consultar os eventos autorizados para sua conta.
                            Gerar uma nova URL revoga imediatamente a anterior.
                            A frequência de atualização depende do aplicativo
                            de calendário utilizado.
                        </div>
                    <?php endif; ?>
                </section>

                <hr class="my-4">

                <section class="calendar-export-section">
                    <div class="d-flex align-items-start gap-3 mb-3">
                        <span class="calendar-export-icon">
                            <i class="fa-solid fa-download"></i>
                        </span>

                        <div>
                            <h5 class="mb-1">Exportação de calendário</h5>
                            <p class="text-muted mb-0">
                                A exportação de calendário permite criar uma
                                cópia de backup dos eventos, que pode ser
                                importada para outros calendários. As
                                atualizações feitas no calendário de origem não
                                serão refletidas nos outros calendários.
                            </p>
                        </div>
                    </div>

                    <form
                        method="get"
                        action="<?= calendarEscapar(
                            BASE_URL . "calendar/exportar.php"
                        ); ?>"
                        id="calendarExportForm"
                    >
                        <div class="row g-4">
                            <div class="col-lg-4">
                                <label
                                    class="form-label fw-semibold"
                                    for="calendarEventosExportar"
                                >
                                    Eventos a exportar
                                </label>

                                <select
                                    class="form-select"
                                    id="calendarEventosExportar"
                                    aria-label="Eventos a exportar"
                                    disabled
                                >
                                    <option selected>Todos os eventos</option>
                                </select>

                                <div class="form-text">
                                    <?= $administrador
                                        ? "Inclui todos os eventos visíveis ao administrador."
                                        : "Inclui somente os eventos vinculados às suas inscrições."; ?>
                                </div>
                            </div>

                            <div class="col-lg-8">
                                <fieldset>
                                    <legend class="form-label fw-semibold fs-6">
                                        Período
                                    </legend>

                                    <div class="calendar-period-options">
                                        <label class="calendar-period-option">
                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                name="periodo"
                                                value="semana"
                                            >
                                            <span>
                                                <strong>Esta semana</strong>
                                                <small>
                                                    De segunda-feira a domingo.
                                                </small>
                                            </span>
                                        </label>

                                        <label class="calendar-period-option">
                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                name="periodo"
                                                value="60dias"
                                                checked
                                            >
                                            <span>
                                                <strong>
                                                    Recentes e próximos 60 dias
                                                </strong>
                                                <small>
                                                    Inclui os últimos 30 dias e
                                                    os próximos 60 dias.
                                                </small>
                                            </span>
                                        </label>

                                        <label class="calendar-period-option">
                                            <input
                                                class="form-check-input"
                                                type="radio"
                                                name="periodo"
                                                value="personalizado"
                                                id="calendarPeriodoPersonalizado"
                                            >
                                            <span>
                                                <strong>Intervalo personalizado</strong>
                                                <small>
                                                    Escolha a data inicial e final.
                                                </small>
                                            </span>
                                        </label>
                                    </div>

                                    <div
                                        class="row g-3 mt-1 calendar-custom-range"
                                        id="calendarCustomRange"
                                    >
                                        <div class="col-md-6">
                                            <label
                                                class="form-label"
                                                for="calendarDataInicio"
                                            >
                                                Data inicial
                                            </label>
                                            <input
                                                type="date"
                                                class="form-control"
                                                id="calendarDataInicio"
                                                name="data_inicio"
                                                value="<?= calendarEscapar(
                                                    $calendarDataInicioPadrao
                                                ); ?>"
                                                disabled
                                            >
                                        </div>

                                        <div class="col-md-6">
                                            <label
                                                class="form-label"
                                                for="calendarDataFim"
                                            >
                                                Data final
                                            </label>
                                            <input
                                                type="date"
                                                class="form-control"
                                                id="calendarDataFim"
                                                name="data_fim"
                                                value="<?= calendarEscapar(
                                                    $calendarDataFimPadrao
                                                ); ?>"
                                                disabled
                                            >
                                        </div>
                                    </div>
                                </fieldset>
                            </div>
                        </div>
                    </form>
                </section>
            </div>

            <div class="modal-footer">
                <button
                    type="button"
                    class="btn btn-outline-secondary"
                    data-bs-dismiss="modal"
                >
                    Fechar
                </button>

                <button
                    type="submit"
                    class="btn btn-success"
                    form="calendarExportForm"
                >
                    <i class="fa-solid fa-file-arrow-down me-1"></i>
                    Exportar arquivo .ics
                </button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . "/../admin/includes/footer.php"; ?>
