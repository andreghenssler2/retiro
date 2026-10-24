<?php
/**
 * Retiro - limpeza dos dados de teste.
 * Executar SOMENTE via CLI após fazer backup do banco.
 * Uso:
 *   php database/limpar_dados_teste.php                 # prévia (sem alterações)
 *   php database/limpar_dados_teste.php --executar      # pede confirmação
 *
 * PRESERVA as configurações bancárias/Asaas, administradores, preferências
 * do sistema e o histórico de migrações. Não altera o Asaas externo.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

const TABELAS_DE_DADOS = [
    'atividades_usuarios',
    'asaas_webhook_eventos',
    'certificados_emitidos',
    'certificado_modelos',
    'eventos',
    'fila_emails',
    'financeiro',
    'inscricoes',
    'notificacoes',
    'notificacoes_lidas',
    'pagamentos',
    'solicitacoes_cancelamento_inscricao',
    'sistema_execucoes',
];

const TABELAS_PRESERVADAS = [
    'configuracoes_bancarias',  // Credenciais e preferências Asaas
    'schema_migrations',       // Migrações já aplicadas
    'tipoacesso',              // Permissões
    'titulo',                  // Configurações visuais gerais
    'email_config',            // Configuração de envio de e-mails
    'minha_comunidade',        // Cadastro estrutural de comunidades
    'notificacao_fontes',     // Configuração de fontes de notificação
];

function sairErro(string $mensagem): never {
    fwrite(STDERR, "[ERRO] {$mensagem}" . PHP_EOL);
    exit(1);
}

function nomeTabela(string $tabela): string {
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $tabela)) {
        sairErro('Nome de tabela inválido.');
    }
    return '`' . $tabela . '`';
}

$executar = in_array('--executar', $argv, true);
$parametrosValidos = ['--executar'];
foreach (array_slice($argv, 1) as $arg) {
    if (!in_array($arg, $parametrosValidos, true)) {
        sairErro('Parâmetro inválido. Use --executar ou execute sem parâmetros para prévia.');
    }
}

$arquivoConfig = dirname(__DIR__) . '/config/conn.php';
if (!is_file($arquivoConfig)) {
    sairErro('Não foi encontrado config/conn.php. Coloque este arquivo em database/.');
}
$config = require $arquivoConfig;
foreach (['host', 'database', 'username', 'password'] as $chave) {
    if (!is_array($config) || !isset($config[$chave])) {
        sairErro("Campo {$chave} ausente na configuração de banco.");
    }
}

try {
    $db = new PDO(
        sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['database'],
            $config['charset'] ?? 'utf8mb4'
        ),
        $config['username'],
        $config['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $tabelasBanco = [];
    $consulta = $db->query('SELECT TABLE_NAME, ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\'');
    foreach ($consulta as $item) {
        $tabelasBanco[$item['TABLE_NAME']] = strtoupper((string)$item['ENGINE']);
    }
    if (!isset($tabelasBanco['configuracoes_bancarias'])) {
        sairErro('Tabela configuracoes_bancarias não encontrada. Operação cancelada para proteger o Asaas.');
    }
    if (!isset($tabelasBanco['usuarios'])) {
        sairErro('Tabela usuarios não encontrada. Operação cancelada.');
    }
    if (!isset($tabelasBanco['schema_migrations'])) {
        sairErro('Tabela schema_migrations não encontrada. Verifique a instalação.');
    }

    $limpar = array_values(array_filter(TABELAS_DE_DADOS, static fn(string $t): bool => isset($tabelasBanco[$t])));
    $limpar[] = 'usuarios'; // Apaga apenas contas não administradoras.
    foreach ($limpar as $tabela) {
        if ($tabelasBanco[$tabela] !== 'INNODB') {
            sairErro("A tabela {$tabela} não usa InnoDB. Não é possível garantir reversão em caso de falha.");
        }
    }

    $usuarioAdmin = (int)$db->query('SELECT COUNT(*) FROM `usuarios` WHERE `tipo` = 1')->fetchColumn();
    if ($usuarioAdmin < 1) {
        sairErro('Nenhum administrador (usuarios.tipo = 1) encontrado. Para não bloquear o acesso, nada será apagado.');
    }

    // Só excluir tabelas conhecidas. Mostrar desconhecidas, sem tocá-las.
    $conhecidas = array_merge(TABELAS_DE_DADOS, TABELAS_PRESERVADAS, ['usuarios']);
    $naoMapeadas = array_values(array_diff(array_keys($tabelasBanco), $conhecidas));

    // Verificar FKs: não alterar tabelas preservadas que dependam de dados excluídos.
    $sqlFk = 'SELECT TABLE_NAME, REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE '
           . 'WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_SCHEMA = DATABASE() '
           . 'AND REFERENCED_TABLE_NAME IS NOT NULL';
    $relacoes = $db->query($sqlFk)->fetchAll();
    foreach ($relacoes as $relacao) {
        $filho = (string)$relacao['TABLE_NAME'];
        $pai = (string)$relacao['REFERENCED_TABLE_NAME'];
        if (in_array($pai, TABELAS_DE_DADOS, true) && in_array($pai, $limpar, true) && !in_array($filho, $limpar, true)) {
            sairErro("A tabela preservada {$filho} referencia {$pai}. Verifique a estrutura antes de excluir dados.");
        }
    }

    // Ordenar exclusão: primeiro tabelas filhas, depois pais, sem desligar FKs.
    $dependencias = array_fill_keys($limpar, []);
    foreach ($relacoes as $relacao) {
        $filho = (string)$relacao['TABLE_NAME'];
        $pai = (string)$relacao['REFERENCED_TABLE_NAME'];
        if ($filho !== $pai && isset($dependencias[$filho], $dependencias[$pai])) {
            $dependencias[$filho][$pai] = true;
        }
    }
    $ordem = [];
    $grafo = $dependencias;
    while ($grafo !== []) {
        $prontos = [];
        foreach ($grafo as $nome => $pais) {
            $temFilhoRestante = false;
            foreach ($grafo as $outra => $referencias) {
                if ($outra !== $nome && isset($referencias[$nome])) {
                    $temFilhoRestante = true;
                    break;
                }
            }
            if (!$temFilhoRestante) {
                $prontos[] = $nome;
            }
        }
        if ($prontos === []) {
            sairErro('Dependência circular entre tabelas. Limpeza cancelada, nenhuma alteração realizada.');
        }
        sort($prontos);
        foreach ($prontos as $nome) {
            $ordem[] = $nome;
            unset($grafo[$nome]);
        }
    }

    echo "=============================================\n";
    echo "RETIRO - LIMPEZA SEGURA DOS DADOS DE TESTE\n";
    echo "=============================================\n";
    echo 'Banco: ' . $config['database'] . "\n";
    echo 'Modo: ' . ($executar ? 'EXECUÇÃO (requer confirmação)' : 'PRÉVIA - nada será excluído') . "\n\n";
    $total = 0;
    foreach ($ordem as $tabela) {
        if ($tabela === 'usuarios') {
            $quantidade = (int)$db->query('SELECT COUNT(*) FROM `usuarios` WHERE `tipo` <> 1 OR `tipo` IS NULL')->fetchColumn();
        } else {
            $quantidade = (int)$db->query('SELECT COUNT(*) FROM ' . nomeTabela($tabela))->fetchColumn();
        }
        $total += $quantidade;
        printf("%-40s %10d\n", $tabela . ($tabela === 'usuarios' ? ' (não admins)' : ''), $quantidade);
    }
    echo "---------------------------------------------\n";
    echo "Total previsto: {$total} registros\n";
    echo "Administradores preservados: {$usuarioAdmin}\n";
    echo 'Tabelas de configurações preservadas: ' . implode(', ', TABELAS_PRESERVADAS) . "\n";
    if ($naoMapeadas !== []) {
        echo 'Tabelas adicionais NÃO alteradas: ' . implode(', ', $naoMapeadas) . "\n";
    }
    echo "\n";

    if (!$executar) {
        echo "[PRÉVIA] Nenhuma alteração foi feita.\n";
        echo "Para executar, faça backup e use: php database/limpar_dados_teste.php --executar\n";
        exit(0);
    }

    echo "ATENÇÃO: essa exclusão é irreversível sem backup.\n";
    echo "Confirme que fez backup e digitou o banco correto.\n";
    echo 'Para continuar, digite exatamente ZERAR RETIRO: ';
    $confirmacao = trim((string)fgets(STDIN));
    if ($confirmacao !== 'ZERAR RETIRO') {
        echo "[CANCELADO] Confirmação incorreta. Nenhum dado apagado.\n";
        exit(0);
    }

    $db->beginTransaction();
    try {
        foreach ($ordem as $tabela) {
            if ($tabela === 'usuarios') {
                $linhas = $db->exec('DELETE FROM `usuarios` WHERE `tipo` <> 1 OR `tipo` IS NULL');
            } else {
                $linhas = $db->exec('DELETE FROM ' . nomeTabela($tabela));
            }
            echo "[LIMPO] {$tabela}: {$linhas} linhas\n";
        }
        $db->commit();
    } catch (Throwable $erro) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $erro;
    }
    echo "\n[OK] Limpeza concluída. Configuração do Asaas, administradores e migrações preservados.\n";
    echo "[INFO] Arquivos de uploads, logs em disco e cobranças no Asaas não foram alterados.\n";

} catch (Throwable $erro) {
    sairErro($erro->getMessage());
}
