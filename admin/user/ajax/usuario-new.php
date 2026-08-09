<?php

require_once "../../../config/settings.php";
require_once "../../../mod/auth/Geral.php";

Middleware::auth();

header("Content-Type: application/json; charset=utf-8");

function responderUsuario(
    bool $status,
    string $mensagem,
    int $http = 200
): never {
    http_response_code($http);

    echo json_encode(
        [
            "status" => $status,
            "msg" => $mensagem
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderUsuario(false, "Método não permitido.", 405);
}

if (!Session::validateCsrf($_POST["_token"] ?? "")) {
    responderUsuario(false, "Token de segurança inválido.", 403);
}

$usuario = new Usuario();
$geral = new Geral();

$id = (int) ($_POST["id"] ?? 0);
$registroAtual = $id > 0
    ? $usuario->buscar($id)
    : false;

if ($id > 0 && !$registroAtual) {
    responderUsuario(false, "Usuário não encontrado.", 404);
}

$ufs = [
    "AC", "AL", "AP", "AM", "BA", "CE", "DF",
    "ES", "GO", "MA", "MT", "MS", "MG", "PA",
    "PB", "PR", "PE", "PI", "RJ", "RN", "RS",
    "RO", "RR", "SC", "SP", "SE", "TO"
];

$nome = trim((string) ($_POST["nome"] ?? ""));
$email = Usuario::normalizarEmail(
    (string) ($_POST["email"] ?? "")
);
$cpf = Usuario::normalizarCpf(
    (string) ($_POST["cpf"] ?? "")
);
$telefone = trim((string) ($_POST["telefone"] ?? ""));
$tipo = (int) ($_POST["tipo"] ?? 3);
$ativo = isset($_POST["ativo"]) ? 1 : 0;
$idComunidade = (int) ($_POST["comunidade"] ?? 0);

$logradouro = trim((string) ($_POST["logradouro"] ?? ""));
$numero = trim((string) ($_POST["numero"] ?? ""));
$bairro = trim((string) ($_POST["bairro"] ?? ""));
$cidade = trim((string) ($_POST["cidade"] ?? ""));
$estado = strtoupper(trim((string) ($_POST["estado"] ?? "RS")));

$senha = (string) ($_POST["senha"] ?? "");
$confirmarSenha = (string) ($_POST["confirmar_senha"] ?? "");

$telefoneNumeros = preg_replace("/\D+/", "", $telefone);

if ($nome === "") {
    responderUsuario(false, "Informe o nome completo.", 422);
}

if (!Usuario::cpfValido($cpf)) {
    responderUsuario(false, "Informe um CPF válido.", 422);
}

if ($usuario->cpfExisteOutro($cpf, $id)) {
    responderUsuario(false, "Este CPF já está cadastrado.", 409);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    responderUsuario(false, "Informe um e-mail válido.", 422);
}

if ($usuario->emailExisteOutro($email, $id)) {
    responderUsuario(false, "Este e-mail já está cadastrado.", 409);
}

if (
    strlen($telefoneNumeros) < 10
    || strlen($telefoneNumeros) > 11
) {
    responderUsuario(false, "Informe um telefone válido com DDD.", 422);
}

if (!in_array($tipo, [1, 2, 3], true)) {
    responderUsuario(false, "Selecione um perfil válido.", 422);
}

$comunidade = $geral->buscarComunidadePorId($idComunidade);

if (!$comunidade) {
    responderUsuario(false, "Selecione uma comunidade válida.", 422);
}

if ($logradouro === "") {
    responderUsuario(false, "Informe o logradouro.", 422);
}

if ($numero === "") {
    responderUsuario(false, "Informe o número do endereço.", 422);
}

if ($bairro === "") {
    responderUsuario(false, "Informe o bairro.", 422);
}

if ($cidade === "") {
    responderUsuario(false, "Informe a cidade.", 422);
}

if (!in_array($estado, $ufs, true)) {
    responderUsuario(false, "Selecione um estado válido.", 422);
}

$senhaFoiInformada = $senha !== "" || $confirmarSenha !== "";

if ($id === 0 || $senhaFoiInformada) {
    if (
        !preg_match(
            "/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$&-])[A-Za-z\d!@#$&-]{6,}$/",
            $senha
        )
    ) {
        responderUsuario(
            false,
            "A senha deve possuir no mínimo 6 caracteres, letra maiúscula, letra minúscula, número e caractere especial (! @ # $ & -).",
            422
        );
    }

    if ($senha !== $confirmarSenha) {
        responderUsuario(false, "As senhas não conferem.", 422);
    }
}

$dados = [
    "id" => $id,
    "nome" => $nome,
    "email" => $email,
    "telefone" => $telefone,
    "cpf" => $cpf,
    "tipo" => $tipo,
    "ativo" => $ativo,
    "comunidade" => $idComunidade,
    "logradouro" => $logradouro,
    "numero" => $numero,
    "bairro" => $bairro,
    "cidade" => $cidade,
    "estado" => $estado
];

if ($senhaFoiInformada || $id === 0) {
    $dados["senha"] = $senha;
}

$fotoNova = "";
$fotoAntiga = (string) ($registroAtual["foto"] ?? "");

if (
    isset($_FILES["foto"])
    && $_FILES["foto"]["error"] !== UPLOAD_ERR_NO_FILE
) {
    if ($_FILES["foto"]["error"] !== UPLOAD_ERR_OK) {
        responderUsuario(false, "Erro ao enviar a foto.", 422);
    }

    if ((int) $_FILES["foto"]["size"] > 5 * 1024 * 1024) {
        responderUsuario(false, "A foto deve possuir no máximo 5 MB.", 422);
    }

    $mimePermitidos = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($_FILES["foto"]["tmp_name"]);

    if (!isset($mimePermitidos[$mime])) {
        responderUsuario(false, "Formato de imagem inválido.", 422);
    }

    $pasta = ROOT_PATH . "/uploads/usuarios/";

    if (!is_dir($pasta) && !mkdir($pasta, 0755, true)) {
        responderUsuario(false, "Não foi possível preparar a pasta de fotos.", 500);
    }

    $fotoNova = "user_"
        . bin2hex(random_bytes(12))
        . "."
        . $mimePermitidos[$mime];

    if (
        !move_uploaded_file(
            $_FILES["foto"]["tmp_name"],
            $pasta . $fotoNova
        )
    ) {
        responderUsuario(false, "Não foi possível salvar a foto.", 500);
    }

    $dados["foto"] = $fotoNova;
}

try {
    $ok = $id > 0
        ? $usuario->editar($dados)
        : $usuario->salvar($dados);

    if (!$ok) {
        throw new RuntimeException("A gravação não foi concluída.");
    }

    if (
        $fotoNova !== ""
        && $fotoAntiga !== ""
        && $fotoAntiga !== $fotoNova
    ) {
        $caminhoAntigo = ROOT_PATH
            . "/uploads/usuarios/"
            . basename($fotoAntiga);

        if (is_file($caminhoAntigo)) {
            @unlink($caminhoAntigo);
        }
    }

    if (
        $id > 0
        && isset($_SESSION["usuario_id"])
        && (int) $_SESSION["usuario_id"] === $id
    ) {
        $_SESSION["usuario_nome"] = $nome;
        $_SESSION["usuario_email"] = $email;

        if ($fotoNova !== "") {
            $_SESSION["usuario_foto"] = $fotoNova;
        }
    }

    responderUsuario(
        true,
        $id > 0
            ? "Usuário atualizado com sucesso."
            : "Usuário cadastrado com sucesso."
    );
} catch (PDOException $erro) {
    if ($fotoNova !== "") {
        $caminhoNovo = ROOT_PATH
            . "/uploads/usuarios/"
            . basename($fotoNova);

        if (is_file($caminhoNovo)) {
            @unlink($caminhoNovo);
        }
    }

    if ((string) $erro->getCode() === "23000") {
        $mensagem = strtolower($erro->getMessage());

        responderUsuario(
            false,
            str_contains($mensagem, "cpf")
                ? "Este CPF já está cadastrado."
                : "Este e-mail já está cadastrado.",
            409
        );
    }

    error_log("Erro ao salvar usuário: " . $erro->getMessage());
    responderUsuario(false, "Não foi possível salvar o usuário.", 500);
} catch (Throwable $erro) {
    if ($fotoNova !== "") {
        $caminhoNovo = ROOT_PATH
            . "/uploads/usuarios/"
            . basename($fotoNova);

        if (is_file($caminhoNovo)) {
            @unlink($caminhoNovo);
        }
    }

    error_log("Erro ao salvar usuário: " . $erro->getMessage());
    responderUsuario(false, "Não foi possível salvar o usuário.", 500);
}
