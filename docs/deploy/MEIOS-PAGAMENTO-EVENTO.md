# Meios de pagamento por evento (2026-10-23)

1. Faça backup dos arquivos e do banco de dados.
2. Atualize os arquivos do sistema.
3. Execute `php database/migrate.php status` e depois `php database/migrate.php migrate` a partir da raiz do projeto. A migração adiciona `eventos.formas_pagamento` com padrão `PIX,Cartao,Boleto` para manter o comportamento dos eventos já cadastrados.
4. Ao criar ou editar um evento, selecione os meios aceitos: PIX, cartão, boleto e/ou dinheiro. Eventos pagos exigem ao menos uma seleção.
5. Se o evento aceitar somente dinheiro, o campo de limite de pagamento não será obrigatório; PIX, cartão e boleto continuam com o limite exigido.
6. No formulário público só aparecem os meios habilitados. Pagamento em dinheiro fica **Pendente** e pode ser confirmado manualmente no módulo Financeiro; **não** gera cobrança Asaas.
7. Teste criação/edição, participante pagando PIX e participante optando por Dinheiro, inclusive restrição de meio desabilitado via POST.

A nova coluna requer a migração antes do uso da tela de criação/edição de eventos. Não execute migrations em produção sem backup.
