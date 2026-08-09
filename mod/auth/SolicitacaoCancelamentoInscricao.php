<?php

declare(strict_types=1);

require_once __DIR__ . "/../database/db.php";

/**
 * Solicitações de cancelamento de inscrições.
 *
 * Regra:
 * - o usuário solicita;
 * - o Administrador aprova ou rejeita;
 * - a solicitação só pode ser criada até 1 dia útil
 *   antes do encerramento das inscrições.
 *
 * Nesta versão, dias úteis são segunda a sexta.
 */
class SolicitacaoCancelamentoInscricao
{
    private PDO $db;

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

    public function contarPendentes(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*)
            FROM solicitacoes_cancelamento_inscricao
            WHERE status = 'Pendente'
        ");

        return (int) $stmt->fetchColumn();
    }

    public function buscarPendentePorInscricao(
        int $idInscricao
    ): ?array {
        if ($idInscricao <= 0) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM solicitacoes_cancelamento_inscricao
            WHERE idInscricao = :idInscricao
              AND status = 'Pendente'
            ORDER BY idSolicitacao DESC
            LIMIT 1
        ");

        $stmt->execute([
            ":idInscricao" => $idInscricao
        ]);

        $registro = $stmt->fetch();

        return $registro ?: null;
    }

    public function buscarUltimaPorInscricao(
        int $idInscricao,
        int $idUsuario
    ): ?array {
        if (
            $idInscricao <= 0
            || $idUsuario <= 0
        ) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT
                s.*,
                a.nome AS administrador
            FROM solicitacoes_cancelamento_inscricao s
            LEFT JOIN usuarios a
                ON a.id = s.idAdministrador
            WHERE s.idInscricao = :idInscricao
              AND s.idUsuario = :idUsuario
            ORDER BY s.idSolicitacao DESC
            LIMIT 1
        ");

        $stmt->execute([
            ":idInscricao" => $idInscricao,
            ":idUsuario" => $idUsuario
        ]);

        $registro = $stmt->fetch();

        return $registro ?: null;
    }

    /**
     * @return array{
     *   permitido:bool,
     *   mensagem:string,
     *   limite:?string,
     *   limite_formatado:?string
     * }
     */
    public function podeSolicitar(
        int $idInscricao,
        int $idUsuario
    ): array {
        $contexto = $this->contextoInscricao(
            $idInscricao,
            $idUsuario
        );

        if (!$contexto) {
            return [
                "permitido" => false,
                "mensagem" =>
                    "Inscrição não encontrada.",
                "limite" => null,
                "limite_formatado" => null
            ];
        }

        if (
            (string) ($contexto["status"] ?? "")
            === "Cancelada"
        ) {
            return [
                "permitido" => false,
                "mensagem" =>
                    "Esta inscrição já está cancelada.",
                "limite" => null,
                "limite_formatado" => null
            ];
        }

        if (
            $this->buscarPendentePorInscricao(
                $idInscricao
            )
        ) {
            return [
                "permitido" => false,
                "mensagem" =>
                    "Já existe uma solicitação "
                    . "de cancelamento em análise.",
                "limite" => null,
                "limite_formatado" => null
            ];
        }

        $fimInscricoes = trim(
            (string) (
                $contexto["inscricao_fim"]
                ?? ""
            )
        );

        if ($fimInscricoes === "") {
            return [
                "permitido" => false,
                "mensagem" =>
                    "O prazo de cancelamento não pode "
                    . "ser calculado porque o encerramento "
                    . "das inscrições não foi configurado.",
                "limite" => null,
                "limite_formatado" => null
            ];
        }

        $limite = $this->limiteSolicitacao(
            $fimInscricoes
        );

        if (!$limite) {
            return [
                "permitido" => false,
                "mensagem" =>
                    "Não foi possível calcular "
                    . "o prazo de cancelamento.",
                "limite" => null,
                "limite_formatado" => null
            ];
        }

        $agora = new DateTimeImmutable(
            "now",
            new DateTimeZone(
                "America/Sao_Paulo"
            )
        );

        if ($agora > $limite) {
            return [
                "permitido" => false,
                "mensagem" =>
                    "O prazo para solicitar o "
                    . "cancelamento encerrou em "
                    . $limite->format(
                        "d/m/Y H:i"
                    )
                    . ".",
                "limite" =>
                    $limite->format(
                        "Y-m-d H:i:s"
                    ),
                "limite_formatado" =>
                    $limite->format(
                        "d/m/Y H:i"
                    )
            ];
        }

        return [
            "permitido" => true,
            "mensagem" =>
                "Você pode solicitar o cancelamento "
                . "até "
                . $limite->format(
                    "d/m/Y H:i"
                )
                . ".",
            "limite" =>
                $limite->format(
                    "Y-m-d H:i:s"
                ),
            "limite_formatado" =>
                $limite->format(
                    "d/m/Y H:i"
                )
        ];
    }

    public function solicitar(
        int $idInscricao,
        int $idUsuario,
        string $motivo
    ): int {
        $motivo = trim($motivo);

        if (
            function_exists("mb_strlen")
                ? mb_strlen(
                    $motivo,
                    "UTF-8"
                ) < 10
                : strlen($motivo) < 10
        ) {
            throw new InvalidArgumentException(
                "Informe o motivo do cancelamento "
                . "com pelo menos 10 caracteres."
            );
        }

        if (
            function_exists("mb_strlen")
                ? mb_strlen(
                    $motivo,
                    "UTF-8"
                ) > 2000
                : strlen($motivo) > 2000
        ) {
            throw new InvalidArgumentException(
                "O motivo do cancelamento "
                . "é muito longo."
            );
        }

        $iniciouTransacao =
            !$this->db->inTransaction();

        if ($iniciouTransacao) {
            $this->db->beginTransaction();
        }

        try {
            /*
             * Bloqueia a inscrição durante a criação
             * da solicitação para evitar dois pedidos
             * simultâneos.
             */
            $stmt = $this->db->prepare("
                SELECT
                    i.idInscricao
                FROM inscricoes i
                WHERE i.idInscricao = :idInscricao
                  AND i.idUsuario = :idUsuario
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                ":idInscricao" => $idInscricao,
                ":idUsuario" => $idUsuario
            ]);

            if (!$stmt->fetch()) {
                throw new RuntimeException(
                    "Inscrição não encontrada."
                );
            }

            $regra = $this->podeSolicitar(
                $idInscricao,
                $idUsuario
            );

            if (!$regra["permitido"]) {
                throw new RuntimeException(
                    $regra["mensagem"]
                );
            }

            $stmt = $this->db->prepare("
                INSERT INTO
                    solicitacoes_cancelamento_inscricao (
                        idInscricao,
                        idUsuario,
                        motivo,
                        status,
                        criado_em
                    )
                VALUES (
                    :idInscricao,
                    :idUsuario,
                    :motivo,
                    'Pendente',
                    NOW()
                )
            ");

            $stmt->execute([
                ":idInscricao" => $idInscricao,
                ":idUsuario" => $idUsuario,
                ":motivo" => $motivo
            ]);

            $id = (int) $this->db
                ->lastInsertId();

            if ($iniciouTransacao) {
                $this->db->commit();
            }

            return $id;
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

    public function listar(
        string $status = ""
    ): array {
        $where = "";
        $params = [];

        if (
            in_array(
                $status,
                [
                    "Pendente",
                    "Aprovada",
                    "Rejeitada"
                ],
                true
            )
        ) {
            $where =
                " WHERE s.status = :status ";

            $params[":status"] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT
                s.*,
                i.status AS statusInscricao,
                i.pagamento AS pagamentoInscricao,
                e.idEvento,
                e.titulo AS evento,
                e.inscricao_fim,
                u.nome AS usuario,
                u.email,
                u.cpf,
                a.nome AS administrador,
                p.idPagamento,
                p.status AS statusPagamento,
                p.formaPagamento,
                p.valor AS valorPagamento,
                p.asaasPaymentId
            FROM solicitacoes_cancelamento_inscricao s
            INNER JOIN inscricoes i
                ON i.idInscricao = s.idInscricao
            INNER JOIN eventos e
                ON e.idEvento = i.idEvento
            INNER JOIN usuarios u
                ON u.id = s.idUsuario
            LEFT JOIN usuarios a
                ON a.id = s.idAdministrador
            LEFT JOIN pagamentos p
                ON p.idPagamento = (
                    SELECT MAX(p2.idPagamento)
                    FROM pagamentos p2
                    WHERE p2.idInscricao =
                        s.idInscricao
                )
            {$where}
            ORDER BY
                CASE
                    WHEN s.status = 'Pendente'
                        THEN 0
                    ELSE 1
                END,
                s.criado_em DESC,
                s.idSolicitacao DESC
        ");

        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function analisar(
        int $idSolicitacao,
        int $idAdministrador,
        string $decisao,
        string $observacao = ""
    ): array {
        if (
            !in_array(
                $decisao,
                ["Aprovada", "Rejeitada"],
                true
            )
        ) {
            throw new InvalidArgumentException(
                "Decisão inválida."
            );
        }

        $observacao = trim($observacao);

        if (
            $decisao === "Rejeitada"
            && (
                function_exists("mb_strlen")
                    ? mb_strlen(
                        $observacao,
                        "UTF-8"
                    ) < 5
                    : strlen($observacao) < 5
            )
        ) {
            throw new InvalidArgumentException(
                "Informe o motivo da rejeição."
            );
        }

        /*
         * Antes de cancelar uma inscrição, sincroniza
         * uma cobrança Asaas existente para evitar
         * cancelar localmente um pagamento que acabou
         * de ser confirmado.
         */
        if ($decisao === "Aprovada") {
            $pre = $this->buscarAdmin(
                $idSolicitacao
            );

            if (!$pre) {
                throw new RuntimeException(
                    "Solicitação não encontrada."
                );
            }

            $idPagamento = (int) (
                $pre["idPagamento"]
                ?? 0
            );

            $asaasPaymentId = trim(
                (string) (
                    $pre["asaasPaymentId"]
                    ?? ""
                )
            );

            if (
                $idPagamento > 0
                && $asaasPaymentId !== ""
                && class_exists(
                    "AsaasPagamentoService"
                )
            ) {
                try {
                    $servicoAsaas =
                        new AsaasPagamentoService(
                            $this->db
                        );

                    if (
                        $servicoAsaas
                            ->estaConfigurado()
                    ) {
                        $servicoAsaas
                            ->sincronizarCobranca(
                                $idPagamento
                            );
                    }
                } catch (Throwable $erro) {
                    throw new RuntimeException(
                        "Não foi possível sincronizar "
                        . "o pagamento antes do "
                        . "cancelamento: "
                        . $erro->getMessage(),
                        0,
                        $erro
                    );
                }
            }
        }

        $iniciouTransacao =
            !$this->db->inTransaction();

        if ($iniciouTransacao) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare("
                SELECT
                    s.*,
                    i.status AS statusInscricao
                FROM
                    solicitacoes_cancelamento_inscricao s
                INNER JOIN inscricoes i
                    ON i.idInscricao =
                        s.idInscricao
                WHERE s.idSolicitacao =
                    :idSolicitacao
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([
                ":idSolicitacao" =>
                    $idSolicitacao
            ]);

            $solicitacao = $stmt->fetch();

            if (!$solicitacao) {
                throw new RuntimeException(
                    "Solicitação não encontrada."
                );
            }

            if (
                (string) $solicitacao["status"]
                !== "Pendente"
            ) {
                throw new RuntimeException(
                    "Esta solicitação já foi analisada."
                );
            }

            if ($decisao === "Rejeitada") {
                $this->atualizarAnalise(
                    $idSolicitacao,
                    $idAdministrador,
                    "Rejeitada",
                    $observacao
                );

                if ($iniciouTransacao) {
                    $this->db->commit();
                }

                return [
                    "status" => "Rejeitada",
                    "pagamentoPago" => false
                ];
            }

            $idInscricao = (int) (
                $solicitacao["idInscricao"]
                ?? 0
            );

            $stmtPagamento =
                $this->db->prepare("
                    SELECT *
                    FROM pagamentos
                    WHERE idInscricao =
                        :idInscricao
                    ORDER BY idPagamento DESC
                    LIMIT 1
                    FOR UPDATE
                ");

            $stmtPagamento->execute([
                ":idInscricao" =>
                    $idInscricao
            ]);

            $pagamento =
                $stmtPagamento->fetch();

            $pagamentoPago =
                $pagamento
                && (string) (
                    $pagamento["status"]
                    ?? ""
                ) === "Pago";

            /*
             * ESTORNO_AUTOMATICO_ASAAS_V2
             *
             * Se o pagamento já foi recebido, o cancelamento
             * somente prossegue depois que o estorno for
             * solicitado ao Asaas com sucesso.
             */
            $estornoSolicitado = false;
            $estornoConcluido = false;
            $estornoBoletoUrl = null;

            if (
                $pagamento
                && $pagamentoPago
            ) {
                $idPagamentoPago =
                    (int) (
                        $pagamento[
                            "idPagamento"
                        ]
                        ?? 0
                    );

                $idAsaasPago = trim(
                    (string) (
                        $pagamento[
                            "asaasPaymentId"
                        ]
                        ?? ""
                    )
                );

                if ($idAsaasPago === "") {
                    throw new RuntimeException(
                        "O pagamento está marcado como Pago, "
                        . "mas não possui cobrança Asaas vinculada. "
                        . "O cancelamento não foi aprovado."
                    );
                }

                $asaas =
                    new AsaasService();

                if (!$asaas->estaConfigurado()) {
                    throw new RuntimeException(
                        "O Asaas não está configurado. "
                        . "Não é possível aprovar o cancelamento "
                        . "de uma inscrição paga sem processar "
                        . "o estorno."
                    );
                }

                $cobranca =
                    $asaas->consultarCobranca(
                        $idAsaasPago
                    );

                $statusAsaas = strtoupper(
                    trim(
                        (string) (
                            $cobranca["status"]
                            ?? ""
                        )
                    )
                );

                $billingType = strtoupper(
                    trim(
                        (string) (
                            $cobranca[
                                "billingType"
                            ]
                            ?? ""
                        )
                    )
                );

                if ($statusAsaas === "REFUNDED") {
                    $estornoSolicitado = true;
                    $estornoConcluido = true;
                } elseif (
                    !in_array(
                        $statusAsaas,
                        [
                            "RECEIVED",
                            "CONFIRMED"
                        ],
                        true
                    )
                ) {
                    throw new RuntimeException(
                        "A cobrança Asaas está com status "
                        . $statusAsaas
                        . " e não pode ser estornada "
                        . "automaticamente neste momento."
                    );
                } elseif (
                    $billingType === "BOLETO"
                ) {
                    $retornoEstorno =
                        $asaas
                            ->solicitarEstornoBoleto(
                                $idAsaasPago
                            );

                    $estornoBoletoUrl = trim(
                        (string) (
                            $retornoEstorno[
                                "requestUrl"
                            ]
                            ?? ""
                        )
                    );

                    if ($estornoBoletoUrl === "") {
                        throw new RuntimeException(
                            "O Asaas iniciou o estorno "
                            . "do boleto, mas não retornou "
                            . "o link necessário para o pagador."
                        );
                    }

                    $estornoSolicitado = true;

                    $stmt = $this->db->prepare("
                        UPDATE pagamentos
                        SET
                            observacao = CONCAT_WS(
                                CHAR(10),
                                NULLIF(
                                    TRIM(observacao),
                                    ''
                                ),
                                :observacao
                            ),
                            asaasAtualizadoEm = NOW()
                        WHERE idPagamento =
                            :idPagamento
                    ");

                    $stmt->execute([
                        ":observacao" =>
                            "Estorno de boleto solicitado. "
                            . "O pagador deve concluir os "
                            . "dados bancários em: "
                            . $estornoBoletoUrl,
                        ":idPagamento" =>
                            $idPagamentoPago
                    ]);
                } elseif (
                    in_array(
                        $billingType,
                        [
                            "PIX",
                            "CREDIT_CARD"
                        ],
                        true
                    )
                ) {
                    $asaas->estornarCobranca(
                        $idAsaasPago,
                        "Cancelamento aprovado da inscrição #"
                            . $idInscricao
                    );

                    $estornoSolicitado = true;

                    /*
                     * Consulta de novo para saber se o Asaas
                     * já marcou a cobrança como REFUNDED.
                     */
                    $cobrancaDepois =
                        $asaas
                            ->consultarCobranca(
                                $idAsaasPago
                            );

                    $statusDepois = strtoupper(
                        trim(
                            (string) (
                                $cobrancaDepois[
                                    "status"
                                ]
                                ?? ""
                            )
                        )
                    );

                    $estornoConcluido =
                        $statusDepois
                        === "REFUNDED";
                } else {
                    throw new RuntimeException(
                        "A forma de pagamento "
                        . $billingType
                        . " não possui fluxo automático "
                        . "de estorno configurado."
                    );
                }

                /*
                 * Sincroniza o pagamento após solicitar
                 * o estorno. REFUNDED vira Estornado.
                 */
                if (
                    class_exists(
                        "AsaasPagamentoService"
                    )
                ) {
                    $servicoPagamento =
                        new AsaasPagamentoService(
                            $this->db
                        );

                    try {
                        $servicoPagamento
                            ->sincronizarCobranca(
                                $idPagamentoPago
                            );
                    } catch (Throwable $erroSync) {
                        /*
                         * A solicitação de estorno já foi enviada.
                         * Não dispara um segundo estorno.
                         */
                        Log::warning(
                            "Estorno solicitado no Asaas, "
                            . "mas falhou a sincronização local",
                            [
                                "idPagamento" =>
                                    $idPagamentoPago,
                                "asaasPaymentId" =>
                                    $idAsaasPago,
                                "erro" =>
                                    $erroSync
                                        ->getMessage()
                            ]
                        );
                    }
                }
            }

            /*
             * Se ainda não foi pago, remove a cobrança
             * pendente do Asaas antes de cancelar localmente.
             */
            if (
                $pagamento
                && !$pagamentoPago
            ) {
                $idAsaas = trim(
                    (string) (
                        $pagamento[
                            "asaasPaymentId"
                        ]
                        ?? ""
                    )
                );

                if (
                    $idAsaas !== ""
                    && class_exists(
                        "AsaasService"
                    )
                ) {
                    $asaas =
                        new AsaasService();

                    if ($asaas->estaConfigurado()) {
                        $cobranca =
                            $asaas
                                ->consultarCobrancaOuNull(
                                    $idAsaas
                                );

                        if ($cobranca !== null) {
                            $statusAsaas =
                                strtoupper(
                                    trim(
                                        (string) (
                                            $cobranca[
                                                "status"
                                            ]
                                            ?? ""
                                        )
                                    )
                                );

                            if (
                                in_array(
                                    $statusAsaas,
                                    [
                                        "RECEIVED",
                                        "CONFIRMED",
                                        "RECEIVED_IN_CASH"
                                    ],
                                    true
                                )
                            ) {
                                throw new RuntimeException(
                                    "O Asaas informa que "
                                    . "este pagamento já "
                                    . "foi recebido. "
                                    . "Atualize a página "
                                    . "e analise novamente."
                                );
                            }

                            if (
                                !in_array(
                                    $statusAsaas,
                                    [
                                        "DELETED",
                                        "REFUNDED"
                                    ],
                                    true
                                )
                            ) {
                                $asaas
                                    ->excluirCobranca(
                                        $idAsaas
                                    );
                            }
                        }
                    }
                }

                $stmt = $this->db->prepare("
                    UPDATE pagamentos
                    SET
                        status = 'Cancelado',
                        dataPagamento = NULL,
                        asaasStatus = CASE
                            WHEN asaasPaymentId
                                IS NOT NULL
                                THEN 'DELETED'
                            ELSE asaasStatus
                        END,
                        observacao = CONCAT_WS(
                            CHAR(10),
                            NULLIF(
                                TRIM(observacao),
                                ''
                            ),
                            :observacao
                        )
                    WHERE idPagamento =
                        :idPagamento
                ");

                $stmt->execute([
                    ":observacao" =>
                        "Pagamento cancelado "
                        . "após aprovação da "
                        . "solicitação de "
                        . "cancelamento da inscrição.",
                    ":idPagamento" =>
                        (int) $pagamento[
                            "idPagamento"
                        ]
                ]);
            }

            /*
             * Cancela a inscrição somente depois do fluxo
             * financeiro acima ter sido aceito pelo Asaas.
             */
            $statusPagamentoInscricao =
                $pagamentoPago
                    ? (
                        $estornoConcluido
                            ? "Estornado"
                            : "Pago"
                    )
                    : "Cancelado";

            $stmt = $this->db->prepare("
                UPDATE inscricoes
                SET
                    status = 'Cancelada',
                    pagamento = :pagamento
                WHERE idInscricao =
                    :idInscricao
            ");

            $stmt->execute([
                ":pagamento" =>
                    $statusPagamentoInscricao,
                ":idInscricao" =>
                    $idInscricao
            ]);

            $observacaoFinal = $observacao;

            if ($pagamentoPago) {
                if ($estornoConcluido) {
                    $aviso =
                        "Pagamento estornado "
                        . "automaticamente no Asaas.";
                } elseif (
                    $estornoBoletoUrl !== null
                ) {
                    $aviso =
                        "Estorno do boleto iniciado no Asaas. "
                        . "O pagador precisa completar os "
                        . "dados bancários no link retornado.";
                } elseif ($estornoSolicitado) {
                    $aviso =
                        "Estorno solicitado ao Asaas e "
                        . "aguardando conclusão.";
                } else {
                    $aviso =
                        "Não houve solicitação de estorno.";
                }

                $observacaoFinal =
                    $observacaoFinal !== ""
                        ? $observacaoFinal
                            . "\n"
                            . $aviso
                        : $aviso;
            }

            $this->atualizarAnalise(
                $idSolicitacao,
                $idAdministrador,
                "Aprovada",
                $observacaoFinal
            );

            if ($iniciouTransacao) {
                $this->db->commit();
            }

            return [
                "status" => "Aprovada",
                "pagamentoPago" =>
                    $pagamentoPago,
                "estornoSolicitado" =>
                    $estornoSolicitado,
                "estornoConcluido" =>
                    $estornoConcluido,
                "estornoBoletoUrl" =>
                    $estornoBoletoUrl
            ];
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

    private function buscarAdmin(
        int $idSolicitacao
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                s.*,
                p.idPagamento,
                p.status AS statusPagamento,
                p.asaasPaymentId
            FROM solicitacoes_cancelamento_inscricao s
            LEFT JOIN pagamentos p
                ON p.idPagamento = (
                    SELECT MAX(p2.idPagamento)
                    FROM pagamentos p2
                    WHERE p2.idInscricao =
                        s.idInscricao
                )
            WHERE s.idSolicitacao =
                :idSolicitacao
            LIMIT 1
        ");

        $stmt->execute([
            ":idSolicitacao" => $idSolicitacao
        ]);

        $registro = $stmt->fetch();

        return $registro ?: null;
    }

    private function atualizarAnalise(
        int $idSolicitacao,
        int $idAdministrador,
        string $status,
        string $observacao
    ): void {
        $stmt = $this->db->prepare("
            UPDATE
                solicitacoes_cancelamento_inscricao
            SET
                status = :status,
                idAdministrador =
                    :idAdministrador,
                observacao_admin =
                    :observacao,
                analisado_em = NOW()
            WHERE idSolicitacao =
                :idSolicitacao
        ");

        $stmt->execute([
            ":status" => $status,
            ":idAdministrador" =>
                $idAdministrador,
            ":observacao" =>
                $observacao !== ""
                    ? $observacao
                    : null,
            ":idSolicitacao" =>
                $idSolicitacao
        ]);
    }

    private function contextoInscricao(
        int $idInscricao,
        int $idUsuario
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                i.idInscricao,
                i.idUsuario,
                i.idEvento,
                i.status,
                i.pagamento,
                e.titulo,
                e.inscricao_fim
            FROM inscricoes i
            INNER JOIN eventos e
                ON e.idEvento = i.idEvento
            WHERE i.idInscricao =
                :idInscricao
              AND i.idUsuario =
                :idUsuario
            LIMIT 1
        ");

        $stmt->execute([
            ":idInscricao" => $idInscricao,
            ":idUsuario" => $idUsuario
        ]);

        $registro = $stmt->fetch();

        return $registro ?: null;
    }

    private function limiteSolicitacao(
        string $fimInscricoes
    ): ?DateTimeImmutable {
        $fuso = new DateTimeZone(
            "America/Sao_Paulo"
        );

        try {
            /*
             * Se o banco possuir somente a data,
             * considera o fim desse dia.
             */
            if (
                preg_match(
                    '/^\d{4}-\d{2}-\d{2}$/',
                    $fimInscricoes
                )
            ) {
                $fim = new DateTimeImmutable(
                    $fimInscricoes
                    . " 23:59:59",
                    $fuso
                );
            } else {
                $fim = new DateTimeImmutable(
                    $fimInscricoes,
                    $fuso
                );
            }
        } catch (Throwable) {
            return null;
        }

        /*
         * Volta pelo menos um dia e continua
         * voltando enquanto cair em sábado/domingo.
         */
        $limite = $fim->modify("-1 day");

        while (
            (int) $limite->format("N") > 5
        ) {
            $limite =
                $limite->modify("-1 day");
        }

        return $limite;
    }
}
