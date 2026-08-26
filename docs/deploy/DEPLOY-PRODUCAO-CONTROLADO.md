# Fase 15 — Deploy de produção controlado

A Fase 15 adiciona um **wrapper operacional** para executar o primeiro deploy
real com as proteções das fases anteriores.

A instalação da Fase 15 **não executa deploy**.

## Princípio principal

O comando `deploy-producao.php` funciona em modo **PLANO** por padrão.

Mesmo com todos os parâmetros corretos, nenhum arquivo de produção é alterado
enquanto não forem fornecidos simultaneamente:

```text
--execute --confirm=DEPLOY-PRODUCAO
```

## Fluxo

O wrapper executa nesta ordem:

1. valida o ZIP da release;
2. executa `prontidao-producao.php`;
3. confere migrations antes do deploy;
4. bloqueia se houver migration pendente sem `--migrate`;
5. chama `aplicar-release.php` com `--keep-maintenance`;
6. mantém o site em manutenção após cópia/migrations/smoke;
7. executa `validar-pos-deploy.php`;
8. somente após tudo passar desativa a manutenção.

Se a validação final falhar, a manutenção permanece ativa.

Rollback de código **não é automático**, porque migrations podem ter sido
aplicadas e o rollback de código não restaura o banco.

## Primeiro: modo PLANO

No servidor de produção, primeiro rode **sem `--execute`**.

Exemplo com backup do banco disponível no servidor:

```bash
php tools/deploy/deploy-producao.php \
  --zip=/home/USUARIO/releases/retiro-X.zip \
  --backup-dir=/home/USUARIO/backups/retiro \
  --db-rotated=SIM \
  --db-backup=/home/USUARIO/backups/retiro/banco.sql.gz \
  --migrate
```

Exemplo quando o banco foi salvo pelo cPanel/phpMyAdmin:

```bash
php tools/deploy/deploy-producao.php \
  --zip=/home/USUARIO/releases/retiro-X.zip \
  --backup-dir=/home/USUARIO/backups/retiro \
  --db-rotated=SIM \
  --db-backup-confirm=CPANEL \
  --migrate
```

O resultado deve terminar em `PLANO APROVADO` e informar que nenhum arquivo foi
alterado.

## Execução real

Somente depois de revisar o plano, repita o mesmo comando acrescentando:

```text
--execute --confirm=DEPLOY-PRODUCAO
```

## Sem migrations

Se o status do banco não tiver `[PENDENTE]`, omita `--migrate`.

Mesmo assim o wrapper continua exigindo evidência de backup do banco para o gate
de prontidão.

## Pós-deploy

A validação final confere:

- versão e commit do `RELEASE-MANIFEST.json`;
- estado esperado da manutenção;
- migrations sem `[PENDENTE]` ou `[ALTERADA]`;
- preflight;
- smoke test;
- auditoria de dados persistentes.

O site só é reaberto depois dessa validação.

## Se houver falha

Não desligue a manutenção por impulso.

Primeiro preserve a saída do comando e identifique em qual etapa falhou.

Se o código precisar de rollback, use o ZIP de backup indicado na própria saída:

```bash
php tools/deploy/rollback-codigo.php \
  --backup=/CAMINHO/retiro-codigo-....zip \
  --confirm=ROLLBACK
```

Depois do rollback, a manutenção continua ativa.

**Importante:** rollback de código não restaura o banco. Se houve migration,
analise o backup SQL e a compatibilidade antes de qualquer restauração.

## Instalação/validação local da Fase 15

```powershell
git add tools\deploy\deploy-producao.php
git add tools\deploy\validar-pos-deploy.php
git add tools\deploy\aplicar-release.php
git add tests\critical\deploy-producao-controlado.php
git add docs\deploy\DEPLOY-PRODUCAO-CONTROLADO.md
git add .github\workflows\quality.yml

php tools\ci-php-lint.php
php tests\critical\deploy-producao-controlado.php
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
git commit -m "Deploy: adicionar execucao controlada de producao"
git push
```
