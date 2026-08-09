<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/settings.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso permitido somente pela linha de comando.');
}

try {
    $credenciamento = new Credenciamento($db);
    $resultado = $credenciamento->finalizarEventosEncerrados();

    echo sprintf(
        "[%s] Eventos finalizados: %d | Ausências registradas: %d%s",
        date('d/m/Y H:i:s'),
        (int) $resultado['eventosFinalizados'],
        (int) $resultado['ausenciasRegistradas'],
        PHP_EOL
    );
} catch (Throwable $erro) {
    fwrite(
        STDERR,
        sprintf(
            "[%s] Erro: %s%s",
            date('d/m/Y H:i:s'),
            $erro->getMessage(),
            PHP_EOL
        )
    );
    exit(1);
}
