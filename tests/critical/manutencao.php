<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    && PHP_SAPI !== 'phpdbg'
) {
    http_response_code(404);
    exit;
}

$raizProjeto = dirname(__DIR__, 2);

require_once
    $raizProjeto
    . '/config/ModoManutencao.php';

require_once
    $raizProjeto
    . '/tools/deploy/DeployUtil.php';

$base =
    sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'retiro-manutencao-teste-'
    . bin2hex(random_bytes(4));

if (!mkdir($base, 0755, true)) {
    throw new RuntimeException(
        'Falha ao criar diretório temporário.'
    );
}

$ok = 0;
$falhas = 0;

$limpar = static function (
    string $dir
): void {
    if (!is_dir($dir)) {
        return;
    }

    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(
            $dir,
            FilesystemIterator::SKIP_DOTS
        ),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($it as $item) {
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

    @rmdir($dir);
};

$testar = static function (
    string $nome,
    callable $teste
) use (&$ok, &$falhas): void {
    try {
        $teste();
        $ok++;
        echo '[OK] ' . $nome . PHP_EOL;
    } catch (Throwable $erro) {
        $falhas++;
        echo '[FALHA] ' . $nome . PHP_EOL;
        echo '        ' . $erro->getMessage() . PHP_EOL;
    }
};

$verdadeiro = static function (
    bool $valor,
    string $mensagem
): void {
    if (!$valor) {
        throw new RuntimeException(
            $mensagem
        );
    }
};

$falso = static function (
    bool $valor,
    string $mensagem
): void {
    if ($valor) {
        throw new RuntimeException(
            $mensagem
        );
    }
};

$igual = static function (
    mixed $esperado,
    mixed $atual,
    string $mensagem
): void {
    if ($esperado !== $atual) {
        throw new RuntimeException(
            $mensagem
            . ' Esperado '
            . var_export($esperado, true)
            . ', obtido '
            . var_export($atual, true)
        );
    }
};

echo '======================================' . PHP_EOL;
echo 'TESTE - MODO DE MANUTENÇÃO' . PHP_EOL;
echo '======================================' . PHP_EOL;
echo PHP_EOL;

try {
    $testar(
        'Estado inicial é inativo',
        static function () use (
            $base,
            $falso
        ): void {
            $status =
                ModoManutencao::status(
                    $base
                );

            $falso(
                $status['ativo'],
                'Manutenção deveria iniciar desativada.'
            );
        }
    );

    $testar(
        'Ativação cria marcador e preserva motivo',
        static function () use (
            $base,
            $verdadeiro,
            $igual
        ): void {
            $status =
                ModoManutencao::ativar(
                    $base,
                    'Teste de deploy',
                    null
                );

            $verdadeiro(
                $status['ativo'],
                'Manutenção deveria estar ativa.'
            );

            $igual(
                'Teste de deploy',
                $status['motivo'],
                'Motivo divergente.'
            );

            $igual(
                null,
                $status['expiraEm'],
                'Deploy sem expiração não deveria ter expiraEm.'
            );
        }
    );

    $testar(
        'Marcador operacional é protegido pelo deploy',
        static function () use (
            $verdadeiro
        ): void {
            $verdadeiro(
                DeployUtil::protegido(
                    'storage/manutencao.json'
                ),
                'storage/manutencao.json precisa ser protegido.'
            );

            $verdadeiro(
                DeployUtil::proibidoNaRelease(
                    'storage/manutencao.json'
                ),
                'Marcador não pode entrar na release.'
            );
        }
    );

    $testar(
        'Marcador expirado é removido automaticamente',
        static function () use (
            $base,
            $falso
        ): void {
            $arquivo =
                ModoManutencao::caminho(
                    $base
                );

            $dados = [
                'schema' => 1,
                'motivo' => 'Expirado',
                'iniciadoEm' => '2020-01-01T00:00:00-03:00',
                'expiraEm' => '2020-01-01T00:01:00-03:00'
            ];

            file_put_contents(
                $arquivo,
                json_encode(
                    $dados,
                    JSON_UNESCAPED_SLASHES
                )
            );

            $status =
                ModoManutencao::status(
                    $base
                );

            $falso(
                $status['ativo'],
                'Marcador expirado deveria ser desativado.'
            );
        }
    );

    $testar(
        'Desativação remove marcador',
        static function () use (
            $base,
            $falso
        ): void {
            ModoManutencao::ativar(
                $base,
                'Desativar',
                5
            );

            ModoManutencao::desativar(
                $base
            );

            $status =
                ModoManutencao::status(
                    $base
                );

            $falso(
                $status['ativo'],
                'Manutenção deveria estar inativa.'
            );
        }
    );
} finally {
    $limpar($base);
}

echo PHP_EOL;
echo 'OK: ' . $ok . PHP_EOL;
echo 'FALHAS: ' . $falhas . PHP_EOL;

exit(
    $falhas === 0
        ? 0
        : 1
);
