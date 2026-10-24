<?php

declare(strict_types=1);

/**
 * Regras puras do domínio de pagamentos.
 *
 * Não acessa banco, sessão ou serviços externos.
 * Mantê-las isoladas permite validar os comportamentos
 * críticos no CI sem depender de credenciais.
 */
final class PagamentoRegraService
{
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

    public static function statusValido(
        string $status
    ): bool {
        return in_array(
            $status,
            self::STATUS_PERMITIDOS,
            true
        );
    }

    public static function formaValida(
        string $forma
    ): bool {
        return in_array(
            $forma,
            self::FORMAS_PERMITIDAS,
            true
        );
    }

    public static function normalizarValor(
        mixed $valor
    ): float {
        if (
            is_int($valor)
            || is_float($valor)
        ) {
            return round(
                (float) $valor,
                2
            );
        }

        $texto = str_replace(
            [
                "R$",
                " ",
                "\xc2\xa0"
            ],
            "",
            trim(
                (string) $valor
            )
        );

        if (
            str_contains(
                $texto,
                ","
            )
        ) {
            $texto =
                str_replace(
                    ".",
                    "",
                    $texto
                );

            $texto =
                str_replace(
                    ",",
                    ".",
                    $texto
                );
        }

        return is_numeric($texto)
            ? round(
                (float) $texto,
                2
            )
            : 0.0;
    }

    public static function statusInscricao(
        string $statusPagamento
    ): string {
        return match ($statusPagamento) {
            "Pago" => "Confirmada",

            "Cancelado",
            "Estornado" => "Cancelada",

            default => "Pendente"
        };
    }

    public static function valorPago(
        string $statusPagamento,
        float $valor
    ): float {
        return $statusPagamento === "Pago"
            ? $valor
            : 0.0;
    }

    public static function deveCancelarPresenca(
        string $statusPagamento
    ): bool {
        return in_array(
            $statusPagamento,
            [
                "Cancelado",
                "Estornado"
            ],
            true
        );
    }

    public static function preservarInscricaoCancelada(
        string $statusPagamento
    ): bool {
        return $statusPagamento
            === "Vencido";
    }

    /**
     * PAYMENT_DELETED após a tolerância de boleto não
     * deve apagar o histórico financeiro de vencimento.
     */
    public static function statusPersistidoAsaas(
        string $novoStatus,
        string $statusAtual,
        string $formaPagamento
    ): string {
        if (
            $novoStatus === "Cancelado"
            && $statusAtual === "Vencido"
            && $formaPagamento === "Boleto"
        ) {
            return "Vencido";
        }

        return $novoStatus;
    }
}
