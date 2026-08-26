<?php

declare(strict_types=1);

/**
 * Regras puras do webhook Asaas.
 *
 * Nenhuma chamada HTTP ou acesso ao banco é executado aqui.
 */
final class AsaasWebhookRegraService
{
    public static function tokenValido(
        string $configurado,
        string $recebido
    ): bool {
        $configurado =
            trim($configurado);

        $recebido =
            trim($recebido);

        return
            $configurado !== ""
            && $recebido !== ""
            && hash_equals(
                $configurado,
                $recebido
            );
    }

    public static function statusLocal(
        string $evento,
        string $statusAsaas
    ): ?string {
        $evento =
            strtoupper(
                trim($evento)
            );

        $statusAsaas =
            strtoupper(
                trim($statusAsaas)
            );

        return match ($evento) {
            "PAYMENT_RECEIVED",
            "PAYMENT_CONFIRMED" =>
                "Pago",

            "PAYMENT_OVERDUE",
            "PAYMENT_BANK_SLIP_CANCELLED" =>
                "Vencido",

            "PAYMENT_REFUNDED" =>
                "Estornado",

            "PAYMENT_DELETED" =>
                "Cancelado",

            default =>
                match ($statusAsaas) {
                    "RECEIVED",
                    "CONFIRMED",
                    "RECEIVED_IN_CASH" =>
                        "Pago",

                    "REFUNDED" =>
                        "Estornado",

                    "DELETED" =>
                        "Cancelado",

                    "OVERDUE" =>
                        "Vencido",

                    "PENDING",
                    "AWAITING_RISK_ANALYSIS" =>
                        "Pendente",

                    default =>
                        null
                }
        };
    }
}
