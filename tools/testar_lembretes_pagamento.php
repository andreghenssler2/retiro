<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Somente terminal.');
}

require_once dirname(__DIR__) . '/mod/services/LembreteVencimentoEmailService.php';

$fuso = new DateTimeZone('America/Sao_Paulo');
$hoje = new DateTimeImmutable('today', $fuso);
$testes = [
    [3, $hoje->modify('+3 days')->format('Y-m-d')],
    [1, $hoje->modify('+1 day')->format('Y-m-d')],
    [0, $hoje->format('Y-m-d')],
    [-1, $hoje->modify('-1 day')->format('Y-m-d')],
];
foreach ($testes as [$esperado, $vencimento]) {
    $real = LembreteVencimentoEmailService::diasAteVencimento($vencimento, $hoje->format('Y-m-d'));
    if ($real !== $esperado) {
        fwrite(STDERR, "[ERRO] Data {$vencimento}: esperado {$esperado}, obtido " . var_export($real, true) . "\n");
        exit(1);
    }
}
echo "[OK] Datas e antecedencias: 3 e 1 dias antes; vencimento e data passada.\n";

if (!in_array('--consultar', $argv, true)) {
    echo "[INFO] Nenhum banco acessado e nenhum e-mail enviado.\n";
    echo "[INFO] Para consultar cobrancas elegiveis sem enviar: php tools/testar_lembretes_pagamento.php --consultar\n";
    exit(0);
}

$config = require dirname(__DIR__) . '/config/conn.php';
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $config['host'], $config['database'], $config['charset'] ?? 'utf8mb4');
$db = new PDO($dsn, $config['username'], $config['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$resumo = (new LembreteVencimentoEmailService($db))->simular();
echo '[OK] Previa (SEM ENVIAR): ' . json_encode($resumo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
