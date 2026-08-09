<?php

declare(strict_types=1);

class Notificacao
{
    private PDO $db;

    private const TIPOS = [
        "usuario",
        "inscricao",
"pagamento",
        "cancelamento"
    ];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();

        $this->db->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $this->db->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );
    }

    /**
     * Importa novos usuários, inscrições e pagamentos recebidos.
     *
     * A sincronização é global, mas a visualização respeita o perfil:
     * - Somente administradores veem todas as notificações.
     * - Moderadores e participantes veem somente notificações
     *   ligadas ao próprio usuário.
     */
    public function sincronizar(): void
    {
        $iniciouTransacao = !$this->db->inTransaction();

        if ($iniciouTransacao) {
            $this->db->beginTransaction();
        }

        try {
            $this->sincronizarUsuarios();
            $this->sincronizarInscricoes();
            $this->sincronizarPagamentos();

            if ($iniciouTransacao) {
                $this->db->commit();
            }
        } catch (Throwable $erro) {
            if (
                $iniciouTransacao
                && $this->db->inTransaction()
            ) {
                $this->db->rollBack();
            }

            throw $erro;
        }
    }

    /**
     * @return array{
     *     dados:array<int,array<string,mixed>>,
     *     naoLidas:int
     * }
     */
    public function listarResumo(
        int $idUsuario,
        int $tipoUsuario,
        int $limite = 8
    ): array {
        $limite = max(1, min(30, $limite));
        [$filtroAcesso, $paramsAcesso] = $this->filtroAcesso(
            $idUsuario,
            $tipoUsuario
        );

        $perfilAdministrativo =
            $this->ehAdministracao($tipoUsuario) ? 1 : 0;

        $params = array_merge(
            [
                ":idLeitor" => $idUsuario,
                ":perfilTituloResumo" => $perfilAdministrativo,
                ":perfilMensagemResumo" => $perfilAdministrativo,
                ":perfilUrlResumo" => $perfilAdministrativo
            ],
            $paramsAcesso
        );

        $stmt = $this->db->prepare("
            SELECT
                n.idNotificacao,
                n.tipo,
                CASE
                    WHEN :perfilTituloResumo = 1
                        THEN n.titulo
                    ELSE COALESCE(
                        NULLIF(n.tituloUsuario, ''),
                        n.titulo
                    )
                END AS titulo,
                CASE
                    WHEN :perfilMensagemResumo = 1
                        THEN n.mensagem
                    ELSE COALESCE(
                        NULLIF(n.mensagemUsuario, ''),
                        n.mensagem
                    )
                END AS mensagem,
                CASE
                    WHEN :perfilUrlResumo = 1
                        THEN n.url
                    ELSE COALESCE(
                        NULLIF(n.urlUsuario, ''),
                        n.url
                    )
                END AS url,
                n.criadoEm,
                CASE
                    WHEN nl.idNotificacao IS NULL THEN 0
                    ELSE 1
                END AS lida
            FROM notificacoes n
            LEFT JOIN notificacoes_lidas nl
                ON nl.idNotificacao = n.idNotificacao
               AND nl.idUsuario = :idLeitor
            {$filtroAcesso}
            ORDER BY n.criadoEm DESC, n.idNotificacao DESC
            LIMIT {$limite}
        ");

        $stmt->execute($params);

        return [
            "dados" => $stmt->fetchAll(),
            "naoLidas" => $this->contarNaoLidas(
                $idUsuario,
                $tipoUsuario
            )
        ];
    }

    /**
     * @return array{
     *     dados:array<int,array<string,mixed>>,
     *     total:int,
     *     pagina:int,
     *     paginas:int,
     *     naoLidas:int
     * }
     */
    public function listarTodos(
        int $idUsuario,
        int $tipoUsuario,
        string $tipo = "",
        string $status = "",
        int $pagina = 1,
        int $limite = 30
    ): array {
        $pagina = max(1, $pagina);
        $limite = max(10, min(100, $limite));

        [$filtroAcesso, $paramsAcesso] = $this->filtroAcesso(
            $idUsuario,
            $tipoUsuario
        );

        $where = [];
        $perfilAdministrativo =
            $this->ehAdministracao($tipoUsuario) ? 1 : 0;

        $params = array_merge(
            [
                ":idLeitor" => $idUsuario,
                ":perfilTituloLista" => $perfilAdministrativo,
                ":perfilMensagemLista" => $perfilAdministrativo,
                ":perfilUrlLista" => $perfilAdministrativo
            ],
            $paramsAcesso
        );

        if (
            $tipo !== ""
            && in_array($tipo, self::TIPOS, true)
        ) {
            $where[] = "n.tipo = :tipo";
            $params[":tipo"] = $tipo;
        }

        if ($status === "nao_lidas") {
            $where[] = "nl.idNotificacao IS NULL";
        } elseif ($status === "lidas") {
            $where[] = "nl.idNotificacao IS NOT NULL";
        }

        $filtroAdicional = $where !== []
            ? " AND " . implode(" AND ", $where)
            : "";

        $stmtTotal = $this->db->prepare("
            SELECT COUNT(*)
            FROM notificacoes n
            LEFT JOIN notificacoes_lidas nl
                ON nl.idNotificacao = n.idNotificacao
               AND nl.idUsuario = :idLeitor
            {$filtroAcesso}
            {$filtroAdicional}
        ");

        $paramsTotal = $params;
        unset(
            $paramsTotal[":perfilTituloLista"],
            $paramsTotal[":perfilMensagemLista"],
            $paramsTotal[":perfilUrlLista"]
        );

        $stmtTotal->execute($paramsTotal);
        $total = (int) $stmtTotal->fetchColumn();
        $paginas = max(1, (int) ceil($total / $limite));

        if ($pagina > $paginas) {
            $pagina = $paginas;
        }

        $offset = ($pagina - 1) * $limite;

        $stmt = $this->db->prepare("
            SELECT
                n.idNotificacao,
                n.tipo,
                CASE
                    WHEN :perfilTituloLista = 1
                        THEN n.titulo
                    ELSE COALESCE(
                        NULLIF(n.tituloUsuario, ''),
                        n.titulo
                    )
                END AS titulo,
                CASE
                    WHEN :perfilMensagemLista = 1
                        THEN n.mensagem
                    ELSE COALESCE(
                        NULLIF(n.mensagemUsuario, ''),
                        n.mensagem
                    )
                END AS mensagem,
                CASE
                    WHEN :perfilUrlLista = 1
                        THEN n.url
                    ELSE COALESCE(
                        NULLIF(n.urlUsuario, ''),
                        n.url
                    )
                END AS url,
                n.criadoEm,
                CASE
                    WHEN nl.idNotificacao IS NULL THEN 0
                    ELSE 1
                END AS lida
            FROM notificacoes n
            LEFT JOIN notificacoes_lidas nl
                ON nl.idNotificacao = n.idNotificacao
               AND nl.idUsuario = :idLeitor
            {$filtroAcesso}
            {$filtroAdicional}
            ORDER BY n.criadoEm DESC, n.idNotificacao DESC
            LIMIT {$limite}
            OFFSET {$offset}
        ");

        $stmt->execute($params);

        return [
            "dados" => $stmt->fetchAll(),
            "total" => $total,
            "pagina" => $pagina,
            "paginas" => $paginas,
            "naoLidas" => $this->contarNaoLidas(
                $idUsuario,
                $tipoUsuario
            )
        ];
    }

    public function contarNaoLidas(
        int $idUsuario,
        int $tipoUsuario
    ): int {
        [$filtroAcesso, $paramsAcesso] = $this->filtroAcesso(
            $idUsuario,
            $tipoUsuario
        );

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM notificacoes n
            LEFT JOIN notificacoes_lidas nl
                ON nl.idNotificacao = n.idNotificacao
               AND nl.idUsuario = :idLeitor
            {$filtroAcesso}
              AND nl.idNotificacao IS NULL
        ");

        $stmt->execute(
            array_merge(
                [":idLeitor" => $idUsuario],
                $paramsAcesso
            )
        );

        return (int) $stmt->fetchColumn();
    }

    public function marcarComoLida(
        int $idNotificacao,
        int $idUsuario,
        int $tipoUsuario
    ): bool {
        if (
            $idNotificacao <= 0
            || $idUsuario <= 0
            || !$this->usuarioPodeVisualizar(
                $idNotificacao,
                $idUsuario,
                $tipoUsuario
            )
        ) {
            return false;
        }

        $stmt = $this->db->prepare("
            INSERT IGNORE INTO notificacoes_lidas (
                idNotificacao,
                idUsuario,
                lidaEm
            ) VALUES (
                :idNotificacao,
                :idUsuario,
                NOW()
            )
        ");

        return $stmt->execute([
            ":idNotificacao" => $idNotificacao,
            ":idUsuario" => $idUsuario
        ]);
    }

    public function marcarTodasComoLidas(
        int $idUsuario,
        int $tipoUsuario
    ): bool {
        if ($idUsuario <= 0) {
            return false;
        }

        [$filtroAcesso, $paramsAcesso] = $this->filtroAcesso(
            $idUsuario,
            $tipoUsuario
        );

        $stmt = $this->db->prepare("
            INSERT IGNORE INTO notificacoes_lidas (
                idNotificacao,
                idUsuario,
                lidaEm
            )
            SELECT
                n.idNotificacao,
                :idLeitor,
                NOW()
            FROM notificacoes n
            {$filtroAcesso}
        ");

        return $stmt->execute(
            array_merge(
                [":idLeitor" => $idUsuario],
                $paramsAcesso
            )
        );
    }

    public function buscarParaUsuario(
        int $idNotificacao,
        int $idUsuario,
        int $tipoUsuario
    ): array|false {
        if (
            $idNotificacao <= 0
            || $idUsuario <= 0
        ) {
            return false;
        }

        $perfilAdministrativo =
            $this->ehAdministracao($tipoUsuario) ? 1 : 0;

        $filtroUsuario = "";
        $params = [
            ":idNotificacao" => $idNotificacao,
            ":perfilTituloBusca" => $perfilAdministrativo,
            ":perfilMensagemBusca" => $perfilAdministrativo,
            ":perfilUrlBusca" => $perfilAdministrativo
        ];

        if (!$this->ehAdministracao($tipoUsuario)) {
            $filtroUsuario =
                " AND n.idUsuarioRelacionado = :idUsuarioRelacionadoBusca";

            $params[":idUsuarioRelacionadoBusca"] = $idUsuario;
        }

        $stmt = $this->db->prepare("
            SELECT
                n.idNotificacao,
                n.tipo,
                CASE
                    WHEN :perfilTituloBusca = 1
                        THEN n.titulo
                    ELSE COALESCE(
                        NULLIF(n.tituloUsuario, ''),
                        n.titulo
                    )
                END AS titulo,
                CASE
                    WHEN :perfilMensagemBusca = 1
                        THEN n.mensagem
                    ELSE COALESCE(
                        NULLIF(n.mensagemUsuario, ''),
                        n.mensagem
                    )
                END AS mensagem,
                CASE
                    WHEN :perfilUrlBusca = 1
                        THEN n.url
                    ELSE COALESCE(
                        NULLIF(n.urlUsuario, ''),
                        n.url
                    )
                END AS url,
                n.criadoEm
            FROM notificacoes n
            WHERE n.idNotificacao = :idNotificacao
            {$filtroUsuario}
            LIMIT 1
        ");

        $stmt->execute($params);

        return $stmt->fetch();
    }

    private function usuarioPodeVisualizar(
        int $idNotificacao,
        int $idUsuario,
        int $tipoUsuario
    ): bool {
        return $this->buscarParaUsuario(
            $idNotificacao,
            $idUsuario,
            $tipoUsuario
        ) !== false;
    }

    /**
     * @return array{0:string,1:array<string,int>}
     */
    private function filtroAcesso(
        int $idUsuario,
        int $tipoUsuario
    ): array {
        if ($this->ehAdministracao($tipoUsuario)) {
            return [" WHERE 1 = 1", []];
        }

        return [
            " WHERE n.idUsuarioRelacionado = :idUsuarioRelacionado",
            [":idUsuarioRelacionado" => $idUsuario]
        ];
    }

    private function ehAdministracao(int $tipoUsuario): bool
    {
        return $tipoUsuario === 1;
    }

    private function sincronizarUsuarios(): void
    {
        [$inicio, $fim] = $this->janelaFonte("usuarios");

        $stmt = $this->db->prepare("
            SELECT
                id,
                nome,
                created_at
            FROM usuarios
            WHERE created_at IS NOT NULL
              AND created_at >= :inicio
              AND created_at <= :fim
            ORDER BY created_at, id
        ");

        $stmt->execute([
            ":inicio" => $inicio,
            ":fim" => $fim
        ]);

        foreach ($stmt->fetchAll() as $registro) {
            $nome = trim(
                (string) ($registro["nome"] ?? "Usuário")
            );

            $this->inserir([
                "tipo" => "usuario",
                "idReferencia" => (int) $registro["id"],
                "idUsuarioRelacionado" => (int) $registro["id"],
                "titulo" => "Novo usuário cadastrado",
                "mensagem" => $nome
                    . " criou um cadastro no sistema.",
                "url" => "admin/user/usuarios.php?pesquisa="
                    . rawurlencode($nome),
                "tituloUsuario" => "Cadastro realizado",
                "mensagemUsuario" =>
                    "Seu cadastro foi criado no sistema.",
                "urlUsuario" => "user/index.php",
                "criadoEm" => (string) $registro["created_at"]
            ]);
        }

        $this->atualizarFonte("usuarios", $fim);
    }

    private function sincronizarInscricoes(): void
    {
        [$inicio, $fim] = $this->janelaFonte("inscricoes");

        $stmt = $this->db->prepare("
            SELECT
                i.idInscricao,
                i.idUsuario,
                COALESCE(u.nome, i.nome) AS participante,
                e.titulo AS evento,
                i.criado_em
            FROM inscricoes i
            INNER JOIN eventos e
                ON e.idEvento = i.idEvento
            LEFT JOIN usuarios u
                ON u.id = i.idUsuario
            WHERE i.criado_em IS NOT NULL
              AND i.criado_em >= :inicio
              AND i.criado_em <= :fim
            ORDER BY i.criado_em, i.idInscricao
        ");

        $stmt->execute([
            ":inicio" => $inicio,
            ":fim" => $fim
        ]);

        foreach ($stmt->fetchAll() as $registro) {
            $participante = trim(
                (string) ($registro["participante"] ?? "Participante")
            );
            $evento = trim(
                (string) ($registro["evento"] ?? "evento")
            );

            $this->inserir([
                "tipo" => "inscricao",
                "idReferencia" => (int) $registro["idInscricao"],
                "idUsuarioRelacionado" => (int) $registro["idUsuario"],
                "titulo" => "Nova inscrição",
                "mensagem" => $participante
                    . " se inscreveu em "
                    . $evento
                    . ".",
                "url" => "admin/inscricao/inscricoes.php?pesquisa="
                    . rawurlencode($participante),
                "tituloUsuario" => "Inscrição realizada",
                "mensagemUsuario" =>
                    "Sua inscrição em "
                    . $evento
                    . " foi registrada.",
                "urlUsuario" => "user/index.php",
                "criadoEm" => (string) $registro["criado_em"]
            ]);
        }

        $this->atualizarFonte("inscricoes", $fim);
    }

    private function sincronizarPagamentos(): void
    {
        [$inicio, $fim] = $this->janelaFonte("pagamentos");

        $stmt = $this->db->prepare("
            SELECT
                p.idPagamento,
                i.idUsuario,
                p.participante,
                p.valor,
                p.formaPagamento,
                p.dataPagamento,
                e.titulo AS evento
            FROM pagamentos p
            LEFT JOIN inscricoes i
                ON i.idInscricao = p.idInscricao
            LEFT JOIN eventos e
                ON e.idEvento = p.idEvento
            WHERE p.status = 'Pago'
              AND p.dataPagamento IS NOT NULL
              AND p.dataPagamento >= :inicio
              AND p.dataPagamento <= :fim
            ORDER BY p.dataPagamento, p.idPagamento
        ");

        $stmt->execute([
            ":inicio" => $inicio,
            ":fim" => $fim
        ]);

        foreach ($stmt->fetchAll() as $registro) {
            $participante = trim(
                (string) ($registro["participante"] ?? "Participante")
            );
            $evento = trim(
                (string) ($registro["evento"] ?? "evento")
            );
            $valor = number_format(
                (float) ($registro["valor"] ?? 0),
                2,
                ",",
                "."
            );
            $forma = trim(
                (string) ($registro["formaPagamento"] ?? "")
            );

            $mensagem = "Pagamento de R$ "
                . $valor
                . " recebido de "
                . $participante
                . " para "
                . $evento
                . ".";

            if (
                $forma !== ""
                && $forma !== "NaoDefinido"
            ) {
                $mensagem .= " Forma: " . $forma . ".";
            }

            $this->inserir([
                "tipo" => "pagamento",
                "idReferencia" => (int) $registro["idPagamento"],
                "idUsuarioRelacionado" => (int) $registro["idUsuario"],
                "titulo" => "Novo pagamento recebido",
                "mensagem" => $mensagem,
                "url" => "admin/financeiro/pagamentos.php?pesquisa="
                    . rawurlencode($participante),
                "tituloUsuario" => "Pagamento recebido",
                "mensagemUsuario" =>
                    "Seu pagamento de R$ "
                    . $valor
                    . " para "
                    . $evento
                    . " foi confirmado.",
                "urlUsuario" => "user/index.php",
                "criadoEm" => (string) $registro["dataPagamento"]
            ]);
        }

        $this->atualizarFonte("pagamentos", $fim);
    }

    /**
     * @return array{0:string,1:string}
     */
    private function janelaFonte(string $fonte): array
    {
        $stmt = $this->db->prepare("
            SELECT sincronizadoEm
            FROM notificacao_fontes
            WHERE fonte = :fonte
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            ":fonte" => $fonte
        ]);

        $inicio = $stmt->fetchColumn();

        if ($inicio === false) {
            $agora = date("Y-m-d H:i:s");

            $stmtInserir = $this->db->prepare("
                INSERT INTO notificacao_fontes (
                    fonte,
                    sincronizadoEm
                ) VALUES (
                    :fonte,
                    :sincronizadoEm
                )
            ");

            $stmtInserir->execute([
                ":fonte" => $fonte,
                ":sincronizadoEm" => $agora
            ]);

            return [$agora, $agora];
        }

        return [
            (string) $inicio,
            date("Y-m-d H:i:s")
        ];
    }

    private function atualizarFonte(
        string $fonte,
        string $sincronizadoEm
    ): void {
        $stmt = $this->db->prepare("
            UPDATE notificacao_fontes
            SET sincronizadoEm = :sincronizadoEm
            WHERE fonte = :fonte
        ");

        $stmt->execute([
            ":sincronizadoEm" => $sincronizadoEm,
            ":fonte" => $fonte
        ]);
    }

    /**
     * @param array<string,mixed> $dados
     */
    private function inserir(array $dados): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO notificacoes (
                tipo,
                idReferencia,
                idUsuarioRelacionado,
                titulo,
                mensagem,
                url,
                tituloUsuario,
                mensagemUsuario,
                urlUsuario,
                criadoEm
            ) VALUES (
                :tipo,
                :idReferencia,
                :idUsuarioRelacionado,
                :titulo,
                :mensagem,
                :url,
                :tituloUsuario,
                :mensagemUsuario,
                :urlUsuario,
                :criadoEm
            )
            ON DUPLICATE KEY UPDATE
                idUsuarioRelacionado =
                    VALUES(idUsuarioRelacionado),
                titulo = VALUES(titulo),
                mensagem = VALUES(mensagem),
                url = VALUES(url),
                tituloUsuario = VALUES(tituloUsuario),
                mensagemUsuario = VALUES(mensagemUsuario),
                urlUsuario = VALUES(urlUsuario),
                criadoEm = VALUES(criadoEm)
        ");

        $stmt->execute([
            ":tipo" => (string) $dados["tipo"],
            ":idReferencia" => (int) $dados["idReferencia"],
            ":idUsuarioRelacionado" =>
                (int) ($dados["idUsuarioRelacionado"] ?? 0) > 0
                    ? (int) $dados["idUsuarioRelacionado"]
                    : null,
            ":titulo" => substr(
                trim((string) $dados["titulo"]),
                0,
                150
            ),
            ":mensagem" => substr(
                trim((string) $dados["mensagem"]),
                0,
                500
            ),
            ":url" => substr(
                trim((string) $dados["url"]),
                0,
                500
            ),
            ":tituloUsuario" => substr(
                trim((string) $dados["tituloUsuario"]),
                0,
                150
            ),
            ":mensagemUsuario" => substr(
                trim((string) $dados["mensagemUsuario"]),
                0,
                500
            ),
            ":urlUsuario" => substr(
                trim((string) $dados["urlUsuario"]),
                0,
                500
            ),
            ":criadoEm" => (string) $dados["criadoEm"]
        ]);
    }
}