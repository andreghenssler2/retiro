# Testes críticos

Execute na raiz do projeto:

```bash
php tests/critical/run.php
```

Os testes não usam credenciais, não acessam a API do Asaas,
não enviam e-mail e não modificam o banco de dados.

Cobertura inicial da Fase 5:

- permissões de Administrador, Moderador e Participante;
- normalização de e-mail e CPF no fluxo público;
- validação dos dígitos do CPF;
- status e formas válidas de pagamento;
- normalização de valores monetários;
- sincronização conceitual pagamento -> inscrição;
- regra de presença para cancelamento/estorno;
- preservação de boleto vencido após PAYMENT_DELETED;
- validação do token do webhook;
- mapeamento de eventos/status do Asaas;
- contratos que garantem que os arquivos reais continuam
  usando as regras testadas.

O GitHub Actions executa esta suíte em PHP 8.2, 8.3 e 8.4.
