<?php

declare(strict_types=1);

/**
 * Baseline de segurança HTTP e sessão.
 *
 * Mantém as decisões em métodos puros para permitir testes sem enviar headers.
 */
final class SegurancaHttp
{
    private const HSTS_MAX_AGE = 15552000; // 180 dias

    /**
     * @param array<string,mixed>|null $server
     */
    public static function https(?array $server = null): bool
    {
        $server ??= $_SERVER;

        $https = strtolower(
            trim(
                (string) (
                    $server['HTTPS']
                    ?? ''
                )
            )
        );

        if ($https !== '' && $https !== 'off' && $https !== '0') {
            return true;
        }

        if ((int) ($server['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }

        $forwarded = strtolower(
            trim(
                (string) (
                    $server['HTTP_X_FORWARDED_PROTO']
                    ?? ''
                )
            )
        );

        if ($forwarded !== '') {
            $primeiro = trim(
                explode(',', $forwarded)[0]
            );

            if ($primeiro === 'https') {
                return true;
            }
        }

        $forwardedSsl = strtolower(
            trim(
                (string) (
                    $server['HTTP_X_FORWARDED_SSL']
                    ?? ''
                )
            )
        );

        return in_array(
            $forwardedSsl,
            ['on', '1', 'true'],
            true
        );
    }

    /**
     * @param array<string,mixed>|null $server
     */
    public static function hostLocal(?array $server = null): bool
    {
        $server ??= $_SERVER;

        $host = strtolower(
            trim(
                (string) (
                    $server['HTTP_HOST']
                    ?? $server['SERVER_NAME']
                    ?? ''
                )
            )
        );

        if ($host === '') {
            return PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg';
        }

        if (str_starts_with($host, '[')) {
            $fim = strpos($host, ']');

            if ($fim !== false) {
                $host = substr($host, 1, $fim - 1);
            }
        } elseif (str_contains($host, ':')) {
            $host = explode(':', $host, 2)[0];
        }

        return in_array(
            $host,
            [
                'localhost',
                '127.0.0.1',
                '::1'
            ],
            true
        );
    }

    /**
     * @param array<string,mixed>|null $server
     * @return array{
     *   lifetime:int,
     *   path:string,
     *   secure:bool,
     *   httponly:bool,
     *   samesite:string
     * }
     */
    public static function parametrosCookie(
        ?array $server = null
    ): array {
        return [
            'lifetime' => 0,
            'path' => '/',
            'secure' => self::https($server),
            'httponly' => true,
            'samesite' => 'Lax'
        ];
    }

    /**
     * @param array<string,mixed>|null $server
     * @return array<string,string>
     */
    public static function cabecalhos(
        ?array $server = null
    ): array {
        $server ??= $_SERVER;

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' =>
                'camera=(), microphone=(), geolocation=()'
        ];

        if (
            self::https($server)
            && !self::hostLocal($server)
        ) {
            $headers['Strict-Transport-Security'] =
                'max-age='
                . self::HSTS_MAX_AGE
                . '; includeSubDomains';
        }

        return $headers;
    }

    public static function iniciarSessao(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent()) {
            session_start();
            return;
        }

        /*
         * Rejeita IDs fornecidos pelo cliente que não existam no servidor
         * e evita fallback para sessão via URL.
         */
        @ini_set('session.use_strict_mode', '1');
        @ini_set('session.use_only_cookies', '1');
        @ini_set('session.use_trans_sid', '0');

        session_set_cookie_params(
            self::parametrosCookie()
        );

        session_start();
    }

    public static function aplicarCabecalhos(): void
    {
        if (
            headers_sent()
            || PHP_SAPI === 'cli'
            || PHP_SAPI === 'phpdbg'
        ) {
            return;
        }

        foreach (self::cabecalhos() as $nome => $valor) {
            header(
                $nome . ': ' . $valor,
                true
            );
        }
    }
}
