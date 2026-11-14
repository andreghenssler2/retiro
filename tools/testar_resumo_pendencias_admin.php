<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once dirname(__DIR__) . '/mod/services/ResumoPendenciasAdminService.php';
function checar(bool $ok, string $msg): void {
    if (!$ok) { fwrite(STDERR, '[FALHA] ' . $msg . PHP_EOL); exit(1); }
    echo '[OK] ' . $msg . PHP_EOL;
}
$hoje = '2026-11-14';
$linhas = [
    ['idInscricao'=>1,'nome'=>'Ana <Silva>','evento'=>'Evento A','forma'=>'PIX','valor'=>100.50,'vencimento'=>'2026-11-13','status'=>'Pendente'],
    ['idInscricao'=>2,'nome'=>'Bruno','evento'=>'Evento B','forma'=>'Cartao','valor'=>50,'vencimento'=>'2026-11-17','status'=>'Pendente'],
    ['idInscricao'=>3,'nome'=>'Celia','evento'=>'Evento B','forma'=>'NaoDefinido','valor'=>10,'vencimento'=>null,'status'=>'Pendente'],
];
checar(ResumoPendenciasAdminService::situacao($linhas[0], $hoje) === 'Vencido', 'Prazo passado sinalizado');
checar(ResumoPendenciasAdminService::situacao($linhas[1], $hoje) === 'Pendente', 'Prazo futuro pendente');
checar(ResumoPendenciasAdminService::situacao($linhas[2], $hoje) === 'Pendente', 'Sem prazo permanece pendente');
checar(ResumoPendenciasAdminService::situacao(['status'=>'Vencido'], $hoje) === 'Vencido', 'Status Vencido preservado');
$html = ResumoPendenciasAdminService::renderizar($linhas, $hoje, 2);
checar(str_contains($html, 'Ana &lt;Silva&gt;') && !str_contains($html, 'Ana <Silva>'), 'Dados em HTML escapados');
checar(str_contains($html, 'R$ 160,50') && str_contains($html, 'Cartão') && str_contains($html, 'Não definida'), 'Valores e formas corretos');
checar(str_contains($html, 'https://retiro.ieclbparobe.com.br/admin/financeiro/pagamentos.php') && !str_contains($html, 'localhost'), 'Link oficial de produção');
checar(str_contains(ResumoPendenciasAdminService::renderizar([], $hoje, 1), 'Nenhum pagamento pendente'), 'Lista vazia');
checar(ResumoPendenciasAdminService::destinatarios(['from_email'=>'admin@example.org'],['destinatario'=>'ADMIN@example.org']) === ['ADMIN@example.org'], 'Destinatarios iguais sem duplicacao');
checar(count(ResumoPendenciasAdminService::destinatarios(['from_email'=>'admin@example.org'],['destinatario'=>'copia@example.org'])) === 2, 'Dois destinatarios distintos');
checar(ResumoPendenciasAdminService::ambienteLocal('C:\\xampp\\htdocs', 'Windows'), 'Bloqueio de envio no XAMPP/Windows');
checar(ResumoPendenciasAdminService::ambienteLocal('/opt/lampp/xampp/htdocs', 'Linux'), 'Bloqueio de pasta XAMPP');
if (in_array('--consultar', $argv, true)) {
    $cfg = require dirname(__DIR__) . '/config/conn.php';
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['database'], $cfg['charset'] ?? 'utf8mb4');
    $db = new PDO($dsn, $cfg['username'], $cfg['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    echo '[CONSULTA] ' . json_encode((new ResumoPendenciasAdminService($db))->simular(), JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
echo '[OK] Nenhum e-mail enviado. Nenhuma inscricao ou pagamento alterado.' . PHP_EOL;
