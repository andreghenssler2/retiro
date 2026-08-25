<?php

declare(strict_types=1);

return [
    "descricao" =>
        "Garantir horário de recebimento dos pagamentos",

    "up" =>
        static function (
            PDO $db
        ): void {
            $stmt =
                $db->query("
                    SHOW COLUMNS
                    FROM pagamentos
                    LIKE 'recebidoEm'
                ");

            $existe =
                $stmt !== false
                && $stmt->fetch()
                    !== false;

            if (!$existe) {
                $db->exec("
                    ALTER TABLE pagamentos
                    ADD COLUMN recebidoEm
                        DATETIME NULL
                        DEFAULT NULL
                    AFTER dataPagamento
                ");
            }

            /*
             * Aproveita somente horários antigos
             * realmente registrados.
             *
             * 00:00:00 pode representar apenas a
             * data retornada pelo gateway, portanto
             * não é convertido em hora real.
             */
            $db->exec("
                UPDATE pagamentos
                SET recebidoEm = dataPagamento
                WHERE status = 'Pago'
                  AND recebidoEm IS NULL
                  AND dataPagamento IS NOT NULL
                  AND TIME(dataPagamento)
                        <> '00:00:00'
            ");
        }
];
