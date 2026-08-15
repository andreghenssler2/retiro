<?php

declare(strict_types=1);

require_once __DIR__
    . "/../../mod/auth/Permissao.php";

$pagina =
    basename(
        (string) (
            $_SERVER["PHP_SELF"]
            ?? ""
        )
    );

$caminhoAtual =
    str_replace(
        "\\",
        "/",
        (string) (
            $_SERVER["PHP_SELF"]
            ?? ""
        )
    );

function sidebarAtivoArquivo(
    string|array $arquivos
): string {
    global $pagina;

    $lista =
        is_array($arquivos)
            ? $arquivos
            : [$arquivos];

    return in_array(
        $pagina,
        $lista,
        true
    )
        ? "active"
        : "";
}

function sidebarAtivoCaminho(
    string|array $caminhos
): string {
    global $caminhoAtual;

    $lista =
        is_array($caminhos)
            ? $caminhos
            : [$caminhos];

    foreach ($lista as $caminho) {
        if (
            $caminho !== ""
            && str_contains(
                $caminhoAtual,
                $caminho
            )
        ) {
            return "active";
        }
    }

    return "";
}

function sidebarAberto(
    string|array $caminhos
): string {
    return sidebarAtivoCaminho(
        $caminhos
    ) !== ""
        ? "open"
        : "";
}

$ehAdmin =
    Permissao::ehAdmin();

$ehModerador =
    Permissao::ehModerador();

$inscricoesAtivas =
    sidebarAtivoCaminho([
        "/admin/inscricao/",
        "/admin/credenciamento/"
    ]);

$financeiroAtivo =
    sidebarAtivoCaminho(
        "/admin/financeiro/"
    );

$certificadosAtivos =
    sidebarAtivoCaminho(
        "/admin/certificado/"
    );

$relatoriosAtivos =
    sidebarAtivoCaminho(
        "/admin/relatorios/"
    );

$configuracoesAtivas =
    sidebarAtivoCaminho([
        "/admin/configuracoes/",
        "/user/atividades.php",
        "/user/permissoes.php"
    ]);

$perfilAtivo =
    sidebarAtivoCaminho([
        "/user/index.php",
        "/user/profile.php",
        "/calendar/"
    ]);
?>

<aside
    class="sidebar"
    id="sidebar"
>

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
                    ENT_QUOTES
                    | ENT_SUBSTITUTE,
                    "UTF-8"
                ); ?>
            </strong>

            <small>
                <?= $ehAdmin
                    ? "Administrador"
                    : (
                        $ehModerador
                            ? "Moderador"
                            : "Participante"
                    ); ?>
            </small>

        </div>

    </div>

    <ul class="sidebar-menu">

        <!-- DASHBOARD -->
        <?php if (
            Permissao::pode(
                "dashboard.visualizar"
            )
        ): ?>
            <li>
                <a
                    class="<?= sidebarAtivoCaminho(
                        "/admin/dashboard/"
                    ); ?>"
                    href="<?= BASE_URL ?>admin/dashboard/"
                >
                    <i class="fas fa-house"></i>
                    <span>Dashboard</span>
                </a>
            </li>
        <?php endif; ?>


        <!-- USUÁRIOS -->
        <?php if ($ehAdmin): ?>
            <li>
                <a
                    class="<?= sidebarAtivoCaminho(
                        "/admin/user/"
                    ); ?>"
                    href="<?= BASE_URL ?>admin/user/usuarios.php"
                >
                    <i class="fas fa-users"></i>
                    <span>Usuários</span>
                </a>
            </li>
        <?php endif; ?>


        <!-- EVENTOS -->
        <?php if (
            Permissao::pode(
                "eventos.visualizar"
            )
        ): ?>
            <li>
                <a
                    class="<?= sidebarAtivoCaminho(
                        "/admin/event/"
                    ); ?>"
                    href="<?= BASE_URL ?>admin/event/eventos.php"
                >
                    <i class="fas fa-calendar-days"></i>
                    <span>Eventos</span>
                </a>
            </li>
        <?php endif; ?>


        <!-- INSCRIÇÕES -->
        <?php if (
            Permissao::pode(
                "inscricoes.visualizar"
            )
            || Permissao::pode(
                "credenciamento.visualizar"
            )
        ): ?>

            <li
                class="has-submenu <?= $inscricoesAtivas
                    !== ""
                        ? "open"
                        : ""; ?>"
            >

                <a
                    href="#"
                    class="<?= $inscricoesAtivas; ?>"
                >
                    <i class="fas fa-clipboard-check"></i>
                    <span>Inscrições</span>

                    <i
                        class="fa fa-chevron-down
                            submenu-arrow"
                    ></i>
                </a>

                <ul class="submenu">

                    <?php if (
                        Permissao::pode(
                            "inscricoes.visualizar"
                        )
                    ): ?>
                        <li>
                            <a
                                class="<?= sidebarAtivoArquivo(
                                    "inscricoes.php"
                                ); ?>"
                                href="<?= BASE_URL ?>admin/inscricao/inscricoes.php"
                            >
                                <i class="fa-solid fa-list"></i>
                                <span>Inscrições</span>
                            </a>
                        </li>

                        <li>
                            <a
                                class="<?= sidebarAtivoArquivo(
                                    "inscricao.php"
                                ); ?>"
                                href="<?= BASE_URL ?>admin/inscricao/inscricao.php"
                            >
                                <i class="fa-solid fa-user-plus"></i>
                                <span>Inscrição Manual</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (
                        Permissao::pode(
                            "credenciamento.visualizar"
                        )
                    ): ?>
                        <li>
                            <a
                                class="<?= sidebarAtivoCaminho(
                                    "/admin/credenciamento/"
                                ); ?>"
                                href="<?= BASE_URL ?>admin/credenciamento/"
                            >
                                <i class="fas fa-id-card-clip"></i>
                                <span>Credenciamento</span>
                            </a>
                        </li>
                    <?php endif; ?>

                </ul>

            </li>

        <?php endif; ?>


        <!-- FINANCEIRO -->
        <?php if (
            Permissao::pode(
                "financeiro.visualizar"
            )
            || Permissao::pode(
                "pagamentos.visualizar"
            )
        ): ?>

            <li
                class="has-submenu <?= $financeiroAtivo
                    !== ""
                        ? "open"
                        : ""; ?>"
            >

                <a
                    href="#"
                    class="<?= $financeiroAtivo; ?>"
                >
                    <i class="fas fa-wallet"></i>
                    <span>Financeiro</span>

                    <i
                        class="fa fa-chevron-down
                            submenu-arrow"
                    ></i>
                </a>

                <ul class="submenu">

                    <?php if (
                        Permissao::pode(
                            "financeiro.visualizar"
                        )
                    ): ?>
                        <li>
                            <a
                                class="<?= (
                                    sidebarAtivoCaminho(
                                        "/admin/financeiro/"
                                    ) !== ""
                                    && $pagina
                                        !== "pagamentos.php"
                                )
                                    ? "active"
                                    : ""; ?>"
                                href="<?= BASE_URL ?>admin/financeiro/"
                            >
                                <i class="fas fa-chart-line"></i>
                                <span>Financeiro</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php if (
                        Permissao::pode(
                            "pagamentos.visualizar"
                        )
                    ): ?>
                        <li>
                            <a
                                class="<?= sidebarAtivoArquivo(
                                    "pagamentos.php"
                                ); ?>"
                                href="<?= BASE_URL ?>admin/financeiro/pagamentos.php"
                            >
                                <i class="fas fa-credit-card"></i>
                                <span>Pagamentos</span>
                            </a>
                        </li>
                    <?php endif; ?>

                </ul>

            </li>

        <?php endif; ?>


        <!-- CERTIFICADOS -->
        <?php if (
            Permissao::pode(
                "certificados.visualizar"
            )
        ): ?>

            <li
                class="has-submenu <?= $certificadosAtivos
                    !== ""
                        ? "open"
                        : ""; ?>"
            >

                <a
                    href="#"
                    class="<?= $certificadosAtivos; ?>"
                >
                    <i class="fas fa-award"></i>
                    <span>Certificados</span>

                    <i
                        class="fa fa-chevron-down
                            submenu-arrow"
                    ></i>
                </a>

                <ul class="submenu">

                    <li>
                        <a
                            class="<?= (
                                sidebarAtivoCaminho(
                                    "/admin/certificado/"
                                ) !== ""
                                && $pagina
                                    !== "modelo.php"
                            )
                                ? "active"
                                : ""; ?>"
                            href="<?= BASE_URL ?>admin/certificado/"
                        >
                            <i class="fas fa-award"></i>
                            <span>Certificados</span>
                        </a>
                    </li>

                    <li>
                        <a
                            class="<?= sidebarAtivoArquivo(
                                "modelo.php"
                            ); ?>"
                            href="<?= BASE_URL ?>admin/certificado/modelo.php"
                        >
                            <i class="fa-solid fa-file-circle-plus"></i>
                            <span>Novo Modelo</span>
                        </a>
                    </li>

                </ul>

            </li>

        <?php endif; ?>


        <!-- RELATÓRIOS -->
        <?php if (
            Permissao::pode(
                "relatorios.visualizar"
            )
        ): ?>

            <li
                class="has-submenu <?= $relatoriosAtivos
                    !== ""
                        ? "open"
                        : ""; ?>"
            >

                <a
                    href="#"
                    class="<?= $relatoriosAtivos; ?>"
                >
                    <i class="fas fa-chart-pie"></i>
                    <span>Relatórios</span>

                    <i
                        class="fa fa-chevron-down
                            submenu-arrow"
                    ></i>
                </a>

                <ul class="submenu">

                    <li>
                        <a
                            class="<?= (
                                $pagina === "index.php"
                                && sidebarAtivoCaminho(
                                    "/admin/relatorios/"
                                ) !== ""
                            )
                                ? "active"
                                : ""; ?>"
                            href="<?= BASE_URL ?>admin/relatorios/"
                        >
                            <i class="fa-solid fa-chart-column"></i>
                            <span>Central de Relatórios</span>
                        </a>
                    </li>

                    <?php if (
                        is_file(
                            __DIR__
                            . "/../relatorios/"
                            . "evento-exportacao.php"
                        )
                    ): ?>
                        <li>
                            <a
                                class="<?= sidebarAtivoArquivo(
                                    [
                                        "evento-exportacao.php",
                                        "evento-exportar.php"
                                    ]
                                ); ?>"
                                href="<?= BASE_URL ?>admin/relatorios/evento-exportacao.php"
                            >
                                <i class="fa-solid fa-file-export"></i>
                                <span>Exportação de Evento</span>
                            </a>
                        </li>
                    <?php endif; ?>

                </ul>

            </li>

        <?php endif; ?>


        <!-- CONFIGURAÇÕES -->
        <?php if ($ehAdmin): ?>

            <li
                class="has-submenu <?= $configuracoesAtivas
                    !== ""
                        ? "open"
                        : ""; ?>"
            >

                <a
                    href="#"
                    class="<?= $configuracoesAtivas; ?>"
                >
                    <i class="fa fa-gears"></i>
                    <span>Configurações</span>

                    <i
                        class="fa fa-chevron-down
                            submenu-arrow"
                    ></i>
                </a>

                <ul class="submenu">

                    <li>
                        <a
                            class="<?= sidebarAtivoArquivo(
                                "email.php"
                            ); ?>"
                            href="<?= BASE_URL ?>admin/configuracoes/email.php"
                        >
                            <i class="fa fa-envelope"></i>
                            <span>E-mail</span>
                        </a>
                    </li>

                    <li>
                        <a
                            class="<?= sidebarAtivoArquivo(
                                "title.php"
                            ); ?>"
                            href="<?= BASE_URL ?>admin/configuracoes/title.php"
                        >
                            <i class="fa fa-heading"></i>
                            <span>Title</span>
                        </a>
                    </li>

                    <?php if (
                        is_file(
                            __DIR__
                            . "/../configuracoes/"
                            . "comunidades.php"
                        )
                    ): ?>
                        <li>
                            <a
                                class="<?= sidebarAtivoArquivo(
                                    "comunidades.php"
                                ); ?>"
                                href="<?= BASE_URL ?>admin/configuracoes/comunidades.php"
                            >
                                <i class="fa-solid fa-church"></i>
                                <span>Comunidades</span>
                            </a>
                        </li>
                    <?php endif; ?>

                    <li>
                        <a
                            class="<?= sidebarAtivoCaminho(
                                "/user/atividades.php"
                            ); ?>"
                            href="<?= BASE_URL ?>user/atividades.php"
                        >
                            <i class="fa fa-clock-rotate-left"></i>
                            <span>Atividades</span>
                        </a>
                    </li>

                    <li>
                        <a
                            class="<?= sidebarAtivoArquivo(
                                "bancario.php"
                            ); ?>"
                            href="<?= BASE_URL ?>admin/configuracoes/bancario.php"
                        >
                            <i class="fa fa-building-columns"></i>
                            <span>Bancário</span>
                        </a>
                    </li>

                    <li>
                        <a
                            class="<?= sidebarAtivoCaminho(
                                "/user/permissoes.php"
                            ); ?>"
                            href="<?= BASE_URL ?>user/permissoes.php"
                        >
                            <i class="fa fa-lock"></i>
                            <span>Permissões</span>
                        </a>
                    </li>

                </ul>

            </li>

        <?php endif; ?>


        <!-- MEU PERFIL -->
        <li
            class="has-submenu <?= $perfilAtivo
                !== ""
                    ? "open"
                    : ""; ?>"
        >

            <a
                href="#"
                class="<?= $perfilAtivo; ?>"
            >
                <i class="fa fa-user"></i>
                <span>Meu Perfil</span>

                <i
                    class="fa fa-chevron-down
                        submenu-arrow"
                ></i>
            </a>

            <ul class="submenu">

                <li>
                    <a
                        class="<?= sidebarAtivoCaminho(
                            "/user/index.php"
                        ); ?>"
                        href="<?= BASE_URL ?>user/index.php"
                    >
                        <i class="fa fa-user-pen"></i>
                        <span>Editar Perfil</span>
                    </a>
                </li>

                <li>
                    <a
                        class="<?= sidebarAtivoCaminho(
                            "/calendar/"
                        ); ?>"
                        href="<?= BASE_URL ?>calendar/"
                    >
                        <i class="fa fa-calendar"></i>
                        <span>Calendário</span>
                    </a>
                </li>

                <li>
                    <a
                        class="<?= sidebarAtivoCaminho(
                            "/user/profile.php"
                        ); ?>"
                        href="<?= BASE_URL ?>user/profile.php"
                    >
                        <i class="fa fa-sliders"></i>
                        <span>Configurações</span>
                    </a>
                </li>

            </ul>

        </li>


        <!-- SAIR -->
        <li>
            <a
                href="<?= BASE_URL ?>login/logout.php"
            >
                <i class="fas fa-right-from-bracket"></i>
                <span>Sair</span>
            </a>
        </li>

    </ul>

    <div class="sidebar-footer"></div>

</aside>
