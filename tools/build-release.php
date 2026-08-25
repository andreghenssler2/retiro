<?php

declare(strict_types=1);

/**
 * Gera um pacote ZIP limpo para produção.
 *
 * Requisitos:
 * - Git;
 * - Composer;
 * - extensão ZipArchive.
 *
 * Uso:
 *
 * php tools/build-release.php
 */

$raiz = dirname(__DIR__);

function brErro(string $mensagem): never
{
    fwrite(
        STDERR,
        "[ERRO] {$mensagem}" . PHP_EOL
    );

    exit(1);
}

function brExecutar(
    string $comando,
    ?string $cwd = null
): array {
    $comandoFinal = $comando;

    if ($cwd !== null) {
        if (PHP_OS_FAMILY === "Windows") {
            $comandoFinal =
                "cd /d "
                . escapeshellarg($cwd)
                . " && "
                . $comando;
        } else {
            $comandoFinal =
                "cd "
                . escapeshellarg($cwd)
                . " && "
                . $comando;
        }
    }

    $saida = [];
    $codigo = 0;

    exec(
        $comandoFinal . " 2>&1",
        $saida,
        $codigo
    );

    return [$codigo, $saida];
}

function brRemoverDiretorio(string $diretorio): void
{
    if (!is_dir($diretorio)) {
        return;
    }

    $itens =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $diretorio,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::CHILD_FIRST
        );

    foreach ($itens as $item) {
        if ($item->isDir()) {
            @rmdir(
                $item->getPathname()
            );
        } else {
            @unlink(
                $item->getPathname()
            );
        }
    }

    @rmdir($diretorio);
}

function brRemoverCaminho(string $caminho): void
{
    if (is_dir($caminho)) {
        brRemoverDiretorio($caminho);
        return;
    }

    if (is_file($caminho)) {
        @unlink($caminho);
    }
}

function brAdicionarZip(
    ZipArchive $zip,
    string $base,
    string $diretorio
): void {
    $itens =
        new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $diretorio,
                FilesystemIterator::SKIP_DOTS
            ),
            RecursiveIteratorIterator::SELF_FIRST
        );

    foreach ($itens as $item) {
        $caminho =
            $item->getPathname();

        $relativo =
            str_replace(
                "\\",
                "/",
                substr(
                    $caminho,
                    strlen($base) + 1
                )
            );

        if ($item->isDir()) {
            $zip->addEmptyDir($relativo);
        } else {
            $zip->addFile(
                $caminho,
                $relativo
            );
        }
    }
}

if (!class_exists("ZipArchive")) {
    brErro(
        "A extensão PHP ZipArchive não está disponível."
    );
}

[$codigoGit] =
    brExecutar(
        "git --version"
    );

if ($codigoGit !== 0) {
    brErro("Git não encontrado.");
}

[$codigoComposer] =
    brExecutar(
        "composer --version"
    );

if ($codigoComposer !== 0) {
    brErro(
        "Composer não encontrado no PATH."
    );
}

[$codigoStatus, $status] =
    brExecutar(
        "git -C "
        . escapeshellarg($raiz)
        . " status --porcelain"
    );

if ($codigoStatus !== 0) {
    brErro(
        "Não foi possível consultar git status."
    );
}

if ($status !== []) {
    brErro(
        "O repositório possui alterações não commitadas. "
        . "Faça commit antes de gerar uma release reproduzível."
    );
}

$versionArquivo =
    $raiz
    . "/mod/version.php";

if (!is_file($versionArquivo)) {
    brErro(
        "mod/version.php não encontrado."
    );
}

$version =
    require $versionArquivo;

if (!is_array($version)) {
    brErro(
        "mod/version.php inválido."
    );
}

$versao =
    preg_replace(
        '/[^0-9A-Za-z._-]+/',
        "-",
        (string) (
            $version["version"]
            ?? "sem-versao"
        )
    );

$build =
    (int) (
        $version["build"]
        ?? 0
    );

$dist =
    $raiz
    . "/dist";

if (
    !is_dir($dist)
    && !mkdir(
        $dist,
        0755,
        true
    )
    && !is_dir($dist)
) {
    brErro(
        "Não foi possível criar /dist."
    );
}

$temp =
    sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . "retiro-release-"
    . bin2hex(
        random_bytes(6)
    );

$staging =
    $temp
    . DIRECTORY_SEPARATOR
    . "retiro";

if (
    !mkdir(
        $staging,
        0755,
        true
    )
) {
    brErro(
        "Não foi possível criar staging."
    );
}

$archive =
    $temp
    . DIRECTORY_SEPARATOR
    . "source.zip";

try {
    echo
        "[1/5] Exportando HEAD do Git..."
        . PHP_EOL;

    [$codigoArchive, $saidaArchive] =
        brExecutar(
            "git -C "
            . escapeshellarg($raiz)
            . " archive --format=zip "
            . "--output="
            . escapeshellarg($archive)
            . " HEAD"
        );

    if ($codigoArchive !== 0) {
        brErro(
            "git archive falhou:"
            . PHP_EOL
            . implode(
                PHP_EOL,
                $saidaArchive
            )
        );
    }

    $zipFonte =
        new ZipArchive();

    if (
        $zipFonte->open($archive)
        !== true
    ) {
        brErro(
            "Não foi possível abrir o archive temporário."
        );
    }

    if (
        !$zipFonte->extractTo(
            $staging
        )
    ) {
        $zipFonte->close();

        brErro(
            "Não foi possível extrair o archive."
        );
    }

    $zipFonte->close();

    echo
        "[2/5] Removendo arquivos que não pertencem "
        . "ao pacote de produção..."
        . PHP_EOL;

    $remover = [
        ".git",
        ".github",
        "arquivos",
        "dist",
        "logs",
        "config/conn.php",
        "config/integracoes.php",
        "config/.bancario.key",
        "lib/vendor"
    ];

    foreach ($remover as $relativo) {
        brRemoverCaminho(
            $staging
            . DIRECTORY_SEPARATOR
            . str_replace(
                "/",
                DIRECTORY_SEPARATOR,
                $relativo
            )
        );
    }

    foreach (
        glob(
            $staging
            . DIRECTORY_SEPARATOR
            . "atualizar-*.php"
        ) ?: []
        as $instalador
    ) {
        @unlink($instalador);
    }

    foreach (
        glob(
            $staging
            . DIRECTORY_SEPARATOR
            . "*.log"
        ) ?: []
        as $log
    ) {
        @unlink($log);
    }

    echo
        "[3/5] Instalando dependências Composer..."
        . PHP_EOL;

    $lib =
        $staging
        . DIRECTORY_SEPARATOR
        . "lib";

    if (
        !is_file(
            $lib
            . DIRECTORY_SEPARATOR
            . "composer.json"
        )
        || !is_file(
            $lib
            . DIRECTORY_SEPARATOR
            . "composer.lock"
        )
    ) {
        brErro(
            "composer.json/composer.lock não encontrados em /lib."
        );
    }

    [$codigoInstall, $saidaInstall] =
        brExecutar(
            "composer install "
            . "--no-dev "
            . "--prefer-dist "
            . "--optimize-autoloader "
            . "--no-interaction "
            . "--no-progress",
            $lib
        );

    foreach ($saidaInstall as $linha) {
        echo $linha . PHP_EOL;
    }

    if ($codigoInstall !== 0) {
        brErro(
            "composer install falhou."
        );
    }

    echo
        "[4/5] Verificando segredos no staging..."
        . PHP_EOL;

    $proibidos = [
        "config/conn.php",
        "config/integracoes.php",
        "config/.bancario.key"
    ];

    foreach ($proibidos as $relativo) {
        $caminho =
            $staging
            . DIRECTORY_SEPARATOR
            . str_replace(
                "/",
                DIRECTORY_SEPARATOR,
                $relativo
            );

        if (file_exists($caminho)) {
            brErro(
                "Arquivo sensível entrou no staging: "
                . $relativo
            );
        }
    }

    echo
        "[5/5] Criando ZIP..."
        . PHP_EOL;

    $nome =
        "retiro-"
        . $versao
        . "-build"
        . $build
        . ".zip";

    $destino =
        $dist
        . DIRECTORY_SEPARATOR
        . $nome;

    if (is_file($destino)) {
        @unlink($destino);
    }

    $zip =
        new ZipArchive();

    if (
        $zip->open(
            $destino,
            ZipArchive::CREATE
            | ZipArchive::OVERWRITE
        ) !== true
    ) {
        brErro(
            "Não foi possível criar o ZIP final."
        );
    }

    brAdicionarZip(
        $zip,
        $staging,
        $staging
    );

    $zip->close();

    if (
        !is_file($destino)
        || filesize($destino) === 0
    ) {
        brErro(
            "ZIP final não foi criado corretamente."
        );
    }

    echo PHP_EOL;
    echo "======================================" . PHP_EOL;
    echo "RELEASE GERADA" . PHP_EOL;
    echo "======================================" . PHP_EOL;
    echo PHP_EOL;

    echo
        "[OK] "
        . $destino
        . PHP_EOL;

    echo
        "[OK] Versão "
        . $versao
        . " | Build "
        . $build
        . PHP_EOL;

    echo
        "[OK] config/conn.php não incluído"
        . PHP_EOL;

    echo
        "[OK] lib/vendor gerado pelo Composer"
        . PHP_EOL;
} finally {
    brRemoverDiretorio(
        $temp
    );
}
