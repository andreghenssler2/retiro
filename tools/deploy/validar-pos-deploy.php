<?php

declare(strict_types=1);

require_once __DIR__ . '/DeployUtil.php';
require_once dirname(__DIR__, 2) . '/config/ModoManutencao.php';

DeployUtil::exigirCli();

$args = DeployUtil::args($argv);

$expectVersion = (string) (
    $args['expect-version']
    ?? ''
);
$expectCommit = (string) (
    $args['expect-commit']
    ?? ''
);
$expectMaintenance = strtolower(
    trim(
        (string) (
            $args['expect-maintenance']
            ?? ''
        )
    )
);

if (
    !in_array(
        $expectMaintenance,
        ['', 'on', 'off'],
        true
    )
) {
    DeployUtil::erro(
        '--expect-maintenance aceita on ou off.'
    );
}

$raiz = DeployUtil::raiz();
$ok = [];
$avisos = [];
$erros = [];

$addOk = static function (
    string $m
) use (&$ok): void {
    $ok[] = $m;
};

$addAviso = static function (
    string $m
) use (&$avisos): void {
    $avisos[] = $m;
};

$addErro = static function (
    string $m
) use (&$erros): void {
    $erros[] = $m;
};

/**
 * @return array{codigo:int,saida:array<int,string>}
 */
$rodarPhp = static function (
    string $arquivo,
    array $argumentos = []
): array {
    $cmd =
        escapeshellarg(PHP_BINARY)
        . ' '
        . escapeshellarg($arquivo);

    foreach ($argumentos as $argumento) {
        $cmd .= ' '
            . escapeshellarg(
                (string) $argumento
            );
    }

    $saida = [];
    $codigo = 0;

    exec(
        $cmd . ' 2>&1',
        $saida,
        $codigo
    );

    return [
        'codigo' => $codigo,
        'saida' => $saida
    ];
};

/*
|--------------------------------------------------------------------------
| Manifesto aplicado
|--------------------------------------------------------------------------
*/

$manifestPath =
    $raiz
    . DIRECTORY_SEPARATOR
    . DeployUtil::RELEASE_MANIFEST;

if (!is_file($manifestPath)) {
    $addErro(
        'RELEASE-MANIFEST.json não encontrado após deploy'
    );
} else {
    $raw = file_get_contents(
        $manifestPath
    );
    $manifest = is_string($raw)
        ? json_decode($raw, true)
        : null;

    if (!is_array($manifest)) {
        $addErro(
            'RELEASE-MANIFEST.json inválido após deploy'
        );
    } else {
        $version = (string) (
            $manifest['version']
            ?? ''
        );
        $commit = (string) (
            $manifest['commit']
            ?? ''
        );

        if (
            $expectVersion !== ''
            && $version !== $expectVersion
        ) {
            $addErro(
                'Versão aplicada difere da esperada'
            );
        } else {
            $addOk(
                'Versão aplicada: '
                . (
                    $version !== ''
                    ? $version
                    : 'não informada'
                )
            );
        }

        if (
            $expectCommit !== ''
            && $commit !== $expectCommit
        ) {
            $addErro(
                'Commit aplicado difere do esperado'
            );
        } else {
            $addOk(
                'Commit aplicado: '
                . (
                    $commit !== ''
                    ? $commit
                    : 'não informado'
                )
            );
        }
    }
}

/*
|--------------------------------------------------------------------------
| Estado da manutenção
|--------------------------------------------------------------------------
*/

try {
    $status =
        ModoManutencao::status($raiz);

    if (
        $expectMaintenance === 'on'
        && !$status['ativo']
    ) {
        $addErro(
            'Manutenção deveria estar ativa durante a validação final'
        );
    } elseif (
        $expectMaintenance === 'off'
        && $status['ativo']
    ) {
        $addErro(
            'Manutenção deveria estar inativa'
        );
    } else {
        $addOk(
            'Estado de manutenção compatível com o esperado'
        );
    }
} catch (Throwable $erro) {
    $addErro(
        'Falha ao consultar manutenção: '
        . $erro->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Migrations
|--------------------------------------------------------------------------
*/

$migrations = $rodarPhp(
    $raiz . '/database/migrate.php',
    ['status']
);

if ($migrations['codigo'] !== 0) {
    $addErro(
        'Falha ao consultar migrations: '
        . implode(
            ' | ',
            $migrations['saida']
        )
    );
} else {
    $pendente = false;
    $alterada = false;

    foreach ($migrations['saida'] as $linha) {
        $linha = (string) $linha;

        if (str_contains($linha, '[PENDENTE]')) {
            $pendente = true;
        }

        if (str_contains($linha, '[ALTERADA]')) {
            $alterada = true;
        }
    }

    if ($alterada) {
        $addErro(
            'Existe migration aplicada com checksum alterado'
        );
    } elseif ($pendente) {
        $addErro(
            'Existe migration pendente após deploy'
        );
    } else {
        $addOk(
            'Migrations sem pendências ou alterações'
        );
    }
}

/*
|--------------------------------------------------------------------------
| Preflight pós-deploy
|--------------------------------------------------------------------------
*/

$preflight = $rodarPhp(
    $raiz . '/tools/deploy/preflight.php'
);

if ($preflight['codigo'] === 0) {
    $addOk(
        'Preflight pós-deploy passou'
    );
} else {
    $addErro(
        'Preflight pós-deploy falhou: '
        . implode(
            ' | ',
            $preflight['saida']
        )
    );
}

/*
|--------------------------------------------------------------------------
| Smoke test
|--------------------------------------------------------------------------
*/

$smoke = $rodarPhp(
    $raiz . '/tools/smoke-test.php'
);

if ($smoke['codigo'] === 0) {
    $addOk(
        'Smoke test pós-deploy passou'
    );
} else {
    $addErro(
        'Smoke test pós-deploy falhou: '
        . implode(
            ' | ',
            $smoke['saida']
        )
    );
}

/*
|--------------------------------------------------------------------------
| Dados persistentes
|--------------------------------------------------------------------------
*/

$auditPath =
    $raiz
    . '/tools/deploy/auditar-dados-persistentes.php';

if (is_file($auditPath)) {
    $audit = $rodarPhp(
        $auditPath
    );

    if ($audit['codigo'] === 0) {
        $addOk(
            'Auditoria de dados persistentes passou'
        );
    } else {
        $addErro(
            'Auditoria de dados persistentes falhou: '
            . implode(
                ' | ',
                $audit['saida']
            )
        );
    }
} else {
    $addAviso(
        'Auditoria de dados persistentes não encontrada'
    );
}

echo "======================================" . PHP_EOL;
echo "VALIDAÇÃO PÓS-DEPLOY" . PHP_EOL;
echo "======================================" . PHP_EOL;

foreach ($ok as $m) {
    echo '[OK] ' . $m . PHP_EOL;
}

foreach ($avisos as $m) {
    echo '[AVISO] ' . $m . PHP_EOL;
}

foreach ($erros as $m) {
    echo '[ERRO] ' . $m . PHP_EOL;
}

echo PHP_EOL;
echo 'OK: ' . count($ok) . PHP_EOL;
echo 'AVISOS: ' . count($avisos) . PHP_EOL;
echo 'ERROS: ' . count($erros) . PHP_EOL;

exit($erros === [] ? 0 : 1);
