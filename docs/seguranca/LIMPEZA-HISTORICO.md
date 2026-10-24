# Limpeza segura do histórico Git — V1.1

> Não execute esta limpeza na pasta `C:\xampp\htdocs`.

A auditoria V1.1 separa migrations SQL legítimas de dumps/snapshots de banco.
**Não use mais `--path-glob "*.sql"`**, pois isso apagaria migrations que fazem
parte do código-fonte do projeto.

## 1. Auditar

```bat
php tools\auditar-historico-seguranca.php
```

Os itens relevantes para remoção são:

- segredos/credenciais por caminho;
- logs antigos;
- backups `.bak` / `.backup`;
- dumps/snapshots de banco conhecidos.

Arquivos como `migrations/*.sql`, `sql/*.sql`, `logs/.gitkeep` e
`logs/.htaccess` não são mais tratados como erro apenas pela extensão/pasta.

## 2. Trocar credenciais antes da reescrita

Qualquer senha/token/chave que tenha existido em repositório público deve ser
considerado comprometido. Troque primeiro no provedor e atualize os arquivos
privados localmente e no servidor.

## 3. Criar clone espelho separado

```bat
cd C:\
mkdir git-limpeza
cd git-limpeza
git clone --mirror https://github.com/andreghenssler2/retiro.git retiro-clean.git
cd retiro-clean.git
```

## 4. Instalar git-filter-repo

```bat
py -m pip install git-filter-repo
git filter-repo --version
```

## 5. Comando base de limpeza

Ajuste os caminhos exatos conforme a auditoria. Para o histórico já observado,
o conjunto seguro conhecido é:

```bat
git filter-repo --force --invert-paths ^
  --path config/conn.php ^
  --path mod/database/banco.sql ^
  --path mod/database/ieclbp28_retiro.sql ^
  --path mod/database/migrations/sistema_completo_v03082026.sql ^
  --path mod/logs/.limpeza.lock ^
  --path mod/logs/.ultima-limpeza ^
  --path-glob "*.log" ^
  --path-glob "*.bak" ^
  --path-glob "*.bak-*" ^
  --path-glob "*.backup" ^
  --path lib/vendor ^
  --path arquivos ^
  --path portal_ieclb_parobe ^
  --path-glob "*.zip" ^
  --path-glob "atualizar-*.php"
```

### Não remova automaticamente

Não inclua glob global para `*.sql`. Os SQL abaixo são migrations/schema e
podem ser código legítimo:

- `calendar/migracao-calendar-token.sql`
- `migrations/email_config_selecionado.sql`
- `mod/database/migrations/*.sql` (exceto snapshot completo listado acima)
- `mod/mail/migrations/*.sql`
- `sql/*.sql`

Dois arquivos merecem revisão manual antes de decidir se ficam no histórico:

- `mod/mail/migrations/teste_pagamento_1.sql`
- `mod/mail/migrations/diagnostico_notificacoes.sql`

Se eles contiverem somente estrutura/dados fictícios, podem permanecer. Se
contiverem dados reais, adicione `--path <arquivo>` ao comando de limpeza.

## 6. Validar ANTES do push

```bat
git rev-list --objects --all | findstr /I /C:"config/conn.php"
git rev-list --objects --all | findstr /I /C:"mod/database/ieclbp28_retiro.sql"
git rev-list --objects --all | findstr /I /C:"mod/database/banco.sql"
```

Nenhum desses comandos deve retornar caminho.

Confira também que migrations legítimas continuam presentes no branch:

```bat
git ls-tree -r --name-only HEAD | findstr /I /C:"mod/database/migrations/"
```

## 7. Push destrutivo somente depois da validação

`git filter-repo` normalmente remove o remote `origin`.

```bat
git remote -v
```

Se necessário:

```bat
git remote add origin https://github.com/andreghenssler2/retiro.git
```

Somente depois de todas as verificações:

```bat
git push --force --mirror origin
```

## 8. Novo clone de desenvolvimento

Depois da reescrita, não continue usando o clone antigo:

```bat
cd C:\xampp
ren htdocs htdocs-antes-limpeza
git clone https://github.com/andreghenssler2/retiro.git htdocs
```

Recoloque somente os arquivos privados necessários. Não copie a pasta `.git`
antiga.
