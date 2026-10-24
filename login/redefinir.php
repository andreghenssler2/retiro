<?php

declare(strict_types=1);

require_once __DIR__
    . "/../config/settings.php";

Session::start();

$usuarioService =
    new Usuario();

$erro = "";
$sucesso = "";
$senhaAlterada = false;

$token = trim(
    (string) (
        $_GET["token"]
        ?? ""
    )
);

if ($token === "") {
    http_response_code(400);
    exit("Token inválido.");
}

$dados =
    $usuarioService
        ->buscarPorToken(
            $token
        );

if (!$dados) {
    http_response_code(400);
    exit(
        "Este link de redefinição "
        . "é inválido ou já foi utilizado."
    );
}

$resetExpira = trim(
    (string) (
        $dados["reset_expira"]
        ?? ""
    )
);

$expiraTimestamp =
    $resetExpira !== ""
        ? strtotime(
            $resetExpira
        )
        : false;

if (
    $expiraTimestamp === false
    || $expiraTimestamp < time()
) {
    http_response_code(400);
    exit(
        "Este link de redefinição expirou. "
        . "Solicite um novo link."
    );
}

if (
    $_SERVER["REQUEST_METHOD"]
    === "POST"
) {
    if (
        !Session::validateCsrf(
            $_POST["_token"]
            ?? ""
        )
    ) {
        $erro =
            "Token de segurança inválido. "
            . "Atualize a página e tente novamente.";
    } else {
        /*
         * Não usar trim() em senhas.
         * A validação abaixo já define exatamente
         * quais caracteres são aceitos.
         */
        $senha =
            (string) (
                $_POST["senha"]
                ?? ""
            );

        $confirmar =
            (string) (
                $_POST["confirmar"]
                ?? ""
            );

        $senhaValida =
            preg_match(
                '/^(?=.*[a-z])'
                . '(?=.*[A-Z])'
                . '(?=.*\d)'
                . '(?=.*[!@#$&-])'
                . '[A-Za-z\d!@#$&-]{6,}$/',
                $senha
            ) === 1;

        if (!$senhaValida) {
            $erro =
                "A senha deve possuir no mínimo "
                . "6 caracteres, contendo pelo menos "
                . "1 letra maiúscula, "
                . "1 letra minúscula, "
                . "1 número e "
                . "1 caractere especial "
                . "(! @ # $ & -).";
        } elseif ($senha !== $confirmar) {
            $erro =
                "As senhas não conferem.";
        } else {
            try {
                $alterou =
                    $usuarioService
                        ->alterarSenha(
                            (int) $dados["id"],
                            $senha
                        );

                if (!$alterou) {
                    throw new RuntimeException(
                        "A alteração da senha "
                        . "não foi concluída."
                    );
                }

                $senhaAlterada = true;

                $sucesso =
                    "Senha alterada com sucesso. "
                    . "Você já pode entrar "
                    . "com a nova senha.";
            } catch (Throwable $exception) {
                $erro =
                    "Não foi possível alterar "
                    . "a senha. Tente novamente.";
            }
        }
    }
}

function redefinirEscapar(
    string $valor
): string {
    return htmlspecialchars(
        $valor,
        ENT_QUOTES
        | ENT_SUBSTITUTE,
        "UTF-8"
    );
}
?>
<!doctype html>

<html lang="pt-br">

<head>

    <?php

    $titulo =
        Title::getAtual();

    if ($titulo) {
        HeaderHTML::metaTags(
            $titulo->getNome(
                "Redefinir Senha"
            ),
            $titulo->getDescricao(),
            $titulo->getKeyword(),
            $titulo->getFavicon()
        );
    }

    ?>

    <link
        rel="stylesheet"
        href="<?= THEME_CSS ?>login.css?v=<?= VERSION; ?>"
    >

</head>

<body class="bg-light">

    <div class="container">

        <div
            class="row justify-content-center
                align-items-center vh-100"
        >

            <div class="col-md-5">

                <div
                    class="card shadow
                        border-0"
                >

                    <div class="card-body p-5">

                        <div
                            class="text-center mb-4"
                        >

                            <i
                                class="fa fa-key
                                    display-3
                                    text-primary"
                            ></i>

                            <h2 class="mt-3">
                                Definir Nova Senha
                            </h2>

                            <p class="text-muted">
                                Informe sua nova senha.
                            </p>

                        </div>

                        <?php if ($erro !== ""): ?>

                            <div
                                class="alert alert-danger"
                                role="alert"
                            >
                                <?= redefinirEscapar(
                                    $erro
                                ); ?>
                            </div>

                        <?php endif; ?>

                        <?php if (
                            $sucesso !== ""
                        ): ?>

                            <div
                                class="alert
                                    alert-success"
                                role="alert"
                            >
                                <?= redefinirEscapar(
                                    $sucesso
                                ); ?>
                            </div>

                        <?php endif; ?>

                        <?php if (
                            !$senhaAlterada
                        ): ?>

                            <form
                                method="POST"
                                id="formRedefinirSenha"
                                autocomplete="off"
                            >

                                <input
                                    type="hidden"
                                    name="_token"
                                    value="<?= redefinirEscapar(
                                        Session::csrf()
                                    ); ?>"
                                >

                                <div class="mb-3">

                                    <label
                                        class="form-label"
                                        for="senha"
                                    >
                                        Nova Senha
                                    </label>

                                    <div
                                        class="input-group"
                                    >

                                        <span
                                            class="input-group-text"
                                        >
                                            <i
                                                class="fa
                                                    fa-lock"
                                            ></i>
                                        </span>

                                        <input
                                            type="password"
                                            name="senha"
                                            id="senha"
                                            class="form-control"
                                            required
                                            minlength="6"
                                            autocomplete="new-password"
                                            aria-describedby="requisitosSenha"
                                        >

                                        <button
                                            class="btn
                                                btn-outline-secondary"
                                            type="button"
                                            data-toggle-password="#senha"
                                            aria-label="Mostrar senha"
                                            aria-pressed="false"
                                        >
                                            <i
                                                class="fa fa-eye"
                                                aria-hidden="true"
                                            ></i>
                                        </button>

                                    </div>

                                    <div
                                        id="requisitosSenha"
                                        class="small mt-2"
                                    >

                                        <div
                                            id="reqMaiuscula"
                                            class="text-danger"
                                        >
                                            <i
                                                class="fa
                                                    fa-times"
                                            ></i>
                                            1 letra maiúscula
                                        </div>

                                        <div
                                            id="reqMinuscula"
                                            class="text-danger"
                                        >
                                            <i
                                                class="fa
                                                    fa-times"
                                            ></i>
                                            1 letra minúscula
                                        </div>

                                        <div
                                            id="reqNumero"
                                            class="text-danger"
                                        >
                                            <i
                                                class="fa
                                                    fa-times"
                                            ></i>
                                            1 número
                                        </div>

                                        <div
                                            id="reqEspecial"
                                            class="text-danger"
                                        >
                                            <i
                                                class="fa
                                                    fa-times"
                                            ></i>
                                            1 caractere especial
                                            (! @ # $ & -)
                                        </div>

                                        <div
                                            id="reqTamanho"
                                            class="text-danger"
                                        >
                                            <i
                                                class="fa
                                                    fa-times"
                                            ></i>
                                            Mínimo de 6 caracteres
                                        </div>

                                        <div
                                            id="reqIgual"
                                            class="text-danger"
                                        >
                                            <i
                                                class="fa
                                                    fa-times"
                                            ></i>
                                            As duas senhas
                                            são iguais
                                        </div>

                                    </div>

                                </div>

                                <div class="mb-4">

                                    <label
                                        class="form-label"
                                        for="confirmar"
                                    >
                                        Confirmar Senha
                                    </label>

                                    <div
                                        class="input-group"
                                    >

                                        <span
                                            class="input-group-text"
                                        >
                                            <i
                                                class="fa
                                                    fa-lock"
                                            ></i>
                                        </span>

                                        <input
                                            type="password"
                                            name="confirmar"
                                            id="confirmar"
                                            class="form-control"
                                            required
                                            minlength="6"
                                            autocomplete="new-password"
                                        >

                                        <button
                                            class="btn
                                                btn-outline-secondary"
                                            type="button"
                                            data-toggle-password="#confirmar"
                                            aria-label="Mostrar senha"
                                            aria-pressed="false"
                                        >
                                            <i
                                                class="fa fa-eye"
                                                aria-hidden="true"
                                            ></i>
                                        </button>

                                    </div>

                                </div>

                                <div class="d-grid">

                                    <button
                                        class="btn
                                            btn-primary"
                                        type="submit"
                                    >
                                        <i
                                            class="fa-solid
                                                fa-check-circle"
                                        ></i>

                                        Alterar Senha
                                    </button>

                                </div>

                            </form>

                        <?php endif; ?>

                        <div
                            class="text-center mt-4"
                        >

                            <a
                                href="<?= BASE_URL ?>login/"
                            >
                                <i
                                    class="fa-solid
                                        fa-arrow-left"
                                ></i>

                                <?= $senhaAlterada
                                    ? "Ir para o Login"
                                    : "Voltar ao Login"; ?>
                            </a>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

    <script
        src="<?= THEME_JS ?>login/redefinir.js?v=<?= VERSION; ?>"
    ></script>

</body>

</html>
