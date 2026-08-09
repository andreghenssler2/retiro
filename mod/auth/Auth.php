<?php

class Auth
{
    private PDO $db;

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $this->db = Database::connect();
    }

    /**
     * Realiza o login
     */
    public function login(string $email, string $senha): bool
    {
        $sql = $this->db->prepare("
            SELECT *
            FROM usuarios
            WHERE email = :email
              AND ativo = 1
            LIMIT 1
        ");

        $sql->bindValue(':email', trim($email));
        $sql->execute();

        $usuario = $sql->fetch(PDO::FETCH_ASSOC);

        if (!$usuario) {
            return false;
        }

        if (!password_verify($senha, $usuario['senha'])) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION['user'] = [
            'id' => (int) $usuario['id'],
            'nome' => $usuario['nome'],
            'email' => $usuario['email'],
            'tipo' => (int) $usuario['tipo'],
            'cpf' => $usuario['cpf'],
            // 'foto' => ""  BASE_URL . 'uploads/usuarios/' . ($usuario['foto'] ?? '<i class="fas fa-user"></i>'),
            'foto' =>"<img src='" . BASE_URL . 'uploads/usuarios/' . ($usuario['foto'] ?? 'user.png') . "' alt='Avatar' class='rounded-circle shadow avatar-image'>",
            
            'created_at' => $usuario['created_at'],
            'ultimo_login' => $usuario['ultimo_login']
        ];

        $this->atualizarUltimoLogin((int) $usuario['id']);

        return true;
    }

    public function rememberMe(): void
    {
        $token = bin2hex(random_bytes(64));

        $expira = date('Y-m-d H:i:s', strtotime('+30 days'));

        $sql = "UPDATE usuarios
               SET remember_token=?,
                   remember_expira=?
             WHERE id=?";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            password_hash($token, PASSWORD_DEFAULT),
            $expira,
            $_SESSION['usuario']['id']
        ]);

        setcookie(
            'remember_me',
            $_SESSION['usuario']['id'] . ':' . $token,
            [
                'expires' => strtotime('+30 days'),
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }
    /**
     * Atualiza último login
     */
    private function atualizarUltimoLogin(int $id): void
    {
        $sql = $this->db->prepare("
            UPDATE usuarios
            SET ultimo_login = NOW()
            WHERE id = :id
        ");

        $sql->bindValue(':id', $id, PDO::PARAM_INT);
        $sql->execute();
    }

    /**
     * Logout
     */
    public static function logout(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

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
     * Usuário logado?
     */
    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    /**
     * Retorna usuário
     */
    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    /**
     * ID
     */
    public static function id(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    /**
     * Nome
     */
    public static function nome(): ?string
    {
        return $_SESSION['user']['nome'] ?? null;
    }

    /**
     * Email
     */
    public static function email(): ?string
    {
        return $_SESSION['user']['email'] ?? null;
    }

    /**
     * Tipo de usuário
     */
    public static function tipo(): int
    {
        return (int) ($_SESSION['user']['tipo'] ?? 0);
    }
    /**
     * Foto do usuário
     */
    public static function fotoUser(): ?string
    {
        return $_SESSION['user']['foto'] ?? null;
    }
    
    /**
     * Foto do usuário
     */
    public static function cpfUser(): ?string
    {
        return $_SESSION['user']['cpf'] ?? '000.000.000-00';
    }

    /**
     * Administrador
     */
    public static function isAdmin(): bool
    {
        return self::tipo() === 1;
    }

    /**
     * Moderador
     */
    public static function isModerador(): bool
    {
        return self::tipo() === 2;
    }

    /**
     * Participante
     */
    public static function isParticipante(): bool
    {
        return self::tipo() === 3;
    }

    /**
     * Exige login
     */
    public static function requireLogin(): void
    {
        if (!self::check()) {
            header('Location: /login/');
            exit;
        }
    }

    /**
     * Exige administrador
     */
    public static function requireAdmin(): void
    {
        self::requireLogin();

        if (!self::isAdmin()) {
            http_response_code(403);
            exit('Acesso negado.');
        }
    }

    /**
     * Exige moderador ou administrador
     */
    public static function requireModerador(): void
    {
        self::requireLogin();

        if (!(self::isAdmin() || self::isModerador())) {
            http_response_code(403);
            exit('Acesso negado.');
        }
    }

    /**
     * Redireciona para o painel correto
     */
    public static function redirectDashboard(): void
    {
        self::requireLogin();

        if (headers_sent($file, $line)) {
            die("Headers enviados em: $file linha $line");
        }

        switch (self::tipo()) {
            case 1:
                header('Location: /admin/index.php');
                break;

            case 2:
                header('Location: /my/index.php?acess=2&id=' . self::id());
                break;
            case 3:
                header('Location: /my/index.php?id=' . self::id());
                break;

            default:
                header('Location: /');
                break;
        }

        exit;
    }
}