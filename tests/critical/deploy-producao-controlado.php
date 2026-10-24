<?php

declare(strict_types=1);

if (
    PHP_SAPI !== 'cli'
    && PHP_SAPI !== 'phpdbg'
) {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__, 2);

$deploy = (string) file_get_contents(
    $raiz
    . '/tools/deploy/deploy-producao.php'
);

$apply = (string) file_get_contents(
    $raiz
    . '/tools/deploy/aplicar-release.php'
);

$post = (string) file_get_contents(
    $raiz
    . '/tools/deploy/validar-pos-deploy.php'
);

$ok = 0;
$falhas = 0;

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

$contem = static function (
    string $fonte,
    string $trecho,
    string $mensagem
): void {
    if (!str_contains($fonte, $trecho)) {
        throw new RuntimeException(
            $mensagem
        );
    }
};

echo "======================================" . PHP_EOL;
echo "TESTE - DEPLOY DE PRODUÇÃO CONTROLADO" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo PHP_EOL;

$testar(
    'Modo padrão é apenas plano, sem execução',
    static function () use (
        $deploy,
        $contem
    ): void {
        $contem(
            $deploy,
            "if (!\$executar)",
            'Gate de plano não encontrado.'
        );

        $contem(
            $deploy,
            '[PLANO] Nenhum arquivo de produção foi alterado.',
            'Mensagem de plano ausente.'
        );
    }
);

$testar(
    'Execução exige confirmação forte',
    static function () use (
        $deploy,
        $contem
    ): void {
        $contem(
            $deploy,
            "DEPLOY-PRODUCAO",
            'Confirmação forte ausente.'
        );
    }
);

$testar(
    'Prontidão roda antes de aplicar a release',
    static function () use ($deploy): void {
        $prontidao = strpos(
            $deploy,
            'prontidao-producao.php'
        );
        $aplicar = strpos(
            $deploy,
            'aplicar-release.php'
        );

        if (
            $prontidao === false
            || $aplicar === false
            || $prontidao > $aplicar
        ) {
            throw new RuntimeException(
                'Ordem prontidão/aplicar incorreta.'
            );
        }
    }
);

$testar(
    'Aplicar-release preserva manutenção para validação final',
    static function () use (
        $deploy,
        $apply,
        $contem
    ): void {
        $contem(
            $deploy,
            '--keep-maintenance',
            'Wrapper não preserva manutenção.'
        );

        $contem(
            $apply,
            'keep-maintenance',
            'Aplicar-release não reconhece keep-maintenance.'
        );
    }
);

$testar(
    'Validação final ocorre antes de reabrir o site',
    static function () use ($deploy): void {
        $pos = strpos(
            $deploy,
            'validar-pos-deploy.php'
        );
        $off = strrpos(
            $deploy,
            'ModoManutencao::desativar'
        );

        if (
            $pos === false
            || $off === false
            || $pos > $off
        ) {
            throw new RuntimeException(
                'Site pode ser reaberto antes da validação final.'
            );
        }
    }
);

$testar(
    'Pós-deploy valida migrations, preflight e smoke',
    static function () use (
        $post,
        $contem
    ): void {
        foreach (
            [
                'database/migrate.php',
                'preflight.php',
                'smoke-test.php',
                'auditar-dados-persistentes.php'
            ]
            as $trecho
        ) {
            $contem(
                $post,
                $trecho,
                'Validação pós-deploy incompleta: '
                    . $trecho
            );
        }
    }
);

$testar(
    'Falha pós-deploy mantém ou reativa manutenção',
    static function () use (
        $deploy,
        $contem
    ): void {
        $contem(
            $deploy,
            "ModoManutencao::ativar",
            'Wrapper não reativa manutenção em falha final.'
        );

        $contem(
            $deploy,
            'Manutenção deve permanecer ATIVA',
            'Orientação de falha ausente.'
        );
    }
);

$testar(
    'Rollback não é automático após migrations',
    static function () use ($deploy): void {
        if (
            str_contains(
                $deploy,
                "require_once __DIR__ . '/rollback-codigo.php'"
            )
            || preg_match(
                '/\$rodarPhp\s*\([^;]*rollback-codigo\.php/s',
                $deploy
            ) === 1
        ) {
            throw new RuntimeException(
                'Rollback automático não deve ocorrer.'
            );
        }
    }
);

echo PHP_EOL;
echo 'OK: ' . $ok . PHP_EOL;
echo 'FALHAS: ' . $falhas . PHP_EOL;

exit($falhas === 0 ? 0 : 1);
