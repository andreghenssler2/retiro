<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Limpeza de logs antigos
|--------------------------------------------------------------------------
|
| Pode ser executado manualmente ou por CRON.
| O prazo é lido de /mod/log/config.php.
|
*/

require_once __DIR__
    . "/../mod/classes/logs.php";

date_default_timezone_set(
    "America/Sao_Paulo"
);

try {
    $config = Log::configuracao();

    $excluidos = Log::limparAntigos(
        true
    );

    $retencao = max(
        1,
        (int) (
            $config["retencao_dias"]
            ?? 30
        )
    );

    echo "["
        . date("d/m/Y H:i:s")
        . "] Limpeza concluída. ";

    echo $excluidos
        . " arquivo(s) excluído(s). ";

    echo "Retenção: "
        . $retencao
        . " dias."
        . PHP_EOL;
} catch (Throwable $erro) {
    fwrite(
        STDERR,
        "["
        . date("d/m/Y H:i:s")
        . "] Erro na limpeza de logs: "
        . $erro->getMessage()
        . PHP_EOL
    );

    exit(1);
}
