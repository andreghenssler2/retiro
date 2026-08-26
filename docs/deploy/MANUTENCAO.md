# Fase 10 — Modo de manutenção e deploy protegido

Esta fase fecha a janela em que um deploy por cópia de arquivos poderia servir
ao usuário uma combinação temporária de arquivos antigos e novos.

## Como funciona

O estado de manutenção é salvo em:

```text
storage/manutencao.json
```

Esse arquivo:

- não é versionado;
- é protegido pelo `DeployUtil`;
- não entra em releases;
- não entra no backup de código gerenciado;
- não depende do banco de dados.

Quando ativo, requisições web que usam `config/settings.php` recebem:

```text
HTTP 503 Service Unavailable
Retry-After
Cache-Control: no-store
X-Robots-Tag: noindex, nofollow
```

Scripts CLI continuam funcionando normalmente.

## Uso manual

Ver estado:

```powershell
php tools\deploy\manutencao.php status
```

Ativar por 30 minutos:

```powershell
php tools\deploy\manutencao.php on
```

Ativar por outro período:

```powershell
php tools\deploy\manutencao.php on --minutes=60 --message="Atualização programada"
```

Desativar:

```powershell
php tools\deploy\manutencao.php off
```

## Deploy

`tools/deploy/aplicar-release.php` passa a:

1. validar a release;
2. criar backup de código;
3. ativar manutenção **sem expiração automática**;
4. copiar a release;
5. opcionalmente executar migrations e smoke test;
6. desativar manutenção somente no final de uma execução bem-sucedida.

Se ocorrer falha depois da ativação, o site permanece em manutenção. Isso é
intencional: corrija ou faça rollback e só então execute:

```powershell
php tools\deploy\manutencao.php off
```

## Rollback

O rollback ativa manutenção antes de restaurar arquivos e a deixa ativa depois
da restauração. Em seguida execute:

```powershell
php database\migrate.php status
php tools\smoke-test.php
php tools\deploy\manutencao.php off
```

O rollback de código continua não restaurando o banco.

## Validação local

```powershell
git add .gitignore
git add config\settings.php
git add config\ModoManutencao.php
git add tools\deploy\DeployUtil.php
git add tools\deploy\aplicar-release.php
git add tools\deploy\rollback-codigo.php
git add tools\deploy\manutencao.php
git add tests\critical\manutencao.php
git add .github\workflows\quality.yml
git add docs\deploy\MANUTENCAO.md

php tools\ci-php-lint.php
php tests\critical\run.php
php tests\critical\asaas-data-hora.php
php tests\critical\seguranca-http.php
php tests\critical\manutencao.php
php tools\deploy\preflight.php
git status
```

O teste novo deve terminar com:

```text
OK: 5
FALHAS: 0
```

## Teste manual local

```powershell
php tools\deploy\manutencao.php on --minutes=5 --message="Teste local"
```

Abra uma página do sistema no navegador. Ela deve responder com a tela de
manutenção. Depois:

```powershell
php tools\deploy\manutencao.php off
```

Confirme que o site voltou a abrir.

## Commit sugerido

```powershell
git commit -m "Deploy: adicionar modo de manutencao seguro"
git push
```
