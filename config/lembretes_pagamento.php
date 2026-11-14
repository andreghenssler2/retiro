<?php

declare(strict_types=1);

// Avisos de PIX e boleto com vencimento em 3 dias e em 1 dia.
// Executados pelo cron unificado: nao e necessario cadastrar novo cron.
return [
    'ativo' => true,
    'limite_por_execucao' => 8,
    'permitir_envio_local' => false, // protecao para XAMPP/WAMP/Laragon
];
