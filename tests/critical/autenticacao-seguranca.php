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

require_once
    $raiz
    . '/mod/services/AutenticacaoRateLimitService.php';

$base =
    sys_get_temp_dir()
    . DIRECTORY_SEPARATOR
    . 'retiro-auth-rate-'
    . bin2hex(random_bytes(4));

if (!mkdir($base, 0755, true)) {
    throw new RuntimeException(
        'Falha ao criar diretório temporário.'
    );
}

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

$verdadeiro = static function (
    bool $valor,
    string $mensagem
): void {
    if (!$valor) {
        throw new RuntimeException($mensagem);
    }
};

$falso = static function (
    bool $valor,
    string $mensagem
): void {
    if ($valor) {
        throw new RuntimeException($mensagem);
    }
};

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
            @rmdir($item->getPathname());
        } else {
            @unlink($item->getPathname());
        }
    }

    @rmdir($dir);
};

echo "======================================" . PHP_EOL;
echo "TESTE - SEGURANÇA DE AUTENTICAÇÃO" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo PHP_EOL;

try {
    $testar(
        'IP usa REMOTE_ADDR e ignora X-Forwarded-For falsificável',
        static function () use ($verdadeiro): void {
            $ip = AutenticacaoRateLimitService::ipCliente([
                'REMOTE_ADDR' => '203.0.113.10',
                'HTTP_X_FORWARDED_FOR' => '198.51.100.99'
            ]);

            $verdadeiro(
                $ip === '203.0.113.10',
                'REMOTE_ADDR deveria prevalecer.'
            );
        }
    );

    $testar(
        'Cinco falhas bloqueiam a combinação e-mail + IP',
        static function () use (
            $base,
            $falso
        ): void {
            $dir = $base . '/login-combinado';
            $servico = new AutenticacaoRateLimitService($dir);

            for ($i = 0; $i < 5; $i++) {
                $servico->registrarFalhaLogin(
                    'teste@example.com',
                    '203.0.113.20'
                );
            }

            $resultado = $servico->verificarLogin(
                'teste@example.com',
                '203.0.113.20'
            );

            $falso(
                $resultado['permitido'],
                'Login deveria estar bloqueado.'
            );
        }
    );

    $testar(
        'Login válido limpa apenas o bucket combinado',
        static function () use (
            $base,
            $verdadeiro
        ): void {
            $dir = $base . '/login-limpar';
            $servico = new AutenticacaoRateLimitService($dir);

            for ($i = 0; $i < 5; $i++) {
                $servico->registrarFalhaLogin(
                    'limpar@example.com',
                    '203.0.113.21'
                );
            }

            $servico->limparLogin(
                'limpar@example.com',
                '203.0.113.21'
            );

            $resultado = $servico->verificarLogin(
                'limpar@example.com',
                '203.0.113.21'
            );

            $verdadeiro(
                $resultado['permitido'],
                'Bucket combinado deveria ter sido limpo.'
            );
        }
    );

    $testar(
        'Muitas contas no mesmo IP acionam limite global',
        static function () use (
            $base,
            $falso
        ): void {
            $dir = $base . '/login-ip';
            $servico = new AutenticacaoRateLimitService($dir);

            for ($i = 0; $i < 25; $i++) {
                $servico->registrarFalhaLogin(
                    'conta' . $i . '@example.com',
                    '203.0.113.22'
                );
            }

            $resultado = $servico->verificarLogin(
                'outra@example.com',
                '203.0.113.22'
            );

            $falso(
                $resultado['permitido'],
                'IP deveria estar temporariamente bloqueado.'
            );
        }
    );

    $testar(
        'Recuperação limita repetição por e-mail',
        static function () use (
            $base,
            $falso
        ): void {
            $dir = $base . '/recuperacao';
            $servico = new AutenticacaoRateLimitService($dir);

            for ($i = 0; $i < 3; $i++) {
                $servico->registrarRecuperacao(
                    'recuperar@example.com',
                    '203.0.113.23'
                );
            }

            $resultado = $servico->verificarRecuperacao(
                'recuperar@example.com',
                '203.0.113.23'
            );

            $falso(
                $resultado['permitido'],
                'Recuperação deveria estar limitada.'
            );
        }
    );

    $testar(
        'Buckets não armazenam e-mail ou IP em texto puro',
        static function () use (
            $base,
            $falso
        ): void {
            $dir = $base . '/privacidade';
            $servico = new AutenticacaoRateLimitService($dir);

            $servico->registrarFalhaLogin(
                'privado@example.com',
                '203.0.113.24'
            );

            $conteudo = '';

            foreach (
                glob($dir . '/*.json') ?: []
                as $arquivo
            ) {
                $conteudo .= basename($arquivo);
                $conteudo .= (string) file_get_contents($arquivo);
            }

            $falso(
                str_contains(
                    $conteudo,
                    'privado@example.com'
                ),
                'E-mail não deveria estar em texto puro.'
            );

            $falso(
                str_contains(
                    $conteudo,
                    '203.0.113.24'
                ),
                'IP não deveria estar em texto puro.'
            );
        }
    );

    $testar(
        'Token de redefinição é armazenado por hash SHA-256',
        static function () use (
            $raiz,
            $verdadeiro
        ): void {
            $fonte = (string) file_get_contents(
                $raiz . '/mod/auth/Usuario.php'
            );

            $verdadeiro(
                str_contains(
                    $fonte,
                    'hashTokenRecuperacao'
                ),
                'Usuario.php precisa usar hashTokenRecuperacao.'
            );

            $verdadeiro(
                preg_match(
                    "/hash\\s*\\(\\s*['\\\"]sha256['\\\"]/",
                    $fonte
                ) === 1,
                'SHA-256 não foi encontrado no fluxo de reset.'
            );
        }
    );

    $testar(
        'storage/seguranca possui bloqueio HTTP',
        static function () use (
            $raiz,
            $verdadeiro
        ): void {
            $arquivo =
                $raiz
                . '/storage/seguranca/.htaccess';

            $verdadeiro(
                is_file($arquivo),
                '.htaccess de segurança ausente.'
            );

            $conteudo = (string) file_get_contents($arquivo);

            $verdadeiro(
                preg_match(
                    '/Require\s+all\s+denied/i',
                    $conteudo
                ) === 1,
                'Bloqueio HTTP ausente.'
            );
        }
    );
} finally {
    $limpar($base);
}

echo PHP_EOL;
echo 'OK: ' . $ok . PHP_EOL;
echo 'FALHAS: ' . $falhas . PHP_EOL;

exit($falhas === 0 ? 0 : 1);
