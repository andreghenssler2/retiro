<?php

declare(strict_types=1);

/**
 * Sincroniza todos os clientes Asaas conhecidos
 * pelo banco local para:
 *
 * notificationDisabled = true
 *
 * Execute:
 *
 * php sincronizar-asaas-sem-notificacoes-v2.php
 */

require_once __DIR__
    . "/config/settings.php";

$asaas =
    new AsaasService();

if (!$asaas->estaConfigurado()) {
    fwrite(
        STDERR,
        "[ERRO] A integração Asaas "
        . "não está configurada ou ativa."
        . PHP_EOL
    );

    exit(1);
}

echo
    "Ambiente: "
    . strtoupper(
        $asaas->ambiente()
    )
    . PHP_EOL;

$clientes = [];

/*
|--------------------------------------------------------------------------
| Função auxiliar para descobrir se coluna existe
|--------------------------------------------------------------------------
*/

function asn2ColunaExiste(
    PDO $db,
    string $tabela,
    string $coluna
): bool {
    $stmt =
        $db->query(
            "SHOW COLUMNS FROM `"
            . str_replace(
                "`",
                "``",
                $tabela
            )
            . "` LIKE "
            . $db->quote($coluna)
        );

    return
        $stmt !== false
        && $stmt->fetch()
            !== false;
}

/*
|--------------------------------------------------------------------------
| usuarios
|--------------------------------------------------------------------------
*/

if (
    asn2ColunaExiste(
        $db,
        "usuarios",
        "asaasCustomerId"
    )
) {
    $stmt =
        $db->query("
            SELECT DISTINCT
                asaasCustomerId
            FROM usuarios
            WHERE
                asaasCustomerId
                    IS NOT NULL
                AND TRIM(
                    asaasCustomerId
                ) <> ''
        ");

    if ($stmt) {
        foreach (
            $stmt->fetchAll(
                PDO::FETCH_COLUMN
            )
            as $id
        ) {
            $id =
                trim(
                    (string) $id
                );

            if ($id !== "") {
                $clientes[$id] =
                    true;
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| pagamentos
|--------------------------------------------------------------------------
*/

if (
    asn2ColunaExiste(
        $db,
        "pagamentos",
        "asaasCustomerId"
    )
) {
    $stmt =
        $db->query("
            SELECT DISTINCT
                asaasCustomerId
            FROM pagamentos
            WHERE
                asaasCustomerId
                    IS NOT NULL
                AND TRIM(
                    asaasCustomerId
                ) <> ''
        ");

    if ($stmt) {
        foreach (
            $stmt->fetchAll(
                PDO::FETCH_COLUMN
            )
            as $id
        ) {
            $id =
                trim(
                    (string) $id
                );

            if ($id !== "") {
                $clientes[$id] =
                    true;
            }
        }
    }
}

$ids =
    array_keys(
        $clientes
    );

echo
    "Clientes encontrados: "
    . count($ids)
    . PHP_EOL;

if ($ids === []) {
    echo
        "[OK] Nenhum cliente antigo "
        . "precisa ser sincronizado."
        . PHP_EOL;

    exit(0);
}

$ok = 0;
$falhas = 0;

foreach ($ids as $idAsaas) {
    try {
        $cliente =
            $asaas
                ->desabilitarNotificacoesCliente(
                    $idAsaas
                );

        if (
            array_key_exists(
                "notificationDisabled",
                $cliente
            )
            && $cliente[
                "notificationDisabled"
            ] !== true
        ) {
            throw new RuntimeException(
                "O retorno não confirmou "
                . "notificationDisabled=true."
            );
        }

        echo
            "[OK] "
            . $idAsaas
            . PHP_EOL;

        $ok++;
    } catch (Throwable $erro) {
        echo
            "[FALHA] "
            . $idAsaas
            . " -> "
            . $erro->getMessage()
            . PHP_EOL;

        $falhas++;
    }

    usleep(100000);
}

echo PHP_EOL;
echo "Atualizados: {$ok}" . PHP_EOL;
echo "Falhas:      {$falhas}" . PHP_EOL;

exit(
    $falhas > 0
        ? 2
        : 0
);
