<?php

declare(strict_types=1);

class AtividadeUsuario
{
    private PDO $db;
    private static bool $requisicaoAgendada = false;
    private static bool $ignorarRequisicao = false;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Agenda o registro automático da requisição no final da execução.
     *
     * A sessão não precisa estar iniciada quando este método é chamado.
     * O usuário autenticado é consultado somente no shutdown.
     */
    public static function agendarRegistroRequisicao(PDO $db): void
    {
        if (
            self::$requisicaoAgendada
            || PHP_SAPI === "cli"
            || PHP_SAPI === "phpdbg"
        ) {
            return;
        }

        self::$requisicaoAgendada = true;

        register_shutdown_function(
            static function () use ($db): void {
                if (self::$ignorarRequisicao) {
                    return;
                }

                $usuario = $_SESSION["user"] ?? null;

                if (
                    !is_array($usuario)
                    || (int) ($usuario["id"] ?? 0) <= 0
                ) {
                    return;
                }

                try {
                    $logger = new self($db);
                    $logger->registrarRequisicao($usuario);
                } catch (Throwable $erro) {
                    /*
                     * Não usa a classe Log para evitar ciclos caso o banco
                     * esteja indisponível.
                     */
                    error_log(
                        "Falha ao registrar atividade do usuário: "
                        . $erro->getMessage()
                    );
                }
            }
        );
    }

    /**
     * Impede que a requisição atual seja registrada automaticamente.
     */
    public static function ignorarRequisicaoAtual(): void
    {
        self::$ignorarRequisicao = true;
    }

    /**
     * Registra imediatamente uma ação específica do usuário autenticado.
     */
    public static function registrarAcaoAtual(
        PDO $db,
        string $acesso,
        string $descricao
    ): bool {
        $usuario = $_SESSION["user"] ?? null;

        if (
            !is_array($usuario)
            || (int) ($usuario["id"] ?? 0) <= 0
        ) {
            return false;
        }

        $logger = new self($db);

        return $logger->registrar([
            "idUsuario" => (int) $usuario["id"],
            "nomeUsuario" => trim(
                (string) ($usuario["nome"] ?? "Usuário")
            ),
            "tipoUsuario" => (int) ($usuario["tipo"] ?? 0),
            "acesso" => $acesso,
            "rota" => self::rotaAtual(),
            "descricao" => $descricao,
            "metodo" => strtoupper(
                (string) ($_SERVER["REQUEST_METHOD"] ?? "CLI")
            ),
            "ip" => self::enderecoIp(),
            "userAgent" => substr(
                trim((string) ($_SERVER["HTTP_USER_AGENT"] ?? "")),
                0,
                500
            )
        ]);
    }

    /**
     * Registra a requisição atual.
     *
     * Não armazena parâmetros GET, dados POST, senhas, tokens ou cookies.
     */
    private function registrarRequisicao(array $usuario): bool
    {
        $rota = self::rotaAtual();
        $metodo = strtoupper(
            (string) ($_SERVER["REQUEST_METHOD"] ?? "GET")
        );
        $acesso = self::identificarAcesso($rota);

        return $this->registrar([
            "idUsuario" => (int) $usuario["id"],
            "nomeUsuario" => trim(
                (string) ($usuario["nome"] ?? "Usuário")
            ),
            "tipoUsuario" => (int) ($usuario["tipo"] ?? 0),
            "acesso" => $acesso,
            "rota" => $rota,
            "descricao" => self::descricaoRequisicao(
                $metodo,
                $acesso,
                $rota
            ),
            "metodo" => $metodo,
            "ip" => self::enderecoIp(),
            "userAgent" => substr(
                trim((string) ($_SERVER["HTTP_USER_AGENT"] ?? "")),
                0,
                500
            )
        ]);
    }

    /**
     * @param array<string,mixed> $dados
     */
    public function registrar(array $dados): bool
    {
        $idUsuario = (int) ($dados["idUsuario"] ?? 0);
        $nomeUsuario = trim(
            (string) ($dados["nomeUsuario"] ?? "")
        );
        $tipoUsuario = (int) ($dados["tipoUsuario"] ?? 0);
        $acesso = trim((string) ($dados["acesso"] ?? ""));
        $rota = trim((string) ($dados["rota"] ?? ""));
        $descricao = trim(
            (string) ($dados["descricao"] ?? "")
        );
        $metodo = strtoupper(
            trim((string) ($dados["metodo"] ?? "GET"))
        );
        $ip = trim((string) ($dados["ip"] ?? ""));
        $userAgent = trim(
            (string) ($dados["userAgent"] ?? "")
        );

        if (
            $idUsuario <= 0
            || $nomeUsuario === ""
            || $acesso === ""
            || $descricao === ""
        ) {
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT INTO atividades_usuarios (
                idUsuario,
                nomeUsuario,
                tipoUsuario,
                acesso,
                rota,
                descricao,
                metodo,
                ip,
                userAgent,
                criadoEm
            ) VALUES (
                :idUsuario,
                :nomeUsuario,
                :tipoUsuario,
                :acesso,
                :rota,
                :descricao,
                :metodo,
                :ip,
                :userAgent,
                NOW()
            )
        ");

        return $stmt->execute([
            ":idUsuario" => $idUsuario,
            ":nomeUsuario" => substr($nomeUsuario, 0, 150),
            ":tipoUsuario" => $tipoUsuario,
            ":acesso" => substr($acesso, 0, 180),
            ":rota" => substr($rota, 0, 500),
            ":descricao" => substr($descricao, 0, 500),
            ":metodo" => substr($metodo, 0, 10),
            ":ip" => substr($ip, 0, 45),
            ":userAgent" => substr($userAgent, 0, 500)
        ]);
    }

    /**
     * Lista os registros do dia atual.
     *
     * @return array{
     *     dados:array<int,array<string,mixed>>,
     *     total:int,
     *     pagina:int,
     *     paginas:int
     * }
     */
    public function listarHoje(
        string $pesquisa = "",
        int $pagina = 1,
        int $limite = 50,
        int $idUsuario = 0
    ): array {
        $inicio = date("Y-m-d 00:00:00");
        $fim = date("Y-m-d 23:59:59");

        return $this->listarPeriodo(
            $inicio,
            $fim,
            $pesquisa,
            $pagina,
            $limite,
            $idUsuario
        );
    }

    /**
     * Lista todos os registros, com filtros opcionais.
     *
     * @return array{
     *     dados:array<int,array<string,mixed>>,
     *     total:int,
     *     pagina:int,
     *     paginas:int
     * }
     */
    public function listarTodos(
        string $pesquisa = "",
        string $dataInicio = "",
        string $dataFim = "",
        int $pagina = 1,
        int $limite = 50,
        int $idUsuario = 0
    ): array {
        $inicio = self::dataValida($dataInicio)
            ? $dataInicio . " 00:00:00"
            : "";

        $fim = self::dataValida($dataFim)
            ? $dataFim . " 23:59:59"
            : "";

        return $this->listarPeriodo(
            $inicio,
            $fim,
            $pesquisa,
            $pagina,
            $limite,
            $idUsuario
        );
    }

    /**
     * @return array{
     *     dados:array<int,array<string,mixed>>,
     *     total:int,
     *     pagina:int,
     *     paginas:int
     * }
     */
    private function listarPeriodo(
        string $inicio,
        string $fim,
        string $pesquisa,
        int $pagina,
        int $limite,
        int $idUsuario = 0
    ): array {
        $pagina = max(1, $pagina);
        $limite = max(10, min(200, $limite));
        $offset = ($pagina - 1) * $limite;

        $where = [];
        $params = [];

        if ($idUsuario > 0) {
            $where[] = "idUsuario = :id_usuario";
            $params[":id_usuario"] = $idUsuario;
        }

        if ($inicio !== "") {
            $where[] = "criadoEm >= :data_inicio";
            $params[":data_inicio"] = $inicio;
        }

        if ($fim !== "") {
            $where[] = "criadoEm <= :data_fim";
            $params[":data_fim"] = $fim;
        }

        $pesquisa = trim($pesquisa);

        if ($pesquisa !== "") {
            $termo = "%" . $pesquisa . "%";

            $where[] = "(
                nomeUsuario LIKE :pesquisa_nome
                OR acesso LIKE :pesquisa_acesso
                OR descricao LIKE :pesquisa_descricao
                OR ip LIKE :pesquisa_ip
                OR rota LIKE :pesquisa_rota
            )";

            $params[":pesquisa_nome"] = $termo;
            $params[":pesquisa_acesso"] = $termo;
            $params[":pesquisa_descricao"] = $termo;
            $params[":pesquisa_ip"] = $termo;
            $params[":pesquisa_rota"] = $termo;
        }

        $sqlWhere = $where !== []
            ? " WHERE " . implode(" AND ", $where)
            : "";

        $stmtTotal = $this->db->prepare(
            "SELECT COUNT(*)
             FROM atividades_usuarios"
             . $sqlWhere
        );
        $stmtTotal->execute($params);

        $total = (int) $stmtTotal->fetchColumn();
        $paginas = max(1, (int) ceil($total / $limite));

        if ($pagina > $paginas) {
            $pagina = $paginas;
            $offset = ($pagina - 1) * $limite;
        }

        $stmt = $this->db->prepare("
            SELECT
                idAtividade,
                idUsuario,
                nomeUsuario,
                tipoUsuario,
                acesso,
                rota,
                descricao,
                metodo,
                ip,
                criadoEm
            FROM atividades_usuarios
            {$sqlWhere}
            ORDER BY criadoEm DESC, idAtividade DESC
            LIMIT {$limite}
            OFFSET {$offset}
        ");

        $stmt->execute($params);

        return [
            "dados" => $stmt->fetchAll(PDO::FETCH_ASSOC),
            "total" => $total,
            "pagina" => $pagina,
            "paginas" => $paginas
        ];
    }

    private static function rotaAtual(): string
    {
        $uri = (string) ($_SERVER["REQUEST_URI"] ?? "/");
        $rota = parse_url($uri, PHP_URL_PATH);

        if (!is_string($rota) || $rota === "") {
            return "/";
        }

        return substr($rota, 0, 500);
    }

    private static function enderecoIp(): string
    {
        $candidatos = [
            $_SERVER["HTTP_CF_CONNECTING_IP"] ?? "",
            $_SERVER["HTTP_X_FORWARDED_FOR"] ?? "",
            $_SERVER["REMOTE_ADDR"] ?? ""
        ];

        foreach ($candidatos as $candidato) {
            $ip = trim(
                explode(",", (string) $candidato)[0]
            );

            if (
                $ip !== ""
                && filter_var($ip, FILTER_VALIDATE_IP)
            ) {
                return $ip;
            }
        }

        return "Não identificado";
    }

    private static function identificarAcesso(string $rota): string
    {
        $mapa = [
            "/admin/dashboard" => "Dashboard",
            "/admin/user" => "Usuários",
            "/admin/event" => "Eventos",
            "/admin/inscricao" => "Inscrições",
            "/admin/credenciamento" => "Credenciamento",
            "/admin/financeiro/pagamentos" => "Pagamentos",
            "/admin/financeiro" => "Financeiro",
            "/admin/certificado" => "Certificados",
            "/admin/relatorios" => "Relatórios",
            "/admin/configuracoes/email" => "Configuração de e-mail",
            "/admin/configuracoes/title" => "Informações do site",
            "/admin/configuracoes/atividades" => "Atividades dos usuários",
            "/admin/configuracoes/bancario" => "Configuração bancária",
            "/admin/perfil" => "Perfil administrativo",
            "/user/certificados" => "Meus certificados",
            "/user/certificado-baixar" => "Download de certificado",
            "/user" => "Meu perfil",
            "/certificado/validar" => "Validação de certificado",
            "/login/logout" => "Encerramento da sessão",
            "/login" => "Autenticação",
            "/api/" => "API do sistema"
        ];

        foreach ($mapa as $prefixo => $nome) {
            if (str_contains($rota, $prefixo)) {
                return $nome;
            }
        }

        $arquivo = basename($rota);

        if ($arquivo === "" || $arquivo === "/") {
            return "Página inicial";
        }

        $nome = pathinfo($arquivo, PATHINFO_FILENAME);
        $nome = str_replace(
            ["-", "_"],
            " ",
            $nome
        );

        return ucfirst($nome !== "" ? $nome : "Sistema");
    }

    private static function descricaoRequisicao(
        string $metodo,
        string $acesso,
        string $rota
    ): string {
        $acao = match ($metodo) {
            "POST" => "Enviou dados ou executou uma ação",
            "PUT", "PATCH" => "Atualizou informações",
            "DELETE" => "Solicitou uma exclusão",
            default => "Acessou"
        };

        return $acao
            . " em "
            . $acesso
            . " ("
            . $rota
            . ").";
    }

    private static function dataValida(string $data): bool
    {
        if ($data === "") {
            return false;
        }

        $objeto = DateTime::createFromFormat(
            "Y-m-d",
            $data
        );

        return $objeto instanceof DateTime
            && $objeto->format("Y-m-d") === $data;
    }
}