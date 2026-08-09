-- Permite o novo tipo "cancelamento" nas notificações.
-- VARCHAR mantém compatibilidade com usuario/inscricao/pagamento.
ALTER TABLE `notificacoes`
    MODIFY COLUMN `tipo` VARCHAR(50) NOT NULL;
