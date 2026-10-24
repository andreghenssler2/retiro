# Retiro — Sistema Integrado de Gestão de Eventos

Sistema web para **criação, divulgação, inscrição, pagamento, credenciamento, acompanhamento e administração de eventos**, com área pública, área do participante e painel administrativo.

O projeto foi desenvolvido em **PHP + MySQL/MariaDB**, com integração de pagamentos pelo **Asaas API v3**, envio de e-mails, geração de certificados, calendário, notificações, histórico de atividades e controle de cancelamentos.

> **Status:** em desenvolvimento ativo  
> **Atualização deste README:** 13/08/2026  
> **Versão registrada no repositório:** `0.1.095`  
> **Build registrado:** `95`
>
> O ambiente local pode conter recursos mais recentes que o número registrado em `mod/version.php`, principalmente as atualizações da **Inscrição Pública**, que foram adicionadas posteriormente por scripts de atualização.

---

## Sumário

- [1. Objetivo do sistema](#1-objetivo-do-sistema)
- [2. Tecnologias](#2-tecnologias)
- [3. Perfis de acesso](#3-perfis-de-acesso)
- [4. Área pública](#4-área-pública)
- [5. Inscrição pública sem login](#5-inscrição-pública-sem-login)
- [6. Área do participante](#6-área-do-participante)
- [7. Painel administrativo](#7-painel-administrativo)
- [8. Gestão de eventos](#8-gestão-de-eventos)
- [9. Inscrições](#9-inscrições)
- [10. Pagamentos e Asaas](#10-pagamentos-e-asaas)
- [11. Cancelamento de inscrição e estorno](#11-cancelamento-de-inscrição-e-estorno)
- [12. Notificações](#12-notificações)
- [13. Credenciamento](#13-credenciamento)
- [14. Certificados](#14-certificados)
- [15. Calendário](#15-calendário)
- [16. Histórico de atividades](#16-histórico-de-atividades)
- [17. Usuários e perfis](#17-usuários-e-perfis)
- [18. Configurações](#18-configurações)
- [19. Segurança](#19-segurança)
- [20. Banco de dados](#20-banco-de-dados)
- [21. Estrutura de diretórios](#21-estrutura-de-diretórios)
- [22. Instalação](#22-instalação)
- [23. Ambiente local](#23-ambiente-local)
- [24. Produção](#24-produção)
- [25. CRON e tarefas automáticas](#25-cron-e-tarefas-automáticas)
- [26. Versionamento](#26-versionamento)
- [27. Fluxos principais](#27-fluxos-principais)
- [28. Regras importantes](#28-regras-importantes)
- [29. Cuidados com dados sensíveis](#29-cuidados-com-dados-sensíveis)
- [30. Estado atual do projeto](#30-estado-atual-do-projeto)

---

# 1. Objetivo do sistema

O **Retiro** centraliza a administração de eventos em um único sistema.

Entre as principais funções estão:

- cadastro de eventos;
- abertura e encerramento de inscrições;
- divulgação pública dos eventos;
- inscrição de participantes;
- inscrição pública sem obrigar login;
- validação de e-mail;
- identificação pelo CPF;
- preenchimento automático de dados já existentes;
- configuração de termos por evento;
- configuração de camiseta por evento;
- controle de vagas e idade;
- pagamentos por PIX;
- pagamentos por boleto;
- pagamentos por cartão de crédito;
- integração com Asaas;
- sincronização de cobranças;
- gestão financeira;
- solicitação de cancelamento;
- análise administrativa do cancelamento;
- estorno de pagamentos;
- credenciamento;
- emissão de certificados;
- calendário;
- notificações;
- logs de acesso e atividades;
- relatórios;
- controle de usuários e permissões.

---

# 2. Tecnologias

## Backend

- PHP 8.x;
- PDO;
- MySQL / MariaDB;
- arquitetura baseada em classes e serviços;
- sessões PHP;
- requisições AJAX/Fetch;
- integração com APIs REST.

## Frontend

- HTML5;
- CSS3;
- JavaScript;
- Bootstrap 5;
- Font Awesome;
- Google Fonts / Inter;
- interfaces responsivas para desktop e dispositivos móveis.

## Integrações

- Asaas API v3;
- SMTP / envio de e-mail;
- geração de QR Code PIX via Asaas;
- boleto bancário via Asaas;
- cartão de crédito via Asaas.

---

# 3. Perfis de acesso

O sistema possui três tipos principais de usuário.

| Tipo | Perfil | Acesso |
|---|---|---|
| `1` | Administrador | Administração completa do sistema |
| `2` | Moderador | Área autenticada com permissões intermediárias |
| `3` | Participante | Área pessoal e inscrições |

A autenticação é controlada pela classe `Auth`.

Principais métodos disponíveis:

```php
Auth::check();
Auth::user();
Auth::id();
Auth::nome();
Auth::email();
Auth::tipo();

Auth::isAdmin();
Auth::isModerador();
Auth::isParticipante();

Auth::requireLogin();
Auth::requireAdmin();
Auth::requireModerador();
```

O login usa `password_verify()` e regenera o ID da sessão após autenticação.

---

# 4. Área pública

A área pública permite acessar informações mesmo sem autenticação.

Entre as páginas públicas estão:

```text
/
eventos/
eventos/detalhe.php
inscricao/
```

## Página principal

A página inicial pode apresentar os eventos cadastrados e disponíveis.

## Lista de eventos

Em:

```text
/eventos/
```

o visitante ou usuário pode visualizar os eventos disponíveis.

## Detalhes do evento

Em:

```text
/eventos/detalhe.php
```

são apresentadas informações do evento, como:

- título;
- imagem;
- descrição;
- data;
- horário;
- local;
- período de inscrição;
- valor;
- disponibilidade;
- informações relacionadas à inscrição.

Quando as inscrições estão abertas, é apresentado o botão:

```text
Inscrever-se
```

O visitante não precisa mais ser enviado obrigatoriamente para `/login/`.

---

# 5. Inscrição pública sem login

A inscrição pública foi criada para permitir que uma pessoa faça sua inscrição **sem precisar possuir senha ou entrar no sistema antes**.

A página principal é:

```text
/inscricao/
```

## Fluxo

```text
Evento
  ↓
Inscrever-se
  ↓
E-mail
  ↓
Código de validação
  ↓
CPF
  ↓
Informações pessoais
  ↓
Termos do evento
  ↓
Endereço e comunidade
  ↓
Saúde e acessibilidade
  ↓
Camiseta, se configurada
  ↓
Resumo
  ↓
Pagamento
```

## 5.1 Validação do e-mail

O participante informa seu e-mail.

O sistema envia um código numérico de **6 dígitos**.

Características do código:

- válido por aproximadamente 10 minutos;
- quantidade de tentativas limitada;
- reenvio controlado;
- fluxo vinculado ao evento;
- sessão temporária de inscrição;
- código armazenado como hash, não em texto puro.

A validação do e-mail funciona como uma autorização temporária para continuar a inscrição.

## 5.2 CPF

Depois de validar o e-mail, o participante informa o CPF.

Assim que o CPF estiver completo, o sistema verifica se existe cadastro anterior.

### Segurança do preenchimento automático

O sistema **não fornece dados pessoais apenas pela consulta de um CPF**.

O preenchimento automático só acontece quando:

```text
CPF informado
+
e-mail cadastrado
=
e-mail que acabou de ser validado
```

Se o CPF estiver vinculado a outro e-mail, o sistema bloqueia a consulta dos dados.

## 5.3 Dados pessoais

São coletados:

- Nome Completo;
- Nacionalidade;
- CPF;
- Data de Nascimento;
- Gênero;
- E-mail já validado;
- Telefone.

Quando o cadastro já existe, os campos disponíveis podem ser preenchidos automaticamente.

## 5.4 Criação/vinculação de usuário

Mesmo sem exigir login, a inscrição é vinculada a um `idUsuario`.

Isso mantém compatibilidade com:

- histórico do participante;
- pagamentos;
- cliente Asaas;
- notificações;
- calendário;
- certificados;
- área `/my/`.

Quando a pessoa ainda não possui usuário:

- é criado um registro em `usuarios`;
- o perfil é criado como Participante (`tipo = 3`);
- o e-mail já validado é registrado;
- uma senha aleatória segura é gerada internamente;
- não é necessário informar senha durante a inscrição.

Caso queira acessar a área do usuário futuramente, a pessoa poderá utilizar o fluxo de recuperação/definição de senha.

## 5.5 Endereço

São coletados:

- País;
- CEP;
- Rua / Logradouro;
- Número;
- Complemento;
- Bairro;
- Cidade;
- Estado;
- Comunidade / Paróquia.

## 5.6 Saúde e acessibilidade

A inscrição pode armazenar:

- restrição a alguma medicação;
- descrição da restrição/medicação;
- deficiência;
- descrição complementar;
- necessidade de recurso de acessibilidade;
- descrição do recurso;
- restrição alimentar;
- descrição da restrição alimentar.

## 5.7 Termos e consentimentos por evento

Os termos não são globais.

Eles são configurados pelo Administrador no cadastro ou edição do evento.

Cada termo pode possuir:

- título;
- descrição;
- link para documento;
- aceite obrigatório ou opcional;
- ordem de apresentação.

Exemplos:

```text
Autorização de uso de imagem
Termo de participação
Política de privacidade
Autorização para menores
Regulamento do evento
```

### Histórico do aceite

O sistema registra uma fotografia do termo no momento do aceite, incluindo:

- título;
- descrição;
- URL;
- obrigatoriedade;
- data e hora;
- IP;
- User-Agent.

Dessa forma, uma alteração futura no texto do termo não altera o histórico da inscrição já realizada.

## 5.8 Camiseta

O evento pode possuir opção de camiseta.

Quando `camiseta_ativa = 1`, o Administrador pode definir quais tamanhos estarão disponíveis.

Tamanhos previstos:

```text
P
M
G
GG
X1
X2
X3
X4
```

A etapa somente aparece quando:

```text
camiseta_ativa = 1
e
há tamanhos configurados para o evento
```

## 5.9 Visual da inscrição

O `/inscricao/` utiliza a mesma identidade visual do sistema.

Principais cores:

```text
Primário:       #0d6efd
Primário escuro:#0b5ed7
Fundo:          #f4f6f9
Cards:          #ffffff
Texto:          #1f2937
Texto secundário:#6b7280
Bordas:         #e5e7eb
```

---

# 6. Área do participante

Depois do login, Participantes e Moderadores utilizam a área pessoal.

A entrada principal é:

```text
/my/
```

O sidebar do usuário é diferente do sidebar administrativo.

Principais opções:

```text
Início
Eventos
Meus Eventos
Meu Perfil
Meu Calendário
Meus Certificados
Minhas Atividades
Configurações
Sair
```

## 6.1 Início

Página de acesso rápido aos principais recursos do participante.

## 6.2 Eventos

Em:

```text
/eventos/
```

o usuário visualiza todos os eventos disponíveis.

## 6.3 Meus Eventos

Em:

```text
/user/eventos.php
```

o participante visualiza os eventos nos quais está inscrito.

A tela pode apresentar:

- dados da inscrição;
- status;
- situação do pagamento;
- dados do evento;
- opção de cancelamento quando permitido.

## 6.4 Meu Perfil

Em:

```text
/user/index.php
```

o usuário visualiza seus dados pessoais.

## 6.5 Configurações do perfil

Em:

```text
/user/profile.php
```

o usuário gerencia informações relacionadas ao perfil.

## 6.6 Meus Certificados

Em:

```text
/user/certificados.php
```

o participante visualiza somente os certificados vinculados ao seu cadastro.

## 6.7 Minhas Atividades

Em:

```text
/user/atividades.php
```

o participante visualiza somente os registros do próprio usuário.

Mesmo que tente alterar manualmente parâmetros pela URL, o backend deve utilizar:

```php
Auth::id();
```

como filtro obrigatório para usuários não administradores.

---

# 7. Painel administrativo

O Administrador possui sidebar e painel próprios.

Principais módulos:

```text
Dashboard
Usuários
Eventos
Inscrições
Credenciamento
Pagamentos
Financeiro
Certificados
Relatórios
Configurações
```

O sidebar administrativo só deve ser exibido para:

```php
Auth::isAdmin() === true
```

Para outros tipos de usuário, o sistema utiliza o sidebar da área do usuário.

---

# 8. Gestão de eventos

O Administrador pode criar e editar eventos.

Entre as configurações já suportadas estão:

- título;
- descrição;
- descrição curta;
- imagem;
- local;
- data de início;
- data de término;
- horário;
- abertura das inscrições;
- encerramento das inscrições;
- evento ativo/inativo;
- inscrição aberta/fechada;
- quantidade de vagas;
- idade mínima;
- idade máxima;
- valor da inscrição;
- pagamento obrigatório;
- camiseta ativa;
- termos e consentimentos;
- tamanhos de camiseta;
- demais campos relacionados ao evento.

## Regras automáticas

O fluxo de inscrição pode verificar:

- evento ativo;
- inscrição aberta;
- data de início das inscrições;
- data final das inscrições;
- quantidade de vagas;
- idade mínima;
- idade máxima;
- inscrição já existente.

---

# 9. Inscrições

As inscrições ficam vinculadas a:

```text
evento
usuário
dados pessoais
pagamento
status
```

Principais informações:

- ID da inscrição;
- ID do evento;
- ID do usuário;
- nome;
- CPF;
- e-mail;
- telefone;
- gênero;
- data de nascimento;
- cidade;
- estado;
- camiseta;
- valor;
- valor pago;
- forma de pagamento;
- status da inscrição;
- status do pagamento;
- presença;
- certificado.

## Status

O sistema trabalha com situações como:

```text
Pendente
Confirmada
Cancelada
```

e situações financeiras como:

```text
Pendente
Pago
Cancelado
Estornado
```

A nomenclatura exata pode variar de acordo com cada módulo.

---

# 10. Pagamentos e Asaas

A integração financeira utiliza a **Asaas API v3**.

O sistema pode trabalhar em:

```text
Sandbox
Produção
```

A configuração bancária é armazenada na área administrativa, sem expor tokens nas páginas públicas.

## 10.1 Formas de pagamento

### PIX

O sistema:

1. cria ou recupera a cobrança;
2. solicita o QR Code PIX;
3. apresenta o QR Code;
4. apresenta o PIX Copia e Cola;
5. permite sincronizar o status da cobrança.

### Boleto

O sistema:

1. cria a cobrança;
2. obtém a linha digitável;
3. disponibiliza link para o boleto;
4. acompanha o status junto ao Asaas.

### Cartão de crédito

O cartão é informado dentro do próprio site.

São solicitados:

- nome do titular;
- número do cartão;
- mês de validade;
- ano de validade;
- CVV.

### Dados do cartão

O sistema **não deve armazenar**:

```text
número completo do cartão
CVV
data de validade como credencial reutilizável
```

Os dados são utilizados somente durante a requisição de pagamento e enviados para o Asaas.

Também não devem ser colocados em:

- logs;
- mensagens de erro;
- histórico de atividades;
- banco de dados;
- dumps de requisição.

## 10.2 Cliente Asaas

As cobranças são associadas ao usuário/participante.

O sistema pode:

- localizar um cliente Asaas já existente;
- criar cliente quando necessário;
- associar a cobrança ao participante.

## 10.3 Sincronização

O sistema possui rotina para consultar a situação da cobrança no Asaas e atualizar o banco local.

Exemplos de estados externos tratados:

```text
PENDING
RECEIVED
CONFIRMED
REFUNDED
```

Após um PIX estar pago, o sistema não deve tentar gerar um novo QR Code da mesma cobrança.

O mesmo princípio é utilizado para dados de boleto de cobranças já encerradas.

---

# 11. Cancelamento de inscrição e estorno

O participante pode solicitar o cancelamento da própria inscrição.

O cancelamento não é realizado imediatamente.

Fluxo:

```text
Participante
  ↓
Solicitar cancelamento
  ↓
Informar motivo
  ↓
Solicitação Pendente
  ↓
Administrador é notificado
  ↓
Administrador analisa
  ├─ Aprova
  └─ Rejeita
```

## 11.1 Prazo

A solicitação pode ser realizada até **1 dia útil antes do encerramento das inscrições**.

Atualmente, o cálculo de dia útil considera:

```text
segunda-feira a sexta-feira
```

Feriados ainda dependem de regra específica, caso sejam adicionados posteriormente.

## 11.2 Motivo

O participante precisa informar o motivo do cancelamento.

Existe validação de tamanho mínimo para evitar solicitações sem justificativa adequada.

## 11.3 Administração

O Administrador possui tela para:

- listar solicitações;
- filtrar por status;
- visualizar participante;
- visualizar evento;
- consultar situação financeira;
- consultar motivo;
- aprovar;
- rejeitar;
- informar observação da análise.

A análise pode ocorrer via AJAX, evitando reload completo da página.

## 11.4 Pagamento pendente

Se ainda houver cobrança pendente, o sistema pode cancelar a cobrança junto ao Asaas antes de concluir o cancelamento local.

## 11.5 Pagamento já pago

Quando a inscrição possui pagamento confirmado, a aprovação do cancelamento inicia o fluxo financeiro de estorno.

### PIX

O sistema solicita estorno pelo Asaas.

Depois sincroniza o status da cobrança.

### Cartão

O sistema solicita estorno da cobrança pelo Asaas e acompanha o retorno.

### Boleto pago

Boleto possui fluxo próprio.

O Asaas pode gerar uma URL para que o pagador informe os dados bancários necessários para recebimento do estorno.

O sistema não deve marcar o pagamento como definitivamente estornado antes da confirmação do processo.

## 11.6 Segurança do fluxo

Se houver erro financeiro importante ao aprovar o cancelamento, o sistema não deve fingir que tudo foi concluído.

O objetivo é evitar:

```text
inscrição cancelada
+
pagamento ainda recebido
+
nenhum aviso ao Administrador
```

---

# 12. Notificações

O sistema possui notificações associadas ao usuário.

Regra:

```text
Participante/Moderador
→ visualiza somente suas próprias notificações

Administrador
→ pode visualizar todas
```

## Cancelamentos

Quando um participante solicita cancelamento:

- Administradores recebem notificação no sistema;
- Administradores podem receber e-mail;
- a mensagem informa os dados necessários para análise;
- pode haver acesso direto à tela de gerenciamento de cancelamentos.

As notificações devem respeitar o usuário relacionado para evitar exposição de informações entre contas.

---

# 13. Credenciamento

O módulo administrativo possui área de:

```text
/admin/credenciamento/
```

Ele é destinado ao acompanhamento de participantes no evento.

Pode ser utilizado para controle relacionado à presença e identificação dos inscritos.

---

# 14. Certificados

O sistema possui módulo de certificados.

## Administrador

A área administrativa permite gerenciar certificados relacionados aos eventos e inscrições.

Exemplo de caminho:

```text
/admin/certificado/
```

## Participante

Cada usuário visualiza somente os certificados associados ao seu cadastro:

```text
/user/certificados.php
```

---

# 15. Calendário

O calendário está disponível em:

```text
/calendar/
```

A visualização depende do perfil.

## Administrador

Pode visualizar os eventos cadastrados no sistema.

## Participante

Visualiza os eventos relacionados às suas inscrições.

O menu do participante apresenta:

```text
Meu Calendário
```

---

# 16. Histórico de atividades

O sistema registra automaticamente atividades dos usuários autenticados.

A classe principal é:

```text
AtividadeUsuario
```

Entre os dados registrados estão:

- usuário;
- perfil;
- tipo de acesso;
- rota;
- descrição;
- método HTTP;
- endereço IP;
- User-Agent;
- data e hora.

## Privacidade dos logs

O logger de requisições não deve armazenar:

- parâmetros GET completos;
- corpo POST;
- senhas;
- tokens;
- cookies;
- dados de cartão.

## Visualização

### Administrador

Pode visualizar:

- registros de todos os usuários;
- atividades do dia;
- histórico completo;
- filtros;
- pesquisa;
- usuário específico;
- intervalo de datas.

### Participante/Moderador

Em:

```text
/user/atividades.php
```

visualiza somente:

```php
idUsuario = Auth::id()
```

Não é permitido escolher outro usuário por parâmetro de URL.

---

# 17. Usuários e perfis

O módulo de usuários permite administrar contas do sistema.

Dados utilizados incluem:

- nome;
- e-mail;
- telefone;
- CPF;
- senha;
- tipo;
- status ativo;
- comunidade;
- endereço;
- cidade;
- estado;
- foto;
- último login;
- data de criação.

Com a Inscrição Pública foram adicionados dados como:

- nacionalidade;
- data de nascimento;
- gênero;
- país;
- CEP;
- complemento;
- data de verificação do e-mail.

## Tipos

```text
1 = Administrador
2 = Moderador
3 = Participante
```

---

# 18. Configurações

O painel administrativo possui área de configurações.

Entre as opções existentes estão:

## E-mail

Configurações para envio de mensagens pelo sistema.

## Title

Configuração de título, descrição, palavras-chave, favicon e informações relacionadas à identidade do site.

## Atividades

Acesso aos logs dos usuários.

## Bancário

Configurações financeiras e integração com pagamentos.

## Usuários

Administração relacionada aos usuários.

## Permissões

Controle de permissões e recursos conforme perfil.

---

# 19. Segurança

O projeto adota diferentes mecanismos de segurança.

## 19.1 Senhas

As senhas são armazenadas com hash.

Exemplo:

```php
password_hash(
    $senha,
    PASSWORD_DEFAULT
);
```

Autenticação:

```php
password_verify(
    $senha,
    $hash
);
```

## 19.2 Sessão

Após login:

```php
session_regenerate_id(true);
```

O sistema possui classes para gerenciamento de sessão e autenticação.

## 19.3 CSRF

Formulários sensíveis utilizam token CSRF.

Exemplo:

```php
Session::csrf();
Session::validateCsrf();
```

## 19.4 Autorização

Páginas são protegidas por:

```php
Auth::requireLogin();
Auth::requireAdmin();
Auth::requireModerador();
Middleware::auth();
Middleware::admin();
```

conforme o contexto.

## 19.5 PDO

Consultas utilizam PDO e prepared statements para evitar concatenação direta de dados do usuário nas consultas SQL.

## 19.6 Escape HTML

Dados apresentados em páginas devem utilizar:

```php
htmlspecialchars();
```

principalmente para conteúdo fornecido pelo usuário.

## 19.7 Dados de cartão

Número de cartão e CVV:

- não são persistidos;
- não são registrados em logs;
- existem somente durante a chamada de pagamento.

## 19.8 CPF e preenchimento automático

Dados pessoais só são retornados após:

```text
e-mail validado
+
CPF correspondente ao mesmo cadastro
```

## 19.9 Transações

Operações importantes utilizam transações quando necessário, especialmente:

- criação de inscrições;
- cancelamentos;
- atualização financeira;
- armazenamento de dados relacionados.

---

# 20. Banco de dados

O sistema utiliza MySQL/MariaDB.

Entre as tabelas existentes ou utilizadas estão:

```text
usuarios
eventos
inscricoes
pagamentos
titulo
tipoacesso
minha_comunidade
configuracoes_bancarias
atividades_usuarios
solicitacoes_cancelamento_inscricao
```

Com a Inscrição Pública foram adicionadas:

```text
evento_termos
evento_camiseta_opcoes
inscricao_publica_fluxos
inscricao_dados_adicionais
inscricao_termos_aceites
```

Também existem tabelas relacionadas a módulos como:

- certificados;
- notificações;
- credenciamento;
- configurações;
- permissões;
- demais recursos administrativos.

## 20.1 `evento_termos`

Armazena termos específicos de cada evento.

Principais campos:

```text
idTermo
idEvento
titulo
descricao
url
obrigatorio
ordem
```

## 20.2 `evento_camiseta_opcoes`

Define tamanhos disponíveis por evento.

```text
idEvento
tamanho
ordem
```

## 20.3 `inscricao_publica_fluxos`

Armazena o fluxo temporário de validação.

Exemplos:

```text
token
idEvento
email
codigo_hash
codigo_expira_em
tentativas_codigo
email_verificado_em
cpf
idUsuario
idInscricao
idPagamento
expira_em
concluido_em
```

## 20.4 `inscricao_dados_adicionais`

Armazena dados complementares da inscrição.

Inclui:

- nacionalidade;
- gênero;
- endereço;
- comunidade;
- saúde;
- deficiência;
- acessibilidade;
- alimentação.

## 20.5 `inscricao_termos_aceites`

Registra os aceites realizados.

Armazena também o snapshot do texto/documento aceito.

---

# 21. Estrutura de diretórios

Estrutura geral do projeto:

```text
/
├── admin/
│   ├── certificado/
│   ├── configuracoes/
│   ├── credenciamento/
│   ├── dashboard/
│   ├── event/
│   ├── financeiro/
│   ├── includes/
│   ├── inscricao/
│   ├── relatorios/
│   └── user/
│
├── calendar/
│
├── config/
│   ├── config.php
│   ├── settings.php
│   └── integracoes.php
│
├── cron/
│
├── eventos/
│
├── includes/
│
├── inscricao/
│   ├── ajax/
│   └── index.php
│
├── install/
│
├── login/
│
├── mod/
│   ├── auth/
│   ├── database/
│   ├── mail/
│   ├── services/
│   ├── autoload.php
│   └── version.php
│
├── my/
│
├── theme/
│   ├── css/
│   ├── img/
│   └── js/
│
├── uploads/
│
├── user/
│   ├── includes/
│   ├── certificados.php
│   ├── eventos.php
│   ├── atividades.php
│   ├── index.php
│   └── profile.php
│
└── index.php
```

A estrutura pode receber novos diretórios conforme os módulos evoluem.

---

# 22. Instalação

O projeto possui estrutura de instalação/configuração.

Em ambientes novos, devem ser configurados:

1. PHP;
2. servidor web;
3. MySQL/MariaDB;
4. banco de dados;
5. configurações do sistema;
6. usuário Administrador;
7. e-mail;
8. integração Asaas quando necessária.

O projeto possui estrutura `/install/` para auxiliar a preparação inicial.

## Configuração principal

O arquivo:

```text
/config/settings.php
```

carrega as configurações principais e o autoload do sistema.

## Banco

A conexão é centralizada na classe de banco e utiliza PDO.

---

# 23. Ambiente local

O sistema pode ser executado em ambiente XAMPP.

Exemplo:

```text
C:\xampp\htdocs\
```

Requisitos recomendados:

```text
Apache
PHP 8.2 ou superior
MySQL / MariaDB
ext-pdo
ext-pdo_mysql
OpenSSL
cURL
mbstring
JSON
```

Para verificar sintaxe:

```bat
php -l arquivo.php
```

---

# 24. Produção

O projeto pode ser publicado em hospedagem com:

- Apache;
- PHP 8.x;
- MySQL;
- HTTPS;
- CRON;
- envio SMTP;
- acesso externo à API do Asaas.

Antes de publicar:

- revisar configurações;
- utilizar credenciais de produção;
- remover scripts temporários de atualização;
- não deixar tokens em arquivos públicos;
- garantir HTTPS;
- conferir permissões de diretórios;
- revisar logs;
- testar pagamentos.

---

# 25. CRON e tarefas automáticas

O sistema possui rotinas que podem ser executadas por CRON.

Um exemplo utilizado é:

```text
cron/processar-boletos-vencidos.php
```

Exemplo de execução:

```bash
cd /caminho/do/projeto && \
/usr/bin/php cron/processar-boletos-vencidos.php
```

Importante:

```text
/caminho/do/projeto
```

é um diretório e não deve ser executado diretamente como comando.

O `cd` deve vir antes do PHP.

---

# 26. Versionamento

As informações de versão ficam em:

```text
mod/version.php
```

Estrutura:

```php
return [
    'version' => '0.1.095',
    'build'   => 95,
    'commit'  => '724b227',
    'branch'  => 'main',
    'date'    => '2026-08-13 19:37:44',
];
```

Essas informações podem ser atualizadas pelo processo de build/deploy.

## Observação sobre atualizações locais

Algumas melhorias recentes foram aplicadas por scripts de atualização e podem ainda não estar refletidas no número de build do repositório.

Entre elas:

```text
Inscrição Pública
Termos por evento
Camiseta por evento
Validação de e-mail
CPF com preenchimento automático
Visual do /inscricao/
Ajustes no cancelamento e estorno
```

---

# 27. Fluxos principais

## 27.1 Login tradicional

```text
/login/
  ↓
E-mail + senha
  ↓
Auth
  ↓
Perfil
  ├─ Administrador → painel administrativo
  ├─ Moderador     → /my/
  └─ Participante  → /my/
```

## 27.2 Inscrição pública

```text
Evento
  ↓
Inscrever-se
  ↓
Validar e-mail
  ↓
Informar CPF
  ↓
Completar dados
  ↓
Aceitar termos
  ↓
Informar endereço
  ↓
Saúde/acessibilidade
  ↓
Camiseta
  ↓
Pagamento
  ↓
Inscrição
```

## 27.3 Pagamento PIX

```text
Inscrição
  ↓
Criar cobrança Asaas
  ↓
Gerar QR Code
  ↓
Participante paga
  ↓
Sincronizar cobrança
  ↓
Pagamento = Pago
```

## 27.4 Cartão

```text
Inscrição
  ↓
Dados do cartão
  ↓
Enviar para Asaas
  ↓
Descartar dados sensíveis
  ↓
Asaas processa
  ↓
Atualizar pagamento
```

## 27.5 Cancelamento

```text
Participante
  ↓
Solicita cancelamento
  ↓
Informa motivo
  ↓
Administrador recebe notificação
  ↓
Análise
  ├─ Rejeitar
  │    ↓
  │  inscrição continua
  │
  └─ Aprovar
       ↓
     verificar pagamento
       ├─ pendente → cancelar cobrança
       └─ pago     → solicitar estorno
                       ↓
                  cancelar inscrição
```

## 27.6 Certificado

```text
Evento/Inscrição
  ↓
Administrador gerencia certificado
  ↓
Certificado associado ao participante
  ↓
/user/certificados.php
```

---

# 28. Regras importantes

## Eventos

A inscrição deve respeitar:

```text
evento ativo
inscrição aberta
data válida
vagas disponíveis
idade permitida
```

## Usuário

O sistema deve impedir que um usuário comum visualize informações de outro usuário.

## Notificações

```text
Usuário comum → somente próprias
Administrador → todas
```

## Atividades

```text
Usuário comum → somente Auth::id()
Administrador → todos
```

## Cancelamento

A solicitação é permitida até 1 dia útil antes do encerramento das inscrições.

## Pagamento

Não marcar como estornado sem retorno compatível do provedor financeiro.

## CPF

Não utilizar CPF sozinho para revelar dados pessoais.

## Cartão

Nunca persistir CVV ou número completo do cartão.

---

# 29. Cuidados com dados sensíveis

O sistema manipula informações pessoais e financeiras.

Dados que exigem cuidado:

```text
CPF
nome
e-mail
telefone
data de nascimento
endereço
informações de saúde
informações de acessibilidade
restrições alimentares
IP
User-Agent
tokens de API
dados bancários
dados transitórios de cartão
```

Boas práticas obrigatórias:

- utilizar HTTPS em produção;
- limitar acesso conforme perfil;
- não expor tokens;
- não registrar dados completos de cartão;
- restringir logs;
- criar backups seguros;
- proteger o banco;
- utilizar senhas com hash;
- usar CSRF;
- usar prepared statements;
- registrar apenas dados necessários;
- revisar permissões de arquivos e diretórios.

---

# 30. Estado atual do projeto

Até esta atualização, o sistema conta com os seguintes módulos principais:

| Módulo | Situação |
|---|---|
| Autenticação | Implementado |
| Administrador / Moderador / Participante | Implementado |
| Sidebar por perfil | Implementado |
| Dashboard administrativo | Implementado |
| Cadastro de usuários | Implementado |
| Perfil do usuário | Implementado |
| Lista pública de eventos | Implementado |
| Detalhe do evento | Implementado |
| Cadastro e edição de eventos | Implementado |
| Inscrição autenticada | Implementado |
| Inscrição pública sem login | Implementado nas atualizações locais recentes |
| Validação de e-mail | Implementado nas atualizações locais recentes |
| Preenchimento por CPF | Implementado nas atualizações locais recentes |
| Termos por evento | Implementado nas atualizações locais recentes |
| Camiseta por evento | Implementado nas atualizações locais recentes |
| Dados de saúde/acessibilidade | Implementado nas atualizações locais recentes |
| PIX | Implementado |
| Boleto | Implementado |
| Cartão de crédito | Implementado |
| Integração Asaas Sandbox/Produção | Implementado |
| Sincronização de pagamentos | Implementado |
| Área financeira | Implementado |
| Solicitação de cancelamento | Implementado |
| Análise administrativa do cancelamento | Implementado |
| Estorno Asaas | Implementado/ajustado nas atualizações recentes |
| Notificações | Implementado |
| Notificação de cancelamento para Admin | Implementado |
| Envio de e-mail para Admin | Implementado |
| Credenciamento | Implementado |
| Certificados | Implementado |
| Área de certificados do usuário | Implementado |
| Calendário | Implementado |
| Meus Eventos | Implementado |
| Logs de atividades | Implementado |
| Minhas Atividades | Implementado |
| Relatórios | Implementado |
| Configurações de e-mail | Implementado |
| Configurações bancárias | Implementado |
| Permissões | Implementado |
| Versionamento/build | Implementado |

---

## Repositório

```text
https://github.com/andreghenssler2/retiro
```

---

## Observação final

Este README deve acompanhar a evolução do projeto.

Sempre que um novo módulo for adicionado ou uma regra importante for alterada, recomenda-se atualizar:

```text
README.md
mod/version.php
banco/migrations ou scripts de atualização
documentação dos endpoints
```

Não inclua no repositório:

```text
tokens Asaas
senhas SMTP
credenciais do banco
chaves privadas
dados reais de cartão
arquivos com informações sensíveis de participantes
```
