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
require_once dirname(__DIR__) . '/mod/services/LembreteVencimentoEmailService.php';
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

// LEMBRETES_VENCIMENTO_AUTOMATICOS_V1
try {
    $lembretes = (new LembreteVencimentoEmailService($db))->processar();
    if (($lembretes['candidatos'] ?? 0) > 0 || ($lembretes['falhas'] ?? 0) > 0) {
        echo '[' . date('d/m/Y H:i:s') . '] Lembretes PIX/Boleto: '
            . json_encode($lembretes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    }
} catch (Throwable $erro) {
    $falhou = true;
    fwrite(STDERR, '[Retiro] Falha ao processar lembretes de vencimento: ' . $erro->getMessage() . PHP_EOL);
}
// RESUMO_PENDENCIAS_ADMIN_V1
try {
    require_once dirname(__DIR__) . '/mod/services/ResumoPendenciasAdminService.php';
    $resumoPendenciasAdmin = (new ResumoPendenciasAdminService($db))->processar();
    if (($resumoPendenciasAdmin['enviados'] ?? 0) > 0 || ($resumoPendenciasAdmin['falhas'] ?? 0) > 0) {
        echo '[' . date('d/m/Y H:i:s') . '] Resumo administrativo de pendencias: '
            . json_encode($resumoPendenciasAdmin, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
    if (($resumoPendenciasAdmin['falhas'] ?? 0) > 0) { $falhou = true; }
} catch (Throwable $erro) {
    $falhou = true;
    fwrite(STDERR, '[Retiro] Falha no resumo administrativo de pendencias: ' . $erro->getMessage() . PHP_EOL);
}
// RETIRO_PRAZO_MANUAL_ADMIN_V1
try {
    require_once dirname(__DIR__) . '/mod/services/PagamentosManuaisPrazoService.php';
    $prazoManual = (new PagamentosManuaisPrazoService($db))->processar();
    if (($prazoManual['enviados'] ?? 0) > 0 || ($prazoManual['falhas'] ?? 0) > 0) {
        echo '[' . date('d/m/Y H:i:s') . '] Prazo de registro manual: '
            . json_encode($prazoManual, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
    if (($prazoManual['falhas'] ?? 0) > 0) { $falhou = true; }
} catch (Throwable $erro) {
    $falhou = true;
    fwrite(STDERR, '[Retiro] Falha no aviso de prazo manual: ' . $erro->getMessage() . PHP_EOL);
}
exit($falhou ? 1 : 0);
