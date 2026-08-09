<?php

/**
 * Copie este arquivo para config/integracoes.php.
 * O arquivo real fica ignorado pelo Git para não publicar credenciais.
 *
 * O ambiente ativo é escolhido em:
 * Admin > Configurações > Bancário.
 */

// Credenciais do ambiente de testes.
define("ASAAS_SANDBOX_API_KEY", "COLE_AQUI_SUA_CHAVE_SANDBOX");
define(
    "ASAAS_SANDBOX_WEBHOOK_TOKEN",
    "troque-por-um-token-sandbox-com-pelo-menos-32-caracteres"
);

// Credenciais do ambiente de produção.
define("ASAAS_PRODUCAO_API_KEY", "COLE_AQUI_SUA_CHAVE_PRODUCAO");
define(
    "ASAAS_PRODUCAO_WEBHOOK_TOKEN",
    "troque-por-um-token-producao-com-pelo-menos-32-caracteres"
);

/**
 * Compatibilidade com a configuração antiga.
 * Use somente quando houver uma única chave para o ambiente selecionado.
 */
// define("ASAAS_API_KEY", "COLE_AQUI_SUA_CHAVE_ASAAS");
// define("ASAAS_WEBHOOK_TOKEN", "troque-por-um-token-forte");
// define("ASAAS_AMBIENTE", "sandbox");

// Opcional. Sobrescreve a URL definida pelo ambiente selecionado.
// define("ASAAS_API_URL", "https://api-sandbox.asaas.com/v3");

// Compatibilidade: o prefixo agora é configurado pela tela Bancário.
// define("ASAAS_REFERENCIA_PREFIXO", "retiro-ieclb-parobe");

// Integração HTTP genérica anterior, mantida opcional.
// define("PAGAMENTO_WEBHOOK_URL", "https://seu-sistema.example/api/pagamentos/webhook");
// define("PAGAMENTO_WEBHOOK_TOKEN", "troque-por-um-segredo-forte");
