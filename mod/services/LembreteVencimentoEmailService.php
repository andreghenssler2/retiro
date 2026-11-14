<?php

declare(strict_types=1);

/**
 * Avisos preventivos para PIX e boleto: 3 e 1 dias corridos antes do vencimento.
 * Executado exclusivamente pelo cron unificado existente, com trava MySQL
 * e unicidade (pagamento, vencimento, antecedencia).
 */
final class LembreteVencimentoEmailService
{
    private const LOCK = 'retiro_lembretes_vencimento_v1';
    private const TABELA = 'email_lembretes_vencimento';
    private DateTimeZone $fuso;

    public function __construct(private PDO $db)
    {
        $this->fuso = new DateTimeZone('America/Sao_Paulo');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** Dias corridos restantes; null para datas invalidas. */
    public static function diasAteVencimento(string $vencimento, string $dataReferencia): ?int
    {
        $fuso = new DateTimeZone('America/Sao_Paulo');
        $v = DateTimeImmutable::createFromFormat('!Y-m-d', $vencimento, $fuso);
        $h = DateTimeImmutable::createFromFormat('!Y-m-d', $dataReferencia, $fuso);
        if (!$v || !$h || $v->format('Y-m-d') !== $vencimento || $h->format('Y-m-d') !== $dataReferencia) {
            return null;
        }
        return (int) $h->diff($v)->format('%r%a');
    }

    /** @return array<string,int|string|bool> */
    public function processar(): array
    {
        $retorno = ['ativo' => true, 'candidatos' => 0, 'enviados' => 0, 'falhas' => 0, 'ignorados' => 0];
        $cfg = $this->configuracao();
        if (!($cfg['ativo'] ?? true)) {
            $retorno['ativo'] = false;
            $retorno['motivo'] = 'Desativado na configuracao';
            return $retorno;
        }
        if (!$this->permiteEnvioNesteAmbiente($cfg)) {
            $retorno['ativo'] = false;
            $retorno['motivo'] = 'Envio bloqueado no ambiente local/XAMPP';
            return $retorno;
        }
        if (!$this->tabelaExiste()) {
            throw new RuntimeException('Tabela email_lembretes_vencimento ausente. Execute aplicar_alteracao.php.');
        }

        $obtido = (int) $this->db->query('SELECT GET_LOCK(' . $this->db->quote(self::LOCK) . ', 0)')->fetchColumn();
        if ($obtido !== 1) {
            $retorno['ocupado'] = true;
            return $retorno;
        }

        try {
            $hoje = new DateTimeImmutable('today', $this->fuso);
            $limite = max(1, min(20, (int) ($cfg['limite_por_execucao'] ?? 8)));
            $pagamentos = $this->listarCandidatos($hoje, $limite);
            $retorno['candidatos'] = count($pagamentos);

            foreach ($pagamentos as $p) {
                $id = (int) $p['idPagamento'];
                $vencimento = (string) $p['dataVencimento'];
                $dias = self::diasAteVencimento($vencimento, $hoje->format('Y-m-d'));
                if (!in_array($dias, [1, 3], true)) {
                    $retorno['ignorados']++;
                    continue;
                }
                $email = trim((string) ($p['emailUsuario'] ?? ''));
                $nome = trim((string) ($p['nomeUsuario'] ?? ''));
                if ($nome === '') {
                    $nome = 'Participante';
                }

                // Reserva tentativas e permite repetir apos 15 minutos se houver falha.
                $this->registrarTentativa($id, $vencimento, $dias);
                try {
                    // Confere novamente antes de enviar: um webhook pode ter confirmado
                    // o pagamento enquanto este lote estava sendo preparado.
                    if (!$this->aindaPendente($id, $vencimento)) {
                        $retorno['ignorados']++;
                        continue;
                    }
                    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new RuntimeException('Nao existe e-mail valido para o destinatario.');
                    }
                    $assunto = sprintf(
                        'Lembrete: pagamento vence em %d %s - %s',
                        $dias,
                        $dias === 1 ? 'dia' : 'dias',
                        (string) $p['evento']
                    );
                    $html = $this->renderizar($p, $nome, $dias);
                    require_once dirname(__DIR__) . '/mail/Mail.php';
                    $enviado = (new Mail())->send($email, $nome, $assunto, $html);
                    if (!$enviado) {
                        throw new RuntimeException('Servico de e-mail retornou falha no envio.');
                    }
                    $this->atualizarTentativa($id, $vencimento, $dias, true, null);
                    $retorno['enviados']++;
                } catch (Throwable $erro) {
                    $this->atualizarTentativa($id, $vencimento, $dias, false, $erro->getMessage());
                    $retorno['falhas']++;
                    error_log('[Retiro] Falha no lembrete de pagamento #' . $id . ': ' . $erro->getMessage());
                }
            }
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(' . $this->db->quote(self::LOCK) . ')');
        }
        return $retorno;
    }

    /** Listagem read-only para diagnostico, sem enviar nem marcar e-mails. */
    public function simular(): array
    {
        if (!$this->tabelaExiste()) {
            throw new RuntimeException('Tabela email_lembretes_vencimento ausente.');
        }
        $hoje = new DateTimeImmutable('today', $this->fuso);
        $candidatos = $this->listarCandidatos($hoje, 500);
        $resumo = ['data' => $hoje->format('d/m/Y'), 'faltam_3_dias' => 0, 'falta_1_dia' => 0];
        foreach ($candidatos as $p) {
            $dias = self::diasAteVencimento((string) $p['dataVencimento'], $hoje->format('Y-m-d'));
            if ($dias === 3) { $resumo['faltam_3_dias']++; }
            if ($dias === 1) { $resumo['falta_1_dia']++; }
        }
        $resumo['candidatos_ate_500'] = count($candidatos);
        return $resumo;
    }

    /** @return array<int,array<string,mixed>> */
    private function listarCandidatos(DateTimeImmutable $hoje, int $limite): array
    {
        $dia1 = $hoje->modify('+1 day')->format('Y-m-d');
        $dia3 = $hoje->modify('+3 days')->format('Y-m-d');
        $limite = max(1, min(500, $limite));
        $sql = "SELECT p.idPagamento, p.dataVencimento, p.formaPagamento, p.valor,
                       p.invoiceUrl, p.bankSlipUrl, p.pixCopiaCola, p.pixExpiracao,
                       p.boletoLinhaDigitavel, e.titulo AS evento,
                       COALESCE(NULLIF(TRIM(u.nome), ''), NULLIF(TRIM(i.nome), ''), NULLIF(TRIM(p.participante), ''), 'Participante') AS nomeUsuario,
                       COALESCE(NULLIF(TRIM(u.email), ''), NULLIF(TRIM(i.email), ''), NULLIF(TRIM(p.email), ''), '') AS emailUsuario
                FROM pagamentos p
                INNER JOIN inscricoes i ON i.idInscricao = p.idInscricao
                INNER JOIN eventos e ON e.idEvento = i.idEvento
                LEFT JOIN usuarios u ON u.id = i.idUsuario
                LEFT JOIN email_lembretes_vencimento l
                  ON l.idPagamento = p.idPagamento
                 AND l.vencimento = p.dataVencimento
                 AND l.diasAntes = CASE WHEN p.dataVencimento = :dia1_join THEN 1 ELSE 3 END
                WHERE p.status = 'Pendente'
                  AND p.formaPagamento IN ('PIX', 'Boleto')
                  AND p.dataPagamento IS NULL
                  AND p.dataVencimento IN (:dia1, :dia3)
                  AND i.status <> 'Cancelada'
                  AND COALESCE(p.valor, 0) > 0
                  AND (l.idLembrete IS NULL OR
                       (l.status IN ('Falhou', 'Processando') AND l.tentativas < 5
                        AND l.tentativaEm <= DATE_SUB(NOW(), INTERVAL 15 MINUTE)))
                ORDER BY p.dataVencimento ASC, p.idPagamento ASC
                LIMIT {$limite}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':dia1_join' => $dia1, ':dia1' => $dia1, ':dia3' => $dia3]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function aindaPendente(int $id, string $vencimento): bool
    {
        $stmt = $this->db->prepare("SELECT 1 FROM pagamentos
            WHERE idPagamento = :id AND status = 'Pendente'
              AND dataPagamento IS NULL AND dataVencimento = :vencimento
              AND formaPagamento IN ('PIX', 'Boleto') LIMIT 1");
        $stmt->execute([':id' => $id, ':vencimento' => $vencimento]);
        return $stmt->fetchColumn() !== false;
    }

    private function registrarTentativa(int $id, string $data, int $dias): void
    {
        $stmt = $this->db->prepare("INSERT INTO email_lembretes_vencimento
            (idPagamento, vencimento, diasAntes, status, tentativas, tentativaEm)
            VALUES (:id, :vencimento, :dias, 'Processando', 1, NOW())
            ON DUPLICATE KEY UPDATE status = 'Processando',
                                    tentativas = tentativas + 1,
                                    tentativaEm = NOW(), ultimaFalha = NULL");
        $stmt->execute([':id' => $id, ':vencimento' => $data, ':dias' => $dias]);
    }

    private function atualizarTentativa(int $id, string $data, int $dias, bool $enviado, ?string $erro): void
    {
        $stmt = $this->db->prepare("UPDATE email_lembretes_vencimento
             SET status = :status,
                 enviadoEm = CASE WHEN :sucesso = 1 THEN NOW() ELSE enviadoEm END,
                 ultimaFalha = :erro
             WHERE idPagamento = :id AND vencimento = :vencimento AND diasAntes = :dias");
        $stmt->execute([
            ':status' => $enviado ? 'Enviado' : 'Falhou',
            ':sucesso' => $enviado ? 1 : 0,
            ':erro' => $erro !== null ? substr($erro, 0, 490) : null,
            ':id' => $id, ':vencimento' => $data, ':dias' => $dias,
        ]);
    }

    private function renderizar(array $p, string $nome, int $dias): string
    {
        $vencimento = (string) $p['dataVencimento'];
        $dataVencimento = DateTimeImmutable::createFromFormat('!Y-m-d', $vencimento, $this->fuso);
        $url = $this->linkPagamento($p);
        $forma = (string) $p['formaPagamento'];
        $pixCopiaCola = trim((string) ($p['pixCopiaCola'] ?? ''));
        $expiracaoTexto = trim((string) ($p['pixExpiracao'] ?? ''));
        if ($expiracaoTexto !== '') {
            try {
                if (new DateTimeImmutable($expiracaoTexto, $this->fuso) <= new DateTimeImmutable("now", $this->fuso)) {
                    $pixCopiaCola = ''; // Nunca incentivar pagamento com codigo PIX expirado.
                }
            } catch (Throwable) {
                $pixCopiaCola = '';
            }
        }
        $boletoLinhaDigitavel = trim((string) ($p['boletoLinhaDigitavel'] ?? ''));
        $evento = (string) ($p['evento'] ?? '');
        $valor = 'R$ ' . number_format((float) ($p['valor'] ?? 0), 2, ',', '.');
        $data = $dataVencimento ? $dataVencimento->format('d/m/Y') : $vencimento;
        ob_start();
        try {
            require dirname(__DIR__) . '/mail/templates/lembrete_vencimento.php';
            return (string) ob_get_clean();
        } catch (Throwable $erro) {
            ob_end_clean();
            throw $erro;
        }
    }

    private function linkPagamento(array $p): string
    {
        $forma = (string) ($p['formaPagamento'] ?? '');
        $candidatos = $forma === 'Boleto'
            ? [$p['bankSlipUrl'] ?? '', $p['invoiceUrl'] ?? '']
            : [$p['invoiceUrl'] ?? ''];
        foreach ($candidatos as $candidato) {
            $url = trim((string) $candidato);
            if (filter_var($url, FILTER_VALIDATE_URL)
                && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https') {
                return $url;
            }
        }
        return 'https://retiro.ieclbparobe.com.br/user/pagamentos.php';
    }

    private function tabelaExiste(): bool
    {
        $stmt = $this->db->query("SHOW TABLES LIKE 'email_lembretes_vencimento'");
        return $stmt->fetchColumn() !== false;
    }

    /** @return array<string,mixed> */
    private function configuracao(): array
    {
        $arquivo = dirname(__DIR__, 2) . '/config/lembretes_pagamento.php';
        return is_file($arquivo) ? (array) require $arquivo : [];
    }

    private function permiteEnvioNesteAmbiente(array $cfg): bool
    {
        if ($cfg['permitir_envio_local'] ?? false) { return true; }
        $raiz = str_replace('\\', '/', dirname(__DIR__, 2));
        if (PHP_OS_FAMILY === 'Windows' || preg_match('~/(?:xampp|wamp|laragon|mamp)/~i', $raiz)) {
            return false;
        }
        return true;
    }
}
