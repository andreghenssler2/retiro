-- Execute esta migração UMA VEZ.
-- Ela mantém a coluna "ativo" com o comportamento atual:
--   ativo = 1 -> configuração de Produção
--   ativo = 0 -> configuração de Sandbox
--
-- A nova coluna "selecionado" define qual configuração está em uso.

ALTER TABLE email_config
    ADD COLUMN selecionado TINYINT(1) NOT NULL DEFAULT 0
    AFTER ativo;

-- Garante que nenhuma configuração fique marcada em duplicidade.
UPDATE email_config
SET selecionado = 0;

-- Na primeira execução, mantém Produção como ambiente ativo,
-- usando a configuração de produção mais recente.
UPDATE email_config
SET selecionado = 1
WHERE idEmailConfig = (
    SELECT idSelecionado
    FROM (
        SELECT idEmailConfig AS idSelecionado
        FROM email_config
        WHERE ativo = 1
        ORDER BY idEmailConfig DESC
        LIMIT 1
    ) AS config_producao
);

-- Se não existir configuração de Produção, seleciona a mais recente.
UPDATE email_config
SET selecionado = 1
WHERE idEmailConfig = (
    SELECT idSelecionado
    FROM (
        SELECT idEmailConfig AS idSelecionado
        FROM email_config
        ORDER BY idEmailConfig DESC
        LIMIT 1
    ) AS config_recente
)
AND NOT EXISTS (
    SELECT 1
    FROM (
        SELECT selecionado
        FROM email_config
        WHERE selecionado = 1
        LIMIT 1
    ) AS config_ativa
);
