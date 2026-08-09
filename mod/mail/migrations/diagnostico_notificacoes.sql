-- Diagnóstico da estrutura de notificações por e-mail

SHOW COLUMNS FROM inscricoes LIKE 'email_inscricao_enviado_em';

SHOW COLUMNS FROM pagamentos LIKE 'email_gerado_enviado_em';

SHOW COLUMNS FROM pagamentos LIKE 'email_status_notificado';

SHOW COLUMNS FROM pagamentos LIKE 'email_status_notificado_em';

-- Para verificar o pagamento usado no teste:
SELECT
    idPagamento,
    status,
    email,
    email_gerado_enviado_em,
    email_status_notificado,
    email_status_notificado_em
FROM pagamentos
WHERE idPagamento = 1;
