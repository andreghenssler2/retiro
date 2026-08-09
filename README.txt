CORREÇÃO V3 - CARTÃO ASAAS
==========================

ERRO CORRIGIDO

Call to undefined method
AsaasPagamentoService::pagarCobrancaCartao()

CAUSA

O endpoint AJAX V2 foi atualizado, porém os serviços PHP antigos
continuaram no servidor.

ARQUIVOS ALTERADOS

mod/services/AsaasService.php
mod/services/AsaasPagamentoService.php
eventos/ajax/processar-pagamento.php

COMO APLICAR

Copie para a raiz do projeto:

atualizar-servicos-cartao-v3.php
arquivos/

Execute:

php atualizar-servicos-cartao-v3.php

O script cria backups antes de alterar qualquer arquivo.

DEPOIS

Teste novamente o cartão no Sandbox.

A resposta deve conter:

"versao": "2026-08-08-04"

Se ainda faltar algum método, o endpoint agora retorna explicitamente
a etapa "servico".

SEGURANÇA

O sistema continua NÃO salvando:

- número completo do cartão;
- CVV;
- validade;
- creditCardToken;
- objeto creditCard.

Não registre $_POST durante testes de cartão.
