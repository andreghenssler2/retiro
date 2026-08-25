<?php

declare(strict_types=1);

$raiz = dirname(__DIR__);

$proibidos = [
    "config/conn.php",
    "config/integracoes.php",
    "config/.bancario.key"
];

$erros = [];
$avisos = [];

$saidaGit = [];
$codigoGit = 0;

@exec(
    "git --version 2>&1",
    $saidaGit,
    $codigoGit
);

if ($codigoGit !== 0) {
    $avisos[] =
        "Git não encontrado. "
        . "Não foi possível verificar arquivos rastreados.";
} else {
    $saida = [];
    $codigo = 0;

    @exec(
        "git -C "
        . escapeshellarg($raiz)
        . " ls-files 2>&1",
        $saida,
        $codigo
    );

    if ($codigo !== 0) {
        $avisos[] =
            "Não foi possível consultar o índice Git.";
    } else {
        foreach ($saida as $arquivo) {
            $arquivo =
                str_replace(
                    "\\",
                    "/",
                    trim((string) $arquivo)
                );

            if (
                in_array(
                    $arquivo,
                    $proibidos,
                    true
                )
            ) {
                $erros[] =
                    "Arquivo sensível rastreado: "
                    . $arquivo;
            }

            if (
                preg_match(
                    '/\.log$/i',
                    $arquivo
                )
                || preg_match(
                    '/\.bak(?:[-.].*)?$/i',
                    $arquivo
                )
            ) {
                $erros[] =
                    "Arquivo operacional rastreado: "
                    . $arquivo;
            }
        }
    }
}

echo "======================================" . PHP_EOL;
echo "VERIFICAÇÃO DE SEGURANÇA" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo PHP_EOL;

foreach (
    array_values(
        array_unique($erros)
    )
    as $erro
) {
    echo "[ERRO] {$erro}" . PHP_EOL;
}

foreach (
    array_values(
        array_unique($avisos)
    )
    as $aviso
) {
    echo "[AVISO] {$aviso}" . PHP_EOL;
}

if ($erros === []) {
    echo "[OK] Nenhum arquivo proibido foi detectado no índice Git." . PHP_EOL;
    exit(0);
}

echo PHP_EOL;
echo "Corrija os itens acima antes do próximo push." . PHP_EOL;
exit(1);
