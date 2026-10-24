<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/settings.php";

Session::start();
Auth::requireLogin();

/*
 * Não precisamos receber o ID do usuário pela URL.
 * A identificação é feita pela sessão autenticada.
 */
if (!empty($_GET)) {
    header("Location: " . BASE_URL . "my/");
    exit;
}

$usuarioLogado = Auth::user() ?? [];

$nomeCompleto = trim(
    (string) ($usuarioLogado["nome"] ?? "Usuário")
);

$nomes = preg_split(
    "/\s+/",
    $nomeCompleto
) ?: [];

$primeiroNome = (string) ($nomes[0] ?? "Usuário");

$tipoUsuario = (int) (
    $usuarioLogado["tipo"] ?? 0
);

$perfilNome = match ($tipoUsuario) {
    1 => "Administrador",
    2 => "Moderador",
    3 => "Participante",
    default => "Usuário"
};

$hora = (int) date("G");

$saudacao = match (true) {
    $hora < 12 => "Bom dia",
    $hora < 18 => "Boa tarde",
    default => "Boa noite"
};

function myEscapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
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

        <div
            class="d-flex flex-wrap
                justify-content-between
                align-items-center gap-3 mb-4"
        >
            <div>
                <h2 class="fw-bold mb-1">
                    <i
                        class="fa-solid fa-house-user
                            text-primary me-2"
                    ></i>

                    <?= myEscapar($saudacao); ?>,
                    <?= myEscapar($primeiroNome); ?>!
                </h2>

                <p class="text-muted mb-0">
                    Bem-vindo à sua área do usuário.
                </p>
            </div>

            <span
                class="badge rounded-pill
                    text-bg-primary fs-6"
            >
                <i
                    class="fa-solid
                        fa-id-badge me-1"
                ></i>

                <?= myEscapar($perfilNome); ?>
            </span>
        </div>

        <div class="row g-4">

            <div class="col-md-6 col-xl-4">
                <a
                    href="<?= BASE_URL ?>user/index.php"
                    class="text-decoration-none text-reset"
                >
                    <div
                        class="card shadow-sm
                            border-0 h-100"
                    >
                        <div class="card-body p-4">

                            <div
                                class="d-flex
                                    align-items-start gap-3"
                            >
                                <div
                                    class="bg-primary-subtle
                                        text-primary rounded-3
                                        p-3 fs-4"
                                >
                                    <i
                                        class="fa-solid
                                            fa-user-gear"
                                    ></i>
                                </div>

                                <div>
                                    <h5 class="fw-bold mb-2">
                                        Meu Perfil
                                    </h5>

                                    <p
                                        class="text-muted mb-0"
                                    >
                                        Atualize seus dados,
                                        endereço, foto e senha.
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-xl-4">
                <a
                    href="<?= BASE_URL ?>user/eventos.php"
                    class="text-decoration-none text-reset"
                >
                    <div
                        class="card shadow-sm
                            border-0 h-100"
                    >
                        <div class="card-body p-4">

                            <div
                                class="d-flex
                                    align-items-start gap-3"
                            >
                                <div
                                    class="bg-warning-subtle
                                        text-warning-emphasis
                                        rounded-3
                                        p-3 fs-4"
                                >
                                    <i
                                        class="fa-solid
                                            fa-clipboard-check"
                                    ></i>
                                </div>

                                <div>
                                    <h5 class="fw-bold mb-2">
                                        Meus Eventos
                                    </h5>

                                    <p class="text-muted mb-0">
                                        Consulte suas inscrições,
                                        pagamentos e solicitações
                                        de cancelamento.
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </a>
            </div>
            <div class="col-md-6 col-xl-4">
                <a
                    href="<?= BASE_URL ?>calendar/"
                    class="text-decoration-none text-reset"
                >
                    <div
                        class="card shadow-sm
                            border-0 h-100"
                    >
                        <div class="card-body p-4">

                            <div
                                class="d-flex
                                    align-items-start gap-3"
                            >
                                <div
                                    class="bg-success-subtle
                                        text-success rounded-3
                                        p-3 fs-4"
                                >
                                    <i
                                        class="fa-solid
                                            fa-calendar-days"
                                    ></i>
                                </div>

                                <div>
                                    <h5 class="fw-bold mb-2">
                                        Calendário
                                    </h5>

                                    <p
                                        class="text-muted mb-0"
                                    >
                                        Consulte os eventos
                                        disponíveis para sua
                                        conta.
                                    </p>
                                </div>
                            </div>

                        </div>
                    </div>
                </a>
            </div>

            <?php if (!Auth::isAdmin()): ?>
                <div class="col-md-6 col-xl-4">
                    <a
                        href="<?= BASE_URL ?>user/certificados.php"
                        class="text-decoration-none text-reset"
                    >
                        <div
                            class="card shadow-sm
                                border-0 h-100"
                        >
                            <div class="card-body p-4">

                                <div
                                    class="d-flex
                                        align-items-start gap-3"
                                >
                                    <div
                                        class="bg-info-subtle
                                            text-info rounded-3
                                            p-3 fs-4"
                                    >
                                        <i
                                            class="fa-solid
                                                fa-certificate"
                                        ></i>
                                    </div>

                                    <div>
                                        <h5 class="fw-bold mb-2">
                                            Meus Certificados
                                        </h5>

                                        <p
                                            class="text-muted mb-0"
                                        >
                                            Consulte e baixe
                                            seus certificados.
                                        </p>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </a>
                </div>
            <?php endif; ?>

        </div>

    </div>
</div>

<?php
require_once __DIR__
    . "/../admin/includes/footer.php";
?>
