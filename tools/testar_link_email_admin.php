<?php

declare(strict_types=1);

/**
 * Testa os links do e-mail administrativo em CLI sem banco ou SMTP.
 * Simula BASE_URL apontando para localhost, como acontece no cron atual.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);
$servico = $raiz . '/mod/services/EmailSistemaNotificacaoService.php';
if (!is_file($servico)) {
    fwrite(STDERR, "[ERRO] Serviço não encontrado.\n");
    exit(1);
}

// A aplicação pode definir http://localhost ao iniciar em modo CLI.
if (!defined('BASE_URL')) {
    define('BASE_URL', 'http://localhost/');
}

require_once $servico;
$classe = new ReflectionClass(EmailSistemaNotificacaoService::class);
$instancia = $classe->newInstanceWithoutConstructor();
$metodo = $classe->getMethod('mensagem');

$configuracao = $raiz . '/config/notificacoes_sistema.php';
$config = is_file($configuracao) ? require $configuracao : [];
$urlPublica = trim((string) (is_array($config) ? ($config['url_site'] ?? '') : ''));
if ($urlPublica === '') {
    $urlPublica = 'https://retiro.ieclbparobe.com.br/';
}

$destinos = [
    'Novo usuário' => 'admin/user/usuarios.php',
    'Nova inscrição' => 'admin/inscricao/inscricoes.php',
    'Pagamento confirmado' => 'admin/financeiro/pagamentos.php',
];

$falhas = 0;
echo "RETIRO - TESTE DOS LINKS ADMINISTRATIVOS\n";
echo "BASE_URL simulada: " . BASE_URL . "\n";
foreach ($destinos as $tipo => $caminho) {
    $msg = $metodo->invoke($instancia, $tipo, [], $caminho);
    $html = (string) ($msg['html'] ?? '');
    preg_match('/href="([^"]+)"/', $html, $matches);
    $link = html_entity_decode((string) ($matches[1] ?? ''), ENT_QUOTES, 'UTF-8');
    $esperado = rtrim($urlPublica, '/') . '/' . $caminho;
    $ok = $link === $esperado;
    printf("[%s] %s: %s\n", $ok ? 'OK' : 'ERRO', $tipo, $link);
    if (!$ok) {
        $falhas++;
    }
}

if ($falhas > 0) {
    fwrite(STDERR, "[ERRO] {$falhas} link(s) incorreto(s).\n");
    exit(1);
}

echo "[OK] Todos os links corretos. Nenhum e-mail foi enviado.\n";
