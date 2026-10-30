<?php

declare(strict_types=1);

/**
 * Sincroniza o sino do portal e envia alertas administrativos por e-mail.
 * Este arquivo ja e invocado pelo cron/geral.sh a cada minuto.
 * Nao cadastrar um segundo cron para os e-mails.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Acesso permitido somente pela linha de comando.');
}

date_default_timezone_set('America/Sao_Paulo');

try {
    require_once dirname(__DIR__) . '/config/settings.php';
    require_once dirname(__DIR__) . '/mod/auth/Notificacao.php';
    require_once dirname(__DIR__) . '/mod/services/EmailSistemaNotificacaoService.php';
} catch (Throwable $erro) {
    fwrite(STDERR, '[Retiro] Inicializacao falhou: ' . $erro->getMessage() . PHP_EOL);
    exit(1);
}

$falhou = false;

try {
    (new Notificacao($db))->sincronizar();
    echo '[' . date('d/m/Y H:i:s') . '] Notificacoes do portal sincronizadas.' . PHP_EOL;
} catch (Throwable $erro) {
    $falhou = true;
    fwrite(STDERR, '[Retiro] Falha na sincronizacao do portal: ' . $erro->getMessage() . PHP_EOL);
}

try {
    $resultado = (new EmailSistemaNotificacaoService($db))->processar();
    echo '[' . date('d/m/Y H:i:s') . '] E-mails administrativos: '
        . json_encode($resultado, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        . PHP_EOL;
} catch (Throwable $erro) {
    $falhou = true;
    fwrite(STDERR, '[Retiro] Falha nas notificacoes por e-mail: ' . $erro->getMessage() . PHP_EOL);
}

exit($falhou ? 1 : 0);
