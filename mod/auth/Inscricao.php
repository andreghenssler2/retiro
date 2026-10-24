<?php

declare(strict_types=1);

require_once __DIR__ . "/../database/db.php";

class Inscricao
{
    private PDO $db;

    public function __construct(?PDO $conexao = null)
    {
        $this->db = $conexao ?? Database::connect();
    }

    public function ultimoId(): int
    {
        return (int) $this->db->lastInsertId();
    }

    public function buscar(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        $sql = "
            SELECT
                i.*,
                e.titulo,
                e.data_inicio,
                e.data_fim,
                e.local,
                e.vagas,
                e.camiseta_ativa,
                e.pagamento_obrigatorio,
                e.valor_inscricao,
                COALESCE(u.nome, i.nome) AS nome,
                COALESCE(u.cpf, i.cpf) AS cpf,
                COALESCE(u.email, i.email) AS email,
                COALESCE(u.telefone, i.telefone) AS telefone,
                u.idComunidade,
                cm.nome_comunidade AS igreja
            FROM inscricoes i
            INNER JOIN eventos e
                ON e.idEvento = i.idEvento
            LEFT JOIN usuarios u
                ON u.id = i.idUsuario
            LEFT JOIN minha_comunidade cm
                ON cm.id = u.idComunidade
            WHERE i.idInscricao = :idInscricao
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([":idInscricao" => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function listar(): array
    {
        return $this->listarPaginado(
            "",
            0,
            "",
            "",
            "criado_em",
            "DESC",
            1,
            100000
        )["dados"];
    }

    public function listarPaginado(
        string $pesquisa = "",
        int $evento = 0,
        string $status = "",
        string $pagamento = "",
        string $ordem = "criado_em",
        string $direcao = "DESC",
        int $pagina = 1,
        int $limite = 20
    ): array {
        $pagina = max(1, $pagina);
        $limite = max(1, min(100000, $limite));
        $offset = ($pagina - 1) * $limite;

        $ordensPermitidas = [
            "criado_em",
            "idInscricao",
            "nome",
            "status",
            "pagamento"
        ];

        if (!in_array($ordem, $ordensPermitidas, true)) {
            $ordem = "criado_em";
        }

        $direcao = strtoupper($direcao) === "ASC"
            ? "ASC"
            : "DESC";

        $where = [];
        $params = [];

        $pesquisa = trim($pesquisa);

        if ($pesquisa !== "") {
            $where[] = "(
                u.nome LIKE :pesquisa_usuario_nome
                OR u.email LIKE :pesquisa_usuario_email
                OR u.cpf LIKE :pesquisa_usuario_cpf
                OR i.nome LIKE :pesquisa_inscricao_nome
                OR i.email LIKE :pesquisa_inscricao_email
                OR i.cpf LIKE :pesquisa_inscricao_cpf
            )";

            $termoPesquisa = "%{$pesquisa}%";

            $params[":pesquisa_usuario_nome"] = $termoPesquisa;
            $params[":pesquisa_usuario_email"] = $termoPesquisa;
            $params[":pesquisa_usuario_cpf"] = $termoPesquisa;
            $params[":pesquisa_inscricao_nome"] = $termoPesquisa;
            $params[":pesquisa_inscricao_email"] = $termoPesquisa;
            $params[":pesquisa_inscricao_cpf"] = $termoPesquisa;
        }

        if ($evento > 0) {
            $where[] = "i.idEvento = :idEvento";
            $params[":idEvento"] = $evento;
        }

        if ($status !== "") {
            $where[] = "i.status = :status";
            $params[":status"] = $status;
        }

        if ($pagamento !== "") {
            $where[] = "i.pagamento = :pagamento";
            $params[":pagamento"] = $pagamento;
        }

        $sqlWhere = $where
            ? " WHERE " . implode(" AND ", $where)
            : "";

        $sqlBase = "
            FROM inscricoes i
            INNER JOIN eventos e
                ON e.idEvento = i.idEvento
            LEFT JOIN usuarios u
                ON u.id = i.idUsuario
        ";

        $stmtTotal = $this->db->prepare(
            "SELECT COUNT(*) {$sqlBase} {$sqlWhere}"
        );
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        $sql = "
            SELECT
                i.*,
                e.titulo,
                e.camiseta_ativa,
                e.pagamento_obrigatorio,
                e.certificado AS evento_certificado,
                e.certificado_ativo AS evento_certificado_ativo,
                COALESCE(u.nome, i.nome) AS nome,
                COALESCE(u.email, i.email) AS email,
                COALESCE(u.cpf, i.cpf) AS cpf,
                COALESCE(u.telefone, i.telefone) AS telefone
            {$sqlBase}
            {$sqlWhere}
            ORDER BY i.{$ordem} {$direcao}
            LIMIT :limite OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $campo => $valor) {
            $stmt->bindValue(
                $campo,
                $valor,
                is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }

        $stmt->bindValue(":limite", $limite, PDO::PARAM_INT);
        $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            "dados" => $stmt->fetchAll(PDO::FETCH_ASSOC),
            "total" => $total
        ];
    }

    /**
     * Salva o relacionamento entre usuário e evento.
     * Os dados pessoais são mantidos como fotografia histórica,
     * mas o vínculo oficial é idUsuario + idEvento.
     */
    public function salvar(array $dados): bool
    {
        $sql = "
            INSERT INTO inscricoes (
                idEvento,
                idUsuario,
                nome,
                cpf,
                rg,
                email,
                telefone,
                sexo,
                data_nascimento,
                cidade,
                estado,
                camiseta,
                observacoes,
                contato_emergencia,
                telefone_emergencia,
                status,
                pagamento,
                presenca,
                certificado,
                valor,
                valor_pago,
                forma_pagamento,
                codigo_pagamento
            ) VALUES (
                :idEvento,
                :idUsuario,
                :nome,
                :cpf,
                :rg,
                :email,
                :telefone,
                :sexo,
                :data_nascimento,
                :cidade,
                :estado,
                :camiseta,
                :observacoes,
                :contato_emergencia,
                :telefone_emergencia,
                :status,
                :pagamento,
                :presenca,
                :certificado,
                :valor,
                :valor_pago,
                :forma_pagamento,
                :codigo_pagamento
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($this->parametrosPersistencia($dados));
    }

    public function editar(array $dados): bool
    {
        $idInscricao = (int) ($dados["idInscricao"] ?? 0);

        if ($idInscricao <= 0) {
            throw new InvalidArgumentException("Inscrição inválida.");
        }

        $sql = "
            UPDATE inscricoes
            SET
                idEvento = :idEvento,
                idUsuario = :idUsuario,
                nome = :nome,
                cpf = :cpf,
                rg = :rg,
                email = :email,
                telefone = :telefone,
                sexo = :sexo,
                data_nascimento = :data_nascimento,
                cidade = :cidade,
                estado = :estado,
                camiseta = :camiseta,
                observacoes = :observacoes,
                contato_emergencia = :contato_emergencia,
                telefone_emergencia = :telefone_emergencia,
                certificado = :certificado,
                valor = :valor
            WHERE idInscricao = :idInscricao
        ";

        $params = $this->parametrosPersistencia($dados);

        unset(
            $params[":status"],
            $params[":pagamento"],
            $params[":presenca"],
            $params[":valor_pago"],
            $params[":forma_pagamento"],
            $params[":codigo_pagamento"]
        );

        $params[":idInscricao"] = $idInscricao;

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    public function excluir(int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        $iniciouTransacao = !$this->db->inTransaction();

        if ($iniciouTransacao) {
            $this->db->beginTransaction();
        }

        try {
            $stmtPagamento = $this->db->prepare("
                DELETE FROM pagamentos
                WHERE idInscricao = :idInscricao
            ");
            $stmtPagamento->execute([":idInscricao" => $id]);

            $stmt = $this->db->prepare("
                DELETE FROM inscricoes
                WHERE idInscricao = :idInscricao
            ");
            $stmt->execute([":idInscricao" => $id]);

            $removido = $stmt->rowCount() > 0;

            if ($iniciouTransacao) {
                $this->db->commit();
            }

            return $removido;
        } catch (Throwable $erro) {
            if ($iniciouTransacao && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $erro;
        }
    }

    public function alterarStatus(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE inscricoes
            SET status = CASE
                WHEN status = 'Pendente' THEN 'Confirmada'
                WHEN status = 'Confirmada' THEN 'Cancelada'
                ELSE 'Pendente'
            END
            WHERE idInscricao = :idInscricao
        ");

        return $stmt->execute([":idInscricao" => $id]);
    }

    /**
     * O pagamento agora é alterado somente em /admin/financeiro.
     */
    public function alterarPagamento(int $id): bool
    {
        return false;
    }

    public function alterarPresenca(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE inscricoes
            SET presenca = IF(presenca = 1, 0, 1)
            WHERE idInscricao = :idInscricao
        ");

        return $stmt->execute([":idInscricao" => $id]);
    }

    public function alterarCertificado(int $id): bool
    {
        $stmt = $this->db->prepare("
            UPDATE inscricoes
            SET certificado = IF(certificado = 1, 0, 1)
            WHERE idInscricao = :idInscricao
        ");

        return $stmt->execute([":idInscricao" => $id]);
    }


    /**
     * Lista todas as inscrições de um usuário,
     * incluindo o histórico de cancelamentos.
     *
     * A página do usuário utiliza este método para
     * exibir "Meus Eventos".
     */
    public function listarPorUsuario(
        int $idUsuario
    ): array {
        if ($idUsuario <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT
                i.idInscricao,
                i.idEvento,
                i.idUsuario,
                i.status AS statusInscricao,
                i.pagamento AS pagamentoInscricao,
                i.valor,
                i.valor_pago,
                i.criado_em AS inscritoEm,

                e.titulo,
                e.slug,
                e.imagem,
                e.tipo,
                e.data_inicio,
                e.hora_inicio,
                e.data_fim,
                e.local,
                e.cidade,
                e.estado,
                e.inscricao_fim,

                p.idPagamento,
                p.status AS statusPagamento,
                p.formaPagamento,
                p.valor AS valorPagamento,
                p.asaasPaymentId

            FROM inscricoes i

            INNER JOIN eventos e
                ON e.idEvento = i.idEvento

            LEFT JOIN pagamentos p
                ON p.idPagamento = (
                    SELECT MAX(p2.idPagamento)
                    FROM pagamentos p2
                    WHERE p2.idInscricao =
                        i.idInscricao
                )

            WHERE i.idUsuario = :idUsuario

            ORDER BY
                CASE
                    WHEN i.status = 'Cancelada'
                        THEN 1
                    ELSE 0
                END ASC,

                CASE
                    WHEN COALESCE(
                        e.data_fim,
                        e.data_inicio
                    ) >= CURDATE()
                        THEN 0
                    ELSE 1
                END ASC,

                CASE
                    WHEN COALESCE(
                        e.data_fim,
                        e.data_inicio
                    ) >= CURDATE()
                        THEN e.data_inicio
                    ELSE NULL
                END ASC,

                i.criado_em DESC
        ");

        $stmt->execute([
            ":idUsuario" => $idUsuario
        ]);

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function listarPorEvento(int $idEvento): array
    {
        $stmt = $this->db->prepare("
            SELECT
                i.*,
                COALESCE(u.nome, i.nome) AS nome,
                COALESCE(u.email, i.email) AS email,
                COALESCE(u.telefone, i.telefone) AS telefone
            FROM inscricoes i
            LEFT JOIN usuarios u
                ON u.id = i.idUsuario
            WHERE i.idEvento = :idEvento
            ORDER BY nome
        ");

        $stmt->execute([":idEvento" => $idEvento]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listarConfirmadas(): array
    {
        return $this->listarPorCampo("status", "Confirmada");
    }

    public function listarPendentes(): array
    {
        return $this->listarPorCampo("status", "Pendente");
    }

    public function listarPagas(): array
    {
        return $this->listarPorCampo("pagamento", "Pago");
    }

    public function cpfExiste(string $cpf, int $id = 0): bool
    {
        $sql = "
            SELECT COUNT(*)
            FROM inscricoes
            WHERE cpf = :cpf
        ";

        $params = [":cpf" => $cpf];

        if ($id > 0) {
            $sql .= " AND idInscricao <> :idInscricao ";
            $params[":idInscricao"] = $id;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Verifica se o usuário possui uma inscrição ativa no evento.
     *
     * Inscrições canceladas ou com pagamento cancelado/estornado são
     * mantidas no histórico, mas não impedem uma nova inscrição.
     */

    public function buscarDoUsuarioNoEvento(
        int $idEvento,
        int $idUsuario
    ): array|false {
        if ($idEvento <= 0 || $idUsuario <= 0) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM inscricoes
            WHERE idEvento = :idEvento
              AND idUsuario = :idUsuario
              AND status <> 'Cancelada'
              AND pagamento NOT IN (
                    'Cancelado',
                    'Estornado'
                  )
            ORDER BY idInscricao DESC
            LIMIT 1
        ");

        $stmt->execute([
            ":idEvento" => $idEvento,
            ":idUsuario" => $idUsuario
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function existeNoEvento(
        int $idEvento,
        int $idUsuario,
        int $idInscricao = 0
    ): bool {
        $sql = "
            SELECT COUNT(*)
            FROM inscricoes
            WHERE idEvento = :idEvento
              AND idUsuario = :idUsuario
              AND status <> 'Cancelada'
              AND pagamento NOT IN ('Cancelado', 'Estornado')
        ";

        $params = [
            ":idEvento" => $idEvento,
            ":idUsuario" => $idUsuario
        ];

        if ($idInscricao > 0) {
            $sql .= " AND idInscricao <> :idInscricao ";
            $params[":idInscricao"] = $idInscricao;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function listarHistoricoUsuario(int $idUsuario): array
    {
        $stmt = $this->db->prepare("
            SELECT
                i.idInscricao,
                e.titulo,
                e.data_inicio,
                i.status,
                i.pagamento,
                i.valor,
                i.valor_pago,
                i.presenca,
                i.certificado,
                i.camiseta
            FROM inscricoes i
            INNER JOIN eventos e
                ON e.idEvento = i.idEvento
            WHERE i.idUsuario = :idUsuario
            ORDER BY e.data_inicio DESC
        ");

        $stmt->execute([":idUsuario" => $idUsuario]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function totalEvento(int $idEvento): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM inscricoes
            WHERE idEvento = :idEvento
              AND status <> 'Cancelada'
        ");

        $stmt->execute([":idEvento" => $idEvento]);

        return (int) $stmt->fetchColumn();
    }

    public function vagasDisponiveis(int $idEvento): int
    {
        $stmt = $this->db->prepare("
            SELECT vagas
            FROM eventos
            WHERE idEvento = :idEvento
            LIMIT 1
        ");
        $stmt->execute([":idEvento" => $idEvento]);

        $vagas = $stmt->fetchColumn();

        if ($vagas === false || $vagas === null || (int) $vagas <= 0) {
            return PHP_INT_MAX;
        }

        return max(0, (int) $vagas - $this->totalEvento($idEvento));
    }

    private function parametrosPersistencia(array $dados): array
    {
        return [
            ":idEvento" => (int) ($dados["idEvento"] ?? 0),
            ":idUsuario" => (int) ($dados["idUsuario"] ?? 0),
            ":nome" => trim((string) ($dados["nome"] ?? "")),
            ":cpf" => $this->textoNulo($dados["cpf"] ?? null),
            ":rg" => $this->textoNulo($dados["rg"] ?? null),
            ":email" => $this->textoNulo($dados["email"] ?? null),
            ":telefone" => $this->textoNulo($dados["telefone"] ?? null),
            ":sexo" => trim((string) ($dados["sexo"] ?? "Masculino")),
            ":data_nascimento" => $this->dataNula($dados["data_nascimento"] ?? null),
            ":cidade" => $this->textoNulo($dados["cidade"] ?? null),
            ":estado" => $this->textoNulo($dados["estado"] ?? null),
            ":camiseta" => $this->textoNulo($dados["camiseta"] ?? null),
            ":observacoes" => $this->textoNulo($dados["observacoes"] ?? null),
            ":contato_emergencia" => $this->textoNulo($dados["contato_emergencia"] ?? null),
            ":telefone_emergencia" => $this->textoNulo($dados["telefone_emergencia"] ?? null),
            ":status" => trim((string) ($dados["status"] ?? "Pendente")),
            ":pagamento" => trim((string) ($dados["pagamento"] ?? "Pendente")),
            ":presenca" => (int) ($dados["presenca"] ?? 0),
            ":certificado" => (int) ($dados["certificado"] ?? 0),
            ":valor" => round((float) ($dados["valor"] ?? 0), 2),
            ":valor_pago" => round((float) ($dados["valor_pago"] ?? 0), 2),
            ":forma_pagamento" => $this->textoNulo($dados["forma_pagamento"] ?? null),
            ":codigo_pagamento" => $this->textoNulo($dados["codigo_pagamento"] ?? null)
        ];
    }

    private function listarPorCampo(string $campo, string $valor): array
    {
        $camposPermitidos = ["status", "pagamento"];

        if (!in_array($campo, $camposPermitidos, true)) {
            throw new InvalidArgumentException("Campo de pesquisa inválido.");
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM inscricoes
            WHERE {$campo} = :valor
            ORDER BY nome
        ");
        $stmt->execute([":valor" => $valor]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function textoNulo(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ""));
        return $texto === "" ? null : $texto;
    }

    private function dataNula(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ""));

        if (
            $texto === ""
            || $texto === "0000-00-00"
        ) {
            return null;
        }

        return $texto;
    }
}
