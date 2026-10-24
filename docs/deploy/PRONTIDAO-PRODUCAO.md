# Fase 14 — Prontidão final para produção

A Fase 14 fecha os principais gates técnicos antes do primeiro deploy real.

Ela **não executa o deploy durante a instalação**.

## O que muda no deploy

`tools/deploy/aplicar-release.php` passa a:

1. executar o preflight antes de tocar no código;
2. executar o ensaio da release antes de tocar no código;
3. criar o backup de código e validar manifesto/checksums do próprio backup;
4. exigir evidência de backup de banco quando `--migrate` for usado;
5. manter manutenção ativa se migration, status de migrations ou smoke falhar;
6. executar smoke test mesmo quando não houver migrations;
7. não reabrir o site se houver migration pendente e `--migrate` não tiver sido
   informado.

### Backup de banco para migrations

Há duas formas aceitas.

Backup verificável no servidor, fora da raiz pública:

```powershell
php tools\deploy\aplicar-release.php `
  --zip="C:\releases\retiro-x.zip" `
  --backup-dir="C:\backups\retiro" `
  --confirm=DEPLOY `
  --migrate `
  --db-backup="C:\backups\retiro\banco.sql.gz"
```

Ou, em hospedagem onde o backup foi feito pelo cPanel/phpMyAdmin e mantido fora
do alcance do script:

```text
--db-backup-confirm=CPANEL
```

Essa segunda modalidade é uma confirmação operacional; o arquivo não pode ser
validado automaticamente pelo PHP.

## Verificar backup de banco

Quando o arquivo está disponível no servidor:

```powershell
php tools\deploy\verificar-backup-banco.php `
  --backup="C:\backups\retiro\banco.sql.gz"
```

Se existir um arquivo ao lado com o mesmo nome acrescido de `.sha256`, o
checksum também será validado.

## Gate de prontidão

Antes do deploy:

```powershell
php tools\deploy\prontidao-producao.php `
  --zip="C:\releases\retiro-x.zip" `
  --backup-dir="C:\backups\retiro" `
  --db-rotated=SIM `
  --db-backup="C:\backups\retiro\banco.sql.gz"
```

Para backup feito externamente pelo cPanel/phpMyAdmin:

```powershell
php tools\deploy\prontidao-producao.php `
  --zip="C:\releases\retiro-x.zip" `
  --backup-dir="C:\backups\retiro" `
  --db-rotated=SIM `
  --db-backup-confirm=CPANEL
```

O comando valida arquivos privados sem mostrar seus valores, extensões PHP,
estado de manutenção, diretório externo de backup, preflight e ensaio da
release.

`--db-rotated=SIM` é uma confirmação explícita do operador. Ela não é gravada
em arquivo nem no banco.

## Se o deploy for executado sem --migrate

Após copiar a release, o deploy consulta as migrations.

Se houver `[PENDENTE]` ou `[ALTERADA]`, o processo termina com erro e **mantém o
site em manutenção**. Corrija a situação, execute migrations somente com backup
do banco, rode o smoke test e apenas então desligue a manutenção.

## Rollback

Rollback de código:

```powershell
php tools\deploy\rollback-codigo.php `
  --backup="CAMINHO_DO_BACKUP.zip" `
  --confirm=ROLLBACK
```

O rollback de código não restaura o banco. Depois do rollback, a manutenção
continua ativa até validação manual.

## Checklist pós-deploy

Antes de desligar qualquer manutenção manual, confirme:

- `php database/migrate.php status`;
- `php tools/smoke-test.php`;
- acesso ao login;
- acesso ao painel administrativo;
- listagem pública de eventos;
- fluxo de inscrição;
- financeiro e saúde do webhook/cron;
- preservação das imagens/uploads persistentes.

## Validação da Fase 14

```powershell
git add tools\deploy\DatabaseBackupValidator.php
git add tools\deploy\verificar-backup-banco.php
git add tools\deploy\prontidao-producao.php
git add tools\deploy\aplicar-release.php
git add tools\deploy\preflight.php
git add tests\critical\prontidao-producao.php
git add docs\deploy\PRONTIDAO-PRODUCAO.md
git add .github\workflows\quality.yml

php tools\ci-php-lint.php
php tests\critical\prontidao-producao.php
php tests\critical\deploy-recuperacao.php
php tests\critical\run.php
php tools\deploy\preflight.php
git status
```

Esperado no teste novo:

```text
OK: 8
FALHAS: 0
```

## Commit sugerido

```powershell
git commit -m "Deploy: adicionar gates finais de prontidao para producao"
git push
```
