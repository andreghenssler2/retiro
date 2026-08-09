<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/settings.php";

Middleware::auth();

$pageStyles = [
    THEME_CSS . "user/profile.css?v=" . VERSION
];

$tipoUsuario = (int) Auth::tipo();
$isAdmin = Auth::isAdmin();

$perfilNome = match ($tipoUsuario) {
    1 => "Administrador",
    2 => "Moderador",
    3 => "Participante",
    default => "Usuário"
};

require_once __DIR__ . "/../admin/includes/header.php";
require_once __DIR__ . "/../admin/includes/navbar.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<div class="content user-profile-page" id="content">
    <div class="container-fluid">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">
                    <i class="fa-solid fa-gear text-primary me-2"></i>
                    Configurações
                </h2>

                <p class="text-muted mb-0">
                    Acesse as configurações e recursos disponíveis para sua conta.
                </p>
            </div>

            <span class="badge rounded-pill text-bg-primary fs-6">
                <?= htmlspecialchars($perfilNome, ENT_QUOTES, "UTF-8"); ?>
            </span>
        </div>

        <div class="row g-4">

            <div class="col-md-6 col-xl-4">
                <a
                    href="<?= BASE_URL ?>user/index.php"
                    class="card border-0 shadow-sm profile-config-card h-100 text-decoration-none"
                >
                    <div class="card-body p-4">
                        <div class="profile-config-icon text-primary bg-primary-subtle">
                            <i class="fa-solid fa-user"></i>
                        </div>

                        <div class="mt-4">
                            <h5 class="text-body mb-2">Meu Perfil</h5>

                            <p class="text-muted mb-0">
                                Atualize seus dados pessoais, endereço, foto e senha.
                            </p>
                        </div>

                        <div class="profile-config-link text-primary mt-4">
                            Acessar perfil
                            <i class="fa-solid fa-arrow-right ms-1"></i>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-xl-4">
                <a
                    href="<?= BASE_URL ?>calendar/"
                    class="card border-0 shadow-sm profile-config-card h-100 text-decoration-none"
                >
                    <div class="card-body p-4">
                        <div class="profile-config-icon text-success bg-success-subtle">
                            <i class="fa-solid fa-calendar-days"></i>
                        </div>

                        <div class="mt-4">
                            <h5 class="text-body mb-2">Calendário</h5>

                            <p class="text-muted mb-0">
                                <?php if ($isAdmin): ?>
                                    Consulte todos os eventos cadastrados no sistema.
                                <?php else: ?>
                                    Consulte os eventos em que você possui inscrição.
                                <?php endif; ?>
                            </p>
                        </div>

                        <div class="profile-config-link text-success mt-4">
                            Abrir calendário
                            <i class="fa-solid fa-arrow-right ms-1"></i>
                        </div>
                    </div>
                </a>
            </div>

            <div class="col-md-6 col-xl-4">
                <a
                    href="<?= BASE_URL ?>user/certificados.php"
                    class="card border-0 shadow-sm profile-config-card h-100 text-decoration-none"
                >
                    <div class="card-body p-4">
                        <div class="profile-config-icon text-info bg-info-subtle">
                            <i class="fa-solid fa-certificate"></i>
                        </div>

                        <div class="mt-4">
                            <h5 class="text-body mb-2">
                                Meus Certificados
                            </h5>

                            <p class="text-muted mb-0">
                                Consulte os certificados disponíveis no sistema.
                            </p>
                        </div>

                        <div class="profile-config-link text-info-emphasis mt-4">
                            Ver certificados
                            <i class="fa-solid fa-arrow-right ms-1"></i>
                        </div>
                    </div>
                </a>
            </div>

            <?php if ($isAdmin): ?>
                <div class="col-md-6 col-xl-4">
                    <a
                        href="<?= BASE_URL ?>user/permissoes.php"
                        class="card border-0 shadow-sm profile-config-card h-100 text-decoration-none"
                    >
                        <div class="card-body p-4">
                            <div class="profile-config-icon text-danger bg-danger-subtle">
                                <i class="fa-solid fa-user-shield"></i>
                            </div>

                            <div class="mt-4">
                                <h5 class="text-body mb-2">
                                    Permissões dos usuários
                                </h5>

                                <p class="text-muted mb-0">
                                    Defina quem será Administrador, Moderador ou Usuário.
                                </p>
                            </div>

                            <div class="profile-config-link text-danger mt-4">
                                Administrar permissões
                                <i class="fa-solid fa-arrow-right ms-1"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="col-md-6 col-xl-4">
                    <a
                        href="<?= BASE_URL ?>user/atividades.php"
                        class="card border-0 shadow-sm profile-config-card h-100 text-decoration-none"
                    >
                        <div class="card-body p-4">
                            <div class="profile-config-icon text-warning bg-warning-subtle">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>

                            <div class="mt-4">
                                <h5 class="text-body mb-2">
                                    Atividades dos usuários
                                </h5>

                                <p class="text-muted mb-0">
                                    Consulte os acessos e ações realizados pelos usuários autenticados.
                                </p>
                            </div>

                            <div class="profile-config-link text-warning-emphasis mt-4">
                                Consultar atividades
                                <i class="fa-solid fa-arrow-right ms-1"></i>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endif; ?>

        </div>

        <div class="alert alert-info mt-4 mb-0">
            <i class="fa-solid fa-circle-info me-1"></i>

            <?php if ($isAdmin): ?>
                Como administrador, você possui acesso ao perfil,
                calendário geral, certificados, permissões e histórico de atividades dos usuários.
            <?php else: ?>
                Os recursos exibidos nesta página respeitam as permissões do seu perfil.
            <?php endif; ?>
        </div>

    </div>
</div>

<?php
require_once __DIR__ . "/../admin/includes/footer.php";
?>
