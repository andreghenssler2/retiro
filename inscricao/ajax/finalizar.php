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

    /*
     * IMPORTANTE:
     * não registrar $_POST neste endpoint.
     * A requisição pode conter número/CVV.
     */
    $resultado =
        $service->finalizar(
            (string) (
                $_POST["fluxo"]
                ?? ""
            ),
            $_POST
        );

    inscricaoPublicaResponder(
        true,
        (string) (
            $resultado["mensagem"]
            ?? "Inscrição processada."
        ),
        $resultado
    );
} catch (Throwable $erro) {
    Log::warning(
        "Falha na inscrição pública",
        [
            "evento" =>
                (int) (
                    $_POST["idEvento"]
                    ?? 0
                ),
            "erro" =>
                $erro->getMessage()
        ]
    );

    inscricaoPublicaResponder(
        false,
        $erro->getMessage(),
        [],
        400
    );
}
