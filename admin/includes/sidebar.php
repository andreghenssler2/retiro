<?php

require_once __DIR__ . "/../../mod/auth/Permissao.php";

$pagina = basename($_SERVER["PHP_SELF"]);
$caminhoAtual = (string) ($_SERVER["PHP_SELF"] ?? "");
$configuracoesAtivas = str_contains(
    $caminhoAtual,
    "/admin/configuracoes/"
);

function active($arquivos)
{
    global $pagina;

    if (is_array($arquivos)) {
        return in_array($pagina, $arquivos, true)
            ? "active"
            : "";
    }

    return $pagina === $arquivos
        ? "active"
        : "";
}

$ehAdmin = Permissao::ehAdmin();
$ehModerador = Permissao::ehModerador();
?>

<aside class="sidebar" id="sidebar">

    <div class="sidebar-user">

        <div class="avatar">
            <?= $_SESSION["user"]["foto"]
                ?? '<i class="fas fa-user"></i>'; ?>
        </div>

        <div class="info">

            <strong>
                <?= htmlspecialchars(
                    (string) (
                        $_SESSION["user"]["nome"]
                        ?? "Usuário"
                    ),
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>
            </strong>

            <small>
                <?= $ehAdmin
                    ? "Administrador"
                    : ($ehModerador
                        ? "Moderador"
                        : "Participante"); ?>
            </small>

        </div>

    </div>

    <ul class="sidebar-menu">

        <?php if (Permissao::pode("dashboard.visualizar")): ?>
            <li>
                <a class="<?= str_contains(
                    $caminhoAtual,
                    "/admin/dashboard/"
                ) ? "active" : ""; ?>" href="<?= BASE_URL ?>admin/dashboard/">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        <?php endif; ?>


        <?php if ($ehAdmin): ?>
            <li>
                <a class="<?= active(
                    ["usuarios.php", "usuario.php"]
                ); ?>" href="<?= BASE_URL ?>admin/user/usuarios.php">
                    <i class="fas fa-users"></i>
                    <span>Usuários</span>
                </a>
            </li>
        <?php endif; ?>


        <?php if (Permissao::pode("eventos.visualizar")): ?>
            <li>
                <a class="<?= active(
                    ["eventos.php", "evento.php"]
                ); ?>" href="<?= BASE_URL ?>admin/event/eventos.php">
                    <i class="fas fa-calendar-days"></i>
                    <span>Eventos</span>
                </a>
            </li>
        <?php endif; ?>


        <?php if (Permissao::pode("inscricoes.visualizar")): ?>
            <li>
                <a class="<?= active(["inscricoes.php"]); ?>" href="<?= BASE_URL ?>admin/inscricao/inscricoes.php">
                    <i class="fas fa-clipboard-check"></i>
                    <span>Inscrições</span>
                </a>
            </li>
        <?php endif; ?>


        <?php if (Permissao::pode("credenciamento.visualizar")): ?>
            <li>
                <a class="<?= str_contains(
                    $caminhoAtual,
                    "/admin/credenciamento/"
                ) ? "active" : ""; ?>" href="<?= BASE_URL ?>admin/credenciamento/">
                    <i class="fas fa-id-card-clip"></i>
                    <span>Credenciamento</span>
                </a>
            </li>
        <?php endif; ?>


        <?php if (Permissao::pode("pagamentos.visualizar")): ?>
            <li>
                <a class="<?= active(["pagamentos.php"]); ?>" href="<?= BASE_URL ?>admin/financeiro/pagamentos.php">
                    <i class="fas fa-credit-card"></i>
                    <span>Pagamentos</span>
                </a>
            </li>
        <?php endif; ?>


        <?php if (Permissao::pode("financeiro.visualizar")): ?>
            <li>
                <a class="<?= (
                    str_contains(
                        $caminhoAtual,
                        "/admin/financeiro/"
                    )
                    && basename($caminhoAtual)
                    !== "pagamentos.php"
                ) ? "active" : ""; ?>" href="<?= BASE_URL ?>admin/financeiro/">
                    <i class="fas fa-chart-line"></i>
                    <span>Financeiro</span>
                </a>
            </li>
        <?php endif; ?>


        <?php if (Permissao::pode("certificados.visualizar")): ?>
            <li>
                <a class="<?= str_contains(
                    $caminhoAtual,
                    "/admin/certificado/"
                ) ? "active" : ""; ?>" href="<?= BASE_URL ?>admin/certificado/">
                    <i class="fas fa-award"></i>
                    <span>Certificados</span>
                </a>
            </li>
        <?php endif; ?>


        <?php if (Permissao::pode("relatorios.visualizar")): ?>
            <li>
                <a class="<?= str_contains(
                    $caminhoAtual,
                    "/admin/relatorios/"
                ) ? "active" : ""; ?>" href="<?= BASE_URL ?>admin/relatorios/">
                    <i class="fas fa-chart-pie"></i>
                    <span>Relatórios</span>
                </a>
            </li>
        <?php endif; ?>


        <?php if ($ehAdmin): ?>

            <li class="has-submenu <?= $configuracoesAtivas
                ? "open"
                : ""; ?>">

                <a href="#" class="<?= $configuracoesAtivas
                    ? "active"
                    : ""; ?>">
                    <i class="fa fa-gears"></i>
                    <span>Configurações</span>
                    <i class="fa fa-chevron-down submenu-arrow"></i>
                </a>

                <ul class="submenu">
                    <li>
                        <a class="<?= active(['email.php']); ?>" href="<?= BASE_URL ?>admin/configuracoes/email.php">
                            <i class="fa fa-envelope"></i>
                            <span>E-mail</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?= active(['title.php']); ?>" href="<?= BASE_URL ?>admin/configuracoes/title.php">
                            <i class="fa fa-heading"></i>
                            <span>Title</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?= active(['atividades.php']); ?>" href="<?= BASE_URL ?>user/atividades.php">
                            <i class="fa fa-clock-rotate-left"></i>
                            <span>Atividades</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?= active(['bancario.php']); ?>" href="<?= BASE_URL ?>admin/configuracoes/bancario.php">
                            <i class="fa fa-building-columns"></i>
                            <span>Bancário</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?= active(['user/index.php']); ?>" href="<?= BASE_URL ?>user/index.php">
                            <i class="fa fa-user"></i>
                            <span>Usuários</span>
                        </a>
                    </li>
                    <li>
                        <a class="<?= active(['permissoes.php']); ?>" href="<?= BASE_URL ?>user/permissoes.php">
                            <i class="fa fa-lock"></i>
                            <span>Permissões</span>
                        </a>
                    </li>
                </ul>

            </li>

        <?php endif; ?>


        <li>
            <a href="<?= BASE_URL ?>user/index.php">
                <i class="fa fa-user"></i>
                <span>Meu Perfil</span>
            </a>
        </li>


        <li>
            <a href="<?= BASE_URL ?>login/logout.php">
                <i class="fas fa-sign-out-alt"></i>
                <span>Sair</span>
            </a>
        </li>

    </ul>

    <div class="sidebar-footer"></div>

</aside>