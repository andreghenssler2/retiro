# Fase 13 — Ensaio de release e recuperação

Esta fase adiciona validação operacional **sem fazer deploy em produção**.

## 1. Ensaio de release

Depois de gerar uma release:

```powershell
php tools\build-release.php
```

localize o ZIP mais recente:

```powershell
$release = Get-ChildItem .\dist\retiro-*.zip |
    Sort-Object LastWriteTime -Descending |
    Select-Object -First 1
```

e rode:

```powershell
php tools\deploy\ensaio-release.php --zip="$($release.FullName)"
```

O ensaio:

- valida o `RELEASE-MANIFEST.json`;
- valida tamanho e SHA-256 de todos os arquivos;
- rejeita caminhos protegidos;
- extrai somente em diretório temporário;
- executa `php -l` em todos os PHP da release;
- carrega o `lib/vendor/autoload.php`;
- verifica a cobertura dos principais dados persistentes;
- remove o staging ao final.

Ele **não altera o projeto nem a produção**.

## 2. Backup de código + verificação

Crie um diretório fora do projeto:

```powershell
New-Item -ItemType Directory C:\xampp\backups\retiro -Force
```

Gere um backup:

```powershell
php tools\deploy\backup-codigo.php --dest=C:\xampp\backups\retiro
```

Selecione o ZIP mais recente:

```powershell
$backup = Get-ChildItem C:\xampp\backups\retiro\retiro-codigo-*.zip |
    Sort-Object LastWriteTime -Descending |
    Select-Object -First 1
```

Valide sem restaurar:

```powershell
php tools\deploy\verificar-backup-codigo.php --backup="$($backup.FullName)"
```

O verificador confere:

- manifesto;
- caminhos seguros;
- ausência de arquivos protegidos;
- tamanhos;
- SHA-256;
- ausência de arquivos extras fora do manifesto.

## 3. Banco de dados

O utilitário existente `backup-banco.php` tenta usar `mysqldump` e recomenda
cPanel/phpMyAdmin quando o executável não estiver disponível.

Em produção Hostgator, prefira um backup do banco pelo cPanel/phpMyAdmin antes
de migrations. O rollback de código não desfaz alterações no banco.

## Validação da fase

```powershell
git add tools\deploy\DeployRecoveryValidator.php
git add tools\deploy\verificar-backup-codigo.php
git add tools\deploy\ensaio-release.php
git add tests\critical\deploy-recuperacao.php
git add docs\deploy\ENSAIO-RECUPERACAO.md
git add .github\workflows\quality.yml

php tools\ci-php-lint.php
php tests\critical\deploy-recuperacao.php
php tests\critical\run.php
php tools\deploy\preflight.php
git status
```

O teste novo deve terminar em:

```text
OK: 6
FALHAS: 0
```

## Antes do primeiro deploy real

Ainda é obrigatório confirmar explicitamente que a senha do banco que já esteve
exposta no histórico público foi trocada no Hostgator e que o `config/conn.php`
de produção usa a nova senha.

## Commit sugerido

```powershell
git commit -m "Deploy: adicionar ensaio de release e verificacao de backup"
git push
```
