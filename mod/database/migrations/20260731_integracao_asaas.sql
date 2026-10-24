-- ============================================================================
-- Integração de pagamentos com o Asaas
-- Data: 31/07/2026
-- Execute uma única vez e faça backup antes da aplicação.
-- ============================================================================

ALTER TABLE usuarios
    ADD COLUMN asaasCustomerId VARCHAR(50) NULL AFTER idComunidade,
    ADD UNIQUE KEY uk_usuarios_asaas_customer (asaasCustomerId);

ALTER TABLE pagamentos
    MODIFY COLUMN formaPagamento ENUM(
        'NaoDefinido',
        'PIX',
        'Cartao',
        'Boleto',
        'Dinheiro',
        'Transferencia'
    ) NOT NULL DEFAULT 'NaoDefinido',
    ADD COLUMN integracao ENUM('Manual', 'Asaas') NOT NULL DEFAULT 'Manual' AFTER formaPagamento,
    ADD COLUMN asaasPaymentId VARCHAR(60) NULL AFTER integracao,
    ADD COLUMN asaasCustomerId VARCHAR(60) NULL AFTER asaasPaymentId,
    ADD COLUMN asaasStatus VARCHAR(60) NULL AFTER asaasCustomerId,
    ADD COLUMN invoiceUrl VARCHAR(500) NULL AFTER asaasStatus,
    ADD COLUMN bankSlipUrl VARCHAR(500) NULL AFTER invoiceUrl,
    ADD COLUMN boletoLinhaDigitavel VARCHAR(150) NULL AFTER bankSlipUrl,
    ADD COLUMN pixQrCode MEDIUMTEXT NULL AFTER boletoLinhaDigitavel,
    ADD COLUMN pixCopiaCola TEXT NULL AFTER pixQrCode,
    ADD COLUMN pixExpiracao DATETIME NULL AFTER pixCopiaCola,
    ADD COLUMN asaasPayload LONGTEXT NULL AFTER pixExpiracao,
    ADD COLUMN asaasAtualizadoEm DATETIME NULL AFTER asaasPayload,
    ADD UNIQUE KEY uk_pagamentos_asaas_payment (asaasPaymentId),
    ADD KEY idx_pagamentos_integracao (integracao),
    ADD KEY idx_pagamentos_asaas_status (asaasStatus);

-- Pagamentos pendentes do fluxo antigo devem ter o meio confirmado novamente.
UPDATE pagamentos
SET formaPagamento = 'NaoDefinido'
WHERE status = 'Pendente'
  AND dataPagamento IS NULL
  AND idInscricao IS NOT NULL;

CREATE TABLE asaas_webhook_eventos (
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
