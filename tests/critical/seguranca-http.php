<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__, 2);

require_once
    $raiz
    . '/config/SegurancaHttp.php';

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

echo "======================================" . PHP_EOL;
echo "TESTE - SEGURANÇA HTTP E SESSÃO" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo PHP_EOL;

$testar(
    'HTTPS direto é detectado',
    static function () use ($verdadeiro): void {
        $verdadeiro(
            SegurancaHttp::https([
                'HTTPS' => 'on',
                'HTTP_HOST' => 'retiro.example'
            ]),
            'HTTPS direto deveria ser detectado.'
        );
    }
);

$testar(
    'HTTPS atrás de proxy é detectado',
    static function () use ($verdadeiro): void {
        $verdadeiro(
            SegurancaHttp::https([
                'HTTP_X_FORWARDED_PROTO' => 'https',
                'HTTP_HOST' => 'retiro.example'
            ]),
            'X-Forwarded-Proto=https deveria ser detectado.'
        );
    }
);

$testar(
    'HTTP local não é marcado como HTTPS',
    static function () use ($falso): void {
        $falso(
            SegurancaHttp::https([
                'HTTP_HOST' => 'localhost:80',
                'SERVER_PORT' => 80
            ]),
            'localhost HTTP não deveria ser HTTPS.'
        );
    }
);

$testar(
    'Cookie de produção HTTPS usa Secure, HttpOnly e SameSite',
    static function () use ($igual, $verdadeiro): void {
        $params = SegurancaHttp::parametrosCookie([
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_HOST' => 'retiro.example'
        ]);

        $verdadeiro(
            $params['secure'] === true,
            'Cookie HTTPS precisa de Secure.'
        );

        $verdadeiro(
            $params['httponly'] === true,
            'Cookie precisa de HttpOnly.'
        );

        $igual(
            'Lax',
            $params['samesite'],
            'SameSite inesperado.'
        );
    }
);

$testar(
    'Headers básicos de segurança estão presentes',
    static function () use ($igual): void {
        $headers = SegurancaHttp::cabecalhos([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'retiro.example'
        ]);

        $igual(
            'nosniff',
            $headers['X-Content-Type-Options'] ?? null,
            'X-Content-Type-Options ausente.'
        );

        $igual(
            'SAMEORIGIN',
            $headers['X-Frame-Options'] ?? null,
            'X-Frame-Options ausente.'
        );

        $igual(
            'strict-origin-when-cross-origin',
            $headers['Referrer-Policy'] ?? null,
            'Referrer-Policy ausente.'
        );
    }
);

$testar(
    'HSTS só é aplicado em HTTPS fora do localhost',
    static function () use (
        $verdadeiro,
        $falso
    ): void {
        $producao = SegurancaHttp::cabecalhos([
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_HOST' => 'retiro.example'
        ]);

        $local = SegurancaHttp::cabecalhos([
            'HTTPS' => 'on',
            'HTTP_HOST' => 'localhost'
        ]);

        $verdadeiro(
            isset(
                $producao[
                    'Strict-Transport-Security'
                ]
            ),
            'Produção HTTPS deveria receber HSTS.'
        );

        $falso(
            isset(
                $local[
                    'Strict-Transport-Security'
                ]
            ),
            'localhost não deve receber HSTS.'
        );
    }
);

echo PHP_EOL;
echo 'OK: ' . $ok . PHP_EOL;
echo 'FALHAS: ' . $falhas . PHP_EOL;

exit($falhas === 0 ? 0 : 1);
