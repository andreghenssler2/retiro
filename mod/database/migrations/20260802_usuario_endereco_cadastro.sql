-- ============================================================================
-- Cadastro completo do usuário
-- Adiciona os campos de endereço na tabela usuarios.
-- Execute apenas uma vez em uma base que ainda não possui essas colunas.
-- Para uma instalação parcialmente atualizada, use migrar_usuario_endereco.php.
-- ============================================================================

ALTER TABLE usuarios
    ADD COLUMN logradouro VARCHAR(180) NULL AFTER idComunidade,
    ADD COLUMN numero VARCHAR(20) NULL AFTER logradouro,
    ADD COLUMN bairro VARCHAR(120) NULL AFTER numero,
    ADD COLUMN cidade VARCHAR(120) NULL AFTER bairro,
    ADD COLUMN estado CHAR(2) NOT NULL DEFAULT 'RS' AFTER cidade;

SELECT
    'Campos de endereço adicionados à tabela usuarios.'
    AS resultado;
