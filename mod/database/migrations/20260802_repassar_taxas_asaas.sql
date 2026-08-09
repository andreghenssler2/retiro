-- Repassar tarifas do Asaas por evento.
-- Execute uma única vez no banco do projeto.

ALTER TABLE eventos
    ADD COLUMN repassar_taxa_asaas TINYINT(1) NOT NULL DEFAULT 0
    AFTER pagamento_obrigatorio;

ALTER TABLE pagamentos
    ADD COLUMN valorCobrancaAsaas DECIMAL(10,2) NULL
    AFTER valor,
    ADD COLUMN valorTaxaRepassada DECIMAL(10,2) NOT NULL DEFAULT 0.00
    AFTER valorCobrancaAsaas;

SELECT 'Estrutura para repasse de tarifas criada.' AS resultado;
