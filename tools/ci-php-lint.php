<?php

declare(strict_types=1);

/**
 * Lint de todos os PHPs rastreados pelo Git.
 *
 * Uso:
 *
 * php tools/ci-php-lint.php
 */

$raiz = dirname(__DIR__);

$saida = [];
$codigo = 0;

exec(
    "git -C "
    . escapeshellarg($raiz)
    . " ls-files -- "
    . escapeshellarg("*.php")
    . " 2>&1",
    $saida,
    $codigo
);

if ($codigo !== 0) {
    fwrite(
        STDERR,
        "[ERRO] Não foi possível listar arquivos PHP."
        . PHP_EOL
    );

    exit(1);
}

$ignorados = [
    "lib/vendor/",
    "portal_ieclb_parobe/"
];

$arquivos = [];

foreach ($saida as $relativo) {
    $relativo =
        str_replace(
            "\\",
            "/",
            trim((string) $relativo)
        );

    if ($relativo === "") {
        continue;
    }

    $ignorar = false;

    foreach ($ignorados as $prefixo) {
        if (
            str_starts_with(
                $relativo,
                $prefixo
            )
        ) {
            $ignorar = true;
            break;
        }
    }

    if ($ignorar) {
        continue;
    }

    $arquivos[] =
        $relativo;
}

if ($arquivos === []) {
    echo
        "[OK] Nenhum PHP rastreado para validar."
        . PHP_EOL;

    exit(0);
}

$php =
    PHP_BINARY !== ""
        ? PHP_BINARY
        : "php";

$falhas = [];

foreach ($arquivos as $relativo) {
    $arquivo =
        $raiz
        . DIRECTORY_SEPARATOR
        . str_replace(
            "/",
            DIRECTORY_SEPARATOR,
            $relativo
        );

    if (!is_file($arquivo)) {
        $falhas[] =
            $relativo
            . " (arquivo não encontrado)";

        continue;
    }

    $saidaLint = [];
    $codigoLint = 0;

    exec(
        escapeshellarg($php)
        . " -l "
        . escapeshellarg($arquivo)
        . " 2>&1",
        $saidaLint,
        $codigoLint
    );

    if ($codigoLint !== 0) {
        $falhas[] =
            $relativo
            . PHP_EOL
            . implode(
                PHP_EOL,
                $saidaLint
            );

        echo
            "[ERRO] "
            . $relativo
            . PHP_EOL;
    } else {
        echo
            "[OK] "
            . $relativo
            . PHP_EOL;
    }
}

echo PHP_EOL;

if ($falhas !== []) {
    fwrite(
        STDERR,
        "[ERRO] "
        . count($falhas)
        . " arquivo(s) PHP com problema."
        . PHP_EOL
    );

    exit(1);
}

echo
    "[OK] "
    . count($arquivos)
    . " arquivo(s) PHP validados."
    . PHP_EOL;

exit(0);
