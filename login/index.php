<?php
require_once "../config/settings.php";

Middleware::guest();

$_SESSION['base_url'] = BASE_URL;

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!Session::validateCsrf($_POST['_token'] ?? '')) {

        $erro = 'Token de segurança inválido.';

    } else {

        $auth = new Auth();

        if ($auth->login($_POST['email'], $_POST['senha'])) {

            Auth::redirectDashboard();

        } else {

            $erro = 'E-mail ou senha inválidos.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <?php

    $titulo = Title::getAtual();
    if ($titulo) {
        HeaderHTML::metaTags($titulo->getNome(), $titulo->getDescricao(), $titulo->getKeyword(), $titulo->getFavicon());
    }


    ?>
    <link rel="stylesheet" href="../theme/css/login.css">

</head>

<body class='logins'>

    <div class="container-fluid">

        <div class="row vh-100">

            <div class="col-lg-7 d-none d-lg-flex bg-login">

                <div class="overlay">

                    <div>

                        <h1><?php echo Title::getAtual()->getNome(); ?></h1>

                        <p class="lead">
                            <?php echo Title::getAtual()->getDescricao(); ?>
                        </p>
                        <p>
                            <?php FooterHTML::versao();?>
                        </p>
                    </div>
                    <div>
                        
                    </div>

                </div>

            </div>

            <div class="col-lg-5 d-flex align-items-center justify-content-center">

                <div class="login-box">

                    <div class="text-center mb-4">

                        <h2>Entrar</h2>

                        <p class="text-muted">
                            Acesse sua conta
                        </p>

                    </div>

                    <?php if ($erro) { ?>

                        <div class="alert alert-danger">

                            <?= $erro ?>

                        </div>

                    <?php } ?>

                    <form method="POST">

                        <input type="hidden" name="_token" value="<?= Session::csrf() ?>">

                        <div class="mb-3">

                            <label>E-mail</label>

                            <div class="input-group">

                                <span class="input-group-text">

                                    <i class="fa fa-envelope"></i>

                                </span>

                                <input type="email" name="email" class="form-control login-email" required>

                            </div>

                        </div>

                        <div class="mb-3">

                            <label>Senha</label>

                            <div class="input-group">

                                <span class="input-group-text">

                                    <i class="fa fa-lock"></i>

                                </span>

                                <input type="password" name="senha" class="form-control" required>
                                <button class="btn btn-outline-secondary toggleSenha" type="button" id="toggleSenha">
                                    <i class="fa fa-eye" ></i>
                                </button>

                            </div>

                        </div>

                        <div class="form-check mb-3">

                            <input type="checkbox" class="form-check-input" name="remember">

                            <label class="form-check-label">

                                Lembrar-me

                            </label>

                        </div>

                        <button class="btn btn-primary w-100">

                            Entrar

                        </button>

                        <div class="text-center mt-4">

                            <a href="recuperar.php">

                                Esqueci minha senha

                            </a>

                            <br>

                            <a href="cadastro.php">

                                Criar Conta

                            </a>

                        </div>

                    </form>

                </div>

            </div>

        </div>

    </div>
    <?php
        FooterHTML::versao();
            
    ?>
    <?php
        echo '<script src="' . THEME_JS . 'login/login.js"></script>';
    ?>
</body>

</html>