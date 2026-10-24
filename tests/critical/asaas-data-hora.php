<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__, 2);

require_once
    $raiz
    . '/mod/services/AsaasWebhookDataHoraService.php';

$ok = 0;
$falhas = 0;

$testar = static function (
    string $nome,
    callable $teste
) use (&$ok, &$falhas): void {
    try {
        $teste();
        $ok++;
        echo '[OK] ' . $nome . PHP_EOL;
    } catch (Throwable $erro) {
        $falhas++;
        echo '[FALHA] ' . $nome . PHP_EOL;
        echo '        ' . $erro->getMessage() . PHP_EOL;
    }
};

$igual = static function (
    mixed $esperado,
    mixed $atual
): void {
    if ($esperado !== $atual) {
        throw new RuntimeException(
            'Esperado '
            . var_export($esperado, true)
            . ', obtido '
            . var_export($atual, true)
        );
    }
};

echo "======================================" . PHP_EOL;
echo "TESTE - DATA/HORA WEBHOOK ASAAS" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo PHP_EOL;

$testar(
    'Webhook UTC é convertido para America/Sao_Paulo',
    static function () use ($igual): void {
        $igual(
            '2026-08-26 19:15:30',
            AsaasWebhookDataHoraService::normalizarDataHora(
                '2026-08-26T22:15:30.000Z'
            )
        );
    }
);

$testar(
    'Webhook com offset é convertido para America/Sao_Paulo',
    static function () use ($igual): void {
        $igual(
            '2026-08-26 19:15:30',
            AsaasWebhookDataHoraService::normalizarDataHora(
                '2026-08-26T20:15:30-02:00'
            )
        );
    }
);

$testar(
    'Data/hora sem offset é tratada como horário local',
    static function () use ($igual): void {
        $igual(
            '2026-08-26 19:15:30',
            AsaasWebhookDataHoraService::normalizarDataHora(
                '2026-08-26 19:15:30'
            )
        );
    }
);

$testar(
    'dateCreated é extraído do payload',
    static function () use ($igual): void {
        $igual(
            '2026-08-26 19:15:30',
            AsaasWebhookDataHoraService::dataHoraEvento([
                'dateCreated' => '2026-08-26T22:15:30Z'
            ])
        );
    }
);

$testar(
    'Valor inválido não inventa data/hora',
    static function () use ($igual): void {
        $igual(
            null,
            AsaasWebhookDataHoraService::normalizarDataHora(
                'data-invalida'
            )
        );

        $igual(
            null,
            AsaasWebhookDataHoraService::dataHoraEvento([])
        );
    }
);

echo PHP_EOL;
echo 'OK: ' . $ok . PHP_EOL;
echo 'FALHAS: ' . $falhas . PHP_EOL;

exit($falhas === 0 ? 0 : 1);
