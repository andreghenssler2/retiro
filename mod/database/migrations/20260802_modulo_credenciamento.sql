-- Execute uma única vez, após realizar backup do banco.

ALTER TABLE inscricoes
    ADD COLUMN presenca_status ENUM('Pendente','Presente','Ausente')
        NOT NULL DEFAULT 'Pendente'
        AFTER presenca;

ALTER TABLE inscricoes
    ADD COLUMN presenca_registrada_em DATETIME NULL
        AFTER presenca_status;

ALTER TABLE inscricoes
    ADD COLUMN presenca_registrada_por INT NULL
        AFTER presenca_registrada_em;

ALTER TABLE inscricoes
    ADD COLUMN presenca_finalizada_em DATETIME NULL
        AFTER presenca_registrada_por;

ALTER TABLE inscricoes
    ADD KEY idx_inscricoes_evento_presenca_status
        (idEvento, presenca_status);

UPDATE inscricoes
SET
    presenca_status = CASE
        WHEN presenca = 1 THEN 'Presente'
        ELSE 'Pendente'
    END,
    presenca_registrada_em = CASE
        WHEN presenca = 1 THEN COALESCE(atualizado_em, criado_em)
        ELSE NULL
    END;
