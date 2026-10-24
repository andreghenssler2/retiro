# Deploy seguro em produção — Fase 7

Esta fase adiciona um fluxo controlado de release, backup, deploy e rollback.

## O que muda

- `tools/build-release.php` passa a gerar `RELEASE-MANIFEST.json` dentro do ZIP.
- Cada arquivo da release recebe SHA-256 no manifesto.
- O ZIP final recebe um arquivo lateral `.sha256`.
- O deploy valida o ZIP antes de copiar qualquer arquivo.
- O deploy cria backup de código automaticamente.
- Credenciais e dados dinâmicos são preservados.
- Rollback de código exige confirmação explícita.
- Backup do banco pode ser feito via `mysqldump` quando disponível.

## 1. Gerar a release

Na raiz do projeto:

```bat
php tools\build-release.php
```

Serão gerados:

```text
dist/retiro-VERSAO-buildN.zip
dist/retiro-VERSAO-buildN.zip.sha256
```

O workflow **Gerar Pacote de Producao** também passa a publicar o ZIP e o
checksum como artefatos.

## 2. Validar a release antes do envio

```bat
php tools\deploy\verificar-release.php --zip=dist\retiro-VERSAO-buildN.zip
```

A validação recusa release com:

- `config/conn.php`;
- `config/integracoes.php`;
- `config/.bancario.key`;
- `.git` / `.github`;
- logs;
- backups `.bak/.backup`;
- arquivos fora do manifesto;
- checksum divergente.

## 3. Preflight no servidor

No servidor, antes de aplicar uma release:

```bash
php tools/deploy/preflight.php
```

O preflight não altera o banco. Ele verifica PHP, extensões, arquivos privados,
Composer, permissões e status das migrations.

## 4. Diretório privado de backups

Use sempre um diretório fora da pasta pública do site. Exemplo genérico:

```bash
mkdir -p /home/USUARIO/backups-retiro
chmod 700 /home/USUARIO/backups-retiro
```

Backup de código:

```bash
php tools/deploy/backup-codigo.php --dest=/home/USUARIO/backups-retiro
```

Credenciais, logs e arquivos dinâmicos não entram nesse ZIP.

## 5. Backup do banco

Antes de qualquer migration em produção, gere um backup do banco pelo cPanel ou
phpMyAdmin.

Se `mysqldump` estiver disponível no terminal:

```bash
php tools/deploy/backup-banco.php --dest=/home/USUARIO/backups-retiro
```

O script não coloca a senha na linha de comando: usa um arquivo temporário que é
removido ao final. O dump é sensível e deve permanecer fora da pasta pública.

## 6. Aplicar a release

Coloque o ZIP em um diretório privado/temporário e execute:

```bash
php tools/deploy/aplicar-release.php \
  --zip=/home/USUARIO/releases/retiro-VERSAO-buildN.zip \
  --backup-dir=/home/USUARIO/backups-retiro \
  --confirm=DEPLOY
```

O comando:

1. valida todo o ZIP e seus hashes;
2. cria backup do código atual;
3. extrai em staging temporário;
4. remove somente arquivos obsoletos pertencentes à release anterior;
5. copia a nova release;
6. preserva credenciais e dados dinâmicos.

Nesse modo, migrations **não** são executadas automaticamente.

Depois:

```bash
php database/migrate.php status
```

Se houver migrations pendentes e o backup do banco já estiver confirmado:

```bash
php database/migrate.php migrate
php tools/smoke-test.php
```

### Deploy com migrations automáticas

Use somente quando o backup do banco já estiver pronto:

```bash
php tools/deploy/aplicar-release.php \
  --zip=/home/USUARIO/releases/retiro-VERSAO-buildN.zip \
  --backup-dir=/home/USUARIO/backups-retiro \
  --confirm=DEPLOY \
  --migrate
```

## 7. Rollback de código

O deploy imprime o caminho do backup gerado. Para restaurar:

```bash
php tools/deploy/rollback-codigo.php \
  --backup=/home/USUARIO/backups-retiro/retiro-codigo-AAAAMMDD-HHMMSS.zip \
  --confirm=ROLLBACK
```

Depois:

```bash
php database/migrate.php status
php tools/smoke-test.php
```

**O rollback acima restaura somente código.** Se uma migration alterou o banco
de forma incompatível, restaure o backup SQL correspondente pelo cPanel,
phpMyAdmin ou procedimento de banco da hospedagem.

## 8. Checklist pós-deploy

No terminal:

```bash
php tools/smoke-test.php
php tools/verificar-seguranca-repositorio.php
```

No navegador, confirme pelo menos:

- login;
- painel;
- eventos;
- inscrição pública;
- painel administrativo;
- financeiro;
- Saúde do Sistema.

## Regras importantes

Não envie `config/conn.php` pelo Git ou dentro da release.
Não execute migration de produção sem backup de banco.
Não guarde SQL/ZIP de backup dentro da pasta pública.
Não substitua o deploy por um ZIP bruto do repositório.
