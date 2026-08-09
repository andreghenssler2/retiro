<?php

class Session {
    /**
     * Inicia a sessão
     */
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /**
     * Define um valor na sessão
     */
    public static function set(string $key, mixed $value): void
    {
        self::start();

        $_SESSION[$key] = $value;
    }

    /**
     * Obtém um valor
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();

        return $_SESSION[$key] ?? $default;
    }

    /**
     * Verifica se existe
     */
    public static function has(string $key): bool
    {
        self::start();

        return isset($_SESSION[$key]);
    }

    /**
     * Remove um item
     */
    public static function remove(string $key): void
    {
        self::start();

        unset($_SESSION[$key]);
    }

    /**
     * Limpa toda sessão
     */
    public static function destroy(): void
    {
        self::start();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {

            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    /**
     * Regenera ID da sessão
     */
    public static function regenerate(): void
    {
        self::start();

        session_regenerate_id(true);
    }

    /**
     * Flash Message
     */
    public static function flash(string $tipo, string $mensagem): void
    {
        self::start();

        $_SESSION['_flash'][$tipo] = $mensagem;
    }

    /**
     * Exibe Flash Message
     */
    public static function getFlash(string $tipo): ?string
    {
        self::start();

        if (!isset($_SESSION['_flash'][$tipo])) {
            return null;
        }

        $msg = $_SESSION['_flash'][$tipo];

        unset($_SESSION['_flash'][$tipo]);

        return $msg;
    }

    /**
     * Existe Flash?
     */
    public static function hasFlash(string $tipo): bool
    {
        self::start();

        return isset($_SESSION['_flash'][$tipo]);
    }

    /**
     * Cria Token CSRF
     */
    public static function csrf(): string
    {
        self::start();

        if (!isset($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf'];
    }

    /**
     * Valida Token CSRF
     */
    public static function validateCsrf(?string $token): bool
    {
        self::start();

        if (!$token) {
            return false;
        }

        if (!isset($_SESSION['_csrf'])) {
            return false;
        }

        return hash_equals($_SESSION['_csrf'], $token);
    }

    /**
     * Tempo máximo da sessão
     */
    public static function timeout(int $minutes = 120): void
    {
        self::start();

        if (!isset($_SESSION['_last_activity'])) {

            $_SESSION['_last_activity'] = time();

            return;
        }

        $tempo = time() - $_SESSION['_last_activity'];

        if ($tempo > ($minutes * 60)) {

            self::destroy();

            header("Location: /login.php?timeout=1");
            exit;
        }

        $_SESSION['_last_activity'] = time();
    }

    /**
     * Retorna todos os dados da sessão
     */
    public static function all(): array
    {
        self::start();

        return $_SESSION;
    }

    /**
     * Esvazia a sessão mantendo-a ativa
     */
    public static function clear(): void
    {
        self::start();

        $_SESSION = [];
    }
}