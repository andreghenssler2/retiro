<?php
declare(strict_types=1);

// Somente CLI. Não carrega settings.php: não agenda e-mails no shutdown.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
$raiz = dirname(__DIR__);
$args = array_slice($argv, 1);
$inscricao = 0;
$confirmarId = '';
$regularizar = false;
foreach ($args as $arg) {
    if ($arg === '--regularizar') $regularizar = true;
    elseif (preg_match('/^--inscricao=([1-9][0-9]*)$/D', $arg, $m)) $inscricao = (int) $m[1];
    elseif (preg_match('/^--confirmar-id=(pay_[A-Za-z0-9_-]+)$/D', $arg, $m)) $confirmarId = $m[1];
    elseif ($arg !== '--consultar') { fwrite(STDERR, "Argumento inválido.\n"); exit(1); }
}
try {
    if ($regularizar && (!$inscricao || !$confirmarId)) throw new RuntimeException('Informe uma inscrição e o ID pay_ exibido no diagnóstico.');
    if (!is_file($raiz . '/config/conn.php')) throw new RuntimeException('Configuração do banco ausente.');
    require_once $raiz . '/config/config.php';
    if (is_file($raiz . '/config/integracoes.php')) require_once $raiz . '/config/integracoes.php';
    Config::init();
    require_once $raiz . '/mod/database/db.php';
    require_once $raiz . '/lib/vendor/autoload.php';
    require_once $raiz . '/mod/title.php';
    require_once $raiz . '/mod/auth/ConfiguracaoBancaria.php';
    require_once $raiz . '/mod/services/HttpClientService.php';
    require_once $raiz . '/mod/services/AsaasService.php';
    require_once $raiz . '/mod/services/PagamentoMeioSeguro.php';
    $db = Database::connect();
    $asaas = new AsaasService();
    if (!$asaas->estaConfigurado()) throw new RuntimeException('Asaas não configurado. Não foi realizada nenhuma alteração.');
    $amb = $asaas->ambiente();
    echo "Ambiente Asaas: $amb\n";
    $sql = "SELECT p.idPagamento, p.idInscricao, p.codigo, p.participante, p.formaPagamento, p.status, p.dataPagamento, p.integracao, p.asaasPaymentId, p.asaasCustomerId, p.valor, p.valorCobrancaAsaas, i.status AS inscricaoStatus, i.pagamento AS inscricaoPagamento, i.valor_pago, i.cpf FROM pagamentos p INNER JOIN inscricoes i ON i.idInscricao = p.idInscricao WHERE p.formaPagamento IN ('Dinheiro','Transferencia') AND (p.integracao = 'Asaas' OR COALESCE(p.asaasPaymentId,'') <> '')";
    $params = [];
    if ($inscricao) { $sql .= ' AND p.idInscricao = ?'; $params[] = $inscricao; }
    $st = $db->prepare($sql . ' ORDER BY p.idPagamento'); $st->execute($params); $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) { echo "Nenhum pagamento manual vinculado ao Asaas encontrado.\n"; exit(0); }
    // Prova de identidade forte: ID, referência exclusiva pelo código e CPF do cliente.
    $conferir = static function(array $p, array $c) use ($asaas): void {
        $id = (string) ($p['asaasPaymentId'] ?? '');
        $codigo = trim(preg_replace('/[^a-z0-9-]+/i', '-', strtolower((string) $p['codigo'])) ?? '', '-');
        $ref = strtolower(trim((string) ($c['externalReference'] ?? '')));
        if (!$codigo || !preg_match('/^\d{4}.+-pag-' . preg_quote(substr($codigo,0,60), '/') . '$/D', $ref)
            || ($c['id'] ?? '') !== $id || ($c['billingType'] ?? '') !== 'PIX') {
            throw new RuntimeException('A cobrança não foi identificada com segurança como o PIX deste pagamento.');
        }
        $valor = (float) ($p['valorCobrancaAsaas'] ?? $p['valor']);
        if ($valor <= 0 || abs($valor - (float) ($c['value'] ?? 0)) > 0.009) throw new RuntimeException('Valor da cobrança diferente.');
        $clienteId = (string) ($c['customer'] ?? '');
        if ($clienteId === '' || (trim((string) ($p['asaasCustomerId'] ?? '')) !== '' && $clienteId !== $p['asaasCustomerId'])) throw new RuntimeException('Cliente Asaas divergente.');
        $cliente = $asaas->consultarCliente($clienteId);
        $cpf = preg_replace('/\D/', '', (string) ($p['cpf'] ?? '')) ?? '';
        $cpfAsaas = preg_replace('/\D/', '', (string) ($cliente['cpfCnpj'] ?? '')) ?? '';
        if (strlen($cpf) !== 11 || !hash_equals($cpf, $cpfAsaas)) throw new RuntimeException('CPF divergente ou ausente. Não é seguro excluir.');
    };
    if (!$regularizar) {
        foreach ($rows as $p) {
            $id = (string) ($p['asaasPaymentId'] ?? '');
            echo "Inscrição #{$p['idInscricao']} | pagamento #{$p['idPagamento']} | {$p['codigo']} | {$p['formaPagamento']} | {$p['status']} | $id\n";
            if ($id === '') { echo "  Sem ID remoto: revisão manual necessária.\n"; continue; }
            try {
                $c = $asaas->consultarCobranca($id);
                echo '  Asaas: ' . ($c['billingType'] ?? '-') . ' | ' . ($c['status'] ?? '-') . ' | fatura ' . ($c['invoiceNumber'] ?? '-') . "\n";
                $conferir($p,$c);
                $safe = $p['formaPagamento'] === 'Dinheiro' && $p['status'] === 'Pendente' && $p['inscricaoStatus'] === 'Pendente' && $p['inscricaoPagamento'] === 'Pendente' && empty($p['dataPagamento']) && (float)$p['valor_pago'] == 0.0 && ($c['status'] ?? '') === 'PENDING' && empty($c['deleted']);
                echo $safe ? "  Elegível para regularização explícita (revalidação obrigatória).\n" : "  Não elegível automaticamente; conferir manualmente.\n";
            } catch (Throwable $e) { echo "  Não foi possível validar cobrança. Revisão manual.\n"; }
        }
        echo "Consulta concluída. Nenhum pagamento alterado; nenhum e-mail enviado por esta ferramenta.\n"; exit(0);
    }
    if (count($rows) !== 1 || $rows[0]['asaasPaymentId'] !== $confirmarId) throw new RuntimeException('A inscrição não possui exatamente uma cobrança conflitante com o ID confirmado.');
    // Não executar na cópia XAMPP com credenciais de produção.
    if ($amb === 'producao' && (PHP_OS_FAMILY === 'Windows' || str_contains(strtolower(str_replace('\\','/',$raiz)), '/xampp/'))) throw new RuntimeException('Operação de produção bloqueada no XAMPP.');
    $pId = (int) $rows[0]['idPagamento'];
    PagamentoMeioSeguro::comLock($db, $pId, function() use ($db,$asaas,$sql,$params,$conferir,$confirmarId,$pId,$raiz): void {
        $db->beginTransaction();
        $excluiu = false;
        try {
            $st = $db->prepare($sql . ' AND p.idPagamento = ? FOR UPDATE'); $st->execute(array_merge($params,[$pId])); $p = $st->fetch(PDO::FETCH_ASSOC);
            if (!$p || $p['asaasPaymentId'] !== $confirmarId || $p['formaPagamento'] !== 'Dinheiro' || $p['status'] !== 'Pendente' || $p['inscricaoStatus'] !== 'Pendente' || $p['inscricaoPagamento'] !== 'Pendente' || !empty($p['dataPagamento']) || (float)$p['valor_pago'] != 0.0) throw new RuntimeException('Estado local mudou ou não é pendente. Operação interrompida.');
            // Verificar colunas e preparar UPDATE antes de tocar no Asaas.
            $colunas = $db->query('SHOW COLUMNS FROM pagamentos')->fetchAll(PDO::FETCH_COLUMN);
            $campos = ['integracao' => "'Manual'", 'asaasPaymentId'=>'NULL','asaasCustomerId'=>'NULL','asaasStatus'=>'NULL','invoiceUrl'=>'NULL','bankSlipUrl'=>'NULL','boletoLinhaDigitavel'=>'NULL','pixQrCode'=>'NULL','pixCopiaCola'=>'NULL','pixExpiracao'=>'NULL','asaasPayload'=>'NULL','asaasAtualizadoEm'=>'NULL','dataVencimento'=>'NULL','valorCobrancaAsaas'=>'NULL','valorTaxaRepassada'=>'0'];
            $obrigatorios = ['integracao','asaasPaymentId','asaasCustomerId','asaasStatus','invoiceUrl','bankSlipUrl','boletoLinhaDigitavel','pixQrCode','pixCopiaCola','pixExpiracao','asaasPayload','asaasAtualizadoEm','dataVencimento'];
            if (array_diff($obrigatorios,$colunas)) throw new RuntimeException('Estrutura de pagamentos incompatível.');
            if (in_array('recebidoEm',$colunas,true)) {
                $st = $db->prepare('SELECT recebidoEm FROM pagamentos WHERE idPagamento = ?'); $st->execute([$pId]);
                if ($st->fetchColumn()) throw new RuntimeException('Existe data de recebimento. Revisão manual necessária.');
            }
            $sets=[]; foreach($campos as $k=>$v) if(in_array($k,$colunas,true)) $sets[]="`$k` = $v";
            $atualizar = $db->prepare('UPDATE pagamentos SET ' . implode(', ', $sets) . " WHERE idPagamento = ? AND asaasPaymentId = ? AND formaPagamento = 'Dinheiro' AND status = 'Pendente' AND dataPagamento IS NULL");
            $c = $asaas->consultarCobranca($confirmarId); $conferir($p,$c);
            if (($c['status'] ?? '') !== 'PENDING' || !empty($c['deleted']) || !empty($c['paymentDate']) || !empty($c['confirmedDate']) || !empty($c['clientPaymentDate'])) throw new RuntimeException('PIX não está comprovadamente aguardando pagamento. Operação bloqueada.');
            $base = PHP_OS_FAMILY === 'Windows' ? (getenv('USERPROFILE') ?: '') : dirname($raiz);
            if (!$base) throw new RuntimeException('Pasta privada de backup indisponível.');
            $pasta = $base . '/backups-retiro';
            if (!is_dir($pasta) && !mkdir($pasta,0700,true)) throw new RuntimeException('Não foi possível criar backup privado.');
            if (is_link($pasta)) throw new RuntimeException('Pasta de backup inválida.');
            $st = $db->prepare('SELECT * FROM pagamentos WHERE idPagamento = ?'); $st->execute([$pId]); $anterior = $st->fetch(PDO::FETCH_ASSOC);
            $backup = $pasta . '/pagamento-dinheiro-' . $pId . '-' . date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.json';
            $conteudo = json_encode(['pagamento'=>$anterior,'inscricao'=>$p['idInscricao'],'asaasId'=>$confirmarId,'fatura'=>$c['invoiceNumber']??null,'statusRemoto'=>$c['status']],JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
            $mask=umask(0077); try{ $n=file_put_contents($backup,$conteudo,LOCK_EX); }finally{umask($mask);} if($n!==strlen($conteudo)) throw new RuntimeException('Não foi possível gravar backup.'); @chmod($backup,0600);
            // Última leitura antes de excluir: o próprio Asaas ainda deve aceitar o DELETE.
            $c = $asaas->consultarCobranca($confirmarId); $conferir($p,$c);
            if (($c['status']??'') !== 'PENDING' || !empty($c['deleted']) || !empty($c['paymentDate']) || !empty($c['confirmedDate']) || !empty($c['clientPaymentDate'])) throw new RuntimeException('Estado remoto mudou. Nenhuma exclusão realizada.');
            if (!$asaas->excluirCobranca($confirmarId)) throw new RuntimeException('Asaas não confirmou a exclusão. Nenhuma atualização local.');
            $excluiu=true;
            $atualizar->execute([$pId,$confirmarId]);
            if ($atualizar->rowCount() !== 1) throw new RuntimeException('Não foi possível desvincular o registro local.');
            $db->commit();
            echo "[OK] PIX indevido excluído no Asaas; pagamento agora Manual/Dinheiro/Pendente. Inscrição preservada.\nBackup privado: $backup\n";
        } catch(Throwable $e){ if($db->inTransaction())$db->rollBack(); if($excluiu) fwrite(STDERR,"ATENÇÃO: Asaas confirmou exclusão, mas etapa local falhou. Não tente gerar outra cobrança; confira o backup e o status remoto.\n"); throw $e; }
    });
} catch(Throwable $e){ fwrite(STDERR,"[ERRO] " . $e->getMessage() . "\n"); exit(1); }
