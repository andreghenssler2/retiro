-- Use somente para testar novamente a notificação do pagamento #1.
-- Após executar, faça uma NOVA alteração de status no sistema.

UPDATE pagamentos
SET
    email_status_notificado = 'Pendente',
    email_status_notificado_em = NOW()
WHERE idPagamento = 1;

SELECT
    idPagamento,
    status,
    email,
    email_status_notificado,
    email_status_notificado_em
FROM pagamentos
WHERE idPagamento = 1;
