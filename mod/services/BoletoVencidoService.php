<?php

declare(strict_types=1);

/**
 * Processa boletos vencidos e cancela a inscrição após o período de tolerância.
 *
 * Regra adotada:
 * - no dia seguinte ao vencimento, o pagamento passa para Vencido;
 * - o participante ainda pode pagar durante três dias úteis completos;
 * - no dia útil seguinte ao fim da tolerância, a cobrança pendente é removida
 *   do Asaas e a inscrição é cancelada;
 * - sábados e domingos não são contados como dias úteis.
 */
class BoletoVencidoService
{
    public const DIAS_UTEIS_TOLERANCIA = 3;

    private PDO $db;
    private Pagamento $pagamentos;
    private AsaasService $asaas;
    private DateTimeZone $fuso;

    public function __construct(
        ?PDO $conexao = null,
        ?Pagamento $pagamentos = null,
        ?AsaasService $asaas = null
    ) {
        $this->db = $conexao ?? Database::connect();
        $this->pagamentos = $pagamentos ?? new Pagamento($this->db);
        $this->asaas = $asaas ?? new AsaasService();
        $this->fuso = new DateTimeZone("America/Sao_Paulo");
    }

    /**
     * @return array{
     *     processados:int,
     *     marcadosVencidos:int,
     *     inscricoesCanceladas:int,
     *     pagosSincronizados:int,
     *     estornadosSincronizados:int,
     *     erros:array<int, array{idPagamento:int,mensagem:string}>
     * }
     */
    public function processar(?DateTimeImmutable $referencia = null): array
    {
        $hoje = ($referencia ?? new DateTimeImmutable("today", $this->fuso))
            ->setTimezone($this->fuso)
            ->setTime(0, 0, 0);

        $resumo = [
            "processados" => 0,
            "marcadosVencidos" => 0,
            "inscricoesCanceladas" => 0,
            "pagosSincronizados" => 0,
            "estornadosSincronizados" => 0,
            "erros" => []
        ];

        foreach ($this->listarBoletosVencidos($hoje) as $pagamento) {
            $resumo["processados"]++;
            $idPagamento = (int) ($pagamento["idPagamento"] ?? 0);

            try {
                if ($idPagamento <= 0) {
                    throw new RuntimeException("Pagamento inválido encontrado no processamento.");
                }

                $vencimento = $this->dataVencimento($pagamento);
                $limite = $this->adicionarDiasUteis(
                    $vencimento,
                    self::DIAS_UTEIS_TOLERANCIA
                );

                $statusAsaas = strtoupper(trim((string) ($pagamento["asaasStatus"] ?? "")));
                $integrado = (string) ($pagamento["integracao"] ?? "Manual") === "Asaas"
                    && trim((string) ($pagamento["asaasPaymentId"] ?? "")) !== "";

                /*
                 * A data local já é suficiente para marcar o boleto como vencido.
                 * A consulta ao Asaas ocorre depois, para que uma indisponibilidade
                 * externa não esconda o vencimento no painel.
                 */
                if ((string) ($pagamento["status"] ?? "Pendente") !== "Vencido") {
                    $this->pagamentos->marcarComoVencido(
                        $idPagamento,
                        $statusAsaas !== "" ? $statusAsaas : "OVERDUE"
                    );
                    $resumo["marcadosVencidos"]++;
                }

                if ($integrado) {
                    $cobranca = $this->asaas->consultarCobranca(
                        (string) $pagamento["asaasPaymentId"]
                    );
                    $statusAsaas = strtoupper(trim((string) ($cobranca["status"] ?? $statusAsaas)));

                    if ($this->cobrancaPaga($statusAsaas)) {
                        $this->pagamentos->atualizarStatusPeloAsaas(
                            (string) $pagamento["asaasPaymentId"],
                            "Pago",
                            $statusAsaas,
                            $this->dataPagamentoAsaas($cobranca),
                            json_encode($cobranca, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null
                        );
                        $resumo["pagosSincronizados"]++;
                        continue;
                    }

                    if ($statusAsaas === "REFUNDED") {
                        $this->pagamentos->atualizarStatusPeloAsaas(
                            (string) $pagamento["asaasPaymentId"],
                            "Estornado",
                            $statusAsaas,
                            null,
                            json_encode($cobranca, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: null
                        );
                        $resumo["estornadosSincronizados"]++;
                        continue;
                    }
                }

                // O terceiro dia útil ainda é integralmente permitido.
                if ($hoje <= $limite) {
                    continue;
                }

                if ($integrado && $statusAsaas !== "DELETED") {
                    $excluido = $this->asaas->excluirCobranca(
                        (string) $pagamento["asaasPaymentId"]
                    );

                    if (!$excluido) {
                        throw new RuntimeException(
                            "O Asaas não confirmou a remoção do boleto vencido."
                        );
                    }

                    $statusAsaas = "DELETED";
                }

                $mensagem = sprintf(
                    "Inscrição cancelada automaticamente: boleto vencido em %s e não pago até %s, após %d dias úteis de tolerância.",
                    $vencimento->format("d/m/Y"),
                    $limite->format("d/m/Y"),
                    self::DIAS_UTEIS_TOLERANCIA
                );

                $this->pagamentos->cancelarInscricaoPorBoletoVencido(
                    $idPagamento,
                    $mensagem,
                    $statusAsaas !== "" ? $statusAsaas : null
                );
                $resumo["inscricoesCanceladas"]++;
            } catch (Throwable $erro) {
                $resumo["erros"][] = [
                    "idPagamento" => $idPagamento,
                    "mensagem" => $erro->getMessage()
                ];

                error_log(
                    "Erro ao processar boleto vencido #{$idPagamento}: "
                    . $erro->getMessage()
                );
            }
        }

        return $resumo;
    }

    public function limiteTolerancia(string|DateTimeInterface $vencimento): DateTimeImmutable
    {
        if ($vencimento instanceof DateTimeInterface) {
            $data = new DateTimeImmutable(
                $vencimento->format("Y-m-d"),
                $this->fuso
            );
        } else {
            $data = new DateTimeImmutable($vencimento, $this->fuso);
        }

        return $this->adicionarDiasUteis(
            $data->setTime(0, 0, 0),
            self::DIAS_UTEIS_TOLERANCIA
        );
    }

    /** @return array<int, array<string, mixed>> */
    private function listarBoletosVencidos(DateTimeImmutable $hoje): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.*,
                i.status AS statusInscricao
            FROM pagamentos p
            INNER JOIN inscricoes i
                ON i.idInscricao = p.idInscricao
            WHERE p.idInscricao IS NOT NULL
              AND p.formaPagamento = 'Boleto'
              AND p.status IN ('Pendente', 'Vencido')
              AND p.dataPagamento IS NULL
              AND p.dataVencimento IS NOT NULL
              AND p.dataVencimento < :hoje
              AND i.status <> 'Cancelada'
            ORDER BY p.dataVencimento ASC, p.idPagamento ASC
        ");
        $stmt->execute([":hoje" => $hoje->format("Y-m-d")]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function dataVencimento(array $pagamento): DateTimeImmutable
    {
        $data = trim((string) ($pagamento["dataVencimento"] ?? ""));

        if ($data === "" || str_starts_with($data, "0000-00-00")) {
            throw new RuntimeException("O boleto não possui data de vencimento válida.");
        }

        return (new DateTimeImmutable($data, $this->fuso))->setTime(0, 0, 0);
    }

    private function adicionarDiasUteis(
        DateTimeImmutable $data,
        int $quantidade
    ): DateTimeImmutable {
        $resultado = $data;
        $contados = 0;

        while ($contados < $quantidade) {
            $resultado = $resultado->modify("+1 day");
            $diaSemana = (int) $resultado->format("N");

            if ($diaSemana <= 5) {
                $contados++;
            }
        }

        return $resultado->setTime(23, 59, 59);
    }

    private function cobrancaPaga(string $statusAsaas): bool
    {
        return in_array(
            $statusAsaas,
            ["RECEIVED", "CONFIRMED", "RECEIVED_IN_CASH"],
            true
        );
    }

    private function dataPagamentoAsaas(array $cobranca): ?string
    {
        foreach (["paymentDate", "confirmedDate", "clientPaymentDate"] as $campo) {
            $valor = trim((string) ($cobranca[$campo] ?? ""));

            if ($valor !== "") {
                return $valor;
            }
        }

        return null;
    }
}
