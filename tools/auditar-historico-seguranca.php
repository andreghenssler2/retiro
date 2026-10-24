<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);

function hgGit(string $raiz, string $args, int &$codigo): array
{
    $saida = [];
    @exec(
        'git -C ' . escapeshellarg($raiz) . ' ' . $args . ' 2>&1',
        $saida,
        $codigo
    );
    return $saida;
}

function hgNorm(string $arquivo): string
{
    return ltrim(str_replace('\\', '/', trim($arquivo)), '/');
}

function hgEhArquivoPermitidoDeLogs(string $arquivo): bool
{
    $base = basename($arquivo);

    return in_array(
        $base,
        ['.gitkeep', '.htaccess'],
        true
    );
}

function hgEhSegredoPorCaminho(string $arquivo): bool
{
    $arquivo = hgNorm($arquivo);

    if ($arquivo === '.env.example') {
        return false;
    }

    return in_array(
        $arquivo,
        [
            'config/conn.php',
            'config/integracoes.php',
            'config/.bancario.key',
            '.env'
        ],
        true
    )
    || (
        str_starts_with($arquivo, '.env.')
        && $arquivo !== '.env.example'
    );
}

function hgEhLogReal(string $arquivo): bool
{
    $arquivo = hgNorm($arquivo);

    if (
        (str_starts_with($arquivo, 'logs/')
            || str_starts_with($arquivo, 'mod/logs/'))
        && !hgEhArquivoPermitidoDeLogs($arquivo)
    ) {
        return true;
    }

    return str_ends_with(
        strtolower($arquivo),
        '.log'
    );
}

function hgEhBackup(string $arquivo): bool
{
    $base = strtolower(basename(hgNorm($arquivo)));

    return (bool) preg_match(
        '/\.bak(?:[-.].*)?$/i',
        $base
    )
    || str_ends_with($base, '.backup');
}

function hgEhDumpBanco(string $arquivo): bool
{
    $arquivo = hgNorm($arquivo);

    /*
     * Não marca migrations SQL legítimas como segredo.
     * Somente snapshots/dumps conhecidos do histórico.
     */
    return in_array(
        $arquivo,
        [
            'mod/database/banco.sql',
            'mod/database/ieclbp28_retiro.sql',
            'mod/database/migrations/sistema_completo_v03082026.sql'
        ],
        true
    );
}

function hgEhRevisaoManual(string $arquivo): bool
{
    $arquivo = hgNorm($arquivo);

    return in_array(
        $arquivo,
        [
            'mod/mail/migrations/teste_pagamento_1.sql',
            'mod/mail/migrations/diagnostico_notificacoes.sql'
        ],
        true
    );
}

function hgPrefixoOperacional(string $arquivo): ?string
{
    $arquivo = hgNorm($arquivo);

    foreach (
        [
            'lib/vendor/' => 'lib/vendor/',
            'arquivos/' => 'arquivos/',
            'portal_ieclb_parobe/' => 'portal_ieclb_parobe/',
            'dist/' => 'dist/'
        ]
        as $prefixo => $rotulo
    ) {
        if (str_starts_with($arquivo, $prefixo)) {
            return $rotulo;
        }
    }

    if (str_ends_with(strtolower($arquivo), '.zip')) {
        return '*.zip';
    }

    if (
        str_starts_with(
            strtolower(basename($arquivo)),
            'atualizar-'
        )
        && str_ends_with(
            strtolower($arquivo),
            '.php'
        )
    ) {
        return 'atualizar-*.php';
    }

    return null;
}

/**
 * @return array<int, string>
 */
function hgArquivosHistorico(string $raiz): array
{
    $codigo = 0;
    $raw = hgGit(
        $raiz,
        'rev-list --objects --all',
        $codigo
    );

    if ($codigo !== 0) {
        fwrite(
            STDERR,
            '[ERRO] Não foi possível consultar o histórico Git.'
            . PHP_EOL
        );
        exit(2);
    }

    $arquivos = [];

    foreach ($raw as $linha) {
        $linha = trim((string) $linha);

        if ($linha === '' || !str_contains($linha, ' ')) {
            continue;
        }

        [, $caminho] = explode(' ', $linha, 2);
        $caminho = hgNorm($caminho);

        if ($caminho !== '') {
            $arquivos[] = $caminho;
        }
    }

    return array_values(
        array_unique($arquivos)
    );
}

/**
 * @return array<int, string>
 */
function hgArquivosIndice(string $raiz): array
{
    $codigo = 0;
    $raw = hgGit(
        $raiz,
        'ls-files',
        $codigo
    );

    if ($codigo !== 0) {
        fwrite(
            STDERR,
            '[ERRO] Não foi possível consultar o índice Git.'
            . PHP_EOL
        );
        exit(2);
    }

    return array_values(
        array_unique(
            array_filter(
                array_map(
                    'hgNorm',
                    $raw
                )
            )
        )
    );
}

/**
 * @param array<int, string> $arquivos
 * @return array{
 *   segredos: array<int,string>,
 *   logs: array<int,string>,
 *   backups: array<int,string>,
 *   dumps: array<int,string>,
 *   revisao: array<int,string>,
 *   operacionais: array<string,int>
 * }
 */
function hgClassificar(array $arquivos): array
{
    $r = [
        'segredos' => [],
        'logs' => [],
        'backups' => [],
        'dumps' => [],
        'revisao' => [],
        'operacionais' => []
    ];

    foreach ($arquivos as $arquivo) {
        if (hgEhSegredoPorCaminho($arquivo)) {
            $r['segredos'][] = $arquivo;
            continue;
        }

        if (hgEhBackup($arquivo)) {
            $r['backups'][] = $arquivo;
            continue;
        }

        if (hgEhLogReal($arquivo)) {
            $r['logs'][] = $arquivo;
            continue;
        }

        if (hgEhDumpBanco($arquivo)) {
            $r['dumps'][] = $arquivo;
            continue;
        }

        if (hgEhRevisaoManual($arquivo)) {
            $r['revisao'][] = $arquivo;
            continue;
        }

        $grupo = hgPrefixoOperacional($arquivo);

        if ($grupo !== null) {
            $r['operacionais'][$grupo] =
                ($r['operacionais'][$grupo] ?? 0) + 1;
        }
    }

    foreach (
        ['segredos', 'logs', 'backups', 'dumps', 'revisao']
        as $chave
    ) {
        sort(
            $r[$chave],
            SORT_NATURAL | SORT_FLAG_CASE
        );
    }

    ksort($r['operacionais']);

    return $r;
}

function hgImprimirLista(
    string $titulo,
    array $itens,
    string $nivel
): void {
    echo PHP_EOL . $titulo . PHP_EOL;

    if ($itens === []) {
        echo '[OK] Nenhum.' . PHP_EOL;
        return;
    }

    foreach ($itens as $item) {
        echo '[' . $nivel . '] ' . $item . PHP_EOL;
    }
}

function hgImprimirResumo(array $dados): void
{
    hgImprimirLista(
        'SEGREDOS / CREDENCIAIS POR CAMINHO',
        $dados['segredos'],
        'CRITICO'
    );

    echo PHP_EOL . 'LOGS ANTIGOS' . PHP_EOL;
    if ($dados['logs'] === []) {
        echo '[OK] Nenhum.' . PHP_EOL;
    } else {
        echo
            '[REMOVER] '
            . count($dados['logs'])
            . ' arquivo(s). Use --path-glob "*.log" e remova arquivos operacionais de mod/logs/.'
            . PHP_EOL;
    }

    echo PHP_EOL . 'BACKUPS ANTIGOS' . PHP_EOL;
    if ($dados['backups'] === []) {
        echo '[OK] Nenhum.' . PHP_EOL;
    } else {
        echo
            '[REMOVER] '
            . count($dados['backups'])
            . ' arquivo(s) .bak/.backup.'
            . PHP_EOL;
    }

    hgImprimirLista(
        'DUMPS / SNAPSHOTS DE BANCO CONHECIDOS',
        $dados['dumps'],
        'REMOVER'
    );

    hgImprimirLista(
        'SQL PARA REVISÃO MANUAL',
        $dados['revisao'],
        'REVISAR'
    );

    echo PHP_EOL . 'ARTEFATOS OPERACIONAIS ANTIGOS' . PHP_EOL;
    if ($dados['operacionais'] === []) {
        echo '[OK] Nenhum.' . PHP_EOL;
    } else {
        foreach ($dados['operacionais'] as $grupo => $quantidade) {
            echo
                '[AVISO] '
                . $grupo
                . ' => '
                . $quantidade
                . ' objeto(s)'
                . PHP_EOL;
        }
    }
}

$codigo = 0;
hgGit($raiz, '--version', $codigo);

if ($codigo !== 0) {
    fwrite(STDERR, '[ERRO] Git não encontrado.' . PHP_EOL);
    exit(2);
}

$indice = hgClassificar(
    hgArquivosIndice($raiz)
);

$historico = hgClassificar(
    hgArquivosHistorico($raiz)
);

echo "======================================" . PHP_EOL;
echo "AUDITORIA DO HISTÓRICO GIT - V1.1" . PHP_EOL;
echo "======================================" . PHP_EOL;

echo PHP_EOL . "=== ÍNDICE ATUAL ===" . PHP_EOL;
hgImprimirResumo($indice);

echo PHP_EOL . "=== HISTÓRICO COMPLETO ===" . PHP_EOL;
hgImprimirResumo($historico);

$problemaIndice =
    $indice['segredos'] !== []
    || $indice['logs'] !== []
    || $indice['backups'] !== []
    || $indice['dumps'] !== [];

$problemaHistorico =
    $historico['segredos'] !== []
    || $historico['logs'] !== []
    || $historico['backups'] !== []
    || $historico['dumps'] !== [];

echo PHP_EOL . "--------------------------------------" . PHP_EOL;

if (!$problemaIndice) {
    echo '[OK] Índice atual sem os caminhos críticos conhecidos.' . PHP_EOL;
} else {
    echo '[ERRO] O índice atual ainda contém itens que devem ser removidos.' . PHP_EOL;
}

if (!$problemaHistorico) {
    echo '[OK] Histórico sem os caminhos críticos conhecidos.' . PHP_EOL;
} else {
    echo '[ERRO] O histórico ainda precisa de limpeza.' . PHP_EOL;
    echo 'Veja docs/seguranca/LIMPEZA-HISTORICO.md.' . PHP_EOL;
}

/*
 * A auditoria falha somente por itens realmente críticos/removíveis.
 * SQL de migrations legítimas e arquivos .gitkeep/.htaccess não são erro.
 */
exit(
    (!$problemaIndice && !$problemaHistorico)
        ? 0
        : 1
);
