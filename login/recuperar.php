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
        $email = Usuario::normalizarEmail(
            (string) ($_POST['email'] ?? '')
        );

        $ip = AutenticacaoRateLimitService::ipCliente();
        $limitador = new AutenticacaoRateLimitService();

        $limite = $limitador->verificarRecuperacao(
            $email,
            $ip
        );

        if ($limite['permitido']) {
            $limitador->registrarRecuperacao(
                $email,
                $ip
            );

            try {
                $usuario = new Usuario();
                $registro = $usuario->buscarPorEmail($email);

                if ($registro) {
                    $token = bin2hex(
                        random_bytes(32)
                    );

                    $expira = date(
                        'Y-m-d H:i:s',
                        strtotime('+1 hour')
                    );

                    $usuario->salvarTokenRecuperacao(
                        $email,
                        $token,
                        $expira
                    );

                    $link =
                        BASE_URL
                        . 'login/redefinir.php?token='
                        . rawurlencode($token);

                    $nome = (string) (
                        $registro['nome']
                        ?? ''
                    );

                    ob_start();

                    include
                        '../mod/mail/templates/recuperar_senha.php';

                    $html = ob_get_clean();

                    if (!is_string($html)) {
                        $html = '';
                    }

                    $mail = new Mail();

                    if (
                        !$mail->send(
                            $email,
                            $nome,
                            'Recuperação de Senha',
                            $html
                        )
                    ) {
                        error_log(
                            'Falha ao enviar e-mail de recuperação.'
                        );
                    }
                }
            } catch (Throwable $exception) {
                /*
                 * A resposta ao usuário permanece genérica para não
                 * revelar existência da conta nem detalhes internos.
                 */
                error_log(
                    'Falha na recuperação de senha: '
                    . $exception->getMessage()
                );
            }
        }

        $sucesso =
            'Se o e-mail existir em nossa base, '
            . 'você receberá uma mensagem '
            . 'para redefinir sua senha.';
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