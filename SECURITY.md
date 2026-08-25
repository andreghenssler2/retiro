# Política de Segurança

## Arquivos que nunca devem ser versionados

```text
config/conn.php
config/integracoes.php
config/.bancario.key
*.log
*.bak
*.bak-*
.env
.env.*
```

Use arquivos `*.example.php` apenas com valores fictícios.

## Credencial exposta

Se uma senha, token ou chave for publicada:

1. considere a credencial comprometida;
2. altere/revogue a credencial;
3. atualize a nova credencial apenas no ambiente;
4. remova o arquivo do índice Git;
5. remova o segredo também do histórico Git.

Apagar um arquivo em um commit novo não remove o conteúdo dos commits antigos.

## Logs

Use `/logs/`. O diretório deve permanecer bloqueado pelo Apache e fora do Git.
