-- Adiciona o status Vencido aos pagamentos e ao espelho da inscrição.
-- Compatível com MySQL/MariaDB antigos: não usa IF NOT EXISTS,
-- information_schema, PROCEDURE, DELIMITER ou CALL.

ALTER TABLE pagamentos
    MODIFY COLUMN status ENUM(
        'Pendente',
        'Vencido',
        'Pago',
        'Cancelado',
        'Estornado'
    ) NOT NULL DEFAULT 'Pendente';

ALTER TABLE inscricoes
    MODIFY COLUMN pagamento ENUM(
        'Pendente',
        'Vencido',
        'Pago',
        'Cancelado',
        'Estornado'
    ) DEFAULT 'Pendente';

SELECT 'Status Vencido instalado com sucesso.' AS resultado;
