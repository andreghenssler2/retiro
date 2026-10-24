<?php

require_once "../../config/settings.php";
require_once "../../mod/auth/Session.php";
require_once "../../mod/auth/Usuario.php";

Session::start();

header(
    "Content-Type: application/json; charset=utf-8"
);

function responder(
    bool $disponivel,
    bool $valido,
    string $mensagem
): never {

    echo json_encode(
        [
            "disponivel" => $disponivel,
            "valido" => $valido,
            "mensagem" => $mensagem
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    http_response_code(405);

    responder(
        false,
        false,
        "Método não permitido."
    );
}

if (
    !Session::validateCsrf(
        $_POST["_token"] ?? ""
    )
) {

    http_response_code(403);

    responder(
        false,
        false,
        "Token de segurança inválido."
    );
}

$campo = trim(
    (string) ($_POST["campo"] ?? "")
);

$valor = trim(
    (string) ($_POST["valor"] ?? "")
);

$usuario = new Usuario();

if ($campo === "email") {

    $email = Usuario::normalizarEmail(
        $valor
    );

    if (
        !filter_var(
            $email,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        responder(
            false,
            false,
            "Informe um e-mail válido."
        );
    }

    $existe = $usuario->emailExiste(
        $email
    );

    responder(
        !$existe,
        true,
        $existe
            ? "Este e-mail já está cadastrado."
            : "E-mail disponível."
    );
}

if ($campo === "cpf") {

    $cpf = Usuario::normalizarCpf(
        $valor
    );

    if (!Usuario::cpfValido($cpf)) {

        responder(
            false,
            false,
            "Informe um CPF válido."
        );
    }

    $existe = $usuario->cpfExiste(
        $cpf
    );

    responder(
        !$existe,
        true,
        $existe
            ? "Este CPF já está cadastrado."
            : "CPF disponível."
    );
}

http_response_code(422);

responder(
    false,
    false,
    "Campo de consulta inválido."
);
