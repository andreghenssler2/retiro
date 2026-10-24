<?php

declare(strict_types=1);

ob_start();

require_once __DIR__ . "/../config/settings.php";
require_once __DIR__ . "/CalendarExport.php";

Middleware::auth();
AtividadeUsuario::ignorarRequisicaoAtual();

if (ob_get_length() !== false) {
    ob_clean();
}

header("Content-Type: application/json; charset=UTF-8");
header("Cache-Control: private, no-store, no-cache, must-revalidate");

try {
    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        http_response_code(405);
        header("Allow: POST");

        echo json_encode(
            ["error" => "Método não permitido."],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    if (!Session::validateCsrf($_POST["_token"] ?? "")) {
        http_response_code(419);

        echo json_encode(
            [
                "error" =>
                    "Token de segurança inválido. Atualize a página."
            ],
            JSON_UNESCAPED_UNICODE
        );
        exit;
    }

    $idUsuario = (int) (Auth::id() ?? 0);

    if ($idUsuario <= 0) {
        throw new RuntimeException("Usuário não identificado.");
    }

    $token = CalendarExport::regenerarToken(
        $db,
        $idUsuario
    );
    $url = BASE_URL
        . "calendar/feed.php?token="
        . rawurlencode($token);

    echo json_encode(
        [
            "success" => true,
            "url" => $url,
            "webcal" => preg_replace(
                '/^https?:\/\//i',
                'webcal://',
                $url
            )
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_THROW_ON_ERROR
    );
} catch (Throwable $erro) {
    http_response_code(500);

    error_log(
        "Erro ao gerar URL do calendário"
        . " | usuario="
        . (int) (Auth::id() ?? 0)
        . " | erro="
        . $erro->getMessage()
    );

    echo json_encode(
        [
            "error" =>
                "Não foi possível gerar uma nova URL do calendário."
        ],
        JSON_UNESCAPED_UNICODE
    );
}
