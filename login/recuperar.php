<?php

require_once '../config/settings.php';
require_once '../mod/mail/Mail.php';
require_once '../mod/auth/Session.php';
require_once '../mod/auth/Usuario.php';
// Config::init();
Session::start();
$erro = '';
$sucesso = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!Session::validateCsrf($_POST['_token'] ?? '')) {
        $erro = 'Token de segurança inválido.';
    } else {

        $email = trim($_POST['email']);

        $usuario = new Usuario();

        if (!$usuario->emailExiste($email)) {

            $erro = 'Nenhum usuário encontrado com este e-mail.';

        } else {

            $token = bin2hex(random_bytes(32));

            $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

            $usuario->salvarTokenRecuperacao(
                $email,
                $token,
                $expira
            );

            /*
             * PHPMailer será implementado na Sprint 2
             */
            // echo BASE_URL;
            $link = BASE_URL . "login/redefinir.php?token=" . $token;

            $nome = $usuario->buscarPorEmail($email)['nome'];

            ob_start();

            include "../mod/mail/templates/recuperar_senha.php";

            $html = ob_get_clean();

            $mail = new Mail();

            if (
                $mail->send(
                    $email,
                    $nome,
                    "Recuperação de Senha",
                    $html
                )
            ) {
                $sucesso = "E-mail enviado com sucesso!";
            } else {
                $sucesso = "Falha ao enviar.";
            }

            $sucesso .= "Caso o e-mail exista em nossa base, você receberá uma mensagem para redefinir sua senha.";

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
        HeaderHTML::metaTags($titulo->getNome("Recuperar"), $titulo->getDescricao(), $titulo->getKeyword(), $titulo->getFavicon());
    }


    ?>

    <link rel="stylesheet" href="<?php echo THEME_CSS; ?>login.css">

</head>

<body>

    <div class="container">

        <div class="row justify-content-center align-items-center vh-100">

            <div class="col-md-5">

                <div class="card shadow-lg border-0">

                    <div class="card-body p-5">

                        <div class="text-center mb-4">

                            <i class="bi bi-envelope-lock display-4 text-primary"></i>

                            <h2 class="mt-3">

                                Recuperar Senha

                            </h2>

                            <p class="text-muted">

                                Informe seu e-mail para receber um link de recuperação.

                            </p>

                        </div>

                        <?php if ($erro) { ?>

                            <div class="alert alert-danger">

                                <?= $erro ?>

                            </div>

                        <?php } ?>

                        <?php if ($sucesso) { ?>

                            <div class="alert alert-success">

                                <?= $sucesso ?>

                            </div>

                        <?php } ?>

                        <form method="POST">

                            <input type="hidden" name="_token" value="<?= Session::csrf(); ?>">

                            <div class="mb-4">

                                <label class="form-label">

                                    E-mail

                                </label>

                                <div class="input-group">

                                    <span class="input-group-text">

                                        <i class="fa fa-envelope"></i>

                                    </span>

                                    <input type="email" name="email" class="form-control" required>

                                </div>

                            </div>

                            <div class="d-grid">

                                <button class="btn btn-primary">

                                    <i class="bi bi-send"></i>

                                    Enviar Link

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

</body>

</html>