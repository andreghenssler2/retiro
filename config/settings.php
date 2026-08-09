<?php

require_once __DIR__ . '/config.php';

$arquivoIntegracoes = __DIR__ . '/integracoes.php';
if (is_file($arquivoIntegracoes)) {
    require_once $arquivoIntegracoes;
}

Config::init();

// Conecta automaticamente com o banco correto
$db = Config::getDB();

$hoje = date('Y-m-d');
$time = date('H:i:s');

require __DIR__ . '/../mod/autoload.php';

/*
 * Registra automaticamente todas as requisições PHP realizadas
 * por usuários autenticados. O registro é executado no shutdown,
 * depois que a página conclui seu processamento.
 */
AtividadeUsuario::agendarRegistroRequisicao($db);

/*
 * Processa notificações automáticas por e-mail no final da
 * requisição:
 * - inscrição realizada;
 * - pagamento gerado;
 * - pago;
 * - vencido;
 * - cancelado;
 * - estornado.
 */
EmailNotificacaoService::agendarProcessamento($db);

// Config::startSecureSession();

?>
