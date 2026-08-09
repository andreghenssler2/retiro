<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/settings.php";
require_once __DIR__ . "/../mod/auth/Geral.php";

Session::start();
Auth::requireLogin();

$pageStyles = [
    THEME_CSS
    . "user/perfil.css?v="
    . VERSION
];

$pageScripts = [
    THEME_JS
    . "user/perfil.js?v="
    . VERSION
];

$idUsuario = (int) (Auth::id() ?? 0);

if ($idUsuario <= 0) {
    header("Location: " . BASE_URL . "login/");
    exit;
}

$usuarioModel = new Usuario();
$geral = new Geral();

$usuario = $usuarioModel->buscar($idUsuario);

if (!$usuario) {
    Auth::logout();
    header("Location: " . BASE_URL . "login/");
    exit;
}

$ufs = [
    "AC", "AL", "AP", "AM", "BA", "CE", "DF",
    "ES", "GO", "MA", "MT", "MS", "MG", "PA",
    "PB", "PR", "PE", "PI", "RJ", "RN", "RS",
    "RO", "RR", "SC", "SP", "SE", "TO"
];

$comunidades = $geral->listarComunidadesAtivas();

function userEscapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function userTamanho(string $valor): int
{
    return function_exists("mb_strlen")
        ? mb_strlen($valor, "UTF-8")
        : strlen($valor);
}

function userCpfFormatado(string $cpf): string
{
    $numeros = Usuario::normalizarCpf($cpf);

    if (strlen($numeros) !== 11) {
        return $cpf;
    }

    return substr($numeros, 0, 3)
        . "."
        . substr($numeros, 3, 3)
        . "."
        . substr($numeros, 6, 3)
        . "-"
        . substr($numeros, 9, 2);
}

function userFotoNome(array $usuario): string
{
    $foto = trim((string) ($usuario["foto"] ?? ""));

    if ($foto === "") {
        return "user.png";
    }

    return basename(str_replace("\\", "/", $foto));
}

function userFotoUrl(array $usuario): string
{
    return UPLOAD_USUARIOS_URL
        . rawurlencode(userFotoNome($usuario));
}

/**
 * @return array{nome:string,caminho:string}|null
 */
function userSalvarFoto(int $idUsuario): ?array
{
    if (
        !isset($_FILES["foto"])
        || !is_array($_FILES["foto"])
        || (int) ($_FILES["foto"]["error"] ?? UPLOAD_ERR_NO_FILE)
            === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    $arquivo = $_FILES["foto"];
    $erro = (int) ($arquivo["error"] ?? UPLOAD_ERR_NO_FILE);

    if ($erro !== UPLOAD_ERR_OK) {
        $mensagens = [
            UPLOAD_ERR_INI_SIZE => "A foto excede o limite do servidor.",
            UPLOAD_ERR_FORM_SIZE => "A foto excede o limite permitido.",
            UPLOAD_ERR_PARTIAL => "A foto foi enviada parcialmente.",
            UPLOAD_ERR_NO_TMP_DIR => "A pasta temporária não está disponível.",
            UPLOAD_ERR_CANT_WRITE => "Não foi possível gravar a foto.",
            UPLOAD_ERR_EXTENSION => "Uma extensão do PHP bloqueou o envio."
        ];

        throw new RuntimeException(
            $mensagens[$erro]
            ?? "Não foi possível enviar a foto."
        );
    }

    $temporario = (string) ($arquivo["tmp_name"] ?? "");
    $tamanho = (int) ($arquivo["size"] ?? 0);
    $nomeOriginal = (string) ($arquivo["name"] ?? "");

    if (
        $temporario === ""
        || !is_uploaded_file($temporario)
    ) {
        throw new RuntimeException(
            "O arquivo enviado não é uma foto válida."
        );
    }

    if ($tamanho <= 0 || $tamanho > 3 * 1024 * 1024) {
        throw new InvalidArgumentException(
            "A foto deve possuir no máximo 3 MB."
        );
    }

    $extensao = strtolower(
        (string) pathinfo($nomeOriginal, PATHINFO_EXTENSION)
    );

    $tiposPermitidos = [
        "jpg" => ["image/jpeg"],
        "jpeg" => ["image/jpeg"],
        "png" => ["image/png"],
        "webp" => ["image/webp"]
    ];

    if (!array_key_exists($extensao, $tiposPermitidos)) {
        throw new InvalidArgumentException(
            "Envie uma foto JPG, PNG ou WEBP."
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string) $finfo->file($temporario));

    if (!in_array($mime, $tiposPermitidos[$extensao], true)) {
        throw new InvalidArgumentException(
            "O conteúdo da foto não corresponde ao formato informado."
        );
    }

    if (
        !is_dir(UPLOAD_USUARIOS)
        && !mkdir(UPLOAD_USUARIOS, 0755, true)
        && !is_dir(UPLOAD_USUARIOS)
    ) {
        throw new RuntimeException(
            "Não foi possível criar a pasta uploads/usuarios."
        );
    }

    if (!is_writable(UPLOAD_USUARIOS)) {
        throw new RuntimeException(
            "A pasta uploads/usuarios não possui permissão de escrita."
        );
    }

    /*
     * O nome é fixo por usuário. Ao trocar o formato,
     * a versão anterior é removida.
     */
    foreach (
        glob(UPLOAD_USUARIOS . "usuario-" . $idUsuario . ".*") ?: []
        as $arquivoAnterior
    ) {
        if (is_file($arquivoAnterior)) {
            @unlink($arquivoAnterior);
        }
    }

    $nomeNovo = "usuario-" . $idUsuario . "." . $extensao;
    $destino = UPLOAD_USUARIOS . $nomeNovo;

    if (!move_uploaded_file($temporario, $destino)) {
        throw new RuntimeException(
            "Não foi possível salvar a foto do perfil."
        );
    }

    @chmod($destino, 0644);

    return [
        "nome" => $nomeNovo,
        "caminho" => $destino
    ];
}

function userExcluirFotoAntiga(
    string $fotoAntiga,
    string $fotoNova
): void {
    $fotoAntiga = basename(
        str_replace("\\", "/", trim($fotoAntiga))
    );

    $fotoNova = basename(
        str_replace("\\", "/", trim($fotoNova))
    );

    if (
        $fotoAntiga === ""
        || $fotoAntiga === "user.png"
        || $fotoAntiga === $fotoNova
    ) {
        return;
    }

    $arquivo = UPLOAD_USUARIOS . $fotoAntiga;

    if (is_file($arquivo)) {
        @unlink($arquivo);
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!Session::validateCsrf($_POST["_token"] ?? "")) {
        Session::flash(
            "error",
            "Token de segurança inválido. Atualize a página e tente novamente."
        );

        header("Location: " . BASE_URL . "user/index.php");
        exit;
    }

    /*
     * CPF e e-mail não são lidos do formulário.
     * Mesmo que a requisição seja alterada manualmente,
     * esses dois campos continuam com os valores do banco.
     */
    $nome = trim((string) ($_POST["nome"] ?? ""));
    $telefone = trim((string) ($_POST["telefone"] ?? ""));
    $idComunidade = (int) ($_POST["idComunidade"] ?? 0);
    $logradouro = trim((string) ($_POST["logradouro"] ?? ""));
    $numero = trim((string) ($_POST["numero"] ?? ""));
    $bairro = trim((string) ($_POST["bairro"] ?? ""));
    $cidade = trim((string) ($_POST["cidade"] ?? ""));
    $estado = strtoupper(
        trim((string) ($_POST["estado"] ?? "RS"))
    );

    $senhaAtual = (string) ($_POST["senha_atual"] ?? "");
    $novaSenha = (string) ($_POST["nova_senha"] ?? "");
    $confirmarSenha = (string) ($_POST["confirmar_senha"] ?? "");

    $removerFoto = isset($_POST["remover_foto"])
        && $_POST["remover_foto"] === "1";

    $telefoneNumeros = preg_replace(
        "/\D+/",
        "",
        $telefone
    ) ?? "";

    $fotoAntiga = userFotoNome($usuario);
    $fotoParaSalvar = "";
    $uploadNovo = null;

    try {
        if ($nome === "") {
            throw new InvalidArgumentException(
                "Informe seu nome completo."
            );
        }

        if (userTamanho($nome) > 150) {
            throw new InvalidArgumentException(
                "O nome deve possuir no máximo 150 caracteres."
            );
        }

        if (
            strlen($telefoneNumeros) < 10
            || strlen($telefoneNumeros) > 11
        ) {
            throw new InvalidArgumentException(
                "Informe um telefone válido com DDD."
            );
        }

        if (!$geral->buscarComunidadeAtivaPorId($idComunidade)) {
            throw new InvalidArgumentException(
                "Selecione uma comunidade válida."
            );
        }

        if (
            $logradouro === ""
            || $numero === ""
            || $bairro === ""
            || $cidade === ""
            || !in_array($estado, $ufs, true)
        ) {
            throw new InvalidArgumentException(
                "Preencha o endereço completo."
            );
        }

        $alterarSenha =
            $senhaAtual !== ""
            || $novaSenha !== ""
            || $confirmarSenha !== "";

        if ($alterarSenha) {
            if (
                $senhaAtual === ""
                || !password_verify(
                    $senhaAtual,
                    (string) $usuario["senha"]
                )
            ) {
                throw new InvalidArgumentException(
                    "A senha atual está incorreta."
                );
            }

            if (
                !preg_match(
                    "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$&-])[A-Za-z\d!@#$&-]{6,}$/",
                    $novaSenha
                )
            ) {
                throw new InvalidArgumentException(
                    "A nova senha deve possuir no mínimo 6 caracteres, com letra maiúscula, minúscula, número e caractere especial (! @ # $ & -)."
                );
            }

            if ($novaSenha !== $confirmarSenha) {
                throw new InvalidArgumentException(
                    "A confirmação da nova senha não confere."
                );
            }

            if (password_verify(
                $novaSenha,
                (string) $usuario["senha"]
            )) {
                throw new InvalidArgumentException(
                    "A nova senha deve ser diferente da senha atual."
                );
            }
        }

        if ($removerFoto) {
            $fotoParaSalvar = "user.png";
        } else {
            $uploadNovo = userSalvarFoto($idUsuario);

            if ($uploadNovo !== null) {
                $fotoParaSalvar = (string) $uploadNovo["nome"];
            }
        }

        $campos = [
            "nome = :nome",
            "telefone = :telefone",
            "idComunidade = :idComunidade",
            "logradouro = :logradouro",
            "numero = :numero",
            "bairro = :bairro",
            "cidade = :cidade",
            "estado = :estado"
        ];

        $parametros = [
            ":nome" => $nome,
            ":telefone" => $telefone,
            ":idComunidade" => $idComunidade,
            ":logradouro" => $logradouro,
            ":numero" => $numero,
            ":bairro" => $bairro,
            ":cidade" => $cidade,
            ":estado" => $estado,
            ":id" => $idUsuario
        ];

        if ($fotoParaSalvar !== "") {
            $campos[] = "foto = :foto";
            $parametros[":foto"] = $fotoParaSalvar;
        }

        if ($alterarSenha) {
            $campos[] = "senha = :senha";
            $parametros[":senha"] = password_hash(
                $novaSenha,
                PASSWORD_DEFAULT
            );
        }

        $sql = "
            UPDATE usuarios
            SET " . implode(",\n", $campos) . "
            WHERE id = :id
            LIMIT 1
        ";

        $stmt = $db->prepare($sql);
        $salvo = $stmt->execute($parametros);

        if (!$salvo) {
            throw new RuntimeException(
                "Não foi possível atualizar o perfil."
            );
        }

        $fotoFinal = $fotoParaSalvar !== ""
            ? $fotoParaSalvar
            : $fotoAntiga;

        if ($fotoParaSalvar !== "") {
            userExcluirFotoAntiga(
                $fotoAntiga,
                $fotoFinal
            );
        }

        $_SESSION["user"]["nome"] = $nome;
        $_SESSION["user"]["foto"] =
            "<img src='"
            . UPLOAD_USUARIOS_URL
            . rawurlencode($fotoFinal)
            . "' alt='Avatar' class='rounded-circle shadow avatar-image'>";

        if ($alterarSenha) {
            Session::regenerate();
        }

        Session::flash(
            "success",
            $alterarSenha
                ? "Perfil e senha atualizados com sucesso."
                : "Perfil atualizado com sucesso."
        );
    } catch (Throwable $erro) {
        if (
            is_array($uploadNovo)
            && isset($uploadNovo["caminho"])
            && is_file((string) $uploadNovo["caminho"])
        ) {
            @unlink((string) $uploadNovo["caminho"]);
        }

        error_log(
            "Erro ao atualizar perfil do usuário"
            . " | usuario=" . $idUsuario
            . " | erro=" . $erro->getMessage()
        );

        Session::flash(
            "error",
            $erro instanceof InvalidArgumentException
                ? $erro->getMessage()
                : "Não foi possível atualizar o perfil."
        );
    }

    header("Location: " . BASE_URL . "user/index.php");
    exit;
}

$usuario = $usuarioModel->buscar($idUsuario);

if (!$usuario) {
    Auth::logout();
    header("Location: " . BASE_URL . "login/");
    exit;
}

$fotoAtualUrl = userFotoUrl($usuario);
$mensagemSucesso = Session::getFlash("success");
$mensagemErro = Session::getFlash("error");

$perfilNome = match ((int) ($usuario["tipo"] ?? 0)) {
    1 => "Administrador",
    2 => "Moderador",
    3 => "Participante",
    default => "Usuário"
};

require_once __DIR__ . "/../admin/includes/header.php";
require_once __DIR__ . "/../admin/includes/navbar.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<div class="content user-perfil-page" id="content">
    <div class="container-fluid">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">
                    <i class="fa-solid fa-user-gear text-primary me-2"></i>
                    Meu perfil
                </h2>

                <p class="text-muted mb-0">
                    Atualize seus dados pessoais, endereço, foto e senha.
                </p>
            </div>

            <span class="badge rounded-pill text-bg-primary fs-6">
                <i class="fa-solid fa-user-shield me-1"></i>
                <?= userEscapar($perfilNome); ?>
            </span>
        </div>

        <?php if ($mensagemSucesso): ?>
            <div
                class="alert alert-success alert-dismissible fade show"
                role="alert"
            >
                <i class="fa-solid fa-circle-check me-1"></i>
                <?= userEscapar((string) $mensagemSucesso); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Fechar"
                ></button>
            </div>
        <?php endif; ?>

        <?php if ($mensagemErro): ?>
            <div
                class="alert alert-danger alert-dismissible fade show"
                role="alert"
            >
                <i class="fa-solid fa-circle-exclamation me-1"></i>
                <?= userEscapar((string) $mensagemErro); ?>

                <button
                    type="button"
                    class="btn-close"
                    data-bs-dismiss="alert"
                    aria-label="Fechar"
                ></button>
            </div>
        <?php endif; ?>

        <form
            method="post"
            enctype="multipart/form-data"
            autocomplete="on"
            id="formPerfil"
        >
            <input
                type="hidden"
                name="_token"
                value="<?= userEscapar(Session::csrf()); ?>"
            >

            <div class="row g-4">

                <div class="col-xl-4">
                    <div class="card shadow-sm border-0 user-card-foto">
                        <div class="card-body text-center p-4">

                            <div class="user-foto-area mx-auto mb-3">
                                <img
                                    src="<?= userEscapar($fotoAtualUrl); ?>"
                                    alt="Foto do usuário"
                                    id="previewFoto"
                                    data-foto-atual="<?= userEscapar($fotoAtualUrl); ?>"
                                    data-foto-padrao="<?= userEscapar(
                                        UPLOAD_USUARIOS_URL . "user.png"
                                    ); ?>"
                                >
                            </div>

                            <h4 class="mb-1">
                                <?= userEscapar(
                                    (string) $usuario["nome"]
                                ); ?>
                            </h4>

                            <span class="badge text-bg-light border text-secondary mb-3">
                                <i class="fa-solid fa-id-badge me-1"></i>
                                <?= userEscapar($perfilNome); ?>
                            </span>

                            <div class="text-start">
                                <label
                                    for="foto"
                                    class="form-label fw-semibold"
                                >
                                    Alterar foto
                                </label>

                                <input
                                    type="file"
                                    class="form-control"
                                    id="foto"
                                    name="foto"
                                    accept=".jpg,.jpeg,.png,.webp"
                                >

                                <div class="form-text">
                                    JPG, PNG ou WEBP. Máximo de 3 MB.
                                </div>

                                <div class="form-check mt-3">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        value="1"
                                        id="removerFoto"
                                        name="remover_foto"
                                    >

                                    <label
                                        class="form-check-label"
                                        for="removerFoto"
                                    >
                                        Remover a foto atual
                                    </label>
                                </div>
                            </div>

                            <hr>

                            <div class="user-detalhes text-start">
                                <div>
                                    <span>Cadastro</span>
                                    <strong>
                                        <?= !empty($usuario["created_at"])
                                            ? date(
                                                "d/m/Y",
                                                strtotime(
                                                    (string) $usuario["created_at"]
                                                )
                                            )
                                            : "-"; ?>
                                    </strong>
                                </div>

                                <div>
                                    <span>Último acesso</span>
                                    <strong>
                                        <?= !empty($usuario["ultimo_login"])
                                            ? date(
                                                "d/m/Y H:i",
                                                strtotime(
                                                    (string) $usuario["ultimo_login"]
                                                )
                                            )
                                            : "Primeiro acesso"; ?>
                                    </strong>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="col-xl-8">

                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">
                                <i class="fa-solid fa-address-card me-1 text-primary"></i>
                                Dados pessoais
                            </h5>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-7">
                                    <label for="nome" class="form-label">
                                        Nome completo
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="nome"
                                        name="nome"
                                        maxlength="150"
                                        autocomplete="name"
                                        required
                                        value="<?= userEscapar(
                                            (string) $usuario["nome"]
                                        ); ?>"
                                    >
                                </div>

                                <div class="col-md-5">
                                    <label for="cpf" class="form-label">
                                        CPF
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control bg-light"
                                        id="cpf"
                                        value="<?= userEscapar(
                                            userCpfFormatado(
                                                (string) $usuario["cpf"]
                                            )
                                        ); ?>"
                                        readonly
                                        aria-readonly="true"
                                    >

                                    <div class="form-text">
                                        O CPF não pode ser alterado.
                                    </div>
                                </div>

                                <div class="col-md-7">
                                    <label for="email" class="form-label">
                                        E-mail
                                    </label>

                                    <input
                                        type="email"
                                        class="form-control bg-light"
                                        id="email"
                                        value="<?= userEscapar(
                                            (string) $usuario["email"]
                                        ); ?>"
                                        readonly
                                        aria-readonly="true"
                                    >

                                    <div class="form-text">
                                        O e-mail não pode ser alterado.
                                    </div>
                                </div>

                                <div class="col-md-5">
                                    <label for="telefone" class="form-label">
                                        Telefone
                                    </label>

                                    <input
                                        type="tel"
                                        class="form-control"
                                        id="telefone"
                                        name="telefone"
                                        maxlength="15"
                                        inputmode="tel"
                                        autocomplete="tel"
                                        required
                                        value="<?= userEscapar(
                                            (string) ($usuario["telefone"] ?? "")
                                        ); ?>"
                                    >
                                </div>

                                <div class="col-12">
                                    <label
                                        for="idComunidade"
                                        class="form-label"
                                    >
                                        Comunidade
                                    </label>

                                    <select
                                        class="form-select"
                                        id="idComunidade"
                                        name="idComunidade"
                                        required
                                    >
                                        <option value="">
                                            Selecione a comunidade
                                        </option>

                                        <?php foreach ($comunidades as $comunidade): ?>
                                            <option
                                                value="<?= (int) $comunidade["id"]; ?>"
                                                <?= (int) $usuario["idComunidade"]
                                                    === (int) $comunidade["id"]
                                                    ? "selected"
                                                    : ""; ?>
                                            >
                                                <?= userEscapar(
                                                    (string) $comunidade["nome_comunidade"]
                                                ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">
                                <i class="fa-solid fa-location-dot me-1 text-primary"></i>
                                Endereço
                            </h5>
                        </div>

                        <div class="card-body">
                            <div class="row g-3">

                                <div class="col-md-8">
                                    <label for="logradouro" class="form-label">
                                        Logradouro
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="logradouro"
                                        name="logradouro"
                                        maxlength="180"
                                        autocomplete="address-line1"
                                        required
                                        value="<?= userEscapar(
                                            (string) ($usuario["logradouro"] ?? "")
                                        ); ?>"
                                    >
                                </div>

                                <div class="col-md-4">
                                    <label for="numero" class="form-label">
                                        Número
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="numero"
                                        name="numero"
                                        maxlength="20"
                                        autocomplete="address-line2"
                                        required
                                        value="<?= userEscapar(
                                            (string) ($usuario["numero"] ?? "")
                                        ); ?>"
                                    >
                                </div>

                                <div class="col-md-4">
                                    <label for="bairro" class="form-label">
                                        Bairro
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="bairro"
                                        name="bairro"
                                        maxlength="120"
                                        required
                                        value="<?= userEscapar(
                                            (string) ($usuario["bairro"] ?? "")
                                        ); ?>"
                                    >
                                </div>

                                <div class="col-md-5">
                                    <label for="cidade" class="form-label">
                                        Cidade
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        id="cidade"
                                        name="cidade"
                                        maxlength="120"
                                        autocomplete="address-level2"
                                        required
                                        value="<?= userEscapar(
                                            (string) ($usuario["cidade"] ?? "")
                                        ); ?>"
                                    >
                                </div>

                                <div class="col-md-3">
                                    <label for="estado" class="form-label">
                                        Estado
                                    </label>

                                    <select
                                        class="form-select"
                                        id="estado"
                                        name="estado"
                                        autocomplete="address-level1"
                                        required
                                    >
                                        <?php foreach ($ufs as $uf): ?>
                                            <option
                                                value="<?= $uf; ?>"
                                                <?= strtoupper(
                                                    (string) ($usuario["estado"] ?? "RS")
                                                ) === $uf
                                                    ? "selected"
                                                    : ""; ?>
                                            >
                                                <?= $uf; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0 mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">
                                <i class="fa-solid fa-key me-1 text-primary"></i>
                                Alterar senha
                            </h5>
                        </div>

                        <div class="card-body">
                            <p class="text-muted">
                                Deixe os campos vazios para manter a senha atual.
                            </p>

                            <div class="row g-3">

                                <div class="col-12">
                                    <label
                                        for="senhaAtual"
                                        class="form-label"
                                    >
                                        Senha atual
                                    </label>

                                    <div class="input-group">
                                        <input
                                            type="password"
                                            class="form-control"
                                            id="senhaAtual"
                                            name="senha_atual"
                                            autocomplete="current-password"
                                        >

                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary toggle-senha"
                                            data-alvo="senhaAtual"
                                            aria-label="Mostrar ou ocultar senha"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label
                                        for="novaSenha"
                                        class="form-label"
                                    >
                                        Nova senha
                                    </label>

                                    <div class="input-group">
                                        <input
                                            type="password"
                                            class="form-control"
                                            id="novaSenha"
                                            name="nova_senha"
                                            autocomplete="new-password"
                                        >

                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary toggle-senha"
                                            data-alvo="novaSenha"
                                            aria-label="Mostrar ou ocultar senha"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label
                                        for="confirmarSenha"
                                        class="form-label"
                                    >
                                        Confirmar nova senha
                                    </label>

                                    <div class="input-group">
                                        <input
                                            type="password"
                                            class="form-control"
                                            id="confirmarSenha"
                                            name="confirmar_senha"
                                            autocomplete="new-password"
                                        >

                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary toggle-senha"
                                            data-alvo="confirmarSenha"
                                            aria-label="Mostrar ou ocultar senha"
                                        >
                                            <i class="fa-solid fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-text">
                                        A nova senha precisa ter letra maiúscula,
                                        minúscula, número e um dos caracteres:
                                        <strong>! @ # $ &amp; -</strong>.
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button
                            type="submit"
                            class="btn btn-primary px-4"
                        >
                            <i class="fa-solid fa-floppy-disk me-1"></i>
                            Salvar alterações
                        </button>
                    </div>

                </div>

            </div>
        </form>

    </div>
</div>

<?php require_once __DIR__ . "/../admin/includes/footer.php"; ?>