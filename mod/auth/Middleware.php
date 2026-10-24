<?php

class Middleware
{
    /**
     * Usuário deve estar logado
     */
    public static function auth(): void
    {
        Session::start();
        Session::timeout(120);

        if (!Auth::check()) {
            Session::flash('error', 'Faça login para continuar.');
            header('Location: ../login');
            exit;
        }
    }

    /**
     * Usuário deve estar deslogado
     */
    public static function guest(): void
    {
        if (Auth::check()) {
            Auth::redirectDashboard();
        }
    }

    /**
     * Apenas administrador
     */
    public static function admin(): void
    {
        self::auth();

        if (!Auth::isAdmin()) {
            self::forbidden();
        }
    }

    /**
     * Administrador ou Moderador
     */
    public static function moderador(): void
    {
        self::auth();

        if (!Auth::isAdmin() && !Auth::isModerador()) {
            self::forbidden();
        }
    }

    /**
     * Apenas participante
     */
    public static function participante(): void
    {
        self::auth();

        if (!Auth::isParticipante()) {
            self::forbidden();
        }
    }

    /**
     * Verifica se o usuário possui um dos perfis informados
     */
    public static function role(array $roles): void
    {
        self::auth();

        if (!in_array(Auth::tipo(), $roles, true)) {
            self::forbidden();
        }
    }

    /**
     * Acesso negado
     */
    public static function forbidden(string $mensagem = 'Acesso negado.'): void
    {
        http_response_code(403);

        include __DIR__ . '/../errors/403.php';

        exit;
    }

    /**
     * Página não encontrada
     */
    public static function notFound(): void
    {
        http_response_code(404);

        include __DIR__ . '/../errors/404.php';

        exit;
    }

    /**
     * Erro interno
     */
    public static function error(string $mensagem = 'Erro interno do sistema.'): void
    {
        http_response_code(500);

        include __DIR__ . '/../errors/500.php';

        exit;
    }
}