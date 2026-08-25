<?php

declare(strict_types=1);

/**
 * Verifica se /arquivos/ ainda está rastreado ou
 * referenciado por código executável do projeto.
 *
 * Execute:
 *
 * php tools/verificar-arquivos-legados.php
 */

$raiz = dirname(__DIR__);

function valExecutar(string $comando): array
{
    $saida = [];
    $codigo = 0;

    exec(
        $comando . " 2>&1",
        $saida,
        $codigo
    );

    return [$codigo, $saida];
}

function valNormalizar(string $arquivo): string
{
    return str_replace(
        "\\",
        "/",
        trim($arquivo)
    );
}

[$codigoGit] =
    valExecutar(
        "git --version"
    );

if ($codigoGit !== 0) {
    fwrite(
        STDERR,
        "[ERRO] Git não encontrado."
        . PHP_EOL
    );

    exit(1);
}

[$codigoLista, $arquivos] =
    valExecutar(
        "git -C "
        . escapeshellarg($raiz)
        . " ls-files"
    );

if ($codigoLista !== 0) {
    fwrite(
        STDERR,
        "[ERRO] Não foi possível listar "
        . "os arquivos rastreados."
        . PHP_EOL
    );

    exit(1);
}

$rastreadosArquivos = [];
$referenciasExecutaveis = [];

$extensoesExecutaveis = [
    "php",
    "js",
    "mjs",
    "cjs"
];

foreach ($arquivos as $relativoOriginal) {
    $relativo =
        valNormalizar(
            (string) $relativoOriginal
        );

    if ($relativo === "") {
        continue;
    }

    if (
        $relativo === "arquivos"
        || str_starts_with(
            $relativo,
            "arquivos/"
        )
    ) {
        $rastreadosArquivos[] =
            $relativo;

        continue;
    }

    $extensao =
        strtolower(
            pathinfo(
                $relativo,
                PATHINFO_EXTENSION
            )
        );

    if (
        !in_array(
            $extensao,
            $extensoesExecutaveis,
            true
        )
    ) {
        continue;
    }

    $caminho =
        $raiz
        . DIRECTORY_SEPARATOR
        . str_replace(
            "/",
            DIRECTORY_SEPARATOR,
            $relativo
        );

    if (!is_file($caminho)) {
        continue;
    }

    $conteudo =
        file_get_contents(
            $caminho
        );

    if (!is_string($conteudo)) {
        continue;
    }

    if (
        preg_match(
            '~(?<![A-Za-z0-9_])/?arquivos[\\\\/]~i',
            $conteudo
        )
    ) {
        $referenciasExecutaveis[] =
            $relativo;
    }
}

echo "======================================" . PHP_EOL;
echo "VERIFICAÇÃO DE /arquivos/" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo PHP_EOL;

if ($referenciasExecutaveis !== []) {
    echo
        "[ERRO] Código executável ainda referencia "
        . "/arquivos/:"
        . PHP_EOL;

    foreach (
        $referenciasExecutaveis
        as $arquivo
    ) {
        echo
            "  - "
            . $arquivo
            . PHP_EOL;
    }

    echo PHP_EOL;
    echo
        "Não remova /arquivos/ do repositório "
        . "até revisar essas referências."
        . PHP_EOL;

    exit(1);
}

echo
    "[OK] Nenhum PHP/JS rastreado fora da pasta "
    . "depende de /arquivos/."
    . PHP_EOL;

if ($rastreadosArquivos === []) {
    echo
        "[OK] /arquivos/ não está rastreado pelo Git."
        . PHP_EOL;
} else {
    echo
        "[AVISO] "
        . count($rastreadosArquivos)
        . " item(ns) de /arquivos/ "
        . "ainda estão rastreados."
        . PHP_EOL;
}

exit(
    $rastreadosArquivos === []
        ? 0
        : 2
);
