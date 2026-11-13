<?php

declare(strict_types=1);

/**
 * Corrige APENAS a montagem do link nos e-mails administrativos.
 * Uso:
 *   php tools/corrigir_link_email_admin.php           # previa
 *   php tools/corrigir_link_email_admin.php --aplicar # backup + alteracao
 *
 * Nunca envia e-mails; nao acessa o banco de dados.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);
$arquivo = $raiz . '/mod/services/EmailSistemaNotificacaoService.php';
$aplicar = in_array('--aplicar', $argv, true);
foreach (array_slice($argv, 1) as $arg) {
    if ($arg !== '--aplicar') {
        fwrite(STDERR, "Parâmetro inválido. Use --aplicar ou nenhum parâmetro.\n");
        exit(1);
    }
}

if (!is_file($arquivo) || !is_readable($arquivo)) {
    fwrite(STDERR, "[ERRO] Serviço não encontrado: {$arquivo}\n");
    exit(1);
}

$conteudo = file_get_contents($arquivo);
if (!is_string($conteudo)) {
    fwrite(STDERR, "[ERRO] Não foi possível ler o arquivo.\n");
    exit(1);
}

// Bloco existente no serviço. O substituímos sem mexer na lógica de envio,
// cópias ocultas, fila, pagamentos, inscrições ou na configuração SMTP.
$anterior = <<<'ORIGINAL'
        $link = defined('BASE_URL')
            ? rtrim((string) BASE_URL, '/') . '/' . ltrim($destino, '/')
            : '';
ORIGINAL;

$novo = <<<'CORRIGIDO'
        // No CRON (PHP CLI), HTTP_HOST não existe. A BASE_URL global pode
        // ser http://localhost, mesmo no servidor de produção.
        // Use uma URL canônica independente do host da requisição.
        // Opcional: definir 'url_site' em config/notificacoes_sistema.php.
        $baseUrlEmail = trim((string) ($this->configuracao()['url_site'] ?? ''));
        if ($baseUrlEmail === '') {
            $baseUrlEmail = 'https://retiro.ieclbparobe.com.br/';
        }

        $partesUrl = parse_url($baseUrlEmail);
        $protocolo = is_array($partesUrl)
            ? strtolower((string) ($partesUrl['scheme'] ?? ''))
            : '';
        $dominio = is_array($partesUrl)
            ? strtolower((string) ($partesUrl['host'] ?? ''))
            : '';
        $ehLocal = in_array($dominio, ['localhost', '127.0.0.1', '::1'], true);
        if (
            $dominio === ''
            || !in_array($protocolo, ['https', 'http'], true)
            || ($protocolo !== 'https' && !$ehLocal)
            || isset($partesUrl['user'], $partesUrl['pass'])
            || isset($partesUrl['query'])
            || isset($partesUrl['fragment'])
        ) {
            throw new RuntimeException('URL pública inválida para os e-mails administrativos.');
        }

        $link = rtrim($baseUrlEmail, '/') . '/' . ltrim($destino, '/');
CORRIGIDO;

// Corrige validação de user/pass individualmente (não permitir só user ou só pass)
$novo = str_replace(
    "isset(\$partesUrl['user'], \$partesUrl['pass'])",
    "isset(\$partesUrl['user'])\n            || isset(\$partesUrl['pass'])",
    $novo
);

$jaCorrigido = str_contains($conteudo, '$baseUrlEmail = trim((string) ($this->configuracao()');
$padraoExistente = substr_count($conteudo, $anterior);

if ($jaCorrigido && $padraoExistente === 0) {
    echo "[OK] O link já utiliza a URL pública canônica. Nenhuma alteração necessária.\n";
    exit(0);
}

if ($padraoExistente !== 1) {
    fwrite(STDERR, "[BLOQUEADO] O trecho esperado não foi encontrado exatamente uma vez.\n"
        . "O arquivo pode ter sido personalizado. Nenhuma alteração efetuada.\n");
    exit(1);
}

echo "=============================================\n";
echo "RETIRO - CORREÇÃO DO LINK DOS E-MAILS\n";
echo "=============================================\n";
echo "Arquivo: mod/services/EmailSistemaNotificacaoService.php\n";
echo "Antes: BASE_URL depende do host HTTP e pode ser localhost no cron.\n";
echo "Depois: https://retiro.ieclbparobe.com.br/ (ou url_site configurada).\n";
echo "SMTP, destinatários, banco, cron e Asaas: não alterados.\n";

if (!$aplicar) {
    echo "\n[PRÉVIA] Nada foi alterado. Para aplicar, use --aplicar.\n";
    exit(0);
}

$alterado = str_replace($anterior, $novo, $conteudo);
$dirBackup = dirname($raiz) . '/backups-retiro';
if (!is_dir($dirBackup) && !mkdir($dirBackup, 0700, true) && !is_dir($dirBackup)) {
    fwrite(STDERR, "[ERRO] Não foi possível criar pasta privada de backups.\n");
    exit(1);
}
@chmod($dirBackup, 0700);

$backup = $dirBackup . '/EmailSistemaNotificacaoService-' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.php.bak';
if (!copy($arquivo, $backup)) {
    fwrite(STDERR, "[ERRO] Não foi possível criar o backup. Nada foi alterado.\n");
    exit(1);
}
@chmod($backup, 0600);

$tmp = tempnam(dirname($arquivo), '.email-url-');
if ($tmp === false) {
    fwrite(STDERR, "[ERRO] Não foi possível criar arquivo temporário. Backup preservado.\n");
    exit(1);
}

try {
    if (file_put_contents($tmp, $alterado, LOCK_EX) !== strlen($alterado)) {
        throw new RuntimeException('Falha ao gravar arquivo temporário.');
    }
    @chmod($tmp, fileperms($arquivo) & 0777);

    // Testa a sintaxe antes de substituir o arquivo em produção.
    $saida = [];
    $status = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $saida, $status);
    if ($status !== 0) {
        throw new RuntimeException('Falha de sintaxe: ' . implode(' | ', $saida));
    }

    if (!rename($tmp, $arquivo)) {
        throw new RuntimeException('Falha ao substituir o arquivo.');
    }
    echo "\n[OK] Correção aplicada após teste de sintaxe PHP.\n";
    echo "[OK] Backup privado: {$backup}\n";
    echo "[PRÓXIMO] Execute o teste: php tools/testar_link_email_admin.php\n";
} catch (Throwable $erro) {
    if (is_file($tmp)) {
        @unlink($tmp);
    }
    fwrite(STDERR, '[ERRO] ' . $erro->getMessage() . "\n");
    exit(1);
}
