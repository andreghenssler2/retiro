<?php

require_once "../config/settings.php";
require_once "../mod/mail/Mail.php";
require_once "../mod/auth/Session.php";
require_once "../mod/auth/Usuario.php";
require_once "../mod/auth/Geral.php";

Session::start();
Middleware::guest();

$usuario = new Usuario();
$geral = new Geral();

$erro = "";
$comunidades = $geral->listarComunidadesAtivas();

$ufs = [
    "AC", "AL", "AP", "AM", "BA", "CE", "DF",
    "ES", "GO", "MA", "MT", "MS", "MG", "PA",
    "PB", "PR", "PE", "PI", "RJ", "RN", "RS",
    "RO", "RR", "SC", "SP", "SE", "TO"
];

$old = static function (string $campo, string $padrao = ""): string {
    return htmlspecialchars(
        (string) ($_POST[$campo] ?? $padrao),
        ENT_QUOTES,
        "UTF-8"
    );
};

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!Session::validateCsrf($_POST["_token"] ?? "")) {

        $erro = "Token de segurança inválido.";

    } else {

        $nome = trim((string) ($_POST["nome"] ?? ""));
        $cpf = Usuario::normalizarCpf(
            (string) ($_POST["cpf"] ?? "")
        );
        $email = Usuario::normalizarEmail(
            (string) ($_POST["email"] ?? "")
        );
        $telefone = trim(
            (string) ($_POST["telefone"] ?? "")
        );
        $idComunidade = (int) (
            $_POST["comunidade"] ?? 0
        );

        $logradouro = trim(
            (string) ($_POST["logradouro"] ?? "")
        );
        $numero = trim(
            (string) ($_POST["numero"] ?? "")
        );
        $bairro = trim(
            (string) ($_POST["bairro"] ?? "")
        );
        $cidade = trim(
            (string) ($_POST["cidade"] ?? "")
        );
        $estado = strtoupper(
            trim((string) ($_POST["estado"] ?? "RS"))
        );

        $senha = (string) ($_POST["senha"] ?? "");
        $confirmarSenha = (string) (
            $_POST["confirmar_senha"] ?? ""
        );

        $telefoneNumeros = preg_replace(
            "/\D+/",
            "",
            $telefone
        );

        $comunidade = $geral
            ->buscarComunidadeAtivaPorId(
                $idComunidade
            );

        if ($nome === "") {

            $erro = "Informe seu nome.";

        } elseif (!Usuario::cpfValido($cpf)) {

            $erro = "Informe um CPF válido.";

        } elseif ($usuario->cpfExiste($cpf)) {

            $erro = "Este CPF já está cadastrado.";

        } elseif (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {

            $erro = "Informe um e-mail válido.";

        } elseif ($usuario->emailExiste($email)) {

            $erro = "Este e-mail já está cadastrado.";

        } elseif (
            strlen($telefoneNumeros) < 10
            || strlen($telefoneNumeros) > 11
        ) {

            $erro = "Informe um telefone válido com DDD.";

        } elseif (!$comunidade) {

            $erro = "Selecione uma comunidade válida.";

        } elseif ($logradouro === "") {

            $erro = "Informe o logradouro.";

        } elseif ($numero === "") {

            $erro = "Informe o número do endereço.";

        } elseif ($bairro === "") {

            $erro = "Informe o bairro.";

        } elseif ($cidade === "") {

            $erro = "Informe a cidade.";

        } elseif (!in_array($estado, $ufs, true)) {

            $erro = "Selecione um estado válido.";

        } elseif (
            !preg_match(
                "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$&-])[A-Za-z\d!@#$&-]{6,}$/",
                $senha
            )
        ) {

            $erro = "A senha deve possuir no mínimo 6 caracteres, contendo letra maiúscula, letra minúscula, número e caractere especial (! @ # $ & -).";

        } elseif ($senha !== $confirmarSenha) {

            $erro = "As senhas não conferem.";

        } else {

            $dados = [
                "nome" => $nome,
                "cpf" => $cpf,
                "email" => $email,
                "telefone" => $telefone,
                "senha" => $senha,
                "comunidade" => $idComunidade,
                "logradouro" => $logradouro,
                "numero" => $numero,
                "bairro" => $bairro,
                "cidade" => $cidade,
                "estado" => $estado
            ];

            try {

                $id = $usuario->cadastrar($dados);

                if (!$id) {
                    throw new RuntimeException(
                        "Não foi possível gravar o usuário."
                    );
                }

                try {

                    $nomeEmail = $nome;

                    ob_start();
                    include "../mod/mail/templates/novo_usuario.php";
                    $html = ob_get_clean();

                    $mail = new Mail();
                    $mail->send(
                        $email,
                        $nomeEmail,
                        "Bem-vindo ao Sistema",
                        $html
                    );

                } catch (Throwable $mailErro) {

                    error_log(
                        "Cadastro criado, mas o e-mail de boas-vindas falhou: "
                        . $mailErro->getMessage()
                    );
                }

                Session::flash(
                    "success",
                    "Cadastro realizado com sucesso. Faça seu login."
                );

                header("Location: index.php");
                exit;

            } catch (PDOException $e) {

                if ((string) $e->getCode() === "23000") {

                    $mensagem = strtolower(
                        $e->getMessage()
                    );

                    if (str_contains($mensagem, "cpf")) {
                        $erro = "Este CPF já está cadastrado.";
                    } else {
                        $erro = "Este e-mail já está cadastrado.";
                    }

                } else {

                    error_log(
                        "Erro ao cadastrar usuário: "
                        . $e->getMessage()
                    );

                    $erro = "Não foi possível concluir o cadastro.";
                }

            } catch (Throwable $e) {

                error_log(
                    "Erro ao cadastrar usuário: "
                    . $e->getMessage()
                );

                $erro = "Não foi possível concluir o cadastro.";
            }
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
        HeaderHTML::metaTags(
            $titulo->getNome("Cadastro"),
            $titulo->getDescricao(),
            $titulo->getKeyword(),
            $titulo->getFavicon()
        );
    }

    ?>

    <link
        rel="stylesheet"
        href="../theme/css/login.css"
    >
</head>

<body class="bg-light logins">

    <div class="container py-5">

        <div class="row justify-content-center">

            <div class="col-xl-9 col-lg-10">

                <div class="card shadow-lg border-0">

                    <div class="card-body p-4 p-md-5">

                        <div class="text-center mb-4">

                            <h2>Criar conta</h2>

                            <p class="text-muted mb-0">
                                Preencha seus dados para acessar o sistema.
                            </p>

                        </div>

                        <?php if ($erro): ?>

                            <div class="alert alert-danger">
                                <?= htmlspecialchars(
                                    $erro,
                                    ENT_QUOTES,
                                    "UTF-8"
                                ); ?>
                            </div>

                        <?php endif; ?>

                        <form
                            method="POST"
                            id="formCadastro"
                            autocomplete="on"
                            data-verificar-url="ajax/verificar-disponibilidade.php"
                        >

                            <input
                                type="hidden"
                                name="_token"
                                id="_token"
                                value="<?= Session::csrf(); ?>"
                            >

                            <h5 class="border-bottom pb-2 mb-3">
                                <i class="fa fa-user me-1"></i>
                                Dados pessoais
                            </h5>

                            <div class="row">

                                <div class="col-md-7 mb-3">

                                    <label
                                        class="form-label"
                                        for="nome"
                                    >
                                        Nome completo
                                    </label>

                                    <input
                                        type="text"
                                        name="nome"
                                        id="nome"
                                        class="form-control"
                                        maxlength="150"
                                        autocomplete="name"
                                        required
                                        value="<?= $old("nome"); ?>"
                                    >

                                </div>

                                <div class="col-md-5 mb-3">

                                    <label
                                        class="form-label"
                                        for="cpf"
                                    >
                                        CPF
                                    </label>

                                    <input
                                        type="text"
                                        name="cpf"
                                        id="cpf"
                                        class="form-control"
                                        inputmode="numeric"
                                        maxlength="14"
                                        autocomplete="off"
                                        required
                                        value="<?= $old("cpf"); ?>"
                                    >

                                    <div
                                        id="cpfFeedback"
                                        class="form-text"
                                        aria-live="polite"
                                    ></div>

                                </div>

                                <div class="col-md-7 mb-3">

                                    <label
                                        class="form-label"
                                        for="email"
                                    >
                                        E-mail
                                    </label>

                                    <input
                                        type="email"
                                        name="email"
                                        id="email"
                                        class="form-control"
                                        maxlength="150"
                                        autocomplete="email"
                                        required
                                        value="<?= $old("email"); ?>"
                                    >

                                    <div
                                        id="emailFeedback"
                                        class="form-text"
                                        aria-live="polite"
                                    ></div>

                                </div>

                                <div class="col-md-5 mb-3">

                                    <label
                                        class="form-label"
                                        for="telefone"
                                    >
                                        Telefone
                                    </label>

                                    <input
                                        type="tel"
                                        name="telefone"
                                        id="telefone"
                                        class="form-control"
                                        maxlength="15"
                                        inputmode="tel"
                                        autocomplete="tel"
                                        required
                                        value="<?= $old("telefone"); ?>"
                                    >

                                </div>

                                <div class="col-12 mb-4">

                                    <label
                                        class="form-label"
                                        for="comunidade"
                                    >
                                        Comunidade
                                    </label>

                                    <select
                                        name="comunidade"
                                        id="comunidade"
                                        class="form-select"
                                        required
                                    >

                                        <option value="">
                                            Selecione a comunidade
                                        </option>

                                        <?php foreach ($comunidades as $item): ?>

                                            <option
                                                value="<?= (int) $item["id"]; ?>"
                                                <?= (
                                                    (int) ($_POST["comunidade"] ?? 0)
                                                    === (int) $item["id"]
                                                ) ? "selected" : ""; ?>
                                            >
                                                <?= htmlspecialchars(
                                                    $item["nome_comunidade"],
                                                    ENT_QUOTES,
                                                    "UTF-8"
                                                ); ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>

                            <h5 class="border-bottom pb-2 mb-3">
                                <i class="fa fa-location-dot me-1"></i>
                                Endereço
                            </h5>

                            <div class="row">

                                <div class="col-md-8 mb-3">

                                    <label
                                        class="form-label"
                                        for="logradouro"
                                    >
                                        Logradouro
                                    </label>

                                    <input
                                        type="text"
                                        name="logradouro"
                                        id="logradouro"
                                        class="form-control"
                                        maxlength="180"
                                        autocomplete="address-line1"
                                        required
                                        value="<?= $old("logradouro"); ?>"
                                    >

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label
                                        class="form-label"
                                        for="numero"
                                    >
                                        Número
                                    </label>

                                    <input
                                        type="text"
                                        name="numero"
                                        id="numero"
                                        class="form-control"
                                        maxlength="20"
                                        autocomplete="address-line2"
                                        required
                                        value="<?= $old("numero"); ?>"
                                    >

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label
                                        class="form-label"
                                        for="bairro"
                                    >
                                        Bairro
                                    </label>

                                    <input
                                        type="text"
                                        name="bairro"
                                        id="bairro"
                                        class="form-control"
                                        maxlength="120"
                                        required
                                        value="<?= $old("bairro"); ?>"
                                    >

                                </div>

                                <div class="col-md-5 mb-3">

                                    <label
                                        class="form-label"
                                        for="cidade"
                                    >
                                        Cidade
                                    </label>

                                    <input
                                        type="text"
                                        name="cidade"
                                        id="cidade"
                                        class="form-control"
                                        maxlength="120"
                                        autocomplete="address-level2"
                                        required
                                        value="<?= $old("cidade"); ?>"
                                    >

                                </div>

                                <div class="col-md-3 mb-4">

                                    <label
                                        class="form-label"
                                        for="estado"
                                    >
                                        Estado
                                    </label>

                                    <?php
                                    $estadoSelecionado = strtoupper(
                                        trim(
                                            (string) (
                                                $_POST["estado"]
                                                ?? "RS"
                                            )
                                        )
                                    );
                                    ?>

                                    <select
                                        name="estado"
                                        id="estado"
                                        class="form-select"
                                        autocomplete="address-level1"
                                        required
                                    >

                                        <?php foreach ($ufs as $uf): ?>

                                            <option
                                                value="<?= $uf; ?>"
                                                <?= (
                                                    $estadoSelecionado === $uf
                                                ) ? "selected" : ""; ?>
                                            >
                                                <?= $uf; ?>
                                            </option>

                                        <?php endforeach; ?>

                                    </select>

                                </div>

                            </div>

                            <h5 class="border-bottom pb-2 mb-3">
                                <i class="fa fa-lock me-1"></i>
                                Segurança
                            </h5>

                            <div class="row">

                                <div class="col-md-6 mb-3">

                                    <label
                                        class="form-label"
                                        for="senha"
                                    >
                                        Senha
                                    </label>

                                    <div class="input-group">

                                        <input
                                            type="password"
                                            name="senha"
                                            id="senha"
                                            class="form-control"
                                            autocomplete="new-password"
                                            minlength="6"
                                            required
                                        >

                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary toggleSenhaCadastro"
                                            data-target="senha"
                                            aria-label="Mostrar senha"
                                        >
                                            <i class="fa fa-eye"></i>
                                        </button>

                                    </div>

                                </div>

                                <div class="col-md-6 mb-3">

                                    <label
                                        class="form-label"
                                        for="confirmar_senha"
                                    >
                                        Confirmar senha
                                    </label>

                                    <div class="input-group">

                                        <input
                                            type="password"
                                            name="confirmar_senha"
                                            id="confirmar_senha"
                                            class="form-control"
                                            autocomplete="new-password"
                                            minlength="6"
                                            required
                                        >

                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary toggleSenhaCadastro"
                                            data-target="confirmar_senha"
                                            aria-label="Mostrar senha"
                                        >
                                            <i class="fa fa-eye"></i>
                                        </button>

                                    </div>

                                </div>

                                <div class="col-12 mb-4">

                                    <div
                                        id="requisitosSenha"
                                        class="small row"
                                    >

                                        <div
                                            id="reqMaiuscula"
                                            class="text-danger col-md-6"
                                        >
                                            <i class="fa fa-times"></i>
                                            1 letra maiúscula
                                        </div>

                                        <div
                                            id="reqMinuscula"
                                            class="text-danger col-md-6"
                                        >
                                            <i class="fa fa-times"></i>
                                            1 letra minúscula
                                        </div>

                                        <div
                                            id="reqNumero"
                                            class="text-danger col-md-6"
                                        >
                                            <i class="fa fa-times"></i>
                                            1 número
                                        </div>

                                        <div
                                            id="reqEspecial"
                                            class="text-danger col-md-6"
                                        >
                                            <i class="fa fa-times"></i>
                                            1 caractere especial
                                        </div>

                                        <div
                                            id="reqTamanho"
                                            class="text-danger col-md-6"
                                        >
                                            <i class="fa fa-times"></i>
                                            Mínimo de 6 caracteres
                                        </div>

                                        <div
                                            id="reqIgual"
                                            class="text-danger col-md-6"
                                        >
                                            <i class="fa fa-times"></i>
                                            Senhas devem ser iguais
                                        </div>

                                    </div>

                                </div>

                            </div>

                            <div class="d-grid">

                                <button
                                    type="submit"
                                    class="btn btn-primary"
                                    id="btnCadastrar"
                                >
                                    <i class="fa fa-user-plus"></i>
                                    Cadastrar
                                </button>

                            </div>

                            <div class="text-center mt-4">

                                <a href="index.php">
                                    Já possui uma conta? Entrar
                                </a>

                            </div>

                        </form>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <script>
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".toggleSenhaCadastro").forEach(function (botao) {
                botao.addEventListener("click", function () {
                    const targetId = this.getAttribute("data-target");
                    const campo = document.getElementById(targetId);
                    const icone = this.querySelector("i");

                    if (!campo) {
                        return;
                    }

                    const mostrar = campo.type === "password";

                    campo.type = mostrar ? "text" : "password";

                    if (icone) {
                        icone.classList.toggle("fa-eye", !mostrar);
                        icone.classList.toggle("fa-eye-slash", mostrar);
                    }

                    this.setAttribute(
                        "aria-label",
                        mostrar ? "Ocultar senha" : "Mostrar senha"
                    );
                });
            });
        });
    </script>

    <script src="../theme/js/login/login.js"></script>
    <script src="../theme/js/login/cadastro.js"></script>

</body>

</html>