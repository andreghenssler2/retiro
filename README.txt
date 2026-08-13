EVENTOS - URL AMIGÁVEL V1
=========================

OBJETIVO

Trocar:

http://localhost/eventos/detalhe.php?slug=retiro-paroquial-2026

por:

http://localhost/eventos/retiro-paroquial-2026


COMO FUNCIONA

O banco continua usando o campo:

eventos.slug

A classe Evento já possui:

Evento::slug()
Evento::buscarPorSlug()
Evento::slugExisteOutro()

O Apache recebe:

/eventos/retiro-paroquial-2026

e internamente executa:

/eventos/detalhe.php?slug=retiro-paroquial-2026

A URL no navegador continua limpa.


REDIRECIONAMENTO DA URL ANTIGA

Se alguém abrir:

/eventos/detalhe.php?slug=retiro-paroquial-2026

o sistema redireciona com HTTP 301 para:

/eventos/retiro-paroquial-2026


ARQUIVOS ALTERADOS

.htaccess
index.php
eventos/index.php
eventos/detalhe.php
user/eventos.php


BANCO

Nenhuma nova tabela.

O instalador apenas verifica se existem eventos antigos sem slug.

Se encontrar:

Retiro Paroquial 2026

gera:

retiro-paroquial-2026

Se já existir, utiliza:

retiro-paroquial-2026-2
retiro-paroquial-2026-3
...


INSTALAÇÃO

Coloque na raiz:

atualizar-eventos-url-amigavel-v1.php

Execute:

php atualizar-eventos-url-amigavel-v1.php


XAMPP

É necessário que o Apache tenha:

mod_rewrite habilitado

e AllowOverride permita .htaccess.

Normalmente o XAMPP já possui mod_rewrite habilitado.


TESTE

1. Reinicie o Apache se necessário.
2. Ctrl + F5.
3. Abra:

http://localhost/eventos/retiro-paroquial-2026

4. Teste também:

http://localhost/eventos/detalhe.php?slug=retiro-paroquial-2026

A URL antiga deve redirecionar para a nova.


PRODUÇÃO

O mesmo padrão será:

https://seu-dominio.com/eventos/retiro-paroquial-2026
