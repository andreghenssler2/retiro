# Fase 12 — Segurança de autenticação e recuperação de senha

Esta fase reduz abuso automatizado no login e na recuperação de senha sem criar
uma migration de banco.

## Login

O login passa a limitar:

- 5 falhas para a mesma combinação e-mail + IP em uma janela de 15 minutos;
- 25 falhas globais no mesmo IP em 15 minutos;
- bloqueio temporário de 15 minutos quando o limite é atingido.

Um login válido limpa apenas o bucket e-mail + IP correspondente. Ele não apaga
o histórico global daquele IP.

O IP usado é `REMOTE_ADDR`. Cabeçalhos como `X-Forwarded-For` não são aceitos
automaticamente, porque seriam falsificáveis sem uma lista explícita de proxies
confiáveis.

## Recuperação de senha

A recuperação passa a limitar:

- 3 solicitações por e-mail em 30 minutos;
- 10 solicitações por IP em 30 minutos.

A tela sempre responde de forma genérica:

```text
Se o e-mail existir em nossa base, você receberá uma mensagem para redefinir sua senha.
```

Assim, a página deixa de informar diretamente se uma conta existe.

## Tokens de reset

O token enviado no link continua sendo aleatório, mas o banco passa a guardar
somente:

```text
SHA-256(token)
```

`buscarPorToken()` também aplica SHA-256 antes da consulta. Portanto, uma cópia
do banco não expõe diretamente links de redefinição ainda válidos.

Como consequência, links de redefinição emitidos **antes** desta atualização
deixam de funcionar. Basta solicitar um novo link.

## Armazenamento local

Os buckets ficam em:

```text
storage/seguranca/rate-limit/
```

Os nomes são hashes SHA-256 e os JSONs guardam apenas timestamps. E-mail e IP não
são armazenados em texto puro.

`storage/seguranca/.htaccess` bloqueia acesso HTTP direto à pasta.

## Validação

```powershell
git add .gitignore
git add login\index.php
git add login\recuperar.php
git add mod\auth\Usuario.php
git add mod\autoload.php
git add mod\services\AutenticacaoRateLimitService.php
git add storage\seguranca\.htaccess
git add tools\deploy\DeployUtil.php
git add tools\deploy\preflight.php
git add tests\critical\autenticacao-seguranca.php
git add .github\workflows\quality.yml
git add docs\seguranca\AUTENTICACAO.md

php tools\ci-php-lint.php
php tests\critical\run.php
php tests\critical\asaas-data-hora.php
php tests\critical\seguranca-http.php
php tests\critical\manutencao.php
php tests\critical\dados-persistentes.php
php tests\critical\autenticacao-seguranca.php
php tools\deploy\preflight.php
git status
```

O teste novo deve terminar em:

```text
OK: 8
FALHAS: 0
```

## Teste manual

Faça algumas tentativas inválidas no login e confirme que a quinta falha passa a
bloquear novas tentativas temporariamente.

Na recuperação de senha, use um e-mail inexistente e confirme que a interface
não informa se a conta existe.

## Commit sugerido

```powershell
git commit -m "Seguranca: limitar tentativas de autenticacao e proteger reset"
git push
```
