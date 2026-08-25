<?php

declare(strict_types=1);

/**
 * Limpeza automática do índice Git.
 *
 * Remove do ÍNDICE, sem apagar do disco:
 *
 * - config/conn.php;
 * - config/integracoes.php;
 * - config/.bancario.key;
 * - *.log;
 * - *.bak e *.bak-*;
 * - *.backup;
 * - *.tmp / *.temp;
 * - dumps SQL na raiz.
 *
 * Execute:
 *
 * php tools/limpar-indice-git.php
 */

$raiz = dirname(__DIR__);

function ligErro(
    string $mensagem
): never {
    fwrite(
        STDERR,
        "[ERRO] {$mensagem}"
        . PHP_EOL
    );

    exit(1);
}

function ligExecutar(
    string $comando
): array {
    $saida = [];
    $codigo = 0;

    exec(
        $comando
        . " 2>&1",
        $saida,
        $codigo
    );

    return [
        $codigo,
        $saida
    ];
}

function ligEhProibido(
    string $arquivo
): bool {
    $arquivo =
        str_replace(
            "\\",
            "/",
            trim($arquivo)
        );

    if ($arquivo === "") {
        return false;
    }

    if (
        in_array(
            $arquivo,
            [
                "config/conn.php",
                "config/integracoes.php",
                "config/.bancario.key"
            ],
            true
        )
    ) {
        return true;
    }

    if (
        preg_match(
            '/\.log$/i',
            $arquivo
        )
    ) {
        return true;
    }

    if (
        preg_match(
            '/\.bak(?:[-.].*)?$/i',
            $arquivo
        )
    ) {
        return true;
    }

    if (
        preg_match(
            '/\.(?:backup|tmp|temp)$/i',
            $arquivo
        )
    ) {
        return true;
    }

    /*
     * Remove dumps SQL somente da raiz.
     * SQLs dentro de database/migrations etc.
     * não são atingidos por esta regra.
     */
    if (
        !str_contains(
            $arquivo,
            "/"
        )
        && preg_match(
            '/\.sql(?:\.gz)?$/i',
            $arquivo
        )
    ) {
        return true;
    }

    return false;
}

/*
|--------------------------------------------------------------------------
| Verifica Git
|--------------------------------------------------------------------------
*/

[$codigoGit, $saidaGit] =
    ligExecutar(
        "git --version"
    );

if ($codigoGit !== 0) {
    ligErro(
        "Git não foi encontrado."
    );
}

/*
|--------------------------------------------------------------------------
| Lê arquivos rastreados
|--------------------------------------------------------------------------
*/

$arquivoTemporario =
    tempnam(
        sys_get_temp_dir(),
        "git-files-"
    );

if ($arquivoTemporario === false) {
    ligErro(
        "Não foi possível criar arquivo temporário."
    );
}

$comandoLista =
    "git -C "
    . escapeshellarg($raiz)
    . " ls-files -z > "
    . escapeshellarg(
        $arquivoTemporario
    );

[$codigoLista, $saidaLista] =
    ligExecutar(
        $comandoLista
    );

if ($codigoLista !== 0) {
    @unlink($arquivoTemporario);

    ligErro(
        "Não foi possível listar "
        . "os arquivos rastreados pelo Git."
        . PHP_EOL
        . implode(
            PHP_EOL,
            $saidaLista
        )
    );
}

$conteudo =
    file_get_contents(
        $arquivoTemporario
    );

@unlink($arquivoTemporario);

if ($conteudo === false) {
    ligErro(
        "Não foi possível ler "
        . "a lista de arquivos do Git."
    );
}

$arquivos =
    array_values(
        array_filter(
            explode(
                "\0",
                $conteudo
            ),
            static fn (
                string $valor
            ): bool =>
                trim($valor) !== ""
        )
    );

$remover = [];

foreach ($arquivos as $arquivo) {
    if (
        ligEhProibido(
            $arquivo
        )
    ) {
        $remover[] =
            str_replace(
                "\\",
                "/",
                $arquivo
            );
    }
}

$remover =
    array_values(
        array_unique(
            $remover
        )
    );

sort(
    $remover,
    SORT_NATURAL
);

echo "======================================" . PHP_EOL;
echo "LIMPEZA DO ÍNDICE GIT" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo PHP_EOL;

if ($remover === []) {
    echo
        "[OK] Nenhum arquivo proibido "
        . "está rastreado."
        . PHP_EOL;

    exit(0);
}

echo
    "Serão removidos SOMENTE do índice Git:"
    . PHP_EOL
    . PHP_EOL;

foreach ($remover as $arquivo) {
    echo
        "  - "
        . $arquivo
        . PHP_EOL;
}

echo PHP_EOL;

$falhas = [];

foreach ($remover as $arquivo) {
    $comando =
        "git -C "
        . escapeshellarg($raiz)
        . " rm --cached "
        . "--ignore-unmatch "
        . "-- "
        . escapeshellarg($arquivo);

    [$codigo, $saida] =
        ligExecutar(
            $comando
        );

    if ($codigo !== 0) {
        $falhas[] = [
            "arquivo" =>
                $arquivo,

            "saida" =>
                implode(
                    PHP_EOL,
                    $saida
                )
        ];

        echo
            "[ERRO] "
            . $arquivo
            . PHP_EOL;

        continue;
    }

    echo
        "[OK] Removido do índice: "
        . $arquivo
        . PHP_EOL;
}

if ($falhas !== []) {
    echo PHP_EOL;

    foreach ($falhas as $falha) {
        echo
            "[ERRO] "
            . $falha["arquivo"]
            . PHP_EOL;

        if (
            trim(
                (string) $falha[
                    "saida"
                ]
            ) !== ""
        ) {
            echo
                $falha["saida"]
                . PHP_EOL;
        }
    }

    exit(1);
}

echo PHP_EOL;
echo "[OK] Os arquivos continuam no disco." . PHP_EOL;
echo "[OK] Apenas deixaram de ser rastreados pelo Git." . PHP_EOL;
echo PHP_EOL;

echo "Próximo passo:" . PHP_EOL;
echo "php tools/verificar-seguranca-repositorio.php" . PHP_EOL;
