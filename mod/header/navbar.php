<?php

class Navbar{

    public static function menu(){
    ?>
    <nav class="topbar">

    <div class="left-menu">

        <button id="toggleSidebar"class="btn-toggle">

            <i class="fa fa-bars"></i>

        </button>

        <span class="page-title">

            <?= Title::getAtual()->getNome(); ?>
        </span>

    </div>

    <div class="right-menu">

        <!-- Pesquisa -->

        <div class="search-box d-none d-lg-flex">

            <i class="fa fa-search"></i>

            <input type="text" placeholder="Pesquisar...">

        </div>

        <!-- Notificações -->

        <?php require __DIR__ . "/../../message/widget.php"; ?>

        <!-- Usuário -->

        <div class="dropdown ms-3">

            <button class="profile-button" data-bs-toggle="dropdown">

                <div class="avatar">

                    <?= $_SESSION['user']['foto'] ?? '<i class="fas fa-user"></i>'; ?>

                </div>

                <div class="profile-info d-none d-md-block">

                    <strong>

                        <?= htmlspecialchars(
                            (string) ($_SESSION['user']['nome'] ?? 'Administrador'),
                            ENT_QUOTES,
                            'UTF-8'
                        ); ?>

                    </strong>

                    <small>

                        <?= match ((int) ($_SESSION['user']['tipo'] ?? 0)) {
                            1 => 'Administrador',
                            2 => 'Moderador',
                            3 => 'Participante',
                            default => 'Usuário'
                        }; ?>

                    </small>

                </div>

                <i class="fa fa-chevron-down ms-2"></i>

            </button>

            <ul class="dropdown-menu dropdown-menu-end shadow">

                <li>

                    <a
                        class="dropdown-item"
                        href="<?= BASE_URL ?>user/index.php"
                    >

                        <i class="fa fa-user me-2"></i>

                        Meu Perfil

                    </a>

                </li>

                <li>

                    <a class="dropdown-item" href="<?= BASE_URL ?>user/atividades.php">

                        <i class="fa fa-gear me-2"></i>

                        Configurações

                    </a>

                </li>

                <li>

                    <hr class="dropdown-divider">

                </li>

                <li>

                    <a class="dropdown-item text-danger" href="<?= BASE_URL ?>login/logout.php">

                        <i class="fa fa-right-from-bracket me-2"></i>

                        Sair

                    </a>

                </li>

            </ul>

        </div>

    </div>

</nav>
    <?php
    }

}