<?php
/**
 * RETIRO - Reset de dados de teste E configurações operacionais.
 * CLI apenas. Prévia por padrão; --executar requer duas confirmações.
 *
 * Não apaga o código PHP, o banco, as tabelas, administradores,
 * schema_migrations, tipoacesso ou config/conn.php. Sem esses itens
 * não seria possível voltar a configurar o Retiro com segurança.
 *
 * Não toca em cobranças/clientes reais na plataforma Asaas.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit(1);
}
date_default_timezone_set('America/Sao_Paulo');

const REINICIO_DADOS = [
    'atividades_usuarios', 'asaas_webhook_eventos', 'certificados_emitidos',
    'certificado_modelos', 'email_sistema_alertas', 'eventos',
    'evento_termos', 'evento_camiseta_opcoes', 'fila_emails', 'financeiro',
    'inscricoes', 'inscricao_publica_fluxos', 'inscricao_dados_adicionais',
    'inscricao_termos_aceites', 'notificacoes', 'notificacoes_lidas',
    'pagamentos', 'solicitacoes_cancelamento_inscricao', 'sistema_execucoes',
];
const REINICIO_CONFIG = [
    'email_config', 'titulo', 'minha_comunidade',
];
const ESTRUTURA_MANTIDA = [
    'schema_migrations', 'tipoacesso', 'notificacao_fontes',
];
const BANCO_DESATIVADO = '!removida';

function erroReset(string $texto): never
{
    fwrite(STDERR, '[BLOQUEADO] ' . $texto . PHP_EOL);
    exit(1);
}
function tab(string $nome): string
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $nome)) {
        erroReset('Nome de tabela inválido.');
    }
    return '`' . $nome . '`';
}
function quantidade(PDO $db, string $tabela): int
{
    return (int) $db->query('SELECT COUNT(*) FROM ' . tab($tabela))->fetchColumn();
}

$args = array_slice($argv, 1);
if (array_diff($args, ['--executar']) !== [] || count($args) > 1) {
    erroReset('Use sem argumentos para a prévia, ou --executar para a exclusão confirmada.');
}
$executar = in_array('--executar', $args, true);
$raiz = dirname(__DIR__);
$arquivoBanco = $raiz . '/config/conn.php';
$arquivoAvisos = $raiz . '/config/notificacoes_sistema.php';
$arquivoIntegracoes = $raiz . '/config/integracoes.php';
$arquivoChave = $raiz . '/config/.bancario.key';

if (!is_file($arquivoBanco)) {
    erroReset('config/conn.php não encontrado.');
}
$config = require $arquivoBanco;
if (!is_array($config)) {
    erroReset('config/conn.php inválido.');
}
foreach (['host', 'database', 'username', 'password'] as $campo) {
    if (!array_key_exists($campo, $config)) {
        erroReset('Campo ausente em config/conn.php: ' . $campo);
    }
}

try {
    $db = new PDO(
        'mysql:host=' . $config['host'] . ';dbname=' . $config['database']
        . ';charset=' . ($config['charset'] ?? 'utf8mb4'),
        (string) $config['username'], (string) $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $bancoAtual = (string) $db->query('SELECT DATABASE()')->fetchColumn();
    if ($bancoAtual === '' || $bancoAtual !== (string) $config['database']) {
        erroReset('Banco selecionado não corresponde à configuração.');
    }

    $tabelas = [];
    $consulta = $db->query("SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'");
    foreach ($consulta as $linha) {
        $tabelas[(string) $linha['TABLE_NAME']] = strtoupper((string) $linha['ENGINE']);
    }
    foreach (['usuarios', 'configuracoes_bancarias', 'schema_migrations', 'tipoacesso'] as $tabela) {
        if (!isset($tabelas[$tabela])) {
            erroReset('Tabela essencial ausente: ' . $tabela . '. Restauração requer revisão.');
        }
    }
    $admins = (int) $db->query('SELECT COUNT(*) FROM usuarios WHERE tipo = 1 AND ativo = 1')->fetchColumn();
    if ($admins < 1) {
        erroReset('Nenhum administrador ativo encontrado. A limpeza não pode retirar o último acesso.');
    }

    $listaDados = array_values(array_filter(REINICIO_DADOS, fn($v) => isset($tabelas[$v])));
    $listaConfig = array_values(array_filter(REINICIO_CONFIG, fn($v) => isset($tabelas[$v])));
    $alvos = array_merge($listaDados, $listaConfig, ['usuarios']);
    $conhecidas = array_merge(REINICIO_DADOS, REINICIO_CONFIG, ESTRUTURA_MANTIDA, ['usuarios', 'configuracoes_bancarias']);
    $outras = array_values(array_diff(array_keys($tabelas), $conhecidas));
    sort($outras);
    $outrasComDados = [];
    foreach ($outras as $nome) {
        $q = quantidade($db, $nome);
        if ($q !== 0) {
            $outrasComDados[$nome] = $q;
        }
    }

    foreach (array_merge($alvos, ['configuracoes_bancarias', 'notificacao_fontes']) as $nome) {
        if (isset($tabelas[$nome]) && $tabelas[$nome] !== 'INNODB') {
            erroReset('Tabela ' . $nome . ' não é InnoDB. Exclusão reversível não pode ser assegurada.');
        }
    }

    $colunasBanco = [];
    foreach ($db->query('SHOW COLUMNS FROM configuracoes_bancarias') as $linha) {
        $colunasBanco[] = (string) $linha['Field'];
    }
    $camposChave = [
        'asaas_sandbox_api_key_enc', 'asaas_sandbox_webhook_token_enc',
        'asaas_producao_api_key_enc', 'asaas_producao_webhook_token_enc',
    ];
    $camposExigidos = array_merge(['id', 'asaas_ativo', 'asaas_ambiente', 'asaas_referencia_prefixo'], $camposChave);
    foreach ($camposExigidos as $campo) {
        if (!in_array($campo, $colunasBanco, true)) {
            erroReset('Configuração bancária incompleta (' . $campo . '). Corrija a migração antes.');
        }
    }

    $adminsComComunidade = 0;
    $vinculoAdminNullable = true;
    $colunaComunidade = $db->query("SHOW COLUMNS FROM usuarios LIKE 'idComunidade'")->fetch(PDO::FETCH_ASSOC);
    if (is_array($colunaComunidade)) {
        $adminsComComunidade = (int) $db->query(
            'SELECT COUNT(*) FROM usuarios WHERE tipo = 1 AND idComunidade IS NOT NULL'
        )->fetchColumn();
        $vinculoAdminNullable = strtoupper((string) $colunaComunidade['Null']) === 'YES';
    }

    $rels = $db->query(
        'SELECT TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_SCHEMA = DATABASE() '
        . 'AND REFERENCED_TABLE_NAME IS NOT NULL'
    )->fetchAll();
    $bloqueiosFK = [];
    foreach ($rels as $r) {
        $filho = (string) $r['TABLE_NAME'];
        $pai = (string) $r['REFERENCED_TABLE_NAME'];
        if (!in_array($pai, $alvos, true) || in_array($filho, $alvos, true)) {
            continue;
        }
        // Vínculo do admin à comunidade será explicitamente anulado.
        if ($filho === 'usuarios' && $pai === 'minha_comunidade' && $vinculoAdminNullable) {
            continue;
        }
        $bloqueiosFK[] = "$filho -> $pai";
    }
    if ($adminsComComunidade > 0 && !$vinculoAdminNullable && in_array('minha_comunidade', $alvos, true)) {
        $bloqueiosFK[] = 'usuarios.idComunidade não aceita NULL; não é seguro apagar comunidades';
    }

    $dependencias = array_fill_keys($alvos, []);
    foreach ($rels as $r) {
        $f = (string) $r['TABLE_NAME'];
        $p = (string) $r['REFERENCED_TABLE_NAME'];
        if ($f !== $p && isset($dependencias[$f], $dependencias[$p])) {
            $dependencias[$f][$p] = true;
        }
    }
    $ordem = [];
    while ($dependencias !== []) {
        $finais = [];
        foreach ($dependencias as $nome => $pais) {
            $temFilhos = false;
            foreach ($dependencias as $outro => $ref) {
                if ($outro !== $nome && isset($ref[$nome])) {
                    $temFilhos = true;
                    break;
                }
            }
            if (!$temFilhos) {
                $finais[] = $nome;
            }
        }
        if (!$finais) {
            $bloqueiosFK[] = 'Relações circulares entre tabelas a excluir';
            break;
        }
        sort($finais);
        foreach ($finais as $nome) {
            $ordem[] = $nome;
            unset($dependencias[$nome]);
        }
    }

    $arquivoExtraLegado = is_file($arquivoIntegracoes);
    $bloqueios = [];
    if ($arquivoExtraLegado) {
        $bloqueios[] = 'Existe config/integracoes.php: revise/remova credenciais legadas após backup (este arquivo NÃO será alterado).';
    }
    if ($outrasComDados) {
        $bloqueios[] = 'Existem tabelas adicionais com dados não classificados.';
    }
    foreach ($bloqueiosFK as $l) {
        $bloqueios[] = 'Chave estrangeira que exige revisão: ' . $l;
    }
    if (!is_writable(dirname($arquivoAvisos)) || (file_exists($arquivoAvisos) && !is_writable($arquivoAvisos))) {
        $bloqueios[] = 'Sem permissão para redefinir config/notificacoes_sistema.php.';
    }
    if (file_exists($arquivoChave) && (!is_writable($arquivoChave) || !is_writable(dirname($arquivoChave)))) {
        $bloqueios[] = 'Sem permissão para remover config/.bancario.key.';
    }

    echo "======================================================\n";
    echo "RETIRO - RESET DE DADOS DE TESTE + CONFIGURAÇÕES\n";
    echo "======================================================\n";
    echo 'Banco: ' . $bancoAtual . PHP_EOL;
    echo 'Modo: ' . ($executar ? 'EXECUÇÃO COM CONFIRMAÇÃO' : 'PRÉVIA - NADA SERÁ ALTERADO') . PHP_EOL;
    echo 'Administradores ativos preservados: ' . $admins . PHP_EOL . PHP_EOL;

    $total = 0;
    foreach ($ordem as $nome) {
        $num = $nome === 'usuarios'
            ? (int) $db->query('SELECT COUNT(*) FROM usuarios WHERE tipo <> 1 OR tipo IS NULL')->fetchColumn()
            : quantidade($db, $nome);
        $total += $num;
        printf("  %-41s %8d\n", $nome === 'usuarios' ? 'usuarios (somente não administradores)' : $nome, $num);
    }
    printf("  %-41s %8d\n", 'configuracoes_bancarias (reset para inativo)', quantidade($db, 'configuracoes_bancarias'));
    echo 'Registros previstos para remoção: ' . $total . PHP_EOL;
    echo 'Banco Asaas: chaves apagadas no Retiro e integração DESATIVADA.' . PHP_EOL;
    echo 'Avisos admin: destinatários redefinidos; notificações DESATIVADAS até configurar SMTP.' . PHP_EOL;
    echo 'Arquivos: chave local Asaas removida; config/notificacoes_sistema.php redefinido.' . PHP_EOL;
    echo 'Mantidos: admins, banco de dados, tipos de acesso, migrations, config/conn.php e código PHP.' . PHP_EOL;
    if ($outras) {
        echo PHP_EOL . 'Tabelas adicionais identificadas (NÃO alteradas):' . PHP_EOL;
        foreach ($outras as $t) {
            echo '  - ' . $t . ': ' . ($outrasComDados[$t] ?? 0) . ' linhas' . PHP_EOL;
        }
    }
    if ($bloqueios) {
        echo PHP_EOL . 'IMPEDIMENTOS PARA EXECUÇÃO:' . PHP_EOL;
        foreach ($bloqueios as $b) {
            echo '  ! ' . $b . PHP_EOL;
        }
    }
    echo PHP_EOL;

    if (!$executar) {
        echo "[PRÉVIA] Nenhuma alteração realizada. Confira backups e bloqueios antes de executar.\n";
        echo "[INFO] Após resolver impedimentos e fazer backup, use --executar.\n";
        exit(0);
    }
    if ($bloqueios) {
        erroReset('Execução não iniciada. Resolva os impedimentos apresentados na prévia.');
    }

    echo "CONFIRA: backups do banco e de config/ devem estar guardados fora do site.\n";
    echo 'Digite exatamente BACKUP CONFIRMADO: ';
    if (trim((string) fgets(STDIN)) !== 'BACKUP CONFIRMADO') {
        erroReset('Confirmação de backup ausente. Nada foi excluído.');
    }
    echo 'Digite exatamente APAGAR TESTES E CONFIGURACOES: ';
    if (trim((string) fgets(STDIN)) !== 'APAGAR TESTES E CONFIGURACOES') {
        erroReset('Confirmação final incorreta. Nada foi excluído.');
    }

    // Arquivo temporário preparado antes da transação; rename somente após commit.
    $textoConfigAvisos = <<<'AVISOS'
<?php

declare(strict_types=1);

// Configuração reiniciada. Reconfigure SMTP e os destinatários antes de ativar.
return [
    'ativo' => false,
    'destinatario' => '',
    'nome_destinatario' => 'Administração do Retiro',
];
AVISOS;
    $temporario = tempnam(dirname($arquivoAvisos), '.retiro-reset-');
    if ($temporario === false || file_put_contents($temporario, $textoConfigAvisos . PHP_EOL) === false) {
        if ($temporario !== false) {
            @unlink($temporario);
        }
        erroReset('Não foi possível preparar config/notificacoes_sistema.php. Nada foi excluído.');
    }
    @chmod($temporario, 0640);

    try {
        $db->beginTransaction();

        // Os administradores sobreviventes não podem referenciar comunidades removidas.
        if (is_array($colunaComunidade) && $vinculoAdminNullable && $adminsComComunidade > 0) {
            $db->exec('UPDATE usuarios SET idComunidade = NULL WHERE tipo = 1');
        }
        foreach ($ordem as $tabela) {
            if ($tabela === 'usuarios') {
                $qtd = $db->exec('DELETE FROM usuarios WHERE tipo <> 1 OR tipo IS NULL');
            } else {
                $qtd = $db->exec('DELETE FROM ' . tab($tabela));
            }
            echo '[REMOVIDO] ' . $tabela . ': ' . $qtd . PHP_EOL;
        }

        // A linha bancária mínima é necessária para impedir fallback
        // automático a chaves de ambiente/arquivos antigos.
        $db->exec('DELETE FROM configuracoes_bancarias WHERE id <> 1');
        $db->exec("INSERT IGNORE INTO configuracoes_bancarias (id, asaas_ativo, asaas_ambiente) VALUES (1, 0, 'sandbox')");
        $setBank = [
            'asaas_ativo = 0', "asaas_ambiente = 'sandbox'", 'asaas_referencia_prefixo = NULL',
            'asaas_sandbox_api_key_enc = ' . $db->quote(BANCO_DESATIVADO),
            'asaas_sandbox_webhook_token_enc = ' . $db->quote(BANCO_DESATIVADO),
            'asaas_producao_api_key_enc = ' . $db->quote(BANCO_DESATIVADO),
            'asaas_producao_webhook_token_enc = ' . $db->quote(BANCO_DESATIVADO),
        ];
        if (in_array('atualizado_por', $colunasBanco, true)) {
            $setBank[] = 'atualizado_por = NULL';
        }
        if (in_array('atualizado_em', $colunasBanco, true)) {
            $setBank[] = 'atualizado_em = NOW()';
        }
        $db->exec('UPDATE configuracoes_bancarias SET ' . implode(', ', $setBank) . ' WHERE id = 1');
        echo "[OK] Asaas desativado e credenciais internas inutilizadas.\n";

        if (isset($tabelas['notificacao_fontes'])) {
            $db->exec('UPDATE notificacao_fontes SET sincronizadoEm = NOW()');
        }
        if (isset($tabelas['email_sistema_alertas'])) {
            $db->exec(<<<'SQL'
                INSERT IGNORE INTO email_sistema_alertas (tipo, idReferencia, criadoEm, enviadoEm)
                SELECT 'usuario', id, NOW(), NOW() FROM usuarios WHERE tipo = 1
            SQL);
        }
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        @unlink($temporario);
        throw $e;
    }

    if (!@rename($temporario, $arquivoAvisos)) {
        @unlink($temporario);
        echo "[ATENÇÃO] Banco limpo, mas não foi possível redefinir config/notificacoes_sistema.php. Corrija manualmente!\n";
    } else {
        echo "[OK] Destinatários e alertas administrativos redefinidos e desativados.\n";
    }
    if (is_file($arquivoChave)) {
        if (!@unlink($arquivoChave)) {
            echo "[ATENÇÃO] Não foi possível remover config/.bancario.key. Exclua manualmente após preservar o backup.\n";
        } else {
            echo "[OK] Chave de criptografia Asaas antiga removida.\n";
        }
    }
    echo "\n[CONCLUÍDO] Dados de teste e configurações removidos. Admin e estrutura mantidos.\n";
    echo "[PRÓXIMO] Entre como administrador e refaça: site, comunidades, SMTP, avisos e Asaas.\n";
    echo "[AVISO] Não reative cron/inscrições antes de configurar SMTP e integrações.\n";
    echo "[AVISO] Arquivos de uploads, PDFs, logs e registros EXTERNOS do Asaas não foram excluídos.\n";
} catch (Throwable $e) {
    erroReset($e->getMessage());
}
