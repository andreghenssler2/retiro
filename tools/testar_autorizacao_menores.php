<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/mod/services/InscricaoPublicaService.php';

$evento = [
    'titulo' => 'Retiro de Jovens',
    'cidade' => 'Parobé',
    'data_inicio' => '2026-11-08',
    'data_fim' => '2026-11-10',
];

$casos = [
    '17 anos no evento' => ['2008-11-09', true],
    '18 anos no início do evento' => ['2008-11-08', false],
    '19 anos no evento' => ['2007-11-08', false],
];

foreach ($casos as $rotulo => [$nascimento, $esperado]) {
    $recebido = InscricaoPublicaService::ehMenorNoEvento($nascimento, $evento);
    if ($recebido !== $esperado) {
        fwrite(STDERR, "[FALHA] {$rotulo}\n");
        exit(1);
    }
    echo "[OK] {$rotulo}\n";
}

$texto = InscricaoPublicaService::textoAutorizacaoMenor($evento);
foreach (['Retiro de Jovens', 'Parobé', '08/11/2026', '10/11/2026'] as $parte) {
    if (!str_contains($texto, $parte)) {
        fwrite(STDERR, "[FALHA] Texto da autorização não contém {$parte}\n");
        exit(1);
    }
}

echo "[OK] Texto dinâmico do evento\n";
echo "Todos os testes da regra de idade e texto passaram.\n";
