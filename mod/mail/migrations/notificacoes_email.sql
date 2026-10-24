-- ============================================================
-- Notificações por e-mail: inscrições e pagamentos
-- Execute este arquivo UMA ÚNICA VEZ.
-- ============================================================

ALTER TABLE inscricoes
    ADD COLUMN email_inscricao_enviado_em DATETIME NULL;

ALTER TABLE pagamentos
    ADD COLUMN email_gerado_enviado_em DATETIME NULL,
    ADD COLUMN email_status_notificado VARCHAR(20) NULL,
    ADD COLUMN email_status_notificado_em DATETIME NULL;

-- Evita que registros antigos disparem e-mails após a instalação.
-- Somente novas inscrições/pagamentos ou futuras alterações
-- de status serão notificadas.

UPDATE inscricoes
SET email_inscricao_enviado_em = NOW()
WHERE email_inscricao_enviado_em IS NULL;

UPDATE pagamentos
SET
    email_gerado_enviado_em = NOW(),
    email_status_notificado = status,
    email_status_notificado_em = NOW()
WHERE email_gerado_enviado_em IS NULL
   OR email_status_notificado IS NULL;

CREATE INDEX idx_inscricoes_email_notificacao
    ON inscricoes (email_inscricao_enviado_em);

CREATE INDEX idx_pagamentos_email_gerado
    ON pagamentos (email_gerado_enviado_em);

CREATE INDEX idx_pagamentos_email_status
    ON pagamentos (email_status_notificado);
