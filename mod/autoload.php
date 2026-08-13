<?php

$composerAutoload = __DIR__ . '/../lib/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

if (PHP_VERSION_ID < 50600) {
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
    }

    $err = 'Composer 2.3.0 dropped support for autoloading on PHP <5.6 and you are running '
        . PHP_VERSION
        . ', please upgrade PHP or use Composer 2.2 LTS via "composer self-update --2.2". Aborting.'
        . PHP_EOL;

    if (!ini_get('display_errors')) {
        if (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg') {
            fwrite(STDERR, $err);
        } elseif (!headers_sent()) {
            echo $err;
        }
    }

    trigger_error($err, E_USER_ERROR);
}

require_once __DIR__ . '/classes/logs.php';
require_once __DIR__ . '/auth/Auth.php';
require_once __DIR__ . '/auth/Session.php';
require_once __DIR__ . '/auth/Middleware.php';
require_once __DIR__ . '/auth/Usuario.php';
require_once __DIR__ . '/auth/Comunidade.php';
require_once __DIR__ . '/auth/AtividadeUsuario.php';
require_once __DIR__ . '/auth/Evento.php';
require_once __DIR__ . '/auth/Inscricao.php';
require_once __DIR__ . '/auth/SolicitacaoCancelamentoInscricao.php';
require_once __DIR__ . '/auth/Credenciamento.php';
require_once __DIR__ . '/auth/Pagamento.php';
require_once __DIR__ . '/auth/Certificado.php';
require_once __DIR__ . '/auth/ConfiguracaoBancaria.php';
require_once __DIR__ . '/services/HttpClientService.php';
require_once __DIR__ . '/services/AsaasService.php';
require_once __DIR__ . '/services/AsaasPagamentoService.php';
require_once __DIR__ . '/services/BoletoVencidoService.php';
require_once __DIR__ . '/services/PagamentoWebhookService.php';
require_once __DIR__ . '/services/EmailNotificacaoService.php';
require_once __DIR__ . '/services/EventoInscricaoPublicaConfig.php';
require_once __DIR__ . '/services/InscricaoPublicaService.php';
require_once __DIR__ . '/services/CancelamentoInscricaoNotificacaoService.php';
require_once __DIR__ . '/services/CertificadoService.php';
require_once __DIR__ . '/auth/DashboardEvento.php';
require_once __DIR__ . '/auth/Financeiro.php';
require_once __DIR__ . '/auth/RelatorioGeral.php';
require_once __DIR__ . '/FooterHTML.php';
require_once __DIR__ . '/HeaderHTML.php';

// require_once __DIR__ . '/header/header.php';
require_once __DIR__ . '/header/navbar.php';

require_once __DIR__ . '/title.php';
require_once __DIR__ . '/mail/Mail.php';
require_once __DIR__ . '/tempo_logado.php';

$tempo = new tempo_logado();
