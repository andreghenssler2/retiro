<?php

declare(strict_types=1);

Session::start();

if (!Auth::check() || Auth::isAdmin()) {
    return;
}

$caminhoAtualUserSidebar = str_replace(
    "\\",
    "/",
    (string) ($_SERVER["PHP_SELF"] ?? "")
);

$tipoUserSidebar = (int) Auth::tipo();

$perfilUserSidebar = match ($tipoUserSidebar) {
    2 => "Moderador",
    3 => "Participante",
    default => "Usuário"
};

$nomeUserSidebar = htmlspecialchars(
    (string) ($_SESSION["user"]["nome"] ?? "Usuário"),
    ENT_QUOTES | ENT_SUBSTITUTE,
    "UTF-8"
);

$userSidebarAtivo = static function (
    string $caminho
) use ($caminhoAtualUserSidebar): string {
    return str_contains(
        $caminhoAtualUserSidebar,
        $caminho
    )
        ? "active"
        : "";
};

$userSidebarPerfilAtivo = static function () use (
    $caminhoAtualUserSidebar
): string {
    return str_ends_with(
        $caminhoAtualUserSidebar,
        "/user/index.php"
    )
        ? "active"
        : "";
};
?>

<aside class="sidebar" id="sidebar">

    <div class="sidebar-user">

        <div class="avatar">
            <?= $_SESSION["user"]["foto"]
                ?? '<i class="fas fa-user"></i>'; ?>
        </div>

        <div class="info">
            <strong>
                <?= $nomeUserSidebar; ?>
            </strong>

            <small>
                <?= htmlspecialchars(
                    $perfilUserSidebar,
                    ENT_QUOTES | ENT_SUBSTITUTE,
                    "UTF-8"
                ); ?>
            </small>
        </div>

    </div>

    <ul class="sidebar-menu">

        <li>
            <a
                class="<?= $userSidebarAtivo("/my/"); ?>"
                href="<?= BASE_URL ?>my/"
            >
                <i class="fa-solid fa-house"></i>
                <span>Início</span>
            </a>
        </li>

        <li>
            <a
                class="<?= $userSidebarAtivo("/eventos/"); ?>"
                href="<?= BASE_URL ?>eventos/"
            >
                <i class="fa-solid fa-calendar-days"></i>
                <span>Eventos</span>
            </a>
        </li>

        <li>
            <a
                class="<?= $userSidebarAtivo(
                    "/user/eventos.php"
                ); ?>"
                href="<?= BASE_URL ?>user/eventos.php"
            >
                <i class="fa-solid fa-clipboard-check"></i>
                <span>Meus Eventos</span>
            </a>
        </li>

        <li>
            <a
                class="<?= $userSidebarPerfilAtivo(); ?>"
                href="<?= BASE_URL ?>user/index.php"
            >
                <i class="fa-solid fa-user"></i>
                <span>Meu Perfil</span>
            </a>
        </li>

        <li>
            <a
                class="<?= $userSidebarAtivo("/calendar/"); ?>"
                href="<?= BASE_URL ?>calendar/"
            >
                <i class="fa-regular fa-calendar"></i>
                <span>Meu Calendário</span>
            </a>
        </li>

        <li>
            <a
                class="<?= $userSidebarAtivo(
                    "/user/certificados.php"
                ); ?>"
                href="<?= BASE_URL ?>user/certificados.php"
            >
                <i class="fa-solid fa-certificate"></i>
                <span>Meus Certificados</span>
            </a>
        </li>

                <li>
            <a
                class="<?= $userSidebarAtivo(
                    "/user/atividades.php"
                ); ?>"
                href="<?= BASE_URL ?>user/atividades.php"
            >
                <i class="fa-solid fa-clock-rotate-left"></i>
                <span>Minhas Atividades</span>
            </a>
        </li>
<li>
            <a
                class="<?= $userSidebarAtivo(
                    "/user/profile.php"
                ); ?>"
                href="<?= BASE_URL ?>user/profile.php"
            >
                <i class="fa-solid fa-gear"></i>
                <span>Configurações</span>
            </a>
        </li>

        <li>
            <a href="<?= BASE_URL ?>login/logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Sair</span>
            </a>
        </li>

    </ul>

    <div class="sidebar-footer"></div>

</aside>
