<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();
header("Content-Type: application/json; charset=utf-8");

function responderSincronizacaoAsaas(bool $sucesso, string $mensagem, array $dados = [], int $http = 200): never
{
    http_response_code($http);
    echo json_encode(
        array_merge(["sucesso" => $sucesso, "mensagem" => $mensagem], $dados),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderSincronizacaoAsaas(false, "Método inválido.", [], 405);
}

if (!Session::validateCsrf((string) ($_POST["_token"] ?? ""))) {
    responderSincronizacaoAsaas(false, "Token de segurança inválido.", [], 419);
}

$idPagamento = (int) ($_POST["idPagamento"] ?? 0);

if ($idPagamento <= 0) {
    responderSincronizacaoAsaas(false, "Pagamento inválido.", [], 422);
}

try {
    $servico = new AsaasPagamentoService($db);
    $pagamento = $servico->sincronizarCobranca($idPagamento);

    responderSincronizacaoAsaas(
        true,
        "Situação consultada no Asaas.",
        ["pagamento" => $pagamento]
    );
} catch (InvalidArgumentException | RuntimeException $erro) {
    responderSincronizacaoAsaas(false, $erro->getMessage(), [], 422);
} catch (Throwable $erro) {
    error_log("Erro ao sincronizar pagamento Asaas #{$idPagamento}: " . $erro->getMessage());
    responderSincronizacaoAsaas(false, "Não foi possível consultar o Asaas.", [], 500);
}
