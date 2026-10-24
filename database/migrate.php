<?php

declare(strict_types=1);

if (PHP_SAPI !== "cli") {
    http_response_code(404);
    exit;
}

$raiz =
    dirname(__DIR__);

$configArquivo =
    $raiz
    . "/config/conn.php";

if (!is_file($configArquivo)) {
    fwrite(
        STDERR,
        "[ERRO] config/conn.php não encontrado."
        . PHP_EOL
    );

    exit(1);
}

$config =
    require $configArquivo;

if (!is_array($config)) {
    fwrite(
        STDERR,
        "[ERRO] config/conn.php inválido."
        . PHP_EOL
    );

    exit(1);
}

foreach (
    [
        "host",
        "database",
        "username",
        "password"
    ]
    as $campo
) {
    if (
        !array_key_exists(
            $campo,
            $config
        )
    ) {
        fwrite(
            STDERR,
            "[ERRO] Campo ausente em config/conn.php: "
            . $campo
            . PHP_EOL
        );

        exit(1);
    }
}

$charset =
    trim(
        (string) (
            $config["charset"]
            ?? "utf8mb4"
        )
    );

if ($charset === "") {
    $charset = "utf8mb4";
}

$dsn =
    "mysql:host="
    . (string) $config["host"]
    . ";dbname="
    . (string) $config["database"]
    . ";charset="
    . $charset;

try {
    $db =
        new PDO(
            $dsn,
            (string) $config["username"],
            (string) $config["password"],
            [
                PDO::ATTR_ERRMODE =>
                    PDO::ERRMODE_EXCEPTION,

                PDO::ATTR_DEFAULT_FETCH_MODE =>
                    PDO::FETCH_ASSOC,

                PDO::ATTR_EMULATE_PREPARES =>
                    false
            ]
        );
} catch (Throwable $erro) {
    fwrite(
        STDERR,
        "[ERRO] Não foi possível conectar ao banco: "
        . $erro->getMessage()
        . PHP_EOL
    );

    exit(1);
}

require_once __DIR__
    . "/MigrationRunner.php";

$runner =
    new MigrationRunner(
        $db,
        __DIR__
        . "/migrations"
    );

$comando =
    strtolower(
        trim(
            (string) (
                $argv[1]
                ?? "status"
            )
        )
    );

try {
    if (
        in_array(
            $comando,
            [
                "migrate",
                "migrar",
                "up"
            ],
            true
        )
    ) {
        echo
            "======================================"
            . PHP_EOL;
        echo
            "MIGRATIONS"
            . PHP_EOL;
        echo
            "======================================"
            . PHP_EOL;
        echo PHP_EOL;

        $quantidade =
            $runner->migrar();

        echo PHP_EOL;

        if ($quantidade === 0) {
            echo
                "[OK] Banco já está atualizado."
                . PHP_EOL;
        } else {
            echo
                "[OK] "
                . $quantidade
                . " migration(s) aplicada(s)."
                . PHP_EOL;
        }

        exit(0);
    }

    if (
        in_array(
            $comando,
            [
                "status",
                "list",
                "listar"
            ],
            true
        )
    ) {
        $status =
            $runner->status();

        echo
            "======================================"
            . PHP_EOL;
        echo
            "STATUS DAS MIGRATIONS"
            . PHP_EOL;
        echo
            "======================================"
            . PHP_EOL;
        echo PHP_EOL;

        if ($status === []) {
            echo
                "Nenhuma migration encontrada."
                . PHP_EOL;

            exit(0);
        }

        foreach ($status as $item) {
            $aplicada =
                (bool) $item["aplicada"];

            $checksumOk =
                !$aplicada
                || (string) $item[
                    "checksumBanco"
                ] === ""
                || hash_equals(
                    (string) $item[
                        "checksumBanco"
                    ],
                    (string) $item[
                        "checksum"
                    ]
                );

            $prefixo =
                $aplicada
                    ? (
                        $checksumOk
                            ? "[APLICADA]"
                            : "[ALTERADA]"
                    )
                    : "[PENDENTE]";

            echo
                $prefixo
                . " "
                . $item["id"]
                . " - "
                . $item["descricao"];

            if (
                $aplicada
                && $item["executadoEm"]
            ) {
                echo
                    " ("
                    . $item["executadoEm"]
                    . ")";
            }

            echo PHP_EOL;
        }

        exit(0);
    }

    if (
        in_array(
            $comando,
            [
                "help",
                "--help",
                "-h"
            ],
            true
        )
    ) {
        echo
            "Uso:"
            . PHP_EOL
            . PHP_EOL;

        echo
            "  php database/migrate.php status"
            . PHP_EOL;

        echo
            "  php database/migrate.php migrate"
            . PHP_EOL;

        exit(0);
    }

    fwrite(
        STDERR,
        "[ERRO] Comando desconhecido: "
        . $comando
        . PHP_EOL
        . "Use: status ou migrate"
        . PHP_EOL
    );

    exit(1);
} catch (Throwable $erro) {
    fwrite(
        STDERR,
        "[ERRO] "
        . $erro->getMessage()
        . PHP_EOL
    );

    exit(1);
}
