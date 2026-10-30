<?php

declare(strict_types=1);

/**
 * Diagnóstico de notificações administrativas do Retiro.
 * Uso: php tools/diagnosticar_email_admin.php [--testar-envio]
 *
 * Somente leitura, exceto --testar-envio, que envia uma única mensagem
 * ao destinatário administrativo configurado. Nunca imprime senhas/tokens.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

date_default_timezone_set('America/Sao_Paulo');

$raiz = dirname(__DIR__);
$testarEnvio = in_array('--testar-envio', $argv, true);

function linha(string $mensagem): void
{
    fwrite(STDOUT, $mensagem . PHP_EOL);
}

function mascararEmail(string $email): string
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return '[não configurado ou inválido]';
    }

    [$usuario, $dominio] = explode('@', $email, 2);
    return substr($usuario, 0, min(2, strlen($usuario))) . '***@' . $dominio;
}

function consultarContagem(PDO $db, string $sql): ?int
{
    try {
        return (int) $db->query($sql)->fetchColumn();
    } catch (Throwable $erro) {
        linha('  AVISO: consulta indisponível (' . get_class($erro) . ').');
        return null;
    }
}

function resultado(string $nome, ?int $valor): void
{
    linha('  ' . $nome . ': ' . ($valor === null ? 'indisponível' : $valor));
}

linha('===================================================');
linha('RETIRO - DIAGNÓSTICO DE E-MAILS ADMINISTRATIVOS');
linha('Executado em: ' . date('d/m/Y H:i:s') . ' (America/Sao_Paulo)');
linha('===================================================');

$arquivoCron = $raiz . '/cron/sincronizar-notificacoes.php';
$arquivoServico = $raiz . '/mod/services/EmailSistemaNotificacaoService.php';
$arquivoConfig = $raiz . '/config/notificacoes_sistema.php';
$arquivoGeral = $raiz . '/cron/geral.sh';

linha('');
linha('1) Arquivos instalados');
linha('  Cron unificado: ' . (is_file($arquivoGeral) ? 'OK' : 'AUSENTE'));
linha('  Serviço administrativo: ' . (is_file($arquivoServico) ? 'OK' : 'AUSENTE'));
linha('  Configuração de alertas: ' . (is_file($arquivoConfig) ? 'OK' : 'AUSENTE'));
linha('  Cron de notificações: ' . (is_file($arquivoCron) ? 'OK' : 'AUSENTE'));
$cronAtualizado = is_readable($arquivoCron)
    && str_contains((string) file_get_contents($arquivoCron), 'EmailSistemaNotificacaoService');
linha('  Cron chama envio administrativo: ' . ($cronAtualizado ? 'SIM' : 'NÃO — arquivo antigo!'));

$confAlerta = [];
try {
    $tmp = is_file($arquivoConfig) ? require $arquivoConfig : [];
    $confAlerta = is_array($tmp) ? $tmp : [];
} catch (Throwable $erro) {
    linha('  ERRO: configuração dos alertas não pode ser carregada: ' . get_class($erro));
}

$ativo = (bool) ($confAlerta['ativo'] ?? true);
$destinoEspecifico = trim((string) ($confAlerta['destinatario'] ?? ''));
linha('  Alertas habilitados: ' . ($ativo ? 'SIM' : 'NÃO'));

$configBanco = $raiz . '/config/conn.php';
if (!is_file($configBanco)) {
    linha('ERRO: config/conn.php não encontrado.');
    exit(1);
}

try {
    $cfg = require $configBanco;
    if (!is_array($cfg)) {
        throw new RuntimeException('config/conn.php não retorna array');
    }
    $db = new PDO(
        'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['database'] . ';charset=' . ($cfg['charset'] ?? 'utf8mb4'),
        (string) $cfg['username'],
        (string) $cfg['password'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (Throwable $erro) {
    linha('ERRO: não foi possível acessar o banco (' . get_class($erro) . ').');
    exit(1);
}

linha('');
linha('2) Configuração de e-mail');
try {
    $stmt = $db->query('SELECT host, username, remetente, ativo, selecionado FROM email_config WHERE selecionado = 1 ORDER BY idEmailConfig DESC LIMIT 1');
    $smtp = $stmt->fetch();
} catch (Throwable $erro) {
    linha('  ERRO: não foi possível consultar a configuração de SMTP (' . get_class($erro) . ').');
    $smtp = false;
}
$remetente = $smtp ? trim((string) ($smtp['username'] ?? '')) : '';
$destinatario = $destinoEspecifico !== '' ? $destinoEspecifico : $remetente;
linha('  Configuração SMTP selecionada: ' . ($smtp ? 'SIM' : 'NÃO'));
linha('  Servidor SMTP preenchido: ' . (!empty($smtp['host']) ? 'SIM' : 'NÃO'));
linha('  Remetente (mascarado): ' . mascararEmail($remetente));
linha('  Destinatário (mascarado): ' . mascararEmail($destinatario));
linha('  Origem do destinatário: ' . ($destinoEspecifico !== '' ? 'config/notificacoes_sistema.php' : 'conta SMTP selecionada'));
if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
    linha('  AÇÃO: configure um destinatário válido em config/notificacoes_sistema.php.');
}

linha('');
linha('3) Tabela e histórico dos alertas');
try {
    $tabelaExiste = (bool) $db->query("SHOW TABLES LIKE 'email_sistema_alertas'")->fetchColumn();
} catch (Throwable $erro) {
    $tabelaExiste = false;
}
linha('  Tabela email_sistema_alertas: ' . ($tabelaExiste ? 'OK' : 'AUSENTE — execute database/migrate.php migrate'));

if ($tabelaExiste) {
    foreach (['usuario', 'inscricao', 'pagamento'] as $tipo) {
        $q = $db->quote($tipo);
        resultado($tipo . ' / total', consultarContagem($db, "SELECT COUNT(*) FROM email_sistema_alertas WHERE tipo = $q"));
        resultado($tipo . ' / pendente', consultarContagem($db, "SELECT COUNT(*) FROM email_sistema_alertas WHERE tipo = $q AND enviadoEm IS NULL"));
        resultado($tipo . ' / falha', consultarContagem($db, "SELECT COUNT(*) FROM email_sistema_alertas WHERE tipo = $q AND enviadoEm IS NULL AND tentativas > 0"));
    }
    linha('  Últimas tentativas pendentes (sem dados pessoais):');
    try {
        $stmt = $db->query("SELECT id, tipo, idReferencia, tentativas, ultimaTentativaEm, ultimoErro FROM email_sistema_alertas WHERE enviadoEm IS NULL ORDER BY id DESC LIMIT 8");
        $rows = $stmt->fetchAll();
        if (!$rows) linha('    Nenhum alerta pendente.');
        foreach ($rows as $row) {
            $err = trim((string) ($row['ultimoErro'] ?? ''));
            $err = preg_replace('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', '[e-mail oculto]', $err) ?? '';
            linha(sprintf('    #%d %s ref=%d tentativas=%d ultimo=%s erro=%s',
                (int) $row['id'], (string) $row['tipo'], (int) $row['idReferencia'],
                (int) $row['tentativas'], (string) ($row['ultimaTentativaEm'] ?? '-'),
                ($err === '' ? '-' : substr($err, 0, 190))));
        }
    } catch (Throwable $erro) {
        linha('    Indisponível (' . get_class($erro) . ').');
    }
}

linha('');
linha('4) Fontes: contas/inscrições ainda sem alerta gerado');
if ($tabelaExiste) {
    resultado('  usuários sem controle de alerta', consultarContagem($db, "SELECT COUNT(*) FROM usuarios u LEFT JOIN email_sistema_alertas a ON a.tipo = 'usuario' AND a.idReferencia = u.id WHERE a.id IS NULL"));
    resultado('  inscrições sem controle de alerta', consultarContagem($db, "SELECT COUNT(*) FROM inscricoes i LEFT JOIN email_sistema_alertas a ON a.tipo = 'inscricao' AND a.idReferencia = i.idInscricao WHERE a.id IS NULL"));
    resultado('  pagamentos pagos sem controle de alerta', consultarContagem($db, "SELECT COUNT(*) FROM pagamentos p LEFT JOIN email_sistema_alertas a ON a.tipo = 'pagamento' AND a.idReferencia = p.idPagamento WHERE p.status = 'Pago' AND a.id IS NULL"));
}

linha('');
linha('5) Como interpretar');
linha('  * Tabela ausente: execute php database/migrate.php migrate');
linha('  * Cron antigo: reinstale cron/sincronizar-notificacoes.php do pacote anterior.');
linha('  * Sem alertas pendentes e sem novos: inscrições anteriores à migração foram marcadas como antigas.');
linha('  * Pendentes com falhas: consulte ultimoErro acima e logs do SMTP.');
linha('  * Sem pendentes, com e-mails marcados enviados: SMTP aceitou ou foram marcados como históricos pela migração.');
linha('  * Destinatário não esperado: configure config/notificacoes_sistema.php.');

if (!$testarEnvio) {
    linha('');
    linha('Diagnóstico de leitura concluído. Nenhum e-mail foi enviado por esta ferramenta.');
    linha('Para testar o SMTP e enviar UM e-mail ao destinatário configurado, execute:');
    linha('  /usr/local/bin/ea-php83 -q tools/diagnosticar_email_admin.php --testar-envio');
    exit(0);
}

linha('');
linha('6) TESTE EXPLÍCITO DE SMTP');
if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
    linha('  ERRO: destinatário inválido. Nenhuma mensagem enviada.');
    exit(2);
}
try {
    require_once $raiz . '/mod/mail/Mail.php';
    $mail = new Mail();
    $html = '<html><body><h2>Retiro — Teste de e-mail administrativo</h2><p>Teste solicitado pelo terminal em '
        . htmlspecialchars(date('d/m/Y H:i:s'), ENT_QUOTES, 'UTF-8')
        . '.</p><p>Esta mensagem não representa um cadastro nem pagamento real.</p></body></html>';
    $ok = $mail->send($destinatario, 'Administração do Retiro', '[Retiro] Teste de e-mail administrativo', $html);
    linha('  Resultado: ' . ($ok ? 'SMTP confirmou a aceitação do envio.' : 'SMTP não confirmou o envio; verifique mod/logs e configuração de e-mail.'));
    exit($ok ? 0 : 3);
} catch (Throwable $erro) {
    linha('  FALHA ao testar SMTP (' . get_class($erro) . '): ' . $erro->getMessage());
    exit(3);
}
