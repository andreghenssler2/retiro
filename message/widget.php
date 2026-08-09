<?php

declare(strict_types=1);

if (!class_exists("Notificacao")) {
    require_once __DIR__ . "/../mod/auth/Notificacao.php";
}

$idMessageWidget = "messageWidget" . bin2hex(random_bytes(3));
?>

<link
    rel="stylesheet"
    href="<?= THEME_CSS ?>message/message.css?v=<?= VERSION; ?>"
>

<div
    class="dropdown message-widget"
    data-message-widget
    data-widget-id="<?= htmlspecialchars(
        $idMessageWidget,
        ENT_QUOTES,
        "UTF-8"
    ); ?>"
>
    <button
        type="button"
        class="icon-button message-widget-botao"
        data-bs-toggle="dropdown"
        data-bs-auto-close="outside"
        aria-expanded="false"
        aria-label="Notificações"
    >
        <i class="fa-solid fa-bell"></i>

        <span
            class="badge rounded-pill bg-danger message-widget-badge"
            hidden
        >
            0
        </span>
    </button>

    <div
        class="dropdown-menu dropdown-menu-end shadow message-widget-menu"
    >
        <div class="message-widget-cabecalho">
            <strong>Notificações</strong>

            <button
                type="button"
                class="btn btn-sm btn-link text-decoration-none p-0 message-widget-marcar-todas"
                disabled
            >
                Marcar todas como lidas
            </button>
        </div>

        <div class="message-widget-lista">
            <div class="message-widget-carregando">
                <span
                    class="spinner-border spinner-border-sm me-1"
                    aria-hidden="true"
                ></span>
                Carregando...
            </div>
        </div>

        <div class="message-widget-rodape">
            <a
                href="<?= BASE_URL ?>message/"
                class="text-decoration-none fw-semibold"
            >
                Ver todas as notificações
            </a>
        </div>
    </div>
</div>

<script>
window.RETIRO_MESSAGE = window.RETIRO_MESSAGE || <?= json_encode(
    [
        "listarUrl" => BASE_URL . "message/ajax/listar.php",
        "marcarTodasUrl" => BASE_URL
            . "message/ajax/marcar-todas.php",
        "abrirUrl" => BASE_URL . "message/abrir.php",
        "csrf" => Session::csrf()
    ],
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
); ?>;
</script>

<script
    src="<?= THEME_JS ?>message/message.js?v=<?= VERSION; ?>"
    defer
></script>
