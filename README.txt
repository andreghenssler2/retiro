MENU ADMINISTRADOR ORGANIZADO V1
================================

OBJETIVO

Reduzir a quantidade de itens soltos no menu
administrativo e organizar funcionalidades relacionadas
em submenus.


ESTRUTURA

Dashboard

Usuários

Eventos

Inscrições
  - Inscrições
  - Inscrição Manual
  - Credenciamento

Financeiro
  - Financeiro
  - Pagamentos

Certificados
  - Certificados
  - Novo Modelo

Relatórios
  - Central de Relatórios
  - Exportação de Evento

Configurações
  - E-mail
  - Title
  - Comunidades, quando instalada
  - Atividades
  - Bancário
  - Permissões

Meu Perfil
  - Editar Perfil
  - Calendário
  - Configurações

Sair


PERMISSÕES

As regras atuais foram mantidas.

Exemplos:

dashboard.visualizar
eventos.visualizar
inscricoes.visualizar
credenciamento.visualizar
financeiro.visualizar
pagamentos.visualizar
certificados.visualizar
relatorios.visualizar

Usuários e Configurações administrativas continuam
restritos ao Administrador.


SUBMENUS

O submenu correspondente fica aberto automaticamente
quando o usuário estiver dentro daquela seção.

Exemplo:

/admin/financeiro/pagamentos.php

Financeiro
  - Financeiro
  - Pagamentos ← ativo


ITENS CONDICIONAIS

Comunidades só aparece quando existir:

admin/configuracoes/comunidades.php

Exportação de Evento só aparece quando existir:

admin/relatorios/evento-exportacao.php


ARQUIVO ALTERADO

admin/includes/sidebar.php


INSTALAÇÃO

Coloque na raiz:

atualizar-menu-admin-organizado-v1.php
arquivos/

Execute:

php atualizar-menu-admin-organizado-v1.php

Depois:

Ctrl + F5
