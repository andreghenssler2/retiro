-- ============================================================================
-- Prazo de pagamento por evento
-- Versao para MariaDB/phpMyAdmin sem acesso ao information_schema.
-- Nao utiliza PROCEDURE, DELIMITER, CALL, PREPARE ou information_schema.
-- ============================================================================

-- MariaDB aceita IF NOT EXISTS em ADD COLUMN.
ALTER TABLE eventos
    ADD COLUMN IF NOT EXISTS pagamento_fim DATETIME NULL AFTER inscricao_fim;

-- Eventos pagos antigos recebem, por padrao, o ultimo minuto do dia anterior.
-- O valor pode ser alterado depois em Admin > Eventos > Editar.
UPDATE eventos
SET pagamento_fim = TIMESTAMP(
        DATE_SUB(data_inicio, INTERVAL 1 DAY),
        '23:59:00'
    )
WHERE pagamento_fim IS NULL
  AND pagamento_obrigatorio = 1
  AND COALESCE(NULLIF(valor_inscricao, 0), valor, 0) > 0;

SELECT 'Migracao do prazo de pagamento concluida.' AS resultado;
