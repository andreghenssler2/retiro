<?php

declare(strict_types=1);

/**
 * Orquestra a geração e a sincronização de cobranças Asaas
 * com os pagamentos das inscrições.
 */
class AsaasPagamentoService
{
    private PDO $db;
    private Pagamento $pagamentos;
    private AsaasService $asaas;
    private ?string $ultimaReferenciaExterna = null;

    private const FORMAS_INTEGRADAS = ["PIX", "Boleto", "Cartao"];

    public function __construct(
        ?PDO $db = null,
        ?Pagamento $pagamentos = null,
        ?AsaasService $asaas = null
    ) {
        $this->db = $db ?? Database::connect();
        $this->pagamentos = $pagamentos ?? new Pagamento($this->db);
        $this->asaas = $asaas ?? new AsaasService();
    }

    public function estaConfigurado(): bool
    {
        return $this->asaas->estaConfigurado();
    }

    public function gerarOuRecuperarCobranca(int $idPagamento, string $forma): array
    {
        if (!in_array($forma, self::FORMAS_INTEGRADAS, true)) {
            throw new InvalidArgumentException("A forma escolhida não utiliza a integração com o Asaas.");
        }

        $pagamento = $this->pagamentos->buscar($idPagamento);

        if (!$pagamento) {
            throw new RuntimeException("Pagamento não encontrado.");
        }

        if ((int) ($pagamento["idInscricao"] ?? 0) <= 0) {
            throw new RuntimeException("O pagamento não está relacionado a uma inscrição.");
        }

        if ((float) ($pagamento["valor"] ?? 0) <= 0) {
            throw new InvalidArgumentException("O pagamento não possui valor para cobrança.");
        }

        $statusLocalAtual = (string) ($pagamento["status"] ?? "");

        if ($statusLocalAtual === "Estornado") {
            throw new RuntimeException("Não é possível gerar uma nova cobrança para um pagamento estornado.");
        }

        $billingType = $this->billingType($forma);
        $referenciaExterna = $this->referenciaPagamento($pagamento);
        $referenciaLegada = "inscricao-pagamento-" . $idPagamento;
        $this->ultimaReferenciaExterna = $referenciaExterna;
        $idAsaasAtual = trim((string) ($pagamento["asaasPaymentId"] ?? ""));

        /*
         * Uma cópia do banco pode reutilizar o mesmo id numérico de pagamento.
         * Por isso, antes de confiar no asaasPaymentId salvo, confirmamos que a
         * cobrança realmente pertence ao participante e ao pagamento atuais.
         */
        if ($idAsaasAtual !== "") {
            $cobrancaAtual = $this->asaas->consultarCobrancaOuNull($idAsaasAtual);

            if (
                $cobrancaAtual === null
                || !$this->cobrancaPertenceAoPagamento(
                    $cobrancaAtual,
                    $pagamento,
                    $referenciaExterna,
                    $referenciaLegada
                )
            ) {
                $this->desvincularCobrancaLocal($pagamento);
                $pagamento = $this->pagamentos->buscar($idPagamento) ?? $pagamento;
                $statusLocalAtual = "Pendente";
                $idAsaasAtual = "";
            } else {
                $billingAtual = strtoupper((string) ($cobrancaAtual["billingType"] ?? ""));
                $statusAtual = strtoupper((string) ($cobrancaAtual["status"] ?? ""));
                $formaAtual = $this->formaLocal($billingAtual);

                if ($this->cobrancaFoiPaga($statusAtual)) {
                    return $this->persistirRetornoAsaas(
                        $pagamento,
                        $formaAtual !== "NaoDefinido" ? $formaAtual : $forma,
                        $cobrancaAtual
                    );
                }

                if ($statusLocalAtual === "Pago") {
                    throw new RuntimeException(
                        "Este pagamento já está marcado como pago. Sincronize a cobrança existente antes de alterar o meio de pagamento."
                    );
                }

                                if (
                    $billingAtual === $billingType
                    && !$this->cobrancaFoiEncerrada(
                        $statusAtual
                    )
                ) {
                    /*
                     * VALOR_COBRANCA_ATUAL_V2
                     *
                     * Se o participante mudou para/de
                     * Visitante antes de pagar, não
                     * reutilizamos uma cobrança pendente
                     * com o valor antigo.
                     */
                    $valorBaseEsperado =
                        round(
                            (float) (
                                $pagamento["valor"]
                                ?? 0
                            ),
                            2
                        );

                    $repassarTaxaEsperada =
                        (int) (
                            $pagamento[
                                "repassarTaxaAsaasEvento"
                            ]
                            ?? 0
                        ) === 1;

                    $valorEsperado =
                        $repassarTaxaEsperada
                            ? $this->calcularValorComTaxa(
                                $valorBaseEsperado,
                                $billingType
                            )
                            : $valorBaseEsperado;

                    $valorAtualAsaas =
                        round(
                            (float) (
                                $cobrancaAtual["value"]
                                ?? 0
                            ),
                            2
                        );

                    if (
                        abs(
                            $valorAtualAsaas
                            - $valorEsperado
                        ) < 0.01
                    ) {
                        return $this
                            ->persistirRetornoAsaas(
                                $pagamento,
                                $forma,
                                $cobrancaAtual
                            );
                    }
                }

                if (!$this->cobrancaFoiEncerrada($statusAtual)) {
                    $this->asaas->excluirCobranca($idAsaasAtual);
                }
            }
        }

        /*
         * A referência nova usa o código interno aleatório, e não somente o id.
         * Isso evita recuperar uma cobrança de outro banco, localhost ou evento.
         */
        $cobrancaRecuperada = $this->asaas->localizarCobrancaPorReferencia($referenciaExterna);

        if ($cobrancaRecuperada === null) {
            $legada = $this->asaas->localizarCobrancaPorReferencia($referenciaLegada);

            if (
                $legada !== null
                && $this->cobrancaPertenceAoPagamento(
                    $legada,
                    $pagamento,
                    $referenciaExterna,
                    $referenciaLegada
                )
            ) {
                $cobrancaRecuperada = $legada;
            }
        }

        if ($cobrancaRecuperada !== null) {
            $billingRecuperado = strtoupper((string) ($cobrancaRecuperada["billingType"] ?? ""));
            $statusRecuperado = strtoupper((string) ($cobrancaRecuperada["status"] ?? ""));
            $formaRecuperada = $this->formaLocal($billingRecuperado);

            if ($this->cobrancaFoiPaga($statusRecuperado)) {
                return $this->persistirRetornoAsaas(
                    $pagamento,
                    $formaRecuperada !== "NaoDefinido" ? $formaRecuperada : $forma,
                    $cobrancaRecuperada
                );
            }

            if ($statusLocalAtual === "Pago") {
                throw new RuntimeException(
                    "Este pagamento já está marcado como pago e não pode receber uma nova cobrança."
                );
            }

                        if (
                $billingRecuperado === $billingType
                && !$this->cobrancaFoiEncerrada(
                    $statusRecuperado
                )
            ) {
                $valorBaseEsperado =
                    round(
                        (float) (
                            $pagamento["valor"]
                            ?? 0
                        ),
                        2
                    );

                $repassarTaxaEsperada =
                    (int) (
                        $pagamento[
                            "repassarTaxaAsaasEvento"
                        ]
                        ?? 0
                    ) === 1;

                $valorEsperado =
                    $repassarTaxaEsperada
                        ? $this->calcularValorComTaxa(
                            $valorBaseEsperado,
                            $billingType
                        )
                        : $valorBaseEsperado;

                $valorRecuperadoAsaas =
                    round(
                        (float) (
                            $cobrancaRecuperada["value"]
                            ?? 0
                        ),
                        2
                    );

                if (
                    abs(
                        $valorRecuperadoAsaas
                        - $valorEsperado
                    ) < 0.01
                ) {
                    return $this
                        ->persistirRetornoAsaas(
                            $pagamento,
                            $forma,
                            $cobrancaRecuperada
                        );
                }
            }

            $idRecuperado = trim((string) ($cobrancaRecuperada["id"] ?? ""));

            if ($idRecuperado !== "" && !$this->cobrancaFoiEncerrada($statusRecuperado)) {
                $this->asaas->excluirCobranca($idRecuperado);
            }
        }

        if ($statusLocalAtual === "Pago") {
            throw new RuntimeException("Não é possível gerar uma nova cobrança para um pagamento já recebido.");
        }

        $clienteId = $this->obterClienteAsaas($pagamento);
        $vencimento = $this->calcularVencimento($pagamento, $forma);
        $valorBase = round((float) $pagamento["valor"], 2);
        $repassarTaxa = (int) ($pagamento["repassarTaxaAsaasEvento"] ?? 0) === 1;
        $valorCobranca = $repassarTaxa
            ? $this->calcularValorComTaxa($valorBase, $billingType)
            : $valorBase;

        $cobranca = $this->asaas->criarCobranca([
            "customer" => $clienteId,
            "billingType" => $billingType,
            "value" => $valorCobranca,
            "dueDate" => $vencimento,
            "description" => (string) ($pagamento["descricao"] ?? "Inscrição de evento"),
            "externalReference" => $referenciaExterna
        ]);

        return $this->persistirRetornoAsaas($pagamento, $forma, $cobranca);
    }


    /**
     * Processa o pagamento de uma inscrição
     * utilizando cartão de crédito.
     *
     * Número, validade e CVV nunca são persistidos.
     */
    public function pagarCobrancaCartao(
        int $idPagamento,
        array $cartao,
        array $titular
    ): array {
        if ($idPagamento <= 0) {
            throw new InvalidArgumentException(
                "Pagamento inválido."
            );
        }

        $pagamento =
            $this->pagamentos->buscar(
                $idPagamento
            );

        if (!$pagamento) {
            throw new RuntimeException(
                "Pagamento não encontrado."
            );
        }

        if (
            (string) (
                $pagamento["status"]
                ?? ""
            ) === "Pago"
        ) {
            return $pagamento;
        }

        if (
            in_array(
                (string) (
                    $pagamento["status"]
                    ?? ""
                ),
                [
                    "Cancelado",
                    "Estornado"
                ],
                true
            )
        ) {
            throw new RuntimeException(
                "Este pagamento não pode "
                . "mais ser processado."
            );
        }

        /*
         * Cria ou recupera a cobrança
         * CREDIT_CARD no Asaas.
         */
        $cobrancaLocal =
            $this->gerarOuRecuperarCobranca(
                $idPagamento,
                "Cartao"
            );

        $idAsaas = trim(
            (string) (
                $cobrancaLocal[
                    "asaasPaymentId"
                ]
                ?? ""
            )
        );

        if ($idAsaas === "") {
            throw new RuntimeException(
                "A cobrança do cartão "
                . "não foi identificada."
            );
        }

        $cobrancaAntes =
            $this->asaas
                ->consultarCobranca(
                    $idAsaas
                );

        $statusAntes = strtoupper(
            trim(
                (string) (
                    $cobrancaAntes[
                        "status"
                    ]
                    ?? ""
                )
            )
        );

        if (
            $this->cobrancaFoiPaga(
                $statusAntes
            )
        ) {
            return $this
                ->persistirRetornoAsaas(
                    $pagamento,
                    "Cartao",
                    $cobrancaAntes
                );
        }

        if (
            $this->cobrancaFoiEncerrada(
                $statusAntes
            )
        ) {
            throw new RuntimeException(
                "A cobrança não aceita "
                . "mais pagamento."
            );
        }

        /*
         * Dados sensíveis são usados somente
         * nesta chamada.
         */
        try {
            $this->asaas
                ->pagarCobrancaComCartao(
                    $idAsaas,
                    $cartao,
                    $titular
                );
        } finally {
            unset(
                $cartao,
                $titular
            );
        }

        /*
         * Consulta novamente a cobrança.
         * Somente esta resposta é persistida.
         */
        $cobrancaDepois =
            $this->asaas
                ->consultarCobranca(
                    $idAsaas
                );

        $pagamentoAtual =
            $this->pagamentos->buscar(
                $idPagamento
            ) ?? $pagamento;

        return $this
            ->persistirRetornoAsaas(
                $pagamentoAtual,
                "Cartao",
                $cobrancaDepois
            );
    }

    public function ambiente(): string
    {
        return $this->asaas->ambiente();
    }

    public function ultimaReferenciaExterna(): ?string
    {
        return $this->ultimaReferenciaExterna;
    }

    public function sincronizarCobranca(int $idPagamento): array
    {
        $pagamento = $this->pagamentos->buscar($idPagamento);

        if (!$pagamento) {
            throw new RuntimeException("Pagamento não encontrado.");
        }

        $idAsaas = trim((string) ($pagamento["asaasPaymentId"] ?? ""));

        if ($idAsaas === "") {
            throw new RuntimeException("Este pagamento ainda não possui cobrança no Asaas.");
        }

        $cobranca = $this->asaas->consultarCobranca($idAsaas);
        $forma = $this->formaLocal((string) ($cobranca["billingType"] ?? ""));

        return $this->persistirRetornoAsaas($pagamento, $forma, $cobranca);
    }

    private function obterClienteAsaas(array $pagamento): string
    {
        $idUsuario = (int) ($pagamento["idUsuario"] ?? 0);

        if ($idUsuario <= 0) {
            throw new RuntimeException("Usuário da inscrição não encontrado.");
        }

        $clienteExistente = trim((string) ($pagamento["asaasCustomerIdUsuario"] ?? ""));
        $cpf = preg_replace('/\D+/', '', (string) ($pagamento["cpf"] ?? "")) ?? "";

        $cliente = $this->asaas->obterOuCriarCliente([
            "name" => (string) ($pagamento["participante"] ?? ""),
            "cpfCnpj" => $cpf,
            "email" => (string) ($pagamento["email"] ?? ""),
            "mobilePhone" => (string) ($pagamento["telefone"] ?? ""),
            "externalReference" => $this->referenciaCliente($idUsuario)
        ], $clienteExistente !== "" ? $clienteExistente : null);

        $clienteId = trim((string) ($cliente["id"] ?? ""));

        if ($clienteId === "") {
            throw new RuntimeException("Não foi possível identificar o cliente no Asaas.");
        }

        if ($clienteExistente !== $clienteId) {
            $stmt = $this->db->prepare("
                UPDATE usuarios
                SET asaasCustomerId = :asaasCustomerId
                WHERE id = :idUsuario
            ");
            $stmt->execute([
                ":asaasCustomerId" => $clienteId,
                ":idUsuario" => $idUsuario
            ]);
        }

        return $clienteId;
    }

    private function persistirRetornoAsaas(
        array $pagamento,
        string $forma,
        array $cobranca
    ): array {

        /*
         * SEGURANÇA:
         * Remove qualquer dado sensível de cartão
         * antes de qualquer persistência local.
         */
        unset(
            $cobranca["creditCard"],
            $cobranca[
                "creditCardHolderInfo"
            ],
            $cobranca["creditCardToken"]
        );

        $idPagamento = (int) ($pagamento["idPagamento"] ?? 0);
        $idAsaas = trim((string) ($cobranca["id"] ?? ""));

        if ($idPagamento <= 0 || $idAsaas === "") {
            throw new RuntimeException("A cobrança retornada pelo Asaas está incompleta.");
        }

        $statusAsaas = strtoupper(trim((string) ($cobranca["status"] ?? "PENDING")));
        $statusLocal = $this->statusLocal($statusAsaas);
        $cobrancaEncerrada = in_array($statusAsaas, ["DELETED", "REFUNDED"], true);

        $qrCode = null;
        $copiaCola = null;
        $expiracaoPix = null;
        $linhaDigitavel = null;

        if (
            $forma === "PIX"
            && !$cobrancaEncerrada
            && $statusLocal !== "Pago"
        ) {
            $pix = $this->asaas->obterQrCodePix($idAsaas);
            $qrCode = $this->normalizarQrCode((string) ($pix["encodedImage"] ?? ""));
            $copiaCola = $this->textoNulo($pix["payload"] ?? null);
            $expiracaoPix = $this->normalizarDataHoraAsaas($pix["expirationDate"] ?? null);
        }

        if (
            $forma === "Boleto"
            && !$cobrancaEncerrada
            && $statusLocal !== "Pago"
        ) {
            $boleto = $this->asaas->obterLinhaDigitavel($idAsaas);
            $linhaDigitavel = $this->textoNulo(
                $boleto["identificationField"]
                ?? $boleto["digitableLine"]
                ?? null
            );
        }
        $dataPagamento = null;

        if ($statusLocal === "Pago") {
            $dataPagamento = $this->normalizarDataHoraAsaas(
                $cobranca["paymentDate"]
                ?? $cobranca["confirmedDate"]
                ?? $cobranca["clientPaymentDate"]
                ?? null
            ) ?? date("Y-m-d H:i:s");
        }

        $valorBase = round((float) ($pagamento["valor"] ?? 0), 2);
        $valorCobrancaAsaas = round(
            (float) ($cobranca["value"] ?? $valorBase),
            2
        );
        $valorTaxaRepassada = max(0, round($valorCobrancaAsaas - $valorBase, 2));

        $dadosPersistencia = [
            "formaPagamento" => $forma,
            "valorCobrancaAsaas" => $valorCobrancaAsaas,
            "valorTaxaRepassada" => $valorTaxaRepassada,
            "integracao" => "Asaas",
            "asaasPaymentId" => $idAsaas,
            "asaasCustomerId" => $this->textoNulo($cobranca["customer"] ?? null),
            "asaasStatus" => $statusAsaas,
            "invoiceUrl" => $this->urlSegura($cobranca["invoiceUrl"] ?? null),
            "bankSlipUrl" => $this->urlSegura($cobranca["bankSlipUrl"] ?? null),
            "boletoLinhaDigitavel" => $linhaDigitavel,
            "pixQrCode" => $qrCode,
            "pixCopiaCola" => $copiaCola,
            "pixExpiracao" => $expiracaoPix,
            "dataVencimento" => $this->normalizarData($cobranca["dueDate"] ?? null),
            "asaasPayload" => json_encode(
                $cobranca,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            ),
            "status" => $statusLocal,
            "dataPagamento" => $dataPagamento
        ];

        $this->pagamentos->atualizarIntegracaoAsaas($idPagamento, $dadosPersistencia);

        return $this->pagamentos->buscar($idPagamento) ?? $dadosPersistencia;
    }

    private function referenciaPagamento(array $pagamento): string
    {
        $codigo = trim((string) ($pagamento["codigo"] ?? ""));

        if ($codigo === "") {
            $codigo = "id-" . (int) ($pagamento["idPagamento"] ?? 0);
        }

        $codigo = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($codigo)) ?? "pagamento";
        $codigo = trim($codigo, '-');

        return date("Y")
            . $this->asaas->prefixoReferencia()
            . "-pag-"
            . substr($codigo, 0, 60);
    }

    private function referenciaCliente(int $idUsuario): string
    {
        return date("Y")
            . $this->asaas->prefixoReferencia()
            . "-user-"
            . $idUsuario;
    }

    private function cobrancaPertenceAoPagamento(
        array $cobranca,
        array $pagamento,
        string $referenciaAtual,
        string $referenciaLegada
    ): bool {
        $referenciaCobranca = trim((string) ($cobranca["externalReference"] ?? ""));
        $valorCobranca = round((float) ($cobranca["value"] ?? 0), 2);
        $valorPagamento = round(
            (float) ($pagamento["valorCobrancaAsaas"] ?? $pagamento["valor"] ?? 0),
            2
        );

        if ($referenciaCobranca === $referenciaAtual) {
            return true;
        }

        if ($valorCobranca <= 0 || abs($valorCobranca - $valorPagamento) > 0.009) {
            return false;
        }

        /*
         * Referências antigas eram formadas apenas pelo id numérico e podem
         * colidir. Elas somente são aceitas quando o CPF do cliente também
         * corresponde ao participante atual.
         */
        if ($referenciaCobranca !== "" && $referenciaCobranca !== $referenciaLegada) {
            return false;
        }

        $clienteId = trim((string) ($cobranca["customer"] ?? ""));
        $cpfLocal = preg_replace('/\D+/', '', (string) ($pagamento["cpf"] ?? "")) ?? "";

        if ($clienteId === "" || $cpfLocal === "") {
            return false;
        }

        $cliente = $this->asaas->consultarClienteOuNull($clienteId);

        if ($cliente === null) {
            return false;
        }

        $cpfAsaas = preg_replace('/\D+/', '', (string) ($cliente["cpfCnpj"] ?? "")) ?? "";

        return $cpfAsaas !== "" && hash_equals($cpfLocal, $cpfAsaas);
    }

    private function desvincularCobrancaLocal(array $pagamento): void
    {
        $idPagamento = (int) ($pagamento["idPagamento"] ?? 0);
        $idInscricao = (int) ($pagamento["idInscricao"] ?? 0);

        if ($idPagamento <= 0) {
            return;
        }

        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                UPDATE pagamentos
                SET
                    formaPagamento = 'NaoDefinido',
                    status = 'Pendente',
                    dataPagamento = NULL,
                    integracao = 'Manual',
                    asaasPaymentId = NULL,
                    asaasCustomerId = NULL,
                    asaasStatus = NULL,
                    invoiceUrl = NULL,
                    bankSlipUrl = NULL,
                    boletoLinhaDigitavel = NULL,
                    pixQrCode = NULL,
                    pixCopiaCola = NULL,
                    pixExpiracao = NULL,
                    asaasPayload = NULL,
                    asaasAtualizadoEm = NULL
                WHERE idPagamento = :idPagamento
            ");
            $stmt->execute([":idPagamento" => $idPagamento]);

            if ($idInscricao > 0) {
                $stmtInscricao = $this->db->prepare("
                    UPDATE inscricoes
                    SET
                        status = 'Pendente',
                        pagamento = 'Pendente',
                        valor_pago = 0,
                        forma_pagamento = NULL
                    WHERE idInscricao = :idInscricao
                ");
                $stmtInscricao->execute([":idInscricao" => $idInscricao]);
            }

            $this->db->commit();
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $erro;
        }
    }

    /**
     * Calcula o valor bruto necessário para que o valor-base do evento seja
     * preservado após o desconto da tarifa Asaas.
     */
        /**
     * Calcula o valor bruto necessário para preservar
     * o valor-base do evento após a tarifa Asaas.
     *
     * Tabela de taxas:
     *
     * BOLETO:
     * R$ 1,99 por boleto pago.
     *
     * PIX:
     * R$ 1,99 por cobrança recebida.
     *
     * CARTÃO DE CRÉDITO:
     * 1x       = 2,99% + R$ 0,49
     * 2 a 6x   = 3,49% + R$ 0,49
     * 7 a 12x  = 3,99% + R$ 0,49
     * 13 a 21x = 4,29% + R$ 0,49
     *
     * CARTÃO DE DÉBITO:
     * 1,89% + R$ 0,35.
     *
     * O terceiro argumento já deixa a regra pronta
     * para cartão parcelado. O checkout atual chama
     * este método sem esse argumento e, portanto,
     * utiliza 1 parcela.
     */
    private function calcularValorComTaxa(
        float $valorBase,
        string $billingType,
        int $parcelas = 1
    ): float {
        $valorBase =
            round(
                $valorBase,
                2
            );

        if ($valorBase <= 0) {
            throw new InvalidArgumentException(
                "O valor-base da cobrança "
                . "deve ser maior que zero."
            );
        }

        $billingType =
            strtoupper(
                trim($billingType)
            );

        $parcelas =
            max(
                1,
                $parcelas
            );

        /*
         * Taxas exclusivamente fixas.
         */
        if (
            $billingType === "BOLETO"
            || $billingType === "PIX"
        ) {
            return
                ceil(
                    (
                        $valorBase
                        + 1.99
                    )
                    * 100
                    - 0.000001
                )
                / 100;
        }

        /*
         * Cartão de crédito.
         */
        if (
            $billingType === "CREDIT_CARD"
            || $billingType === "CARTAO"
            || $billingType === "CARTÃO"
        ) {
            if ($parcelas <= 1) {
                $percentual = 2.99;
            } elseif ($parcelas <= 6) {
                $percentual = 3.49;
            } elseif ($parcelas <= 12) {
                $percentual = 3.99;
            } elseif ($parcelas <= 21) {
                $percentual = 4.29;
            } else {
                throw new InvalidArgumentException(
                    "O cartão de crédito aceita "
                    . "no máximo 21 parcelas "
                    . "para o cálculo da taxa."
                );
            }

            $taxaFixa = 0.49;

            /*
             * Fazemos gross-up.
             *
             * Exemplo:
             *
             * valor líquido desejado = 100,00
             * taxa = 2,99% + 0,49
             *
             * bruto =
             * (100,00 + 0,49) / (1 - 0,0299)
             *
             * Assim, após a tarifa percentual e
             * fixa, o evento preserva o valor-base.
             */
            $divisor =
                1
                - (
                    $percentual
                    / 100
                );

            $valorBruto =
                (
                    $valorBase
                    + $taxaFixa
                )
                / $divisor;

            return
                ceil(
                    $valorBruto
                    * 100
                    - 0.000001
                )
                / 100;
        }

        /*
         * Cartão de débito.
         *
         * A modalidade fica preparada na regra,
         * embora o checkout atual não exponha
         * cartão de débito como opção.
         */
        if (
            $billingType === "DEBIT_CARD"
            || $billingType === "DEBIT"
            || $billingType === "DEBITO"
            || $billingType === "DÉBITO"
        ) {
            $percentual = 1.89;
            $taxaFixa = 0.35;

            $divisor =
                1
                - (
                    $percentual
                    / 100
                );

            $valorBruto =
                (
                    $valorBase
                    + $taxaFixa
                )
                / $divisor;

            return
                ceil(
                    $valorBruto
                    * 100
                    - 0.000001
                )
                / 100;
        }

        throw new InvalidArgumentException(
            "Não existe uma taxa Asaas "
            . "configurada para a forma "
            . "de pagamento "
            . $billingType
            . "."
        );
    }

    private function calcularBoletoComTaxa(float $valorBase, array $taxa): float
    {
        $tarifa = $this->valorComDescontoVigente(
            (float) ($taxa["defaultValue"] ?? 0),
            (float) ($taxa["discountValue"] ?? 0),
            $taxa["expirationDate"] ?? null
        );

        return $valorBase + max(0, $tarifa);
    }

    private function calcularCartaoComTaxa(float $valorBase, array $taxa): float
    {
        $tarifaFixa = max(0, (float) ($taxa["operationValue"] ?? 0));
        $percentual = $this->valorComDescontoVigente(
            (float) ($taxa["oneInstallmentPercentage"] ?? 0),
            (float) ($taxa["discountOneInstallmentPercentage"] ?? 0),
            $taxa["discountExpiration"] ?? null
        );

        $percentual = max(0, $percentual);

        if ($percentual >= 100) {
            throw new RuntimeException("A tarifa de cartão retornada pelo Asaas é inválida.");
        }

        return ($valorBase + $tarifaFixa) / (1 - ($percentual / 100));
    }

    private function calcularPixComTaxa(float $valorBase, array $taxa): float
    {
        $gratuitos = max(0, (int) ($taxa["monthlyCreditsWithoutFee"] ?? 0));
        $recebidos = max(0, (int) ($taxa["creditsReceivedOfCurrentMonth"] ?? 0));

        if ($gratuitos > $recebidos) {
            return $valorBase;
        }

        $tarifaFixa = $this->valorComDescontoVigente(
            (float) ($taxa["fixedFeeValue"] ?? 0),
            (float) ($taxa["fixedFeeValueWithDiscount"] ?? 0),
            $taxa["discountExpiration"] ?? null
        );

        if ($tarifaFixa > 0) {
            return $valorBase + $tarifaFixa;
        }

        $percentual = max(0, (float) ($taxa["percentageFee"] ?? 0));
        $minimo = max(0, (float) ($taxa["minimumFeeValue"] ?? 0));
        $maximo = max(0, (float) ($taxa["maximumFeeValue"] ?? 0));

        if ($percentual <= 0 && $minimo <= 0) {
            return $valorBase;
        }

        $valorBruto = $valorBase;

        for ($tentativa = 0; $tentativa < 12; $tentativa++) {
            $tarifa = $valorBruto * ($percentual / 100);
            $tarifa = max($minimo, $tarifa);

            if ($maximo > 0) {
                $tarifa = min($maximo, $tarifa);
            }

            $novoValor = $valorBase + $tarifa;

            if (abs($novoValor - $valorBruto) < 0.001) {
                $valorBruto = $novoValor;
                break;
            }

            $valorBruto = $novoValor;
        }

        return $valorBruto;
    }

    private function valorComDescontoVigente(
        float $valorPadrao,
        float $valorDesconto,
        mixed $expiracao
    ): float {
        if ($valorDesconto <= 0 || !$this->descontoVigente($expiracao)) {
            return $valorPadrao;
        }

        return $valorDesconto;
    }

    private function descontoVigente(mixed $expiracao): bool
    {
        $texto = trim((string) ($expiracao ?? ""));

        if ($texto === "") {
            return false;
        }

        try {
            $fuso = new DateTimeZone("America/Sao_Paulo");
            return new DateTimeImmutable($texto, $fuso) >= new DateTimeImmutable("now", $fuso);
        } catch (Throwable) {
            return false;
        }
    }

    private function arredondarParaCimaCentavos(float $valor): float
    {
        return ceil(($valor - 0.0000001) * 100) / 100;
    }

    private function calcularVencimento(array $pagamento, string $forma): string
    {
        $fuso = new DateTimeZone("America/Sao_Paulo");
        $agora = new DateTimeImmutable("now", $fuso);
        $dataEventoTexto = trim((string) ($pagamento["dataInicioEvento"] ?? ""));
        $dataEvento = DateTimeImmutable::createFromFormat(
            "!Y-m-d",
            $dataEventoTexto,
            $fuso
        );

        if (!$dataEvento instanceof DateTimeImmutable) {
            throw new RuntimeException("A data de início do evento é inválida.");
        }

        $limiteMaximo = $dataEvento
            ->modify("-1 day")
            ->setTime(23, 59, 59);

        $limiteConfiguradoTexto = trim((string) ($pagamento["pagamentoFimEvento"] ?? ""));
        $limitePagamento = null;

        if ($limiteConfiguradoTexto !== "") {
            $limitePagamento = DateTimeImmutable::createFromFormat(
                "!Y-m-d H:i:s",
                $limiteConfiguradoTexto,
                $fuso
            );

            if (!$limitePagamento instanceof DateTimeImmutable) {
                throw new RuntimeException("O limite de pagamento configurado no evento é inválido.");
            }
        } else {
            // Compatibilidade com eventos antigos: usa o último instante do dia anterior.
            $limitePagamento = $limiteMaximo;
        }

        if ($limitePagamento > $limiteMaximo) {
            throw new RuntimeException(
                "O limite de pagamento do evento deve ser, no máximo, até "
                . $limiteMaximo->format("d/m/Y H:i")
                . "."
            );
        }

        if ($agora > $limitePagamento) {
            throw new RuntimeException(
                "O prazo para pagamentos foi encerrado em "
                . $limitePagamento->format("d/m/Y H:i")
                . "."
            );
        }

        return $limitePagamento->format("Y-m-d");
    }

    private function cobrancaFoiPaga(string $statusAsaas): bool
    {
        return in_array(
            strtoupper(trim($statusAsaas)),
            ["RECEIVED", "CONFIRMED", "RECEIVED_IN_CASH"],
            true
        );
    }

    private function cobrancaFoiEncerrada(string $statusAsaas): bool
    {
        return in_array(
            strtoupper(trim($statusAsaas)),
            ["DELETED", "REFUNDED"],
            true
        );
    }

    private function billingType(string $forma): string
    {
        return match ($forma) {
            "PIX" => "PIX",
            "Boleto" => "BOLETO",
            "Cartao" => "CREDIT_CARD",
            default => throw new InvalidArgumentException("Forma de pagamento inválida.")
        };
    }

    private function formaLocal(string $billingType): string
    {
        return match (strtoupper(trim($billingType))) {
            "PIX" => "PIX",
            "BOLETO" => "Boleto",
            "CREDIT_CARD" => "Cartao",
            default => "NaoDefinido"
        };
    }

    private function statusLocal(string $statusAsaas): string
    {
        return match (strtoupper($statusAsaas)) {
            "RECEIVED", "CONFIRMED", "RECEIVED_IN_CASH" => "Pago",
            "REFUNDED" => "Estornado",
            "DELETED" => "Cancelado",
            "OVERDUE" => "Vencido",
            default => "Pendente"
        };
    }

    private function normalizarQrCode(string $imagem): ?string
    {
        $imagem = trim($imagem);

        if ($imagem === "") {
            return null;
        }

        if (str_starts_with($imagem, "data:image/")) {
            return $imagem;
        }

        return "data:image/png;base64," . $imagem;
    }

    private function normalizarData(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ""));

        if ($texto === "") {
            return null;
        }

        try {
            return (new DateTimeImmutable($texto))->format("Y-m-d");
        } catch (Throwable) {
            return null;
        }
    }

    private function normalizarDataHoraAsaas(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ""));

        if ($texto === "") {
            return null;
        }

        try {
            return (new DateTimeImmutable($texto))->format("Y-m-d H:i:s");
        } catch (Throwable) {
            return null;
        }
    }

    private function textoNulo(mixed $valor): ?string
    {
        $texto = trim((string) ($valor ?? ""));
        return $texto === "" ? null : $texto;
    }

    private function urlSegura(mixed $valor): ?string
    {
        $url = trim((string) ($valor ?? ""));

        if ($url === "" || !filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        $esquema = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        return in_array($esquema, ["http", "https"], true) ? $url : null;
    }
}
