<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

header("Content-Type: application/json; charset=utf-8");

try {

    Middleware::auth();

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {

        http_response_code(405);

        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Método de requisição não permitido."
        ]);

        exit;
    }

    $token = $_POST["_token"] ?? "";

    if (!Session::validateCsrf($token)) {

        http_response_code(419);

        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Token de segurança inválido."
        ]);

        exit;
    }

    $idEvento = filter_input(
        INPUT_POST,
        "evento",
        FILTER_VALIDATE_INT
    );

    $idEvento = $idEvento !== false && $idEvento !== null
        ? $idEvento
        : 0;

    $pagamento = new Pagamento($db);

    /*
     * Caso os seus métodos ainda não recebam $idEvento,
     * remova o parâmetro:
     *
     * $pagamento->totalRecebido();
     */

    $totalRecebido = $idEvento > 0
        ? $pagamento->totalRecebido($idEvento)
        : $pagamento->totalRecebido();

    $totalPendente = $idEvento > 0
        ? $pagamento->totalPendente($idEvento)
        : $pagamento->totalPendente();

    $totalVencido = $idEvento > 0
        ? $pagamento->totalVencido($idEvento)
        : $pagamento->totalVencido();

    $totalCancelado = $idEvento > 0
        ? $pagamento->totalCancelado($idEvento)
        : $pagamento->totalCancelado();

    echo json_encode(
        [
            "sucesso" => true,
            "recebido" => (float) $totalRecebido,
            "pendente" => (float) $totalPendente,
            "vencido" => (float) $totalVencido,
            "cancelado" => (float) $totalCancelado
        ],
        JSON_UNESCAPED_UNICODE
    );

} catch (Throwable $erro) {

    error_log(
        "Erro em pagamentos-cards.php: "
        . $erro->getMessage()
    );

    http_response_code(500);

    echo json_encode(
        [
            "sucesso" => false,
            "mensagem" => "Não foi possível carregar os totais financeiros."
        ],
        JSON_UNESCAPED_UNICODE
    );
}