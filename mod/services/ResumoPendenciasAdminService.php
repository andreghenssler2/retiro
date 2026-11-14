<?php

declare(strict_types=1);

/** Resumo administrativo independente dos e-mails dos participantes. */
final class ResumoPendenciasAdminService
{
    private const LOCK = 'retiro_resumo_pendencias_admin_v1';
    private DateTimeZone $fuso;

    public function __construct(private PDO $db)
    {
        $this->fuso = new DateTimeZone('America/Sao_Paulo');
    }

    private function config(string $nome): array
    {
        $arquivo = dirname(__DIR__, 2) . '/config/' . $nome . '.php';
        if (!is_file($arquivo)) { return []; }
        $cfg = require $arquivo;
        return is_array($cfg) ? $cfg : [];
    }

    public static function ambienteLocal(string $pasta, string $sistema): bool
    {
        return $sistema === 'Windows' || (bool) preg_match('~/(?:xampp|wamp|wamp64|laragon|mamp)(?:/|$)~i', str_replace('\\', '/', $pasta));
    }

    /** Reutiliza os dois enderecos dos alertas administrativos; sem duplicar. */
    public static function destinatarios(array $smtp, array $admin): array
    {
        $lista = [];
        foreach ([$smtp['from_email'] ?? '', $admin['destinatario'] ?? ''] as $endereco) {
            $email = trim((string) $endereco);
            if ($email === '') { continue; }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Endereco administrativo invalido; revise as configuracoes de e-mail.');
            }
            $lista[strtolower($email)] = $email;
        }
        if ($lista === []) { throw new RuntimeException('Nenhum destinatario administrativo configurado.'); }
        return array_values($lista);
    }

    private function lembretesHoje(DateTimeImmutable $hoje): int
    {
        // A combinacao prazo/antecedencia identifica o dia local do disparo,
        // sem depender do fuso do servidor SQL.
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM email_lembretes_vencimento
            WHERE status = 'Enviado' AND enviadoEm IS NOT NULL
              AND ((diasAntes = 1 AND vencimento = :amanha)
                OR (diasAntes = 3 AND vencimento = :dia3))");
        $stmt->execute([':amanha' => $hoje->modify('+1 day')->format('Y-m-d'), ':dia3' => $hoje->modify('+3 days')->format('Y-m-d')]);
        return (int) $stmt->fetchColumn();
    }

    private function listar(): array
    {
        // Apenas a cobranca mais recente de cada inscricao, sem contar
        // cobrancas antigas substituidas. Nao exporta CPF, dados de saude,
        // enderecos de e-mail ou codigos de pagamento dos participantes.
        $sql = "SELECT i.idInscricao, e.titulo AS evento,
                       COALESCE(NULLIF(TRIM(i.nome), ''), NULLIF(TRIM(u.nome), ''), NULLIF(TRIM(p.participante), ''), 'Participante') AS nome,
                       p.formaPagamento AS forma, p.valor, p.dataVencimento AS vencimento, p.status
                FROM inscricoes i
                INNER JOIN eventos e ON e.idEvento = i.idEvento
                LEFT JOIN usuarios u ON u.id = i.idUsuario
                INNER JOIN pagamentos p ON p.idPagamento = (
                    SELECT MAX(p2.idPagamento) FROM pagamentos p2 WHERE p2.idInscricao = i.idInscricao
                )
                WHERE i.status <> 'Cancelada' AND p.status IN ('Pendente', 'Vencido')
                  AND p.dataPagamento IS NULL AND COALESCE(p.valor, 0) > 0
                ORDER BY e.titulo ASC, i.nome ASC, i.idInscricao ASC";
        return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function situacao(array $linha, string $hoje): string
    {
        $v = trim((string) ($linha['vencimento'] ?? ''));
        return ($linha['status'] ?? '') === 'Vencido' || (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $v) && $v !== '0000-00-00' && $v < $hoje)
            ? 'Vencido' : 'Pendente';
    }

    public static function renderizar(array $linhas, string $hoje, int $lembretes): string
    {
        $esc = static fn(mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $data = static function(mixed $v): string {
            $s = trim((string) $v);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/D', $s) || $s === '0000-00-00') { return 'Não informado'; }
            return substr($s, 8, 2) . '/' . substr($s, 5, 2) . '/' . substr($s, 0, 4);
        };
        $moeda = static fn(float $v): string => 'R$ ' . number_format($v, 2, ',', '.');
        $total = 0.0; $vencidos = 0; $htmlLinhas = '';
        foreach ($linhas as $linha) {
            $status = self::situacao($linha, $hoje);
            $valor = (float) ($linha['valor'] ?? 0); $total += $valor;
            if ($status === 'Vencido') { $vencidos++; }
            $forma = match ((string) ($linha['forma'] ?? '')) {
                'Cartao' => 'Cartão', 'NaoDefinido', '' => 'Não definida', 'Transferencia' => 'Transferência',
                default => (string) $linha['forma'],
            };
            $cor = $status === 'Vencido' ? '#9a3412' : '#854d0e';
            $htmlLinhas .= '<tr><td>#' . (int) $linha['idInscricao'] . '</td><td>' . $esc($linha['nome']) . '</td><td>' . $esc($linha['evento']) . '</td><td>' . $esc($forma) . '</td><td>' . $esc($moeda($valor)) . '</td><td>' . $esc($data($linha['vencimento'] ?? '')) . '</td><td style="color:' . $cor . '"><strong>' . $esc($status) . '</strong></td></tr>';
        }
        $tabela = $linhas === [] ? '<p>Nenhum pagamento pendente no momento da consulta.</p>' : '<table cellpadding="8" cellspacing="0" border="1" style="width:100%;border-collapse:collapse;border:1px solid #dbe2ea;font-size:12px;text-align:left"><thead style="background:#e9eff7"><tr><th>Inscrição</th><th>Nome</th><th>Evento</th><th>Forma</th><th>Valor</th><th>Vencimento</th><th>Situação</th></tr></thead><tbody>' . $htmlLinhas . '</tbody></table>';
        return '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"></head><body style="margin:0;padding:20px;background:#f5f7fa;font-family:Arial,sans-serif;color:#172b42"><div style="max-width:920px;margin:auto;background:white;padding:22px;border:1px solid #dbe2ea"><h2 style="color:#174b82">Resumo de pagamentos pendentes</h2><p>Retiro · ' . $esc($data($hoje)) . '</p><p>O sistema registrou o envio de lembretes de vencimento aos participantes. Confira a situação financeira no momento desta consulta.</p><p><strong>Lembretes registrados hoje até esta consulta:</strong> ' . $lembretes . '<br><strong>Inscrições com pendências:</strong> ' . count($linhas) . '<br><strong>Vencidas:</strong> ' . $vencidos . '<br><strong>Total das cobranças listadas:</strong> ' . $esc($moeda($total)) . '</p>' . $tabela . '<p>Inscrições canceladas e cobranças já pagas não constam nesta lista. Vencimento não significa cancelamento. Confira os pagamentos antes de tomar qualquer decisão.</p><p><a href="https://retiro.ieclbparobe.com.br/admin/financeiro/pagamentos.php" style="display:inline-block;background:#174b82;padding:12px 18px;color:white;text-decoration:none;border-radius:5px">Abrir financeiro do Retiro</a></p><p style="font-size:11px;color:#64748b">Mensagem administrativa automática e confidencial. Não encaminhe a terceiros.</p></div></body></html>';
    }

    public function simular(): array
    {
        $hoje = new DateTimeImmutable('today', $this->fuso);
        $linhas = $this->listar();
        $vencidos = count(array_filter($linhas, fn(array $l): bool => self::situacao($l, $hoje->format('Y-m-d')) === 'Vencido'));
        return ['lembretes_enviados_hoje' => $this->lembretesHoje($hoje), 'pendencias' => count($linhas), 'vencidas' => $vencidos];
    }

    public function processar(): array
    {
        $r = ['enviados' => 0, 'falhas' => 0];
        if (self::ambienteLocal(dirname(__DIR__, 2), PHP_OS_FAMILY)) { return $r + ['motivo' => 'Envio bloqueado no ambiente local']; }
        $admin = $this->config('notificacoes_sistema');
        $lembretes = $this->config('lembretes_pagamento');
        if (!($admin['ativo'] ?? true) || !($lembretes['ativo'] ?? true)) { return $r + ['motivo' => 'Notificacoes desativadas']; }
        $lock = $this->db->query("SELECT GET_LOCK('" . self::LOCK . "', 0)");
        if ((int) $lock->fetchColumn() !== 1) { return $r; }
        try {
            $hoje = new DateTimeImmutable('today', $this->fuso);
            $dia = $hoje->format('Y-m-d');
            $n = $this->lembretesHoje($hoje);
            if ($n === 0) { return $r; }
            $emails = self::destinatarios($this->config('mail'), $admin);
            foreach ($emails as $email) {
                $hash = hash('sha256', strtolower($email));
                $chave = [':dia' => $dia, ':hash' => $hash];
                $stmt = $this->db->prepare('SELECT enviadoEm, tentativas, (tentativaEm <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)) AS podeTentar FROM email_resumo_pendencias_admin WHERE dataReferencia = :dia AND destinatarioHash = :hash');
                $stmt->execute($chave);
                $estado = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($estado && (!empty($estado['enviadoEm']) || (int) $estado['tentativas'] >= 5 || (int) $estado['podeTentar'] !== 1)) { continue; }
                $linhas = $this->listar();
                $html = self::renderizar($linhas, $dia, $n);
                $stmt = $this->db->prepare('INSERT INTO email_resumo_pendencias_admin (dataReferencia, destinatarioHash, tentativas, tentativaEm) VALUES (:dia, :hash, 1, NOW()) ON DUPLICATE KEY UPDATE tentativas = tentativas + 1, tentativaEm = NOW()');
                $stmt->execute($chave);
                try {
                    require_once dirname(__DIR__) . '/mail/Mail.php';
                    $nome = trim((string) ($admin['nome_destinatario'] ?? 'Administração do Retiro')) ?: 'Administração do Retiro';
                    if (!(new Mail())->send($email, $nome, '[Retiro] Pagamentos pendentes - ' . $hoje->format('d/m/Y'), $html)) { throw new RuntimeException('SMTP nao confirmou o envio.'); }
                    $stmt = $this->db->prepare('UPDATE email_resumo_pendencias_admin SET enviadoEm = NOW() WHERE dataReferencia = :dia AND destinatarioHash = :hash');
                    $stmt->execute($chave);
                    $r['enviados']++;
                } catch (Throwable $e) {
                    $r['falhas']++;
                    error_log('[Retiro] Falha no resumo administrativo de pendencias (nova tentativa em 15 min).');
                }
            }
            return $r;
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('" . self::LOCK . "')");
        }
    }
}
