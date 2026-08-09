<?php

declare(strict_types=1);

require_once '../../../config/settings.php';

Middleware::auth();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function responderRelatorio(array $dados, int $http = 200): never
{
    http_response_code($http);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    responderRelatorio([
        'sucesso' => false,
        'mensagem' => 'Método não permitido.'
    ], 405);
}

if (!Session::validateCsrf((string) ($_POST['_token'] ?? ''))) {
    responderRelatorio([
        'sucesso' => false,
        'mensagem' => 'Token de segurança inválido. Atualize a página.'
    ], 419);
}

$dataInicio = trim((string) ($_POST['dataInicio'] ?? ''));
$dataFim = trim((string) ($_POST['dataFim'] ?? ''));
$idEvento = filter_var(
    $_POST['idEvento'] ?? 0,
    FILTER_VALIDATE_INT,
    ['options' => ['default' => 0, 'min_range' => 0]]
);

try {
    $relatorio = (new Financeiro($db))->relatorio(
        $dataInicio,
        $dataFim,
        (int) $idEvento
    );

    responderRelatorio([
        'sucesso' => true,
        'relatorio' => $relatorio
    ]);
} catch (InvalidArgumentException $erro) {
    responderRelatorio([
        'sucesso' => false,
        'mensagem' => $erro->getMessage()
    ], 422);
} catch (Throwable $erro) {
    error_log('Erro em relatorio-financeiro.php: ' . $erro->getMessage());

    responderRelatorio([
        'sucesso' => false,
        'mensagem' => 'Não foi possível gerar o relatório financeiro.'
    ], 500);
}
