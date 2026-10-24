# Fase 11 — Dados persistentes e uploads seguros

O sistema grava arquivos enviados por usuários e administradores em diretórios
que não podem ser tratados como código de release.

## Diretórios/arquivos persistentes

A proteção de deploy cobre:

- `uploads/usuarios/` — fotos de perfil, preservando apenas `user.png` como
  asset estático da aplicação;
- `uploads/eventos/` — imagens dos eventos;
- `uploads/comunidades/` — imagens das comunidades;
- `uploads/comprovantes/` — comprovantes financeiros;
- `uploads/certificados/modelos/` — modelos enviados pelo administrador;
- `storage/certificados/` — certificados gerados;
- `theme/img/favicon.*` — favicon enviado pelo administrador;
- `theme/img/site-imagem.*` — imagem institucional enviada pelo administrador;
- credenciais privadas e marcador de manutenção já protegidos nas fases
  anteriores.

Esses arquivos não devem entrar no ZIP de release, no backup de código nem ser
apagados por rollback.

## Segurança de uploads

`uploads/.htaccess`:

- desabilita listagem de diretório;
- bloqueia acesso a extensões executáveis como PHP, PHAR, PHTML, CGI e scripts.

A validação MIME existente nas telas continua sendo a primeira barreira. O
`.htaccess` é uma defesa adicional no Apache/cPanel.

## Auditoria

Rode:

```powershell
php tools\deploy\auditar-dados-persistentes.php
```

O comando falha se encontrar dados persistentes rastreados pelo Git.

Se aparecer algo como:

```text
[ERRO] Dado persistente está rastreado pelo Git: uploads/eventos/...
```

não apague o arquivo físico. Apenas remova do índice Git:

```powershell
git rm --cached "caminho/do/arquivo"
```

O `.gitignore` impedirá que volte a ser rastreado.

## Preflight

O preflight também verifica:

- `uploads/` gravável;
- `theme/img/` gravável;
- presença de `uploads/.htaccess`.

## Validação

```powershell
git add .gitignore
git add uploads\.htaccess
git add tools\deploy\DeployUtil.php
git add tools\deploy\preflight.php
git add tools\deploy\auditar-dados-persistentes.php
git add tools\build-release.php
git add tests\critical\dados-persistentes.php
git add .github\workflows\quality.yml
git add docs\deploy\DADOS-PERSISTENTES.md

php tools\ci-php-lint.php
php tests\critical\run.php
php tests\critical\asaas-data-hora.php
php tests\critical\seguranca-http.php
php tests\critical\manutencao.php
php tests\critical\dados-persistentes.php
php tools\deploy\auditar-dados-persistentes.php
php tools\deploy\preflight.php
git status
```

Se a Fase 10 ainda não estiver no seu branch, o teste `manutencao.php` pode não
existir; nesse caso conclua primeiro a Fase 10.

## Commit sugerido

```powershell
git commit -m "Deploy: proteger dados persistentes e uploads"
git push
```
