<?php

declare(strict_types=1);

/**
 * Envia notificações por e-mail relacionadas a inscrições
 * e mudanças de status dos pagamentos.
 *
 * A execução é agendada no shutdown da requisição para que
 * o e-mail seja enviado somente depois que as alterações
 * principais do sistema forem concluídas.
 */
class EmailNotificacaoService
{
    private PDO $db;

    private static bool $agendado = false;

    private const LIMITE_POR_EXECUCAO = 5;

    private const STATUS_NOTIFICADOS = [
        "Pago",
        "Vencido",
        "Cancelado",
        "Estornado"
    ];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * Agenda o processamento uma única vez por requisição.
     */
    public static function agendarProcessamento(PDO $db): void
    {
        if (self::$agendado) {
            return;
        }

        self::$agendado = true;

        register_shutdown_function(
            static function () use ($db): void {
                try {
                    (new self($db))->processar();
                } catch (Throwable $erro) {
                    error_log(
                        "Erro no processamento das notificações de e-mail: "
                        . $erro->getMessage()
                    );
                }
            }
        );
    }

    /**
     * Processa inscrições novas, pagamentos gerados e
     * alterações de status ainda não notificadas.
     */
    public function processar(): void
    {
        if (!$this->estruturaDisponivel()) {
            return;
        }

        /*
         * Evita que duas requisições processem o mesmo e-mail
         * simultaneamente.
         */
        if (!$this->obterLock()) {
            return;
        }

        try {
            $this->processarInscricoes();
            $this->processarPagamentosGerados();
            $this->normalizarStatusPendentes();
            $this->processarStatusPagamentos();
        } finally {
            $this->liberarLock();
        }
    }

    /**
     * Confere se a migração necessária já foi executada.
     */
    private function estruturaDisponivel(): bool
    {
        try {
            $campos = [
                ["inscricoes", "email_inscricao_enviado_em"],
                ["pagamentos", "email_gerado_enviado_em"],
                ["pagamentos", "email_status_notificado"],
                ["pagamentos", "email_status_notificado_em"]
            ];

            $faltando = [];

            foreach ($campos as [$tabela, $campo]) {
                $stmt = $this->db->query(
                    "SHOW COLUMNS FROM `{$tabela}` LIKE "
                    . $this->db->quote($campo)
                );

                if ($stmt->fetch() === false) {
                    $faltando[] = $tabela . "." . $campo;
                }
            }

            if ($faltando !== []) {
                Log::warning(
                    "Notificações de e-mail desativadas: migração incompleta",
                    [
                        "colunasAusentes" => $faltando,
                        "migracao" => "mod/mail/migrations/notificacoes_email.sql"
                    ]
                );

                return false;
            }

            return true;
        } catch (Throwable $erro) {
            Log::warning(
                "Não foi possível validar a estrutura das notificações de e-mail",
                [
                    "erro" => $erro->getMessage()
                ]
            );

            return false;
        }
    }

    /**
     * Notifica imediatamente um pagamento específico.
     *
     * Útil para endpoints AJAX, pois o resultado é registrado
     * antes da resposta JSON ser encerrada. O processamento
     * agendado no shutdown continua como segunda camada e não
     * duplica o e-mail porque o status é marcado como notificado.
     */
    public function notificarStatusPagamentoPorId(
        int $idPagamento
    ): bool {
        if ($idPagamento <= 0) {
            return false;
        }

        if (!$this->estruturaDisponivel()) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT
                p.*,
                e.titulo AS evento,
                e.data_inicio AS dataEvento,
                COALESCE(u.nome, p.participante) AS nomeUsuario,
                COALESCE(u.email, p.email) AS emailUsuario
            FROM pagamentos p
            LEFT JOIN eventos e
                ON e.idEvento = p.idEvento
            LEFT JOIN inscricoes i
                ON i.idInscricao = p.idInscricao
            LEFT JOIN usuarios u
                ON u.id = i.idUsuario
            WHERE p.idPagamento = :idPagamento
              AND p.idInscricao IS NOT NULL
            LIMIT 1
        ");

        $stmt->execute([
            ":idPagamento" => $idPagamento
        ]);

        $pagamento = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($pagamento)) {
            Log::warning(
                "Pagamento não encontrado para notificação por e-mail",
                [
                    "idPagamento" => $idPagamento
                ]
            );

            return false;
        }

        $status = trim(
            (string) ($pagamento["status"] ?? "")
        );

        if (
            !in_array(
                $status,
                self::STATUS_NOTIFICADOS,
                true
            )
        ) {
            return false;
        }

        $ultimoStatus = trim(
            (string) (
                $pagamento["email_status_notificado"]
                ?? ""
            )
        );

        if ($ultimoStatus === $status) {
            Log::info(
                "Notificação de pagamento não reenviada: status já notificado",
                [
                    "idPagamento" => $idPagamento,
                    "status" => $status
                ]
            );

            return true;
        }

        $nome = trim(
            (string) (
                $pagamento["nomeUsuario"]
                ?? $pagamento["participante"]
                ?? ""
            )
        );

        $email = trim(
            (string) (
                $pagamento["emailUsuario"]
                ?? $pagamento["email"]
                ?? ""
            )
        );

        if (!$this->emailValido($email)) {
            Log::warning(
                "Pagamento sem e-mail válido para notificação de status",
                [
                    "idPagamento" => $idPagamento,
                    "status" => $status,
                    "email" => $email
                ]
            );

            return false;
        }

        $arquivo = match ($status) {
            "Pago" => "pagamento_pago.php",
            "Vencido" => "pagamento_vencido.php",
            "Cancelado" => "pagamento_cancelado.php",
            "Estornado" => "pagamento_estornado.php",
            default => ""
        };

        if ($arquivo === "") {
            return false;
        }

        $variaveis = $this->variaveisPagamento(
            $pagamento,
            $nome,
            $email
        );

        $variaveis["statusPagamento"] = $status;

        try {
            $html = $this->renderizarTemplate(
                $arquivo,
                $variaveis
            );

            $assunto = match ($status) {
                "Pago" => "Pagamento confirmado",
                "Vencido" => "Pagamento vencido",
                "Cancelado" => "Pagamento cancelado",
                "Estornado" => "Pagamento estornado",
                default => "Atualização do pagamento"
            };

            $evento = trim(
                (string) ($variaveis["evento"] ?? "")
            );

            if ($evento !== "") {
                $assunto .= " - " . $evento;
            }

            if (!$this->enviar(
                $email,
                $nome,
                $assunto,
                $html
            )) {
                Log::warning(
                    "Falha no envio imediato da notificação do pagamento",
                    [
                        "idPagamento" => $idPagamento,
                        "status" => $status,
                        "email" => $email
                    ]
                );

                return false;
            }

            $this->marcarStatusComoNotificado(
                $idPagamento,
                $status
            );

            Log::info(
                "Notificação de status do pagamento enviada por e-mail",
                [
                    "idPagamento" => $idPagamento,
                    "status" => $status,
                    "email" => $email,
                    "modo" => "imediato"
                ]
            );

            return true;
        } catch (Throwable $erro) {
            Log::warning(
                "Erro ao preparar/enviar notificação do pagamento",
                [
                    "idPagamento" => $idPagamento,
                    "status" => $status,
                    "email" => $email,
                    "erro" => $erro->getMessage()
                ]
            );

            return false;
        }
    }

    /**
     * Usa um lock do MySQL para evitar envio duplicado por concorrência.
     */
    private function obterLock(): bool
    {
        try {
            $stmt = $this->db->query(
                "SELECT GET_LOCK('retiro_email_notificacoes', 0)"
            );

            return (int) $stmt->fetchColumn() === 1;
        } catch (Throwable) {
            return true;
        }
    }

    private function liberarLock(): void
    {
        try {
            $this->db->query(
                "SELECT RELEASE_LOCK('retiro_email_notificacoes')"
            );
        } catch (Throwable) {
            // Não interrompe a aplicação por falha ao liberar o lock.
        }
    }

    /**
     * Envia o e-mail de inscrição realizada.
     */
    private function processarInscricoes(): void
    {
        $stmt = $this->db->query("
            SELECT
                i.idInscricao,
                i.status AS statusInscricao,
                i.pagamento AS statusPagamento,
                COALESCE(u.nome, i.nome) AS nome,
                COALESCE(u.email, i.email) AS email,
                e.titulo AS evento,
                e.data_inicio AS dataEvento,
                e.hora_inicio AS horaEvento,
                e.local AS localEvento
            FROM inscricoes i
            INNER JOIN eventos e
                ON e.idEvento = i.idEvento
            LEFT JOIN usuarios u
                ON u.id = i.idUsuario
            WHERE i.email_inscricao_enviado_em IS NULL
            ORDER BY i.idInscricao ASC
            LIMIT " . self::LIMITE_POR_EXECUCAO
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $registro) {
            $idInscricao = (int) ($registro["idInscricao"] ?? 0);
            $nome = trim((string) ($registro["nome"] ?? ""));
            $email = trim((string) ($registro["email"] ?? ""));

            if ($idInscricao <= 0) {
                continue;
            }

            if (!$this->emailValido($email)) {
                $this->marcarInscricaoComoNotificada($idInscricao);

                Log::warning(
                    "Inscrição sem e-mail válido para notificação",
                    [
                        "idInscricao" => $idInscricao,
                        "email" => $email
                    ]
                );

                continue;
            }

            $variaveis = [
                "nome" => $nome,
                "email" => $email,
                "idInscricao" => $idInscricao,
                "evento" => (string) ($registro["evento"] ?? ""),
                "dataEvento" => $this->formatarData(
                    $registro["dataEvento"] ?? null
                ),
                "horaEvento" => $this->formatarHora(
                    $registro["horaEvento"] ?? null
                ),
                "localEvento" => (string) ($registro["localEvento"] ?? ""),
                "statusInscricao" => (string) (
                    $registro["statusInscricao"] ?? "Pendente"
                ),
                "statusPagamento" => (string) (
                    $registro["statusPagamento"] ?? "Pendente"
                ),
                "nomeSistema" => $this->nomeSistema()
            ];

            $html = $this->renderizarTemplate(
                "inscricao_realizada.php",
                $variaveis
            );

            if ($this->enviar(
                $email,
                $nome,
                "Inscrição realizada - " . $variaveis["evento"],
                $html
            )) {
                $this->marcarInscricaoComoNotificada($idInscricao);

                Log::info(
                    "Notificação de inscrição realizada enviada por e-mail",
                    [
                        "idInscricao" => $idInscricao,
                        "email" => $email
                    ]
                );
            }
        }
    }

    /**
     * Envia a notificação de pagamento gerado.
     *
     * O pagamento local nasce como NaoDefinido. O e-mail "Gerado"
     * só é enviado depois que existe uma forma de pagamento definida,
     * evitando avisar antes da cobrança realmente ser disponibilizada.
     */
    private function processarPagamentosGerados(): void
    {
        $stmt = $this->db->query("
            SELECT
                p.*,
                e.titulo AS evento,
                e.data_inicio AS dataEvento,
                COALESCE(u.nome, p.participante) AS nomeUsuario,
                COALESCE(u.email, p.email) AS emailUsuario
            FROM pagamentos p
            LEFT JOIN eventos e
                ON e.idEvento = p.idEvento
            LEFT JOIN inscricoes i
                ON i.idInscricao = p.idInscricao
            LEFT JOIN usuarios u
                ON u.id = i.idUsuario
            WHERE p.idInscricao IS NOT NULL
              AND p.email_gerado_enviado_em IS NULL
              AND p.formaPagamento <> 'NaoDefinido'
            ORDER BY p.idPagamento ASC
            LIMIT " . self::LIMITE_POR_EXECUCAO
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $pagamento) {
            $idPagamento = (int) ($pagamento["idPagamento"] ?? 0);
            $nome = trim((string) ($pagamento["nomeUsuario"] ?? ""));
            $email = trim((string) ($pagamento["emailUsuario"] ?? ""));

            if ($idPagamento <= 0) {
                continue;
            }

            if (!$this->emailValido($email)) {
                $this->marcarPagamentoGeradoComoNotificado($idPagamento);

                Log::warning(
                    "Pagamento gerado sem e-mail válido para notificação",
                    [
                        "idPagamento" => $idPagamento,
                        "email" => $email
                    ]
                );

                continue;
            }

            $variaveis = $this->variaveisPagamento(
                $pagamento,
                $nome,
                $email
            );

            $html = $this->renderizarTemplate(
                "pagamento_gerado.php",
                $variaveis
            );

            if ($this->enviar(
                $email,
                $nome,
                "Pagamento gerado - " . $variaveis["evento"],
                $html
            )) {
                $this->marcarPagamentoGeradoComoNotificado($idPagamento);

                Log::info(
                    "Notificação de pagamento gerado enviada por e-mail",
                    [
                        "idPagamento" => $idPagamento,
                        "email" => $email
                    ]
                );
            }
        }
    }

    /**
     * Marca Pendente como estado conhecido sem enviar um e-mail
     * específico, pois o aviso correspondente é "Pagamento gerado".
     */
    private function normalizarStatusPendentes(): void
    {
        $this->db->exec("
            UPDATE pagamentos
            SET
                email_status_notificado = 'Pendente',
                email_status_notificado_em = NOW()
            WHERE status = 'Pendente'
              AND (
                    email_status_notificado IS NULL
                    OR email_status_notificado <> 'Pendente'
              )
        ");
    }

    /**
     * Envia notificações apenas quando o status atual é diferente
     * do último status que já foi notificado.
     */
    private function processarStatusPagamentos(): void
    {
        $statusSql = implode(
            ", ",
            array_map(
                fn(string $status): string => $this->db->quote($status),
                self::STATUS_NOTIFICADOS
            )
        );

        $stmt = $this->db->query("
            SELECT
                p.*,
                e.titulo AS evento,
                e.data_inicio AS dataEvento,
                COALESCE(u.nome, p.participante) AS nomeUsuario,
                COALESCE(u.email, p.email) AS emailUsuario
            FROM pagamentos p
            LEFT JOIN eventos e
                ON e.idEvento = p.idEvento
            LEFT JOIN inscricoes i
                ON i.idInscricao = p.idInscricao
            LEFT JOIN usuarios u
                ON u.id = i.idUsuario
            WHERE p.idInscricao IS NOT NULL
              AND p.status IN ({$statusSql})
              AND (
                    p.email_status_notificado IS NULL
                    OR p.email_status_notificado <> p.status
              )
            ORDER BY p.idPagamento ASC
            LIMIT " . self::LIMITE_POR_EXECUCAO
        );

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $pagamento) {
            $idPagamento = (int) ($pagamento["idPagamento"] ?? 0);
            $status = trim((string) ($pagamento["status"] ?? ""));
            $nome = trim((string) ($pagamento["nomeUsuario"] ?? ""));
            $email = trim((string) ($pagamento["emailUsuario"] ?? ""));

            if (
                $idPagamento <= 0
                || !in_array($status, self::STATUS_NOTIFICADOS, true)
            ) {
                continue;
            }

            if (!$this->emailValido($email)) {
                $this->marcarStatusComoNotificado(
                    $idPagamento,
                    $status
                );

                Log::warning(
                    "Pagamento sem e-mail válido para notificação de status",
                    [
                        "idPagamento" => $idPagamento,
                        "status" => $status,
                        "email" => $email
                    ]
                );

                continue;
            }

            $arquivo = match ($status) {
                "Pago" => "pagamento_pago.php",
                "Vencido" => "pagamento_vencido.php",
                "Cancelado" => "pagamento_cancelado.php",
                "Estornado" => "pagamento_estornado.php",
                default => ""
            };

            if ($arquivo === "") {
                continue;
            }

            $variaveis = $this->variaveisPagamento(
                $pagamento,
                $nome,
                $email
            );

            $variaveis["statusPagamento"] = $status;

            $html = $this->renderizarTemplate(
                $arquivo,
                $variaveis
            );

            $assunto = match ($status) {
                "Pago" => "Pagamento confirmado",
                "Vencido" => "Pagamento vencido",
                "Cancelado" => "Pagamento cancelado",
                "Estornado" => "Pagamento estornado",
                default => "Atualização do pagamento"
            };

            $assunto .= " - " . $variaveis["evento"];

            if ($this->enviar(
                $email,
                $nome,
                $assunto,
                $html
            )) {
                $this->marcarStatusComoNotificado(
                    $idPagamento,
                    $status
                );

                Log::info(
                    "Notificação de status do pagamento enviada por e-mail",
                    [
                        "idPagamento" => $idPagamento,
                        "status" => $status,
                        "email" => $email
                    ]
                );
            }
        }
    }

    /**
     * Prepara as variáveis usadas pelos templates financeiros.
     */
    private function variaveisPagamento(
        array $pagamento,
        string $nome,
        string $email
    ): array {
        return [
            "nome" => $nome,
            "email" => $email,
            "idPagamento" => (int) (
                $pagamento["idPagamento"] ?? 0
            ),
            "idInscricao" => (int) (
                $pagamento["idInscricao"] ?? 0
            ),
            "evento" => (string) (
                $pagamento["evento"]
                ?? $pagamento["tituloEvento"]
                ?? ""
            ),
            "dataEvento" => $this->formatarData(
                $pagamento["dataEvento"]
                ?? $pagamento["dataInicioEvento"]
                ?? null
            ),
            "codigoPagamento" => (string) (
                $pagamento["codigo"] ?? ""
            ),
            "valor" => $this->formatarMoeda(
                (float) ($pagamento["valor"] ?? 0)
            ),
            "formaPagamento" => $this->nomeFormaPagamento(
                (string) (
                    $pagamento["formaPagamento"] ?? ""
                )
            ),
            "statusPagamento" => (string) (
                $pagamento["status"] ?? ""
            ),
            "dataVencimento" => $this->formatarData(
                $pagamento["dataVencimento"] ?? null
            ),
            "dataPagamento" => $this->formatarDataHora(
                $pagamento["dataPagamento"] ?? null
            ),
            "invoiceUrl" => $this->urlSegura(
                $pagamento["invoiceUrl"] ?? null
            ),
            "bankSlipUrl" => $this->urlSegura(
                $pagamento["bankSlipUrl"] ?? null
            ),
            "pixCopiaCola" => trim(
                (string) ($pagamento["pixCopiaCola"] ?? "")
            ),
            "boletoLinhaDigitavel" => trim(
                (string) (
                    $pagamento["boletoLinhaDigitavel"] ?? ""
                )
            ),
            "nomeSistema" => $this->nomeSistema()
        ];
    }

    private function marcarInscricaoComoNotificada(
        int $idInscricao
    ): void {
        $stmt = $this->db->prepare("
            UPDATE inscricoes
            SET email_inscricao_enviado_em = NOW()
            WHERE idInscricao = :id
        ");

        $stmt->execute([":id" => $idInscricao]);
    }

    private function marcarPagamentoGeradoComoNotificado(
        int $idPagamento
    ): void {
        $stmt = $this->db->prepare("
            UPDATE pagamentos
            SET
                email_gerado_enviado_em = NOW(),
                email_status_notificado = COALESCE(
                    email_status_notificado,
                    'Pendente'
                ),
                email_status_notificado_em = COALESCE(
                    email_status_notificado_em,
                    NOW()
                )
            WHERE idPagamento = :id
        ");

        $stmt->execute([":id" => $idPagamento]);
    }

    private function marcarStatusComoNotificado(
        int $idPagamento,
        string $status
    ): void {
        $stmt = $this->db->prepare("
            UPDATE pagamentos
            SET
                email_status_notificado = :status,
                email_status_notificado_em = NOW()
            WHERE idPagamento = :id
        ");

        $stmt->execute([
            ":status" => $status,
            ":id" => $idPagamento
        ]);
    }

    private function enviar(
        string $email,
        string $nome,
        string $assunto,
        string $html
    ): bool {
        try {
            $mail = new Mail();

            return $mail->send(
                $email,
                $nome,
                $assunto,
                $html
            );
        } catch (Throwable $erro) {
            Log::warning(
                "Falha ao enviar notificação automática por e-mail",
                [
                    "email" => $email,
                    "assunto" => $assunto,
                    "erro" => $erro->getMessage()
                ]
            );

            return false;
        }
    }

    /**
     * Renderiza um template usando o mesmo padrão:
     * ob_start() + include + ob_get_clean().
     */
    private function renderizarTemplate(
        string $arquivo,
        array $variaveis
    ): string {
        $caminho = __DIR__
            . "/../mail/templates/"
            . basename($arquivo);

        if (!is_file($caminho)) {
            throw new RuntimeException(
                "Template de e-mail não encontrado: "
                . basename($arquivo)
            );
        }

        extract(
            $variaveis,
            EXTR_SKIP
        );

        ob_start();

        try {
            include $caminho;

            return (string) ob_get_clean();
        } catch (Throwable $erro) {
            ob_end_clean();
            throw $erro;
        }
    }

    private function emailValido(string $email): bool
    {
        return $email !== ""
            && filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            ) !== false;
    }

    private function nomeSistema(): string
    {
        try {
            $titulo = Title::getAtual();

            if ($titulo) {
                $nome = trim(
                    (string) $titulo->getNome()
                );

                if ($nome !== "") {
                    return $nome;
                }
            }
        } catch (Throwable) {
            // Usa o nome padrão abaixo.
        }

        return "Sistema de Eventos";
    }

    private function nomeFormaPagamento(string $forma): string
    {
        return match ($forma) {
            "PIX" => "PIX",
            "Cartao" => "Cartão",
            "Boleto" => "Boleto",
            "Dinheiro" => "Dinheiro",
            "Transferencia" => "Transferência",
            default => "Não definido"
        };
    }

    private function formatarMoeda(float $valor): string
    {
        return "R$ "
            . number_format(
                $valor,
                2,
                ",",
                "."
            );
    }

    private function formatarData(mixed $valor): string
    {
        $valor = trim((string) $valor);

        if (
            $valor === ""
            || str_starts_with(
                $valor,
                "0000-00-00"
            )
        ) {
            return "";
        }

        try {
            return (
                new DateTimeImmutable($valor)
            )->format("d/m/Y");
        } catch (Throwable) {
            return $valor;
        }
    }

    private function formatarHora(mixed $valor): string
    {
        $valor = trim((string) $valor);

        if ($valor === "") {
            return "";
        }

        try {
            return (
                new DateTimeImmutable($valor)
            )->format("H:i");
        } catch (Throwable) {
            return $valor;
        }
    }

    private function formatarDataHora(mixed $valor): string
    {
        $valor = trim((string) $valor);

        if (
            $valor === ""
            || str_starts_with(
                $valor,
                "0000-00-00"
            )
        ) {
            return "";
        }

        try {
            return (
                new DateTimeImmutable($valor)
            )->format("d/m/Y H:i");
        } catch (Throwable) {
            return $valor;
        }
    }

    private function urlSegura(mixed $valor): string
    {
        $url = trim((string) $valor);

        if (
            $url === ""
            || filter_var(
                $url,
                FILTER_VALIDATE_URL
            ) === false
        ) {
            return "";
        }

        $scheme = strtolower(
            (string) parse_url(
                $url,
                PHP_URL_SCHEME
            )
        );

        return in_array(
            $scheme,
            ["http", "https"],
            true
        )
            ? $url
            : "";
    }
}
