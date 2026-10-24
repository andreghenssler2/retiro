-- Configuração operacional e credenciais criptografadas da integração bancária.
-- Para instalações existentes, a própria página admin/configuracoes/bancario.php
-- verifica e adiciona as colunas ausentes usando SHOW COLUMNS.

CREATE TABLE IF NOT EXISTS configuracoes_bancarias (
    id TINYINT UNSIGNED NOT NULL,
    asaas_ativo TINYINT(1) NOT NULL DEFAULT 1,
    asaas_ambiente VARCHAR(20) NOT NULL DEFAULT 'sandbox',
    asaas_referencia_prefixo VARCHAR(60) DEFAULT NULL,
    asaas_sandbox_api_key_enc TEXT DEFAULT NULL,
    asaas_sandbox_webhook_token_enc TEXT DEFAULT NULL,
    asaas_producao_api_key_enc TEXT DEFAULT NULL,
    asaas_producao_webhook_token_enc TEXT DEFAULT NULL,
    atualizado_por INT DEFAULT NULL,
    criado_em DATETIME DEFAULT NULL,
    atualizado_em DATETIME DEFAULT NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO configuracoes_bancarias (
    id,
    asaas_ativo,
    asaas_ambiente,
    asaas_referencia_prefixo,
    criado_em,
    atualizado_em
) VALUES (
    1,
    1,
    'sandbox',
    NULL,
    NOW(),
    NOW()
);

SELECT 'Configuração bancária criada.' AS resultado;
