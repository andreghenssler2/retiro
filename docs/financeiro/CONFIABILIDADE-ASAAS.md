# Fase 8 — Confiabilidade financeira

Esta fase corrige dois pontos de produção:

1. **Data/hora de recebimento do Asaas**
   - `payment.paymentDate` pode conter somente a data.
   - `pagamentos.recebidoEm` passa a usar o `dateCreated` do webhook quando
     o evento confirma pagamento.
   - Horários com `Z`/offset são convertidos para `America/Sao_Paulo`.
   - A primeira hora conhecida é preservada. Um webhook posterior não
     desloca o recebimento para frente.

2. **PDO persistente**
   - As duas conexões do arquivo `mod/database/db.php` passam a usar
     `PDO::ATTR_PERSISTENT => false`.
   - Isso reduz risco de reaproveitar estado de sessão/transação de uma
     conexão persistente entre requisições em hospedagem compartilhada.

## Instalação

Na raiz:

```powershell
php atualizar-confiabilidade-financeira-v1.php
```

Depois:

```powershell
php tools\ci-php-lint.php
php tests\critical\run.php
php tests\critical\asaas-data-hora.php
php tools\deploy\preflight.php
git status
```

Não há migration de banco nesta fase: `pagamentos.recebidoEm` já é requisito
do sistema e é validado pelo smoke test.

## Resultado esperado

O teste `asaas-data-hora.php` deve terminar com:

```text
OK: 5
FALHAS: 0
```

O preflight não deve apresentar `[ERRO]`.

## Git

Quando as validações passarem:

```powershell
git add api\asaas\webhook.php
git add mod\autoload.php
git add mod\database\db.php
git add mod\services\AsaasWebhookDataHoraService.php
git add tests\critical\asaas-data-hora.php
git add .github\workflows\quality.yml
git add docs\financeiro\CONFIABILIDADE-ASAAS.md

git commit -m "Financeiro: registrar horario exato do Asaas e estabilizar PDO"
git push
```
