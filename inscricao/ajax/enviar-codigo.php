<?php

declare(strict_types=1);

require_once __DIR__
    . "/../../config/settings.php";

Session::start();

header(
    "Content-Type: application/json; charset=UTF-8"
);

header(
    "Cache-Control: no-store, no-cache, must-revalidate, max-age=0"
);

function inscricaoPublicaResponder(
    bool $status,
    string $mensagem,
    array $dados = [],
    int $http = 200
): never {
    http_response_code($http);

    echo json_encode(
        [
            "status" => $status,
            "mensagem" => $mensagem,
            "dados" => $dados
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

if (
    $_SERVER["REQUEST_METHOD"]
    !== "POST"
) {
    inscricaoPublicaResponder(
        false,
        "Método não permitido.",
        [],
        405
    );
}

if (
    !Session::validateCsrf(
        $_POST["_token"] ?? ""
    )
) {
    inscricaoPublicaResponder(
        false,
        "Token de segurança inválido. Atualize a página.",
        [],
        419
    );
}

try {
    $service =
        new InscricaoPublicaService(
            $db
        );

    $idEvento = filter_input(
        INPUT_POST,
        "idEvento",
        FILTER_VALIDATE_INT
    ) ?: 0;

    $resultado =
        $service->enviarCodigo(
            $idEvento,
            (string) (
                $_POST["email"]
                ?? ""
            )
        );

    inscricaoPublicaResponder(
        true,
        "Código enviado para o e-mail informado.",
        $resultado
    );
} catch (Throwable $erro) {
    inscricaoPublicaResponder(
        false,
        $erro->getMessage(),
        [],
        400
    );
}
