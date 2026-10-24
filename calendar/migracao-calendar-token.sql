-- Execute uma única vez no banco de dados do sistema.
-- O token permite que aplicativos externos assinem o calendário
-- sem utilizar a sessão do navegador.

ALTER TABLE usuarios
    ADD COLUMN calendar_token CHAR(64) NULL DEFAULT NULL;

CREATE UNIQUE INDEX idx_usuarios_calendar_token
    ON usuarios (calendar_token);
