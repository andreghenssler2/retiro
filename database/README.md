# Migrations do banco

As alterações de estrutura do banco passam a ser versionadas em:

```text
database/migrations/
```

## Ver o status

```bash
php database/migrate.php status
```

## Aplicar pendentes

```bash
php database/migrate.php migrate
```

A execução cria automaticamente:

```text
schema_migrations
```

Essa tabela registra:

- ID da migration;
- descrição;
- checksum SHA-256;
- data/hora de execução;
- tempo de execução.

## Regra importante

Depois que uma migration for aplicada, **não edite o arquivo antigo**.

Crie outra migration.

O runner compara o checksum do arquivo com o checksum gravado no banco e interrompe se detectar alteração em migration já executada.

## Nome dos arquivos

Use:

```text
AAAAMMDDHHMMSS_descricao.php
```

Exemplo:

```text
20260825190000_adicionar_campo_exemplo.php
```

## Estrutura

```php
<?php

declare(strict_types=1);

return [
    "descricao" => "Adicionar campo exemplo",

    "up" => static function (PDO $db): void {
        $db->exec("
            ALTER TABLE tabela
            ADD COLUMN exemplo VARCHAR(100) NULL
        ");
    }
];
```

## Produção

Antes do deploy:

```bash
php database/migrate.php status
```

Após enviar os arquivos:

```bash
php database/migrate.php migrate
```

O `migrate.php` funciona somente via CLI.

O diretório `/database/` também possui `.htaccess` bloqueando acesso pelo navegador.
