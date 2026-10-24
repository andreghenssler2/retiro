-- ============================================================================
-- Reparo da estrutura de pagamentos, prazo e Asaas
-- Compatível com MariaDB/XAMPP e sem acesso ao information_schema.
-- Pode ser executado novamente: usa IF NOT EXISTS nas colunas e na tabela.
-- ============================================================================

ALTER TABLE eventos
    ADD COLUMN IF NOT EXISTS pagamento_fim DATETIME NULL;

ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS asaasCustomerId VARCHAR(50) NULL;

ALTER TABLE pagamentos
    MODIFY COLUMN formaPagamento ENUM(
        'NaoDefinido',
        'PIX',
        'Cartao',
        'Boleto',
        'Dinheiro',
        'Transferencia'
    ) NOT NULL DEFAULT 'NaoDefinido';

ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS integracao ENUM('Manual', 'Asaas') NOT NULL DEFAULT 'Manual';
ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS asaasPaymentId VARCHAR(60) NULL;
ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS asaasCustomerId VARCHAR(60) NULL;
ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS asaasStatus VARCHAR(60) NULL;
ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS invoiceUrl VARCHAR(500) NULL;
ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS bankSlipUrl VARCHAR(500) NULL;
ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS boletoLinhaDigitavel VARCHAR(150) NULL;
ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS pixQrCode MEDIUMTEXT NULL;
ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS pixCopiaCola TEXT NULL;
ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS pixExpiracao DATETIME NULL;
ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS asaasPayload LONGTEXT NULL;
ALTER TABLE pagamentos
    ADD COLUMN IF NOT EXISTS asaasAtualizadoEm DATETIME NULL;

CREATE TABLE IF NOT EXISTS asaas_webhook_eventos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    eventoId VARCHAR(100) NOT NULL,
    evento VARCHAR(100) NOT NULL,
    asaasPaymentId VARCHAR(60) DEFAULT NULL,
    payload LONGTEXT NOT NULL,
    recebidoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processadoEm DATETIME DEFAULT NULL,
    erro TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_asaas_webhook_evento (eventoId),
    KEY idx_asaas_webhook_pagamento (asaasPaymentId),
    KEY idx_asaas_webhook_processado (processadoEm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE eventos
SET pagamento_fim = TIMESTAMP(
        DATE_SUB(data_inicio, INTERVAL 1 DAY),
        '23:59:00'
    )
WHERE pagamento_fim IS NULL
  AND pagamento_obrigatorio = 1
  AND COALESCE(NULLIF(valor_inscricao, 0), valor, 0) > 0;

UPDATE pagamentos
SET formaPagamento = 'NaoDefinido'
WHERE status = 'Pendente'
  AND dataPagamento IS NULL
  AND idInscricao IS NOT NULL
  AND (formaPagamento IS NULL OR formaPagamento = '');

SELECT 'Estrutura de pagamentos e Asaas reparada.' AS resultado;
