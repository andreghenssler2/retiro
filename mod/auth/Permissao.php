<?php

declare(strict_types=1);

/**
 * Controle central de permissões do sistema.
 *
 * Perfis:
 * 1 = Administrador
 * 2 = Moderador
 * 3 = Participante
 */
final class Permissao
{
    public const ADMINISTRADOR = 1;
    public const MODERADOR = 2;
    public const PARTICIPANTE = 3;

    /**
     * O administrador possui acesso total.
     * Os demais perfis recebem apenas as permissões listadas abaixo.
     *
     * @var array<int, array<int, string>>
     */
    private const PERMISSOES = [
        self::MODERADOR => [
            "dashboard.visualizar",

            "eventos.visualizar",
            "eventos.criar",
            "eventos.editar",

            "inscricoes.visualizar",
            "inscricoes.editar",
            "inscricoes.confirmar",

            "credenciamento.visualizar",
            "credenciamento.registrar",

            "pagamentos.visualizar",
            "financeiro.visualizar",

            "certificados.visualizar",
            "certificados.modelo",
            "certificados.emitir",
            "certificados.reenviar",
            "certificados.revogar",

            "relatorios.visualizar",
            "relatorios.exportar",

            "notificacoes.visualizar",
            "perfil.proprio",
        ],

        self::PARTICIPANTE => [
            "perfil.proprio",
            "notificacoes.proprias",
            "inscricoes.proprias",
            "pagamentos.proprios",
            "certificados.proprios",
        ],
    ];

    private static function garantirSessao(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            if (class_exists("Session")) {
                Session::start();
            } else {
                session_start();
            }
        }
    }

    public static function tipo(): int
    {
        self::garantirSessao();

        return (int) ($_SESSION["user"]["tipo"] ?? 0);
    }

    public static function autenticado(): bool
    {
        self::garantirSessao();

        return is_array($_SESSION["user"] ?? null)
            && (int) ($_SESSION["user"]["id"] ?? 0) > 0;
    }

    public static function ehAdmin(): bool
    {
        return self::tipo() === self::ADMINISTRADOR;
    }

    public static function ehModerador(): bool
    {
        return self::tipo() === self::MODERADOR;
    }

    public static function ehParticipante(): bool
    {
        return self::tipo() === self::PARTICIPANTE;
    }

    /**
     * Administrador ou Moderador.
     */
    public static function ehEquipe(): bool
    {
        return in_array(
            self::tipo(),
            [self::ADMINISTRADOR, self::MODERADOR],
            true
        );
    }

    public static function pode(string $permissao): bool
    {
        if (!self::autenticado()) {
            return false;
        }

        $tipo = self::tipo();

        if ($tipo === self::ADMINISTRADOR) {
            return true;
        }

        return in_array(
            $permissao,
            self::PERMISSOES[$tipo] ?? [],
            true
        );
    }

    /**
     * Bloqueia a execução quando o usuário não possuir a permissão.
     * Em endpoints AJAX retorna JSON 403.
     * Em páginas normais redireciona para o painel correspondente.
     */
    public static function exigir(string $permissao): void
    {
        self::garantirSessao();

        if (!self::autenticado()) {
            self::redirecionarLogin();
        }

        if (self::pode($permissao)) {
            return;
        }

        http_response_code(403);

        if (self::requisicaoJson()) {
            header("Content-Type: application/json; charset=UTF-8");

            echo json_encode(
                [
                    "status" => false,
                    "msg" => "Você não possui permissão para executar esta ação."
                ],
                JSON_UNESCAPED_UNICODE
            );

            exit;
        }

        if (class_exists("Session")) {
            Session::flash(
                "error",
                "Você não possui permissão para acessar esta área."
            );
        }

        $destino = self::tipo() === self::PARTICIPANTE
            ? self::baseUrl() . "user/index.php"
            : self::baseUrl() . "admin/dashboard/";

        header("Location: " . $destino);
        exit;
    }

    private static function requisicaoJson(): bool
    {
        $rota = strtolower(
            (string) ($_SERVER["PHP_SELF"] ?? "")
        );

        $accept = strtolower(
            (string) ($_SERVER["HTTP_ACCEPT"] ?? "")
        );

        return str_contains($rota, "/ajax/")
            || str_contains($accept, "application/json");
    }

    private static function redirecionarLogin(): never
    {
        header("Location: " . self::baseUrl() . "login/");
        exit;
    }

    private static function baseUrl(): string
    {
        return defined("BASE_URL")
            ? (string) BASE_URL
            : "/";
    }
}
