<?php

declare(strict_types=1);

require_once __DIR__ . "/../database/db.php";

class Pagamento
{
    private PDO $db;

    /** @var array<string, bool> */
    private array $cacheColunas = [];

    private const STATUS_PERMITIDOS = [
        "Pendente",
        "Vencido",
        "Pago",
        "Cancelado",
        "Estornado"
    ];

    private const FORMAS_PERMITIDAS = [
        "NaoDefinido",
        "PIX",
        "Cartao",
        "Boleto",
        "Dinheiro",
        "Transferencia"
    ];

    public function __construct(?PDO $conexao = null)
    {
        $this->db = $conexao ?? Database::connect();

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
     * Verifica uma coluna usando SHOW COLUMNS.
     * Não depende de acesso ao banco information_schema.
     */
    private function possuiColuna(string $tabela, string $coluna): bool
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $tabela)
            || !preg_match('/^[A-Za-z0-9_]+$/', $coluna)) {
            return false;
        }

        $chave = $tabela . "." . $coluna;

        if (array_key_exists($chave, $this->cacheColunas)) {
            return $this->cacheColunas[$chave];
        }

        try {
            $sql = "SHOW COLUMNS FROM `{$tabela}` LIKE " . $this->db->quote($coluna);
            $resultado = $this->db->query($sql)->fetch();
            $this->cacheColunas[$chave] = $resultado !== false;
        } catch (Throwable) {
            $this->cacheColunas[$chave] = false;
        }

        return $this->cacheColunas[$chave];
    }

    /**
     * Informa se a estrutura mínima da integração Asaas está instalada.
     */
    public function estruturaAsaasDisponivel(): bool
    {
        $colunasPagamento = [
            "integracao",
            "asaasPaymentId",
            "asaasCustomerId",
            "asaasStatus",
            "invoiceUrl",
            "bankSlipUrl",
            "boletoLinhaDigitavel",
            "pixQrCode",
            "pixCopiaCola",
            "pixExpiracao",
            "asaasPayload",
            "asaasAtualizadoEm"
        ];

        foreach ($colunasPagamento as $coluna) {
            if (!$this->possuiColuna("pagamentos", $coluna)) {
                return false;
            }
        }

        return $this->possuiColuna("usuarios", "asaasCustomerId")
            && $this->possuiColuna("eventos", "pagamento_fim");
    }

    /**
     * Cria o pagamento correspondente a uma inscrição.
     *
     * O pagamento nunca é criado manualmente: ele nasce a partir
     * do relacionamento entre usuário e evento.
     */
    public function criarParaInscricao(int $idInscricao): int
    {
        if ($idInscricao <= 0) {
            throw new InvalidArgumentException("Inscrição inválida.");
        }

        $sql = "
            SELECT
                i.idInscricao,
                i.idEvento,
                i.idUsuario,
                i.nome,
                i.email,
                i.valor AS valorInscricaoAtual,
                e.titulo AS tituloEvento,
                e.valor,
                e.valor_inscricao,
                e.pagamento_obrigatorio
            FROM inscricoes i
            INNER JOIN eventos e
                ON e.idEvento = i.idEvento
            WHERE i.idInscricao = :idInscricao
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([":idInscricao" => $idInscricao]);

        $inscricao = $stmt->fetch();

        if (!$inscricao) {
            throw new RuntimeException("Inscrição não encontrada.");
        }

        $pagamentoObrigatorio = (int) (
            $inscricao["pagamento_obrigatorio"] ?? 1
        ) === 1;

                /*
         * VALOR_DA_INSCRICAO_V2
         *
         * O valor final já foi calculado no momento
         * da inscrição. Isso inclui valor normal,
         * valor de visitante e gratuidade.
         *
         * Por isso inscricoes.valor é a fonte
         * de verdade do pagamento.
         */
        $valorConfigurado =
            round(
                (float) (
                    $inscricao[
                        "valorInscricaoAtual"
                    ]
                    ?? 0
                ),
                2
            );

        if ($valorConfigurado < 0) {
            $valorConfigurado = 0;
        }

$existente = $this->buscarPorInscricao($idInscricao);

        /*
         * Evento gratuito ou que não exige pagamento:
         * a inscrição já fica confirmada. Caso exista um pagamento
         * antigo, ele é cancelado pelo sistema e preservado no histórico.
         */
        if (!$pagamentoObrigatorio || $valorConfigurado <= 0) {
            if ($existente && (string) $existente["status"] !== "Cancelado") {
                $stmtCancelar = $this->db->prepare("
                    UPDATE pagamentos
                    SET
                        status = 'Cancelado',
                        dataPagamento = NULL,
                        observacao = CONCAT(
                            COALESCE(observacao, ''),
                            CASE WHEN COALESCE(observacao, '') = '' THEN '' ELSE '\n' END,
                            'Cancelado automaticamente: o evento não exige pagamento.'
                        )
                    WHERE idPagamento = :idPagamento
                ");
                $stmtCancelar->execute([
                    ":idPagamento" => (int) $existente["idPagamento"]
                ]);
            }

            $stmt = $this->db->prepare("
                UPDATE inscricoes
                SET
                    status = 'Confirmada',
                    pagamento = 'Pago',
                    valor = :valor,
                    valor_pago = 0,
                    forma_pagamento = NULL,
                    codigo_pagamento = NULL
                WHERE idInscricao = :idInscricao
            ");

            $stmt->execute([
                ":valor" => $valorConfigurado,
                ":idInscricao" => $idInscricao
            ]);

            return 0;
        }

        if ($existente) {
            /*
             * Mantém o registro existente e apenas sincroniza
             * os dados imutáveis da inscrição.
             */
            $stmt = $this->db->prepare("
                UPDATE pagamentos
                SET
                    idEvento = :idEvento,
                    participante = :participante,
                    email = :email,
                    descricao = :descricao,
                    valor = :valor
                WHERE idPagamento = :idPagamento
            ");

            $stmt->execute([
                ":idEvento" => (int) $inscricao["idEvento"],
                ":participante" => (string) $inscricao["nome"],
                ":email" => $this->textoNulo($inscricao["email"] ?? null),
                ":descricao" => "Inscrição - " . (string) $inscricao["tituloEvento"],
                ":valor" => $valorConfigurado,
                ":idPagamento" => (int) $existente["idPagamento"]
            ]);

            $this->sincronizarInscricaoComPagamento(
                $idInscricao,
                (string) $existente["status"],
                $valorConfigurado,
                (string) $existente["formaPagamento"],
                (string) $existente["codigo"]
            );

            return (int) $existente["idPagamento"];
        }

        $codigo = $this->gerarCodigo();

        $idPagamento = $this->salvar([
            "idEvento" => (int) $inscricao["idEvento"],
            "idInscricao" => $idInscricao,
            "codigo" => $codigo,
            "participante" => (string) $inscricao["nome"],
            "email" => $inscricao["email"] ?? null,
            "descricao" => "Inscrição - " . (string) $inscricao["tituloEvento"],
            "formaPagamento" => "NaoDefinido",
            "valor" => $valorConfigurado,
            "status" => "Pendente",
            "dataVencimento" => null,
            "dataPagamento" => null,
            "observacao" => null,
            "comprovante" => null
        ]);

        $this->sincronizarInscricaoComPagamento(
            $idInscricao,
            "Pendente",
            $valorConfigurado,
            "NaoDefinido",
            $codigo
        );

        return $idPagamento;
    }

    /**
     * Compatibilidade com o fluxo anterior.
     */
    public function sincronizarInscricao(
        int $idInscricao,
        int $idEvento = 0,
        int $idUsuario = 0,
        float $valor = 0,
        float $valorPago = 0,
        string $statusInscricao = "Pendente",
        string $statusPagamento = "Pendente",
        string $formaPagamento = "",
        string $codigoPagamento = ""
    ): int {
        return $this->criarParaInscricao($idInscricao);
    }

    /**
     * Insere um pagamento. Este método é utilizado internamente
     * pela geração automática da inscrição.
     */
    public function salvar(array $dados): int
    {
        $dados = $this->normalizarDados($dados);
        $this->validarDados($dados);

        if ($dados["codigo"] === "") {
            $dados["codigo"] = $this->gerarCodigo();
        }

        if ($this->codigoExiste($dados["codigo"])) {
            throw new InvalidArgumentException("O código do pagamento já existe.");
        }

        if ($dados["status"] === "Pago" && $dados["dataPagamento"] === null) {
            $dados["dataPagamento"] = date("Y-m-d H:i:s");
        }

        $sql = "
            INSERT INTO pagamentos (
                idEvento,
                idInscricao,
                codigo,
                participante,
                email,
                descricao,
                formaPagamento,
                valor,
                status,
                dataVencimento,
                dataPagamento,
                observacao,
                comprovante
            ) VALUES (
                :idEvento,
                :idInscricao,
                :codigo,
                :participante,
                :email,
                :descricao,
                :formaPagamento,
                :valor,
                :status,
                :dataVencimento,
                :dataPagamento,
                :observacao,
                :comprovante
            )
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ":idEvento" => $dados["idEvento"],
            ":idInscricao" => $dados["idInscricao"],
            ":codigo" => $dados["codigo"],
            ":participante" => $dados["participante"],
            ":email" => $dados["email"],
            ":descricao" => $dados["descricao"],
            ":formaPagamento" => $dados["formaPagamento"],
            ":valor" => $dados["valor"],
            ":status" => $dados["status"],
            ":dataVencimento" => $dados["dataVencimento"],
            ":dataPagamento" => $dados["dataPagamento"],
            ":observacao" => $dados["observacao"],
            ":comprovante" => $dados["comprovante"]
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza somente os dados relacionados ao recebimento.
     * Evento, inscrição, participante, código e valor são imutáveis.
     */
    public function atualizarRecebimento(
        int $idPagamento,
        array $dados
    ): bool {
        $pagamentoAtual = $this->buscar($idPagamento);

        if (!$pagamentoAtual) {
            throw new RuntimeException("Pagamento não encontrado.");
        }

        $status = trim((string) ($dados["status"] ?? $pagamentoAtual["status"]));
        $forma = trim((string) (
            $dados["formaPagamento"]
            ?? $dados["forma"]
            ?? $pagamentoAtual["formaPagamento"]
        ));

        $this->validarStatus($status);
        $this->validarFormaPagamento($forma);

        $dataPagamento = $this->normalizarDataHora(
            $dados["dataPagamento"] ?? $pagamentoAtual["dataPagamento"] ?? null
        );

        if ($status === "Pago" && $dataPagamento === null) {
            $dataPagamento = date("Y-m-d H:i:s");
        }

        if ($status !== "Pago") {
            $dataPagamento = null;
        }

        $observacao = $this->textoNulo(
            $dados["observacao"] ?? $pagamentoAtual["observacao"] ?? null
        );

        $comprovante = $this->textoNulo(
            $dados["comprovante"] ?? $pagamentoAtual["comprovante"] ?? null
        );

        $iniciouTransacao = !$this->db->inTransaction();

        if ($iniciouTransacao) {
            $this->db->beginTransaction();
        }

        try {
            
            /*
             * HORA_RECEBIMENTO_MANUAL_V1_1
             *
             * Se o recebimento for confirmado agora,
             * grava o instante do sistema.
             *
             * COALESCE preserva a primeira hora.
             */
            $recebidoEmSql =
                $status === "Pago"
                    ? "COALESCE(recebidoEm, NOW())"
                    : "NULL";
$stmt = $this->db->prepare("
                UPDATE pagamentos
                SET
                    formaPagamento = :formaPagamento,
                    status = :status,
                    dataPagamento = :dataPagamento,
                    recebidoEm = {$recebidoEmSql},
                    observacao = :observacao,
                    comprovante = :comprovante
                WHERE idPagamento = :idPagamento
            ");

            $stmt->execute([
                ":formaPagamento" => $forma,
                ":status" => $status,
                ":dataPagamento" => $dataPagamento,
                ":observacao" => $observacao,
                ":comprovante" => $comprovante,
                ":idPagamento" => $idPagamento
            ]);

            $idInscricao = (int) ($pagamentoAtual["idInscricao"] ?? 0);

            if ($idInscricao > 0) {
                $this->sincronizarInscricaoComPagamento(
                    $idInscricao,
                    $status,
                    (float) $pagamentoAtual["valor"],
                    $forma,
                    (string) $pagamentoAtual["codigo"]
                );
            }

            if ($iniciouTransacao) {
                $this->db->commit();
            }
        } catch (Throwable $erro) {
            if ($iniciouTransacao && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $erro;
        }

        $atualizado = $this->buscar($idPagamento);

        if ($atualizado && class_exists("PagamentoWebhookService")) {
            try {
                (new PagamentoWebhookService())->notificarAtualizacao($atualizado);
            } catch (Throwable $erroIntegracao) {
                error_log(
                    "Falha na integração HTTP do pagamento #{$idPagamento}: "
                    . $erroIntegracao->getMessage()
                );
            }
        }

        return true;
    }

    /**
     * Compatibilidade: a edição completa antiga agora atualiza
     * somente os dados do recebimento.
     */
    public function editar(int $idPagamento, array $dados): bool
    {
        return $this->atualizarRecebimento($idPagamento, $dados);
    }

    public function buscar(int $idPagamento): ?array
    {
        if ($idPagamento <= 0) {
            return null;
        }

        // Compatibilidade com bancos em que a migração foi aplicada apenas
        // parcialmente. O recebimento manual continua abrindo, enquanto a
        // integração Asaas permanece bloqueada até concluir a migração.
        $campoPagamentoFim = $this->possuiColuna("eventos", "pagamento_fim")
            ? "e.pagamento_fim"
            : "NULL";

        $campoAsaasUsuario = $this->possuiColuna("usuarios", "asaasCustomerId")
            ? "u.asaasCustomerId"
            : "NULL";

        $campoRepassarTaxaAsaas = $this->possuiColuna("eventos", "repassar_taxa_asaas")
            ? "e.repassar_taxa_asaas"
            : "0";

        $stmt = $this->db->prepare("
            SELECT
                p.*,
                e.titulo AS tituloEvento,
                e.data_inicio AS dataInicioEvento,
                e.hora_inicio AS horaInicioEvento,
                {$campoPagamentoFim} AS pagamentoFimEvento,
                {$campoRepassarTaxaAsaas} AS repassarTaxaAsaasEvento,
                i.idUsuario,
                i.cpf,
                i.telefone,
                i.camiseta,
                i.status AS statusInscricao,
                {$campoAsaasUsuario} AS asaasCustomerIdUsuario
            FROM pagamentos p
            LEFT JOIN eventos e
                ON e.idEvento = p.idEvento
            LEFT JOIN inscricoes i
                ON i.idInscricao = p.idInscricao
            LEFT JOIN usuarios u
                ON u.id = i.idUsuario
            WHERE p.idPagamento = :idPagamento
            LIMIT 1
        ");

        $stmt->execute([":idPagamento" => $idPagamento]);
        $resultado = $stmt->fetch();

        return $resultado ?: null;
    }

    public function buscarPorInscricao(int $idInscricao): ?array
    {
        if ($idInscricao <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM pagamentos
            WHERE idInscricao = :idInscricao
            ORDER BY idPagamento DESC
            LIMIT 1
        ");

        $stmt->execute([":idInscricao" => $idInscricao]);
        $resultado = $stmt->fetch();

        return $resultado ?: null;
    }

    public function pesquisar(array $filtros = []): array
    {
        $sql = "
            SELECT
                p.*,
                e.titulo AS tituloEvento,
                i.status AS statusInscricao,
                i.camiseta
            FROM pagamentos p
            LEFT JOIN eventos e
                ON e.idEvento = p.idEvento
            LEFT JOIN inscricoes i
                ON i.idInscricao = p.idInscricao
            WHERE p.idInscricao IS NOT NULL
        ";

        $parametros = [];
        $this->aplicarFiltros($sql, $parametros, $filtros);

        $sql .= " ORDER BY p.criadoEm DESC, p.idPagamento DESC ";

        $limite = max(1, min(100, (int) ($filtros["limite"] ?? 10)));
        $offset = max(0, (int) ($filtros["offset"] ?? 0));

        $sql .= " LIMIT :limite OFFSET :offset ";

        $stmt = $this->db->prepare($sql);
        $this->bindFiltros($stmt, $parametros);
        $stmt->bindValue(":limite", $limite, PDO::PARAM_INT);
        $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function totalPesquisar(array $filtros = []): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM pagamentos p
            WHERE p.idInscricao IS NOT NULL
        ";

        $parametros = [];
        $this->aplicarFiltros($sql, $parametros, $filtros);

        $stmt = $this->db->prepare($sql);
        $this->bindFiltros($stmt, $parametros);
        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }

    /**
     * Pagamentos vinculados a inscrições não são excluídos manualmente.
     */
    public function excluir(int $idPagamento): bool
    {
        throw new RuntimeException(
            "O pagamento é gerado pela inscrição e não pode ser excluído manualmente."
        );
    }

    public function alterarStatus(int $idPagamento, string $status): bool
    {
        return $this->atualizarRecebimento(
            $idPagamento,
            ["status" => $status]
        );
    }

    /**
     * Acrescenta uma ocorrência ao histórico textual do pagamento sem apagar
     * observações anteriores.
     */
    public function adicionarObservacao(int $idPagamento, string $texto): bool
    {
        $texto = trim($texto);

        if ($idPagamento <= 0 || $texto === "") {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE pagamentos
            SET observacao = CONCAT_WS(
                CHAR(10),
                NULLIF(TRIM(observacao), ''),
                :texto
            )
            WHERE idPagamento = :idPagamento
        ");

        $stmt->execute([
            ":texto" => $texto,
            ":idPagamento" => $idPagamento
        ]);

        return $stmt->rowCount() > 0;
    }

    public function totalRecebido(int $idEvento = 0): float
    {
        return $this->totalPorStatus("Pago", $idEvento);
    }

    public function totalPendente(int $idEvento = 0): float
    {
        return $this->totalPorStatus("Pendente", $idEvento);
    }

    public function totalVencido(int $idEvento = 0): float
    {
        return $this->totalPorStatus("Vencido", $idEvento);
    }

    public function totalCancelado(int $idEvento = 0): float
    {
        return $this->totalPorStatus("Cancelado", $idEvento);
    }

    public function totalEstornado(int $idEvento = 0): float
    {
        return $this->totalPorStatus("Estornado", $idEvento);
    }

    public function totalPorForma(int $idEvento = 0): array
    {
        $sql = "
            SELECT
                formaPagamento,
                COUNT(*) AS quantidade,
                COALESCE(SUM(valor), 0) AS total
            FROM pagamentos
            WHERE status = 'Pago'
              AND idInscricao IS NOT NULL
        ";

        $params = [];

        if ($idEvento > 0) {
            $sql .= " AND idEvento = :idEvento ";
            $params[":idEvento"] = $idEvento;
        }

        $sql .= " GROUP BY formaPagamento ORDER BY formaPagamento ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function ultimos(int $limite = 10, int $idEvento = 0): array
    {
        $filtros = [
            "limite" => max(1, min(100, $limite)),
            "offset" => 0
        ];

        if ($idEvento > 0) {
            $filtros["evento"] = $idEvento;
        }

        return $this->pesquisar($filtros);
    }

    public function existe(int $idPagamento): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM pagamentos
            WHERE idPagamento = :idPagamento
            LIMIT 1
        ");

        $stmt->execute([":idPagamento" => $idPagamento]);

        return (bool) $stmt->fetchColumn();
    }

    public function codigoExiste(string $codigo, int $ignorarId = 0): bool
    {
        $sql = "
            SELECT 1
            FROM pagamentos
            WHERE codigo = :codigo
        ";

        $params = [":codigo" => $codigo];

        if ($ignorarId > 0) {
            $sql .= " AND idPagamento <> :ignorarId ";
            $params[":ignorarId"] = $ignorarId;
        }

        $sql .= " LIMIT 1 ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Compatibilidade com chamadas antigas.
     */
    /**
     * Atualiza os metadados da cobrança criada no Asaas e sincroniza
     * o status da inscrição relacionada.
     */
    public function atualizarIntegracaoAsaas(
        int $idPagamento,
        array $dados
    ): bool {
        $pagamentoAtual = $this->buscar($idPagamento);

        if (!$pagamentoAtual) {
            throw new RuntimeException("Pagamento não encontrado.");
        }

        $forma = trim((string) ($dados["formaPagamento"] ?? $pagamentoAtual["formaPagamento"] ?? "NaoDefinido"));
        $status = trim((string) ($dados["status"] ?? $pagamentoAtual["status"] ?? "Pendente"));

        $this->validarFormaPagamento($forma);
        $this->validarStatus($status);

        $iniciouTransacao = !$this->db->inTransaction();
        if ($iniciouTransacao) {
            $this->db->beginTransaction();
        }

        try {
            $campos = [
                "formaPagamento = :formaPagamento",
                "integracao = 'Asaas'",
                "asaasPaymentId = :asaasPaymentId",
                "asaasCustomerId = :asaasCustomerId",
                "asaasStatus = :asaasStatus",
                "invoiceUrl = :invoiceUrl",
                "bankSlipUrl = :bankSlipUrl",
                "boletoLinhaDigitavel = :boletoLinhaDigitavel",
                "pixQrCode = :pixQrCode",
                "pixCopiaCola = :pixCopiaCola",
                "pixExpiracao = :pixExpiracao",
                "dataVencimento = :dataVencimento",
                "asaasPayload = :asaasPayload",
                "asaasAtualizadoEm = NOW()",
                "status = :status",
                "dataPagamento = :dataPagamento"
            ];

            $parametros = [
                ":formaPagamento" => $forma,
                ":asaasPaymentId" => $this->textoNulo($dados["asaasPaymentId"] ?? null),
                ":asaasCustomerId" => $this->textoNulo($dados["asaasCustomerId"] ?? null),
                ":asaasStatus" => $this->textoNulo($dados["asaasStatus"] ?? null),
                ":invoiceUrl" => $this->textoNulo($dados["invoiceUrl"] ?? null),
                ":bankSlipUrl" => $this->textoNulo($dados["bankSlipUrl"] ?? null),
                ":boletoLinhaDigitavel" => $this->textoNulo($dados["boletoLinhaDigitavel"] ?? null),
                ":pixQrCode" => $this->textoNulo($dados["pixQrCode"] ?? null),
                ":pixCopiaCola" => $this->textoNulo($dados["pixCopiaCola"] ?? null),
                ":pixExpiracao" => $this->normalizarDataHora($dados["pixExpiracao"] ?? null),
                ":dataVencimento" => $this->normalizarData($dados["dataVencimento"] ?? null),
                ":asaasPayload" => $this->textoNulo($dados["asaasPayload"] ?? null),
                ":status" => $status,
                ":dataPagamento" => $this->normalizarDataHora($dados["dataPagamento"] ?? null),
                ":idPagamento" => $idPagamento
            ];

                        /*
             * HORA_RECEBIMENTO_ASAAS_V1_1
             *
             * Quando o site detecta status Pago,
             * registra a hora do sistema.
             *
             * A primeira hora é preservada.
             */
            if (
                $this->possuiColuna(
                    "pagamentos",
                    "recebidoEm"
                )
            ) {
                if ($status === "Pago") {
                    $campos[] =
                        "recebidoEm = "
                        . "COALESCE(recebidoEm, NOW())";
                } else {
                    $campos[] =
                        "recebidoEm = NULL";
                }
            }
if ($this->possuiColuna("pagamentos", "valorCobrancaAsaas")) {
                $campos[] = "valorCobrancaAsaas = :valorCobrancaAsaas";
                $parametros[":valorCobrancaAsaas"] = round(
                    (float) ($dados["valorCobrancaAsaas"] ?? $pagamentoAtual["valor"] ?? 0),
                    2
                );
            }

            if ($this->possuiColuna("pagamentos", "valorTaxaRepassada")) {
                $campos[] = "valorTaxaRepassada = :valorTaxaRepassada";
                $parametros[":valorTaxaRepassada"] = max(
                    0,
                    round((float) ($dados["valorTaxaRepassada"] ?? 0), 2)
                );
            }

            $stmt = $this->db->prepare(
                "UPDATE pagamentos SET "
                . implode(",\n                    ", $campos)
                . " WHERE idPagamento = :idPagamento"
            );

            $stmt->execute($parametros);

            $idInscricao = (int) ($pagamentoAtual["idInscricao"] ?? 0);
            if ($idInscricao > 0) {
                $this->sincronizarInscricaoComPagamento(
                    $idInscricao,
                    $status,
                    (float) ($pagamentoAtual["valor"] ?? 0),
                    $forma,
                    (string) ($pagamentoAtual["codigo"] ?? "")
                );
            }

            if ($iniciouTransacao) {
                $this->db->commit();
            }
        } catch (Throwable $erro) {
            if ($iniciouTransacao && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $erro;
        }

        return true;
    }

    public function buscarPorAsaasPaymentId(string $asaasPaymentId): ?array
    {
        $asaasPaymentId = trim($asaasPaymentId);

        if ($asaasPaymentId === "") {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM pagamentos
            WHERE asaasPaymentId = :asaasPaymentId
            LIMIT 1
        ");
        $stmt->execute([":asaasPaymentId" => $asaasPaymentId]);
        $resultado = $stmt->fetch();

        return $resultado ?: null;
    }

    /**
     * Marca um boleto como vencido, mantendo a inscrição pendente durante
     * o período de tolerância.
     */
    public function marcarComoVencido(
        int $idPagamento,
        ?string $asaasStatus = "OVERDUE"
    ): bool {
        $pagamentoAtual = $this->buscar($idPagamento);

        if (!$pagamentoAtual) {
            throw new RuntimeException("Pagamento não encontrado.");
        }

        if ((string) ($pagamentoAtual["formaPagamento"] ?? "") !== "Boleto") {
            throw new RuntimeException("Somente boletos podem ser marcados como vencidos.");
        }

        if (!in_array(
            (string) ($pagamentoAtual["status"] ?? ""),
            ["Pendente", "Vencido"],
            true
        )) {
            return false;
        }

        $iniciouTransacao = !$this->db->inTransaction();

        if ($iniciouTransacao) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE pagamentos
                SET
                    status = 'Vencido',
                    asaasStatus = COALESCE(:asaasStatus, asaasStatus),
                    asaasAtualizadoEm = CASE
                        WHEN :asaasStatusData IS NOT NULL THEN NOW()
                        ELSE asaasAtualizadoEm
                    END,
                    dataPagamento = NULL
                WHERE idPagamento = :idPagamento
            ");
            $statusNormalizado = $this->textoNulo($asaasStatus);
            $stmt->execute([
                ":asaasStatus" => $statusNormalizado,
                ":asaasStatusData" => $statusNormalizado,
                ":idPagamento" => $idPagamento
            ]);

            $idInscricao = (int) ($pagamentoAtual["idInscricao"] ?? 0);

            if ($idInscricao > 0) {
                $this->sincronizarInscricaoComPagamento(
                    $idInscricao,
                    "Vencido",
                    (float) ($pagamentoAtual["valor"] ?? 0),
                    "Boleto",
                    (string) ($pagamentoAtual["codigo"] ?? "")
                );
            }

            if ($iniciouTransacao) {
                $this->db->commit();
            }
        } catch (Throwable $erro) {
            if ($iniciouTransacao && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $erro;
        }

        return true;
    }

    /**
     * Mantém o pagamento com status Vencido, mas cancela a inscrição depois
     * dos três dias úteis de tolerância.
     */
    public function cancelarInscricaoPorBoletoVencido(
        int $idPagamento,
        string $observacao,
        ?string $asaasStatus = null
    ): bool {
        $pagamentoAtual = $this->buscar($idPagamento);

        if (!$pagamentoAtual) {
            throw new RuntimeException("Pagamento não encontrado.");
        }

        if ((string) ($pagamentoAtual["formaPagamento"] ?? "") !== "Boleto") {
            throw new RuntimeException("O pagamento informado não é um boleto.");
        }

        $idInscricao = (int) ($pagamentoAtual["idInscricao"] ?? 0);

        if ($idInscricao <= 0) {
            throw new RuntimeException("A inscrição vinculada ao boleto não foi encontrada.");
        }

        $observacao = trim($observacao);

        if ($observacao === "") {
            $observacao = "Inscrição cancelada automaticamente por boleto vencido.";
        }

        $iniciouTransacao = !$this->db->inTransaction();

        if ($iniciouTransacao) {
            $this->db->beginTransaction();
        }

        try {
            $stmtPagamento = $this->db->prepare("
                UPDATE pagamentos
                SET
                    status = 'Vencido',
                    dataPagamento = NULL,
                    asaasStatus = COALESCE(:asaasStatus, asaasStatus),
                    asaasAtualizadoEm = CASE
                        WHEN :asaasStatusData IS NOT NULL THEN NOW()
                        ELSE asaasAtualizadoEm
                    END,
                    observacao = CASE
                        WHEN LOCATE(:marcador, COALESCE(observacao, '')) > 0
                            THEN observacao
                        ELSE CONCAT_WS(
                            '\n',
                            NULLIF(TRIM(observacao), ''),
                            :observacao
                        )
                    END
                WHERE idPagamento = :idPagamento
            ");

            $statusNormalizado = $this->textoNulo($asaasStatus);
            $stmtPagamento->execute([
                ":asaasStatus" => $statusNormalizado,
                ":asaasStatusData" => $statusNormalizado,
                ":marcador" => "Inscrição cancelada automaticamente: boleto vencido",
                ":observacao" => $observacao,
                ":idPagamento" => $idPagamento
            ]);

            $stmtInscricao = $this->db->prepare("
                UPDATE inscricoes
                SET
                    status = 'Cancelada',
                    pagamento = 'Vencido',
                    valor_pago = 0,
                    forma_pagamento = 'Boleto',
                    codigo_pagamento = :codigo,
                    presenca = 0,
                    observacoes = CASE
                        WHEN LOCATE(:marcador, COALESCE(observacoes, '')) > 0
                            THEN observacoes
                        ELSE CONCAT_WS(
                            '\n',
                            NULLIF(TRIM(observacoes), ''),
                            :observacao
                        )
                    END
                WHERE idInscricao = :idInscricao
            ");
            $stmtInscricao->execute([
                ":codigo" => (string) ($pagamentoAtual["codigo"] ?? ""),
                ":marcador" => "Inscrição cancelada automaticamente: boleto vencido",
                ":observacao" => $observacao,
                ":idInscricao" => $idInscricao
            ]);

            if ($iniciouTransacao) {
                $this->db->commit();
            }
        } catch (Throwable $erro) {
            if ($iniciouTransacao && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $erro;
        }

        return true;
    }

    public function atualizarStatusPeloAsaas(
        string $asaasPaymentId,
        string $status,
        ?string $asaasStatus = null,
        ?string $dataPagamento = null,
        ?string $payload = null
    ): bool {
        $pagamentoAtual = $this->buscarPorAsaasPaymentId($asaasPaymentId);

        if (!$pagamentoAtual) {
            return false;
        }

        $this->validarStatus($status);

        /*
         * Quando o cron remove um boleto no Asaas após a tolerância,
         * o webhook PAYMENT_DELETED não deve trocar Vencido por Cancelado.
         * A inscrição já está Cancelada e o histórico financeiro permanece
         * identificado como boleto vencido.
         */
        $statusPersistido = $status;

        if (
            $status === "Cancelado"
            && (string) ($pagamentoAtual["status"] ?? "") === "Vencido"
            && (string) ($pagamentoAtual["formaPagamento"] ?? "") === "Boleto"
        ) {
            $statusPersistido = "Vencido";
        }

        $iniciouTransacao = !$this->db->inTransaction();
        if ($iniciouTransacao) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE pagamentos
                SET
                    status = :status,
                    asaasStatus = :asaasStatus,
                    asaasPayload = COALESCE(:asaasPayload, asaasPayload),
                    asaasAtualizadoEm = NOW(),
                    dataPagamento = :dataPagamento
                WHERE idPagamento = :idPagamento
            ");
            $stmt->execute([
                ":status" => $statusPersistido,
                ":asaasStatus" => $this->textoNulo($asaasStatus),
                ":asaasPayload" => $this->textoNulo($payload),
                ":dataPagamento" => $statusPersistido === "Pago"
                    ? ($this->normalizarDataHora($dataPagamento) ?? date("Y-m-d H:i:s"))
                    : null,
                ":idPagamento" => (int) $pagamentoAtual["idPagamento"]
            ]);

            $idInscricao = (int) ($pagamentoAtual["idInscricao"] ?? 0);
            if ($idInscricao > 0) {
                $this->sincronizarInscricaoComPagamento(
                    $idInscricao,
                    $statusPersistido,
                    (float) ($pagamentoAtual["valor"] ?? 0),
                    (string) ($pagamentoAtual["formaPagamento"] ?? "NaoDefinido"),
                    (string) ($pagamentoAtual["codigo"] ?? "")
                );
            }

            if ($iniciouTransacao) {
                $this->db->commit();
            }
        } catch (Throwable $erro) {
            if ($iniciouTransacao && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $erro;
        }

        return true;
    }

    public function alterarStatusPagamento(
        int $idInscricao,
        string $status
    ): bool {
        $this->validarStatus($status);

        $pagamento = $this->buscarPorInscricao($idInscricao);

        if ($pagamento) {
            return $this->alterarStatus(
                (int) $pagamento["idPagamento"],
                $status
            );
        }

        return false;
    }

    private function sincronizarInscricaoComPagamento(
        int $idInscricao,
        string $statusPagamento,
        float $valor,
        string $formaPagamento,
        string $codigo
    ): void {
        $statusInscricao = match ($statusPagamento) {
            "Pago" => "Confirmada",
            "Cancelado", "Estornado" => "Cancelada",
            "Vencido" => "Pendente",
            default => "Pendente"
        };

        $valorPago = $statusPagamento === "Pago"
            ? $valor
            : 0;

        /*
         * Pagamento cancelado ou estornado cancela também
         * a inscrição e remove qualquer presença já marcada.
         */
        $cancelarPresenca = in_array(
            $statusPagamento,
            ["Cancelado", "Estornado"],
            true
        );

        $stmt = $this->db->prepare("
            UPDATE inscricoes
            SET
                status = CASE
                    WHEN :preservarCancelada = 1 AND status = 'Cancelada'
                        THEN 'Cancelada'
                    ELSE :statusInscricao
                END,
                pagamento = :statusPagamento,
                valor = :valor,
                valor_pago = :valorPago,
                forma_pagamento = :formaPagamento,
                codigo_pagamento = :codigo,
                presenca = CASE
                    WHEN :cancelarPresenca = 1 THEN 0
                    ELSE presenca
                END
            WHERE idInscricao = :idInscricao
        ");

        $stmt->execute([
            ":statusInscricao" => $statusInscricao,
            ":preservarCancelada" => $statusPagamento === "Vencido" ? 1 : 0,
            ":statusPagamento" => $statusPagamento,
            ":valor" => $valor,
            ":valorPago" => $valorPago,
            ":formaPagamento" => $formaPagamento,
            ":codigo" => $codigo,
            ":cancelarPresenca" => $cancelarPresenca ? 1 : 0,
            ":idInscricao" => $idInscricao
        ]);
    }

    private function totalPorStatus(string $status, int $idEvento = 0): float
    {
        $this->validarStatus($status);

        $sql = "
            SELECT COALESCE(SUM(valor), 0)
            FROM pagamentos
            WHERE status = :status
              AND idInscricao IS NOT NULL
        ";

        $params = [":status" => $status];

        if ($idEvento > 0) {
            $sql .= " AND idEvento = :idEvento ";
            $params[":idEvento"] = $idEvento;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (float) $stmt->fetchColumn();
    }

    private function aplicarFiltros(
        string &$sql,
        array &$parametros,
        array $filtros
    ): void {
        $pesquisa = trim((string) ($filtros["pesquisa"] ?? ""));

        if ($pesquisa !== "") {
            $sql .= "
                AND (
                    p.codigo LIKE :pesquisaCodigo
                    OR p.participante LIKE :pesquisaParticipante
                    OR p.email LIKE :pesquisaEmail
                    OR p.descricao LIKE :pesquisaDescricao
                )
            ";
            $termoPesquisa = "%{$pesquisa}%";
            $parametros["pesquisaCodigo"] = $termoPesquisa;
            $parametros["pesquisaParticipante"] = $termoPesquisa;
            $parametros["pesquisaEmail"] = $termoPesquisa;
            $parametros["pesquisaDescricao"] = $termoPesquisa;
        }

        $idEvento = (int) (
            $filtros["evento"]
            ?? $filtros["idEvento"]
            ?? 0
        );

        if ($idEvento > 0) {
            $sql .= " AND p.idEvento = :idEvento ";
            $parametros["idEvento"] = $idEvento;
        }

        $status = trim((string) ($filtros["status"] ?? ""));

        if ($status !== "") {
            $this->validarStatus($status);
            $sql .= " AND p.status = :status ";
            $parametros["status"] = $status;
        }

        $forma = trim((string) (
            $filtros["forma"]
            ?? $filtros["formaPagamento"]
            ?? ""
        ));

        if ($forma !== "") {
            $this->validarFormaPagamento($forma);
            $sql .= " AND p.formaPagamento = :formaPagamento ";
            $parametros["formaPagamento"] = $forma;
        }
    }

    private function bindFiltros(PDOStatement $stmt, array $parametros): void
    {
        foreach ($parametros as $campo => $valor) {
            $stmt->bindValue(
                ":" . $campo,
                $valor,
                is_int($valor) ? PDO::PARAM_INT : PDO::PARAM_STR
            );
        }
    }

    private function normalizarDados(array $dados): array
    {
        return [
            "idEvento" => (int) ($dados["idEvento"] ?? $dados["evento"] ?? 0),
            "idInscricao" => $this->inteiroNulo($dados["idInscricao"] ?? null),
            "codigo" => trim((string) ($dados["codigo"] ?? "")),
            "participante" => trim((string) ($dados["participante"] ?? "")),
            "email" => $this->textoNulo($dados["email"] ?? null),
            "descricao" => $this->textoNulo($dados["descricao"] ?? null),
            "formaPagamento" => trim((string) (
                $dados["formaPagamento"]
                ?? $dados["forma"]
                ?? "NaoDefinido"
            )),
            "valor" => $this->normalizarValor($dados["valor"] ?? 0),
            "status" => trim((string) ($dados["status"] ?? "Pendente")),
            "dataVencimento" => $this->normalizarData($dados["dataVencimento"] ?? null),
            "dataPagamento" => $this->normalizarDataHora($dados["dataPagamento"] ?? null),
            "observacao" => $this->textoNulo($dados["observacao"] ?? null),
            "comprovante" => $this->textoNulo($dados["comprovante"] ?? null)
        ];
    }

    private function validarDados(array $dados): void
    {
        if ($dados["idEvento"] <= 0) {
            throw new InvalidArgumentException("Selecione um evento.");
        }

        if (($dados["idInscricao"] ?? 0) <= 0) {
            throw new InvalidArgumentException("O pagamento deve estar vinculado a uma inscrição.");
        }

        if ($dados["participante"] === "") {
            throw new InvalidArgumentException("Informe o participante.");
        }

        if ($dados["valor"] <= 0) {
            throw new InvalidArgumentException("O valor deve ser maior que zero.");
        }

        if (
            $dados["email"] !== null
            && !filter_var($dados["email"], FILTER_VALIDATE_EMAIL)
        ) {
            throw new InvalidArgumentException("Informe um e-mail válido.");
        }

        $this->validarStatus($dados["status"]);
        $this->validarFormaPagamento($dados["formaPagamento"]);
    }

    private function validarStatus(string $status): void
    {
        if (!in_array($status, self::STATUS_PERMITIDOS, true)) {
            throw new InvalidArgumentException("Status de pagamento inválido.");
        }
    }

    private function validarFormaPagamento(string $forma): void
    {
        if (!in_array($forma, self::FORMAS_PERMITIDAS, true)) {
            throw new InvalidArgumentException("Forma de pagamento inválida.");
        }
    }

    private function gerarCodigo(): string
    {
        do {
            $codigo = "PG-"
                . date("Ymd")
                . "-"
                . strtoupper(bin2hex(random_bytes(3)));
        } while ($this->codigoExiste($codigo));

        return $codigo;
    }

    private function normalizarValor(mixed $valor): float
    {
        if (is_int($valor) || is_float($valor)) {
            return round((float) $valor, 2);
        }

        $texto = str_replace(
            ["R$", " ", "\xc2\xa0"],
            "",
            trim((string) $valor)
        );

        if (str_contains($texto, ",")) {
            $texto = str_replace(".", "", $texto);
            $texto = str_replace(",", ".", $texto);
        }

        return is_numeric($texto)
            ? round((float) $texto, 2)
            : 0.0;
    }

    private function normalizarData(mixed $data): ?string
    {
        $texto = trim((string) ($data ?? ""));

        if ($texto === "") {
            return null;
        }

        foreach (["!Y-m-d", "!d/m/Y"] as $formato) {
            $objeto = DateTime::createFromFormat($formato, $texto);
            $erros = DateTime::getLastErrors();

            if (
                $objeto instanceof DateTime
                && ($erros === false || (($erros["warning_count"] ?? 0) === 0 && ($erros["error_count"] ?? 0) === 0))
            ) {
                return $objeto->format("Y-m-d");
            }
        }

        throw new InvalidArgumentException("Data inválida.");
    }

    private function normalizarDataHora(mixed $data): ?string
    {
        $texto = trim((string) ($data ?? ""));

        if (
            $texto === ""
            || $texto === "0000-00-00"
            || $texto === "0000-00-00 00:00:00"
        ) {
            return null;
        }

        $formatos = [
            "!Y-m-d\\TH:i:s",
            "!Y-m-d\\TH:i",
            "!Y-m-d H:i:s",
            "!Y-m-d H:i",
            "!Y-m-d"
        ];

        foreach ($formatos as $formato) {
            $objeto = DateTime::createFromFormat($formato, $texto);
            $erros = DateTime::getLastErrors();

            if (
                $objeto instanceof DateTime
                && ($erros === false || (($erros["warning_count"] ?? 0) === 0 && ($erros["error_count"] ?? 0) === 0))
            ) {
                return $objeto->format("Y-m-d H:i:s");
            }
        }

        throw new InvalidArgumentException("Data e hora inválidas.");
    }

    private function textoNulo(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ""));
        return $texto === "" ? null : $texto;
    }

    private function inteiroNulo(mixed $valor): ?int
    {
        $numero = (int) ($valor ?? 0);
        return $numero > 0 ? $numero : null;
    }
}
