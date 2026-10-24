<?php

declare(strict_types=1);

/**
 * Normaliza e persiste a data/hora exata dos eventos de pagamento do Asaas.
 *
 * O campo payment.paymentDate pode chegar apenas como data (AAAA-MM-DD).
 * Para recebidoEm, usamos preferencialmente o dateCreated do próprio webhook,
 * que possui precisão de data/hora. O valor é convertido para o timezone
 * oficial da aplicação.
 */
final class AsaasWebhookDataHoraService
{
    private const TIMEZONE_APLICACAO = 'America/Sao_Paulo';

    public static function dataHoraEvento(array $payload): ?string
    {
        return self::normalizarDataHora(
            $payload['dateCreated'] ?? null
        );
    }

    public static function normalizarDataHora(mixed $valor): ?string
    {
        if (!is_scalar($valor)) {
            return null;
        }

        $texto = trim((string) $valor);

        if ($texto === '') {
            return null;
        }

        try {
            $timezone = new DateTimeZone(self::TIMEZONE_APLICACAO);

            /*
             * Quando o valor possui offset/Z, DateTimeImmutable respeita
             * o timezone informado pelo próprio valor e depois convertemos
             * para o timezone da aplicação.
             *
             * Quando não há offset, interpretamos como horário local.
             */
            if (
                preg_match(
                    '/(?:Z|[+-]\d{2}:?\d{2})$/i',
                    $texto
                )
            ) {
                $data = new DateTimeImmutable($texto);
                $data = $data->setTimezone($timezone);
            } else {
                $data = new DateTimeImmutable(
                    $texto,
                    $timezone
                );
            }

            return $data->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Registra o instante exato do primeiro webhook que confirmou
     * recebimento, sem atrasar uma data/hora já registrada anteriormente.
     */
    public static function registrarRecebidoEm(
        PDO $db,
        string $asaasPaymentId,
        ?string $recebidoEm
    ): void {
        $asaasPaymentId = trim($asaasPaymentId);

        if ($asaasPaymentId === '' || $recebidoEm === null) {
            return;
        }

        $stmt = $db->prepare("
            UPDATE pagamentos
            SET recebidoEm = CASE
                WHEN recebidoEm IS NULL
                    THEN :recebidoNovo
                WHEN recebidoEm > :recebidoComparacao
                    THEN :recebidoSubstituto
                ELSE recebidoEm
            END
            WHERE asaasPaymentId = :asaasPaymentId
        ");

        $stmt->execute([
            ':recebidoNovo' => $recebidoEm,
            ':recebidoComparacao' => $recebidoEm,
            ':recebidoSubstituto' => $recebidoEm,
            ':asaasPaymentId' => $asaasPaymentId
        ]);
    }
}
