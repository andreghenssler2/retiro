<?php
declare(strict_types=1);

/** Prazo de registro financeiro, nunca prazo de cobrança do participante. */
final class PagamentosManuaisPrazoService
{
    private const LOCK = 'retiro_prazo_manual_admin_v1';
    private const TABELA = 'email_prazo_manual_admin';
    private const FUSO = 'America/Sao_Paulo';
    public function __construct(private PDO $db) {}

    public static function data(mixed $v): ?DateTimeImmutable
    {
        $s = trim((string)$v);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}(?:$|[ T]\d{2}:\d{2}:\d{2}$)/D', $s)) return null;
        $s = substr($s,0,10);
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s, new DateTimeZone(self::FUSO));
        return $d && $d->format('Y-m-d') === $s && substr($s,0,4) !== '0000' ? $d : null;
    }
    public static function prazo(array $linha): ?DateTimeImmutable
    {
        $inicio = self::data($linha['data_inicio'] ?? null);
        $fim = self::data($linha['data_fim'] ?? null);
        if (!$inicio) return null;
        if (!$fim) $fim = $inicio;
        if ($fim < $inicio) return null;
        return $fim->modify('+2 days');
    }
    public static function classificar(array $linha, string $hoje): ?array
    {
        $prazo = self::prazo($linha); $dia = self::data($hoje);
        if (!$prazo || !$dia || $dia <= $prazo->modify('-2 days')) return null;
        $linha['prazo_registro'] = $prazo->format('Y-m-d');
        $linha['situacao_registro'] = $dia > $prazo ? 'Prazo de registro ultrapassado' : ($dia == $prazo ? 'Último dia para registrar' : 'A registrar');
        $linha['conflito_asaas'] = ($linha['integracao'] ?? '') === 'Asaas' || trim((string)($linha['asaasPaymentId'] ?? '')) !== '';
        return $linha;
    }
    public function consultar(?string $hoje = null): array
    {
        $dia = $hoje ?? (new DateTimeImmutable('today', new DateTimeZone(self::FUSO)))->format('Y-m-d');
        if (!self::data($dia)) throw new InvalidArgumentException('Data inválida.');
        // Não limita a eventos ativos: um evento encerrado pode ter sido desativado.
        $sql = "SELECT i.idInscricao, p.idPagamento, e.titulo AS evento, e.data_inicio, e.data_fim,
                    COALESCE(NULLIF(TRIM(i.nome),''), NULLIF(TRIM(p.participante),''), 'Participante') AS nome,
                    p.formaPagamento AS forma, p.valor, p.integracao, p.asaasPaymentId
                FROM inscricoes i INNER JOIN eventos e ON e.idEvento=i.idEvento
                INNER JOIN pagamentos p ON p.idPagamento=(SELECT MAX(p2.idPagamento) FROM pagamentos p2 WHERE p2.idInscricao=i.idInscricao)
                WHERE i.status <> 'Cancelada' AND COALESCE(i.pagamento,'') <> 'Pago'
                  AND p.formaPagamento IN ('Dinheiro','Transferencia')
                  AND p.status IN ('Pendente','Vencido') AND p.dataPagamento IS NULL AND p.valor > 0
                  AND e.data_inicio < :hoje
                ORDER BY e.data_inicio ASC,e.titulo ASC,i.nome ASC,i.idInscricao ASC";
        $stmt = $this->db->prepare($sql); $stmt->execute([':hoje'=>$dia]);
        $resultado=[];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
            $v=self::classificar($linha,$dia); if ($v!==null) $resultado[]=$v;
        }
        return $resultado;
    }
    public static function renderizar(array $linhas, string $dia): string
    {
        $esc=static fn(mixed $v): string=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
        $data=static fn(mixed $v): string=>self::data($v)?->format('d/m/Y') ?? '—';
        $moeda=static fn(float $v): string=>'R$ '.number_format($v,2,',','.');
        $total=0.0; $atrasados=0; $trs='';
        foreach($linhas as $l){
            $total+=(float)$l['valor']; $atrasados+=($l['prazo_registro']<$dia)?1:0;
            $forma=$l['forma']==='Transferencia'?'Transferência':'Dinheiro';
            $obs=$l['conflito_asaas']?' — vínculo Asaas: conferir antes de marcar':'';
            $trs.='<tr><td>#'.(int)$l['idInscricao'].'</td><td>'.$esc($l['nome']).'</td><td>'.$esc($l['evento']).'</td><td>'.$esc($forma).'</td><td>'.$esc($moeda((float)$l['valor'])).'</td><td>'.$esc($data($l['prazo_registro'])).'</td><td>'.$esc($l['situacao_registro'].$obs).'</td></tr>';
        }
        return '<!doctype html><html lang="pt-BR"><meta charset="UTF-8"><body style="font-family:Arial,sans-serif;color:#24364a;padding:20px"><h2 style="color:#174b82">Registro de pagamentos manuais</h2><p>Retiro · '.$esc($data($dia)).'</p><p>Confira os recebimentos em dinheiro e transferência dos eventos encerrados. O prazo administrativo é até o segundo dia corrido após o término do evento.</p><p><strong>Pendências: '.count($linhas).' | Após o prazo: '.$atrasados.' | Total a conferir: '.$esc($moeda($total)).'</strong></p><table border="1" cellpadding="7" cellspacing="0" style="border-collapse:collapse;font-size:12px"><thead style="background:#e9eff7"><tr><th>Inscrição</th><th>Participante</th><th>Evento</th><th>Forma</th><th>Valor</th><th>Prazo de registro</th><th>Situação</th></tr></thead><tbody>'.$trs.'</tbody></table><p>Marque como Pago apenas valores efetivamente recebidos. Registros com vínculo Asaas precisam de conferência. Esta notificação não cancela inscrições e não constitui cobrança ao participante.</p><p><a href="https://retiro.ieclbparobe.com.br/admin/financeiro/pagamentos.php">Abrir financeiro do Retiro</a></p><p style="font-size:11px">Mensagem administrativa confidencial.</p></body></html>';
    }
    public static function instalar(PDO $db): void
    {
        $db->exec("CREATE TABLE IF NOT EXISTS email_prazo_manual_admin (dataReferencia DATE NOT NULL,destinatarioHash CHAR(64) CHARACTER SET ascii NOT NULL,tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,tentativaEm DATETIME NOT NULL,enviadoEm DATETIME NULL,PRIMARY KEY(dataReferencia,destinatarioHash)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $db->query('SELECT dataReferencia,destinatarioHash,tentativas,tentativaEm,enviadoEm FROM email_prazo_manual_admin LIMIT 0');
    }
    private function config(string $nome): array
    {
        $p=dirname(__DIR__,2).'/config/'.$nome.'.php';
        $cfg=is_file($p)?require $p:[]; return is_array($cfg)?$cfg:[];
    }
    public function processar(): array
    {
        $r=['enviados'=>0,'falhas'=>0];
        // Mesmo ambiente e destinatários do resumo administrativo existente.
        require_once __DIR__.'/ResumoPendenciasAdminService.php';
        if (ResumoPendenciasAdminService::ambienteLocal(dirname(__DIR__,2),PHP_OS_FAMILY)) return $r+['motivo'=>'Ambiente local'];
        $admin=$this->config('notificacoes_sistema');
        if (empty($admin['ativo'])) return $r+['motivo'=>'Alertas administrativos desativados'];
        if ((int)$this->db->query("SELECT GET_LOCK('".self::LOCK."',0)")->fetchColumn()!==1) return $r;
        try {
            $dia=(new DateTimeImmutable('today',new DateTimeZone(self::FUSO)))->format('Y-m-d');
            if ($this->consultar($dia)===[]) return $r;
            $emails=ResumoPendenciasAdminService::destinatarios($this->config('mail'),$admin);
            foreach($emails as $email){
                $chave=[':dia'=>$dia,':hash'=>hash('sha256',strtolower($email))];
                $stmt=$this->db->prepare('SELECT enviadoEm,tentativas,(tentativaEm <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)) AS podeTentar FROM email_prazo_manual_admin WHERE dataReferencia=:dia AND destinatarioHash=:hash');
                $stmt->execute($chave); $estado=$stmt->fetch(PDO::FETCH_ASSOC);
                if ($estado && (!empty($estado['enviadoEm']) || (int)$estado['tentativas']>=5 || (int)$estado['podeTentar']!==1)) continue;
                $linhas=$this->consultar($dia); if ($linhas===[]) break;
                $stmt=$this->db->prepare('INSERT INTO email_prazo_manual_admin(dataReferencia,destinatarioHash,tentativas,tentativaEm) VALUES(:dia,:hash,1,NOW()) ON DUPLICATE KEY UPDATE tentativas=tentativas+1,tentativaEm=NOW()');
                $stmt->execute($chave);
                try {
                    require_once dirname(__DIR__).'/mail/Mail.php';
                    $nome=trim((string)($admin['nome_destinatario']??'Administração do Retiro')) ?: 'Administração do Retiro';
                    if (!(new Mail())->send($email,$nome,'[Retiro] Registrar pagamentos manuais - '.self::data($dia)->format('d/m/Y'),self::renderizar($linhas,$dia))) throw new RuntimeException('SMTP não confirmou o envio.');
                    $stmt=$this->db->prepare('UPDATE email_prazo_manual_admin SET enviadoEm=NOW() WHERE dataReferencia=:dia AND destinatarioHash=:hash'); $stmt->execute($chave); $r['enviados']++;
                } catch(Throwable $e) { $r['falhas']++; error_log('[Retiro] Falha no aviso administrativo de pagamentos manuais.'); }
            }
            return $r;
        } finally { $this->db->query("SELECT RELEASE_LOCK('".self::LOCK."')"); }
    }
}
