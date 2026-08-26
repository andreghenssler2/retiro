# Fase 9 — Segurança HTTP e sessão

Esta fase ativa uma baseline de segurança para todas as páginas que carregam
`config/settings.php`, sem introduzir uma Content Security Policy restritiva que
possa quebrar JavaScript, Font Awesome, Bootstrap ou integrações existentes.

## O que muda

### Sessão

A sessão passa a usar:

- `session.use_strict_mode = 1`;
- somente cookies;
- `session.use_trans_sid = 0`;
- cookie `HttpOnly`;
- `SameSite=Lax`;
- cookie `Secure` quando HTTPS for detectado;
- detecção de HTTPS direta ou atrás de proxy por `X-Forwarded-Proto`.

O login já regenera o ID da sessão após autenticação; esta fase preserva esse
comportamento.

### Headers HTTP

São enviados nas respostas PHP:

```text
X-Content-Type-Options: nosniff
X-Frame-Options: SAMEORIGIN
Referrer-Policy: strict-origin-when-cross-origin
Permissions-Policy: camera=(), microphone=(), geolocation=()
```

Em produção HTTPS também é enviado:

```text
Strict-Transport-Security: max-age=15552000; includeSubDomains
```

HSTS não é enviado em `localhost`.

## O que não foi ativado deliberadamente

Não foi adicionada uma CSP rígida nesta fase. Uma CSP deve ser implantada depois
de inventariar todos os scripts, estilos, fontes, imagens e endpoints externos,
preferencialmente começando em `Content-Security-Policy-Report-Only`.

Também não é aplicado `Cache-Control: no-store` globalmente, porque isso pode
interferir com páginas que atualmente dependem do comportamento de cache do
navegador.

## Instalação

Na raiz:

```powershell
php atualizar-seguranca-http-sessao-v1.php
```

Depois:

```powershell
git add config\config.php
git add config\SegurancaHttp.php
git add tests\critical\seguranca-http.php
git add .github\workflows\quality.yml
git add docs\seguranca\SEGURANCA-HTTP-SESSAO.md

php tools\ci-php-lint.php
php tests\critical\run.php
php tests\critical\asaas-data-hora.php
php tests\critical\seguranca-http.php
php tools\deploy\preflight.php
git status
```

O novo teste deve terminar com:

```text
OK: 6
FALHAS: 0
```

## Teste no navegador

Após instalar, abra o sistema local normalmente e confirme login/logout e
navegação. Em produção, depois do deploy, confirme os headers HTTP pelo DevTools
do navegador ou pelo painel de diagnóstico da hospedagem.

## Commit sugerido

```powershell
git commit -m "Seguranca: fortalecer headers HTTP e sessao"
git push
```
