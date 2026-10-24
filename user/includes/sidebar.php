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

$paginaAtualUserSidebar = basename($caminhoAtualUserSidebar);
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
    string|array $caminhos
) use ($caminhoAtualUserSidebar): string {
    $lista = is_array($caminhos) ? $caminhos : [$caminhos];

    foreach ($lista as $caminho) {
        if ($caminho !== "" && str_contains($caminhoAtualUserSidebar, $caminho)) {
            return "active";
        }
    }

    return "";
};

$eventosAtivo = $userSidebarAtivo([
    "/eventos/",
    "/user/eventos.php"
]);

$perfilAtivo = $userSidebarAtivo([
    "/user/profile.php",
    "/user/atividades.php",
    "/user/index.php"
]);
?>

<aside class="sidebar" id="sidebar">

    <div class="sidebar-user">
        <div class="avatar">
            <?= $_SESSION["user"]["foto"] ?? '<i class="fas fa-user"></i>'; ?>
        </div>

        <div class="info">
            <strong><?= $nomeUserSidebar; ?></strong>
            <small><?= htmlspecialchars($perfilUserSidebar, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8"); ?></small>
        </div>
    </div>

    <ul class="sidebar-menu">

        <li>
            <a class="<?= $userSidebarAtivo("/my/"); ?>" href="<?= BASE_URL ?>my/">
                <i class="fa-solid fa-house"></i>
                <span>Início</span>
            </a>
        </li>

        <li class="has-submenu <?= $eventosAtivo !== "" ? "open" : ""; ?>">
            <a href="#" class="<?= $eventosAtivo; ?>">
                <i class="fa-solid fa-calendar-days"></i>
                <span>Eventos</span>
                <i class="fa fa-chevron-down submenu-arrow"></i>
            </a>

            <ul class="submenu">
                <li>
                    <a class="<?= $userSidebarAtivo("/user/eventos.php"); ?>" href="<?= BASE_URL ?>user/eventos.php">
                        <i class="fa-solid fa-clipboard-check"></i>
                        <span>Meus eventos</span>
                    </a>
                </li>
            </ul>
        </li>

        <li>
            <a class="<?= $userSidebarAtivo("/user/certificados.php"); ?>" href="<?= BASE_URL ?>user/certificados.php">
                <i class="fa-solid fa-certificate"></i>
                <span>Meus Certificados</span>
            </a>
        </li>

        <li>
            <a class="<?= $userSidebarAtivo(["/user/pagamentos.php", "/eventos/pagamento.php"]); ?>" href="<?= BASE_URL ?>user/pagamentos.php">
                <i class="fa-solid fa-credit-card"></i>
                <span>Meus Pagamentos</span>
            </a>
        </li>

        <li>
            <a class="<?= $userSidebarAtivo("/calendar/"); ?>" href="<?= BASE_URL ?>calendar/">
                <i class="fa-regular fa-calendar"></i>
                <span>Meu Calendário</span>
            </a>
        </li>

        <li class="has-submenu <?= $perfilAtivo !== "" ? "open" : ""; ?>">
            <a href="#" class="<?= $perfilAtivo; ?>">
                <i class="fa-solid fa-user"></i>
                <span>Meu Perfil</span>
                <i class="fa fa-chevron-down submenu-arrow"></i>
            </a>

            <ul class="submenu">
                <li>
                    <a class="<?= $userSidebarAtivo("/user/profile.php"); ?>" href="<?= BASE_URL ?>user/profile.php">
                        <i class="fa-solid fa-gears"></i>
                        <span>Configurações</span>
                    </a>
                </li>

                <li>
                    <a class="<?= $userSidebarAtivo("/user/atividades.php"); ?>" href="<?= BASE_URL ?>user/atividades.php">
                        <i class="fa-solid fa-clock-rotate-left"></i>
                        <span>Minhas Atividades</span>
                    </a>
                </li>

                <li>
                    <a class="<?= ($paginaAtualUserSidebar === "index.php" && str_contains($caminhoAtualUserSidebar, "/user/")) ? "active" : ""; ?>" href="<?= BASE_URL ?>user/index.php">
                        <i class="fa-solid fa-user-pen"></i>
                        <span>Editar Perfil</span>
                    </a>
                </li>
            </ul>
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
