-- ============================================================================
-- Migração: inscrição vinculada a usuário/evento e pagamento automático
-- Data: 30/07/2026
--
-- Antes de executar, faça backup do banco.
-- A migração interrompe com uma mensagem clara quando encontra dados duplicados
-- ou referências órfãs que precisam ser corrigidas manualmente.
-- ============================================================================

DELIMITER $$

DROP PROCEDURE IF EXISTS migrar_inscricao_pagamento_automatico$$

CREATE PROCEDURE migrar_inscricao_pagamento_automatico()
BEGIN
    DECLARE duplicadas INT DEFAULT 0;
    DECLARE pagamentos_duplicados INT DEFAULT 0;
    DECLARE usuarios_orfaos INT DEFAULT 0;
    DECLARE eventos_orfaos INT DEFAULT 0;
    DECLARE inscricoes_orfas INT DEFAULT 0;

    SELECT COUNT(*) INTO duplicadas
    FROM (
        SELECT idEvento, idUsuario
        FROM inscricoes
        WHERE idUsuario IS NOT NULL
          AND status <> 'Cancelada'
          AND pagamento NOT IN ('Cancelado', 'Estornado')
        GROUP BY idEvento, idUsuario
        HAVING COUNT(*) > 1
    ) AS duplicidade;

    IF duplicadas > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Existem usuários com mais de uma inscrição ativa no mesmo evento. Resolva as duplicidades ativas antes de executar a migração.';
    END IF;

    SELECT COUNT(*) INTO pagamentos_duplicados
    FROM (
        SELECT idInscricao
        FROM pagamentos
        WHERE idInscricao IS NOT NULL
        GROUP BY idInscricao
        HAVING COUNT(*) > 1
    ) AS duplicidade;

    IF pagamentos_duplicados > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Existem inscrições com mais de um pagamento. Mantenha somente o pagamento válido antes de executar a migração.';
    END IF;

    SELECT COUNT(*) INTO usuarios_orfaos
    FROM inscricoes i
    LEFT JOIN usuarios u ON u.id = i.idUsuario
    WHERE i.idUsuario IS NOT NULL
      AND u.id IS NULL;

    IF usuarios_orfaos > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Existem inscrições vinculadas a usuários inexistentes.';
    END IF;

    SELECT COUNT(*) INTO eventos_orfaos
    FROM pagamentos p
    LEFT JOIN eventos e ON e.idEvento = p.idEvento
    WHERE e.idEvento IS NULL;

    IF eventos_orfaos > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Existem pagamentos vinculados a eventos inexistentes.';
    END IF;

    SELECT COUNT(*) INTO inscricoes_orfas
    FROM pagamentos p
    LEFT JOIN inscricoes i ON i.idInscricao = p.idInscricao
    WHERE p.idInscricao IS NOT NULL
      AND i.idInscricao IS NULL;

    IF inscricoes_orfas > 0 THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Existem pagamentos vinculados a inscrições inexistentes.';
    END IF;

    -- Remove as chaves antigas antes de alinhar os tipos das colunas.
    IF EXISTS (
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'pagamentos'
          AND CONSTRAINT_NAME = 'fk_pagamentos_evento'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        ALTER TABLE pagamentos DROP FOREIGN KEY fk_pagamentos_evento;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'pagamentos'
          AND CONSTRAINT_NAME = 'fk_pagamentos_inscricao'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        ALTER TABLE pagamentos DROP FOREIGN KEY fk_pagamentos_inscricao;
    END IF;

    ALTER TABLE inscricoes
        MODIFY idUsuario INT NULL,
        MODIFY camiseta ENUM('PP','P','M','G','GG','XGG') NULL DEFAULT NULL;

    ALTER TABLE pagamentos
        MODIFY idEvento INT NOT NULL,
        MODIFY idInscricao INT NULL;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'inscricoes'
          AND INDEX_NAME = 'idx_inscricoes_evento_usuario'
    ) THEN
        ALTER TABLE inscricoes
            ADD KEY idx_inscricoes_evento_usuario (idEvento, idUsuario);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'inscricoes'
          AND INDEX_NAME = 'idx_inscricoes_usuario'
    ) THEN
        ALTER TABLE inscricoes
            ADD KEY idx_inscricoes_usuario (idUsuario);
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE CONSTRAINT_SCHEMA = DATABASE()
          AND TABLE_NAME = 'inscricoes'
          AND CONSTRAINT_NAME = 'fk_inscricao_usuario'
          AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ) THEN
        ALTER TABLE inscricoes
            ADD CONSTRAINT fk_inscricao_usuario
            FOREIGN KEY (idUsuario)
            REFERENCES usuarios (id)
            ON UPDATE CASCADE
            ON DELETE RESTRICT;
    END IF;

    IF NOT EXISTS (
        SELECT 1
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'pagamentos'
          AND INDEX_NAME = 'uk_pagamentos_inscricao'
    ) THEN
        ALTER TABLE pagamentos
            ADD UNIQUE KEY uk_pagamentos_inscricao (idInscricao);
    END IF;

    ALTER TABLE pagamentos
        ADD CONSTRAINT fk_pagamentos_evento
        FOREIGN KEY (idEvento)
        REFERENCES eventos (idEvento)
        ON UPDATE CASCADE
        ON DELETE RESTRICT;

    ALTER TABLE pagamentos
        ADD CONSTRAINT fk_pagamentos_inscricao
        FOREIGN KEY (idInscricao)
        REFERENCES inscricoes (idInscricao)
        ON UPDATE CASCADE
        ON DELETE CASCADE;
END$$

CALL migrar_inscricao_pagamento_automatico()$$
DROP PROCEDURE migrar_inscricao_pagamento_automatico$$

DELIMITER ;
