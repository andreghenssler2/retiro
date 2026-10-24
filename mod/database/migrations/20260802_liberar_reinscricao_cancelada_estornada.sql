-- ============================================================================
-- Permite nova inscrição após cancelamento ou estorno
-- Data: 02/08/2026
--
-- Execute uma única vez no banco já instalado.
-- Não usa information_schema, PROCEDURE, DELIMITER ou CALL.
-- ============================================================================

ALTER TABLE inscricoes
    DROP INDEX uk_inscricoes_evento_usuario;

ALTER TABLE inscricoes
    ADD INDEX idx_inscricoes_evento_usuario (idEvento, idUsuario);

SELECT
    'Reinscrição após cancelamento ou estorno liberada.' AS resultado;
