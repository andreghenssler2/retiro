<?php
require_once __DIR__ . '/SegurancaHttp.php';

/**
 * Classe de configuração geral do site
 * Detecta automaticamente se está em localhost ou produção
 * e define a BASE_URL dinamicamente
 */


class Config
{
    // Charset e Timezone
    const CHARSET = 'UTF-8';
    const TIMEZONE = 'America/Sao_Paulo';

    // Armazena conexão com o banco de dados
    private static $dbConn = null;

    /**
     * Inicializa configurações globais
     */
    public static function init()
    {
        // Inicia sessão segura
        SegurancaHttp::iniciarSessao();

        // Define timezone e charset
        date_default_timezone_set(self::TIMEZONE);
        SegurancaHttp::aplicarCabecalhos();
        // header('Content-Type: text/html; charset=UTF-8');

        // Detecta o host atual e define as URLs
        self::setBaseUrl();

        // Define headers de segurança
        // self::setSecurityHeaders();
    }


    /**
     * Detecta se está em localhost ou produção e define BASE_URL dinamicamente
     */
    private static function setBaseUrl()
    {
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $_SESSION['host'] = $host;

        if (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false) {
            $base = "http://localhost/"; // altere para seu caminho local
            define('conectar', 'desenvolvimento');
            $_SESSION['conectar'] = $base;

        } else {
            $base = "https://retiro.ieclbparobe.com.br/"; // altere para seu domínio real
            define('conectar', 'producao');
            $_SESSION['conectar'] = $base;

        }
        $config = require __DIR__ . '/../mod/version.php';

        $version = "{$config['version']}";

        // Define constantes de caminho dinamicamente
        // define('BASE_URL',   $base);
        define('BASE_URL', $base);
        define('ROOT_PATH', dirname(__DIR__));
        define('VERSION', $version);
        define('UPLOAD_USUARIOS', ROOT_PATH . '/uploads/usuarios/');

        define('UPLOAD_USUARIOS_URL', BASE_URL . 'uploads/usuarios/');
        define('API_URL', BASE_URL . 'api/');
        define('ASSETS_URL', BASE_URL . 'upload/assets/');
        define('ASSETS_IMG', BASE_URL . 'upload/img/');
        define('THEME_CSS', BASE_URL . 'theme/css/');
        define('THEME_JS', BASE_URL . 'theme/js/');
        define('THEME_IMG', BASE_URL . 'theme/img/');
    }

    /**
     * Define headers de segurança
     */
    private static function setSecurityHeaders()
    {
        header('X-Frame-Options: SAMEORIGIN');
        header('X-Content-Type-Options: nosniff');
        header('X-XSS-Protection: 1; mode=block');
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        header('Referrer-Policy: no-referrer-when-downgrade');
        header('Permissions-Policy: geolocation=(), microphone=()');
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }

    /**
     * Inicia sessão com segurança
     */
    /**
     * Inicia sessão com segurança
     */
    private static function startSecureSession(): void
    {
        // Se a sessão já estiver ativa, não faz nada.
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        // Se já houve saída para o navegador, apenas inicia a sessão,
        // pois não é mais possível alterar os parâmetros do cookie.
        if (headers_sent()) {
            session_start();
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax'
        ]);

        session_start();
    }

    /**
     * Retorna conexão com o banco
     */
    public static function getDB()
    {
        if (self::$dbConn === null) {
            require_once __DIR__ . '/../mod/database/db.php';
            self::$dbConn = conectarBanco(conectar); // desenvolvimento
        }
        return self::$dbConn;
    }

    /**
     * Redireciona de forma segura
     */
    public static function redirect($url)
    {
        $safeUrl = filter_var($url, FILTER_SANITIZE_URL);
        if (!headers_sent()) {
            header('Location: ' . $safeUrl);
        } else {
            echo "<script>window.location.href='" . htmlspecialchars($safeUrl, ENT_QUOTES) . "';</script>";
        }
        exit;
    }
}