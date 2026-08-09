<?php

require_once '../config/settings.php';
require_once '../mod/auth/Session.php';
require_once '../mod/auth/Usuario.php';

Session::start();
$usuario = new Usuario();

$erro = '';
$sucesso = '';

$token = trim($_GET['token'] ?? '');

if (empty($token)) {
    die('Token inválido.');
}

$usuario = new Usuario();

$dados = $usuario->buscarPorToken($token);

if (!$dados) {
    die('Token inválido.');
}

if (strtotime($dados['reset_expira']) < time()) {
    die('Este link expirou.');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    if (!Session::validateCsrf($_POST['_token'] ?? '')) {
        $erro = "Token CSRF inválido.";
    } else {

        $senha = trim($_POST['senha']);
        $confirmar = trim($_POST['confirmar']);

        // Mínimo 6 caracteres
        if (
            !preg_match(
                '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$&-])[A-Za-z\d!@#$&-]{6,}$/',
                $senha
            )
        ) {

            $erro = "A senha deve possuir no mínimo 6 caracteres, contendo pelo menos 1 letra maiúscula, 1 letra minúscula, 1 número e 1 caractere especial (! @ # $ & -).";

        } elseif ($senha !== $confirmar) {

            $erro = "As senhas não conferem.";

        }

    }

}
?>
<!doctype html>

<html lang="pt-br">

<head>

    <?php

    $titulo = Title::getAtual();
    if ($titulo) {
        HeaderHTML::metaTags($titulo->getNome("Redefinir Senha"), $titulo->getDescricao(), $titulo->getKeyword(), $titulo->getFavicon());
    }


    ?>

    <link rel="stylesheet" href="../theme/css/login.css">


</head>

<body class="bg-light">

    <div class="container">

        <div class="row justify-content-center align-items-center vh-100">

            <div class="col-md-5">

                <div class="card shadow border-0">

                    <div class="card-body p-5">

                        <div class="text-center mb-4">

                            <i class="fa fa-key display-3 text-primary"></i>

                            <h2 class="mt-3">

                                Definir Nova Senha

                            </h2>

                            <p class="text-muted">

                                Informe sua nova senha.

                            </p>

                        </div>

                        <?php if ($erro): ?>

                            <div class="alert alert-danger">

                                <?= htmlspecialchars($erro) ?>

                            </div>

                        <?php endif; ?>

                        <form method="POST">

                            <input type="hidden" name="_token" value="<?= Session::csrf(); ?>">

                            <div class="mb-3">

                                <label class="form-label">

                                    Nova Senha

                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">

                                        <i class="fa fa-lock"></i>

                                    </span>

                                    <input type="password" name="senha" class="form-control toggleSenha" required
                                        minlength="6"><button class="btn btn-outline-secondary toggleSenha"
                                        type="button" id="toggleSenha">
                                        <i class="fa fa-eye"></i>
                                    </button>

                                </div>
                                <div id="requisitosSenha" class="small mt-2">

                                    <div class="text-danger">
                                        <i class="fa fa-times"></i> 1 letra maiúscula
                                    </div>

                                    <div class="text-danger">
                                        <i class="fa fa-times"></i> 1 letra minúscula
                                    </div>

                                    <div class="text-danger">
                                        <i class="fa fa-times"></i> 1 número
                                    </div>

                                    <div class="text-danger">
                                        <i class="fa fa-times"></i> 1 caractere especial (! @ # $ & -)
                                    </div>

                                    <div class="text-danger">
                                        <i class="fa fa-times"></i> Mínimo de 6 caracteres
                                    </div>

                                </div>

                            </div>

                            <div class="mb-4">

                                <label class="form-label">

                                    Confirmar Senha

                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">

                                        <i class="fa fa fa-lock"></i>

                                    </span>

                                    <input type="password" name="confirmar" class="form-control toggleSenha" required
                                        minlength="6">
                                    <button class="btn btn-outline-secondary toggleSenha" type="button"
                                        id="toggleSenha">
                                        <i class="fa fa-eye"></i>
                                    </button>

                                </div>

                            </div>

                            <div class="d-grid">

                                <button class="btn btn-primary">

                                    <i class="bi bi-check-circle"></i>

                                    Alterar Senha

                                </button>

                            </div>

                            <div class="text-center mt-4">

                                <a href="index.php">

                                    <i class="bi bi-arrow-left"></i>

                                    Voltar ao Login

                                </a>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>
    <script src="../theme/js/login/login.js"></script>
</body>

</html>