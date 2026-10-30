<?php

declare(strict_types=1);

/**
 * Avisos ADMINISTRATIVOS sobre:
 * - nova conta no Retiro;
 * - nova inscricao em evento;
 * - pagamento efetivamente confirmado (status Pago).
 *
 * O e-mail do participante continua sendo tratado pelo modulo existente.
 * Os alertas sao enfileirados em email_sistema_alertas, com chave unica por
 * tipo e registro, evitando duplicacoes ao executar o cron repetidamente.
 */
final class EmailSistemaNotificacaoService
{
    private const MAX_TENTATIVAS = 12;
    private const LOCK = 'retiro_email_sistema_alertas';

    public function __construct(private PDO $db)
    {
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /** @return array<string,int|string|bool> */
    public function processar(): array
    {
        $resumo = [
            'ativo' => true,
            'destinatario' => 'configurado',
            'novos' => 0,
            'enviados' => 0,
            'falhas' => 0,
            'pendentes' => 0,
        ];

        $configuracao = $this->configuracao();
        if (!($configuracao['ativo'] ?? true)) {
            $resumo['ativo'] = false;
            return $resumo;
        }

        if (!$this->tabelaExiste()) {
            throw new RuntimeException(
                'A tabela email_sistema_alertas nao existe. Execute php database/migrate.php migrate.'
            );
        }

        $lock = $this->db->query('SELECT GET_LOCK(' . $this->db->quote(self::LOCK) . ', 0)')->fetchColumn();
        if ((int) $lock !== 1) {
            $resumo['ocupado'] = true;
            return $resumo;
        }

        try {
            $resumo['novos'] = $this->capturarEventos();
            $resumo['pendentes'] = $this->contarPendentes();
            if ($resumo['pendentes'] === 0) {
                return $resumo;
            }

            $configEmail = require dirname(__DIR__, 2) . '/config/mail.php';
            if (!is_array($configEmail)) {
                $configEmail = [];
            }

            $destinatario = trim((string) ($configuracao['destinatario'] ?? ''));
            if ($destinatario === '') {
                $destinatario = trim((string) ($configEmail['from_email'] ?? ''));
            }

            if (!filter_var($destinatario, FILTER_VALIDATE_EMAIL)) {
                // Nao gasta tentativas, pois a configuracao pode ser corrigida.
                throw new RuntimeException(
                    'E-mail do sistema nao configurado. Confira Configuracoes > E-mail ou config/notificacoes_sistema.php.'
                );
            }

            require_once dirname(__DIR__) . '/mail/Mail.php';
            $mail = new Mail();
            $nomeDestinatario = trim((string) ($configuracao['nome_destinatario'] ?? 'Administração do Retiro'));
            if ($nomeDestinatario === '') {
                $nomeDestinatario = 'Administração do Retiro';
            }

            $stmt = $this->db->prepare(<<<'SQL'
                SELECT id, tipo, idReferencia
                FROM email_sistema_alertas
                WHERE enviadoEm IS NULL
                  AND tentativas < :maxTentativas
                  AND (
                      ultimaTentativaEm IS NULL
                      OR ultimaTentativaEm <= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                  )
                ORDER BY id ASC
                LIMIT 10
            SQL);
            $stmt->bindValue(':maxTentativas', self::MAX_TENTATIVAS, PDO::PARAM_INT);
            $stmt->execute();

            foreach ($stmt->fetchAll() as $alerta) {
                $id = (int) $alerta['id'];

                try {
                    $mensagem = $this->montarMensagem(
                        (string) $alerta['tipo'],
                        (int) $alerta['idReferencia']
                    );

                    if ($mensagem === null) {
                        // Registro excluido/cancelado antes do envio: encerra sem mandar aviso falso.
                        $this->marcarEnviado($id);
                        continue;
                    }

                    $enviado = $mail->send(
                        $destinatario,
                        $nomeDestinatario,
                        $mensagem['assunto'],
                        $mensagem['html']
                    );

                    if (!$enviado) {
                        throw new RuntimeException('Servidor SMTP nao confirmou o envio.');
                    }

                    $this->marcarEnviado($id);
                    $resumo['enviados']++;
                } catch (Throwable $erro) {
                    $this->marcarFalha($id, $erro->getMessage());
                    $resumo['falhas']++;
                    error_log('Retiro: erro no alerta administrativo #' . $id . ': ' . $erro->getMessage());
                }
            }

            $resumo['pendentes'] = $this->contarPendentes();
            return $resumo;
        } finally {
            $this->db->query('SELECT RELEASE_LOCK(' . $this->db->quote(self::LOCK) . ')');
        }
    }

    private function configuracao(): array
    {
        $arquivo = dirname(__DIR__, 2) . '/config/notificacoes_sistema.php';
        $config = is_file($arquivo) ? require $arquivo : [];
        return is_array($config) ? $config : [];
    }

    private function tabelaExiste(): bool
    {
        $consulta = $this->db->query("SHOW TABLES LIKE 'email_sistema_alertas'");
        return $consulta !== false && $consulta->fetchColumn() !== false;
    }

    private function capturarEventos(): int
    {
        $total = 0;

        // Um usuario pode se cadastrar diretamente ou durante a inscricao.
        $total += $this->db->exec(<<<'SQL'
            INSERT IGNORE INTO email_sistema_alertas (tipo, idReferencia)
            SELECT 'usuario', u.id
            FROM usuarios u
            LEFT JOIN email_sistema_alertas a
                ON a.tipo = 'usuario' AND a.idReferencia = u.id
            WHERE a.id IS NULL
            ORDER BY u.id ASC
            LIMIT 100
        SQL);

        $total += $this->db->exec(<<<'SQL'
            INSERT IGNORE INTO email_sistema_alertas (tipo, idReferencia)
            SELECT 'inscricao', i.idInscricao
            FROM inscricoes i
            LEFT JOIN email_sistema_alertas a
                ON a.tipo = 'inscricao' AND a.idReferencia = i.idInscricao
            WHERE a.id IS NULL
            ORDER BY i.idInscricao ASC
            LIMIT 100
        SQL);

        // Pagamentos gerados e ainda pendentes NAO disparam este e-mail.
        $total += $this->db->exec(<<<'SQL'
            INSERT IGNORE INTO email_sistema_alertas (tipo, idReferencia)
            SELECT 'pagamento', p.idPagamento
            FROM pagamentos p
            LEFT JOIN email_sistema_alertas a
                ON a.tipo = 'pagamento' AND a.idReferencia = p.idPagamento
            WHERE p.status = 'Pago' AND a.id IS NULL
            ORDER BY p.idPagamento ASC
            LIMIT 100
        SQL);

        return $total;
    }

    private function contarPendentes(): int
    {
        return (int) $this->db->query(
            'SELECT COUNT(*) FROM email_sistema_alertas WHERE enviadoEm IS NULL'
        )->fetchColumn();
    }

    /** @return array{assunto:string,html:string}|null */
    private function montarMensagem(string $tipo, int $idReferencia): ?array
    {
        if ($tipo === 'usuario') {
            $s = $this->db->prepare('SELECT id, nome, email, created_at FROM usuarios WHERE id = :id LIMIT 1');
            $s->execute([':id' => $idReferencia]);
            $u = $s->fetch();
            if (!$u) {
                return null;
            }

            return $this->mensagem(
                'Novo usuário cadastrado',
                [
                    'Nome' => (string) ($u['nome'] ?? ''),
                    'E-mail' => (string) ($u['email'] ?? ''),
                    'Data do cadastro' => $this->data($u['created_at'] ?? null),
                ],
                'admin/user/usuarios.php'
            );
        }

        if ($tipo === 'inscricao') {
            $s = $this->db->prepare(<<<'SQL'
                SELECT i.idInscricao, i.nome, i.email, i.criado_em,
                    COALESCE(u.nome, i.nome) AS participante,
                    COALESCE(u.email, i.email) AS emailParticipante,
                    e.titulo AS evento
                FROM inscricoes i
                LEFT JOIN usuarios u ON u.id = i.idUsuario
                LEFT JOIN eventos e ON e.idEvento = i.idEvento
                WHERE i.idInscricao = :id
                LIMIT 1
            SQL);
            $s->execute([':id' => $idReferencia]);
            $i = $s->fetch();
            if (!$i) {
                return null;
            }

            return $this->mensagem(
                'Nova inscrição no evento',
                [
                    'Inscrição' => '#' . (int) $i['idInscricao'],
                    'Participante' => (string) ($i['participante'] ?? ''),
                    'E-mail' => (string) ($i['emailParticipante'] ?? ''),
                    'Evento' => (string) ($i['evento'] ?? ''),
                    'Data da inscrição' => $this->data($i['criado_em'] ?? null),
                ],
                'admin/inscricao/inscricoes.php'
            );
        }

        if ($tipo === 'pagamento') {
            $s = $this->db->prepare(<<<'SQL'
                SELECT p.idPagamento, p.codigo, p.participante, p.valor,
                    p.formaPagamento, p.recebidoEm, p.dataPagamento,
                    e.titulo AS evento
                FROM pagamentos p
                LEFT JOIN eventos e ON e.idEvento = p.idEvento
                WHERE p.idPagamento = :id AND p.status = 'Pago'
                LIMIT 1
            SQL);
            $s->execute([':id' => $idReferencia]);
            $p = $s->fetch();
            if (!$p) {
                return null;
            }

            $formas = [
                'Cartao' => 'Cartão de crédito',
                'Boleto' => 'Boleto bancario',
                'Dinheiro' => 'Dinheiro (presencial)',
                'Transferência' => 'Transferência',
                'PIX' => 'PIX',
            ];
            $forma = (string) ($p['formaPagamento'] ?? '');
            $horario = $p['recebidoEm'] ?: $p['dataPagamento'];
            $horarioTexto = $this->data($horario);
            if (is_string($horario) && (preg_match('/^\d{4}-\d{2}-\d{2}$/', $horario) || str_ends_with($horario, '00:00:00'))) {
                $horarioTexto = $this->dataSomente($horario) . ' (horário não informado)';
            }

            return $this->mensagem(
                'Pagamento recebido',
                [
                    'Pagamento' => (string) ($p['codigo'] ?: '#' . $p['idPagamento']),
                    'Participante' => (string) ($p['participante'] ?? ''),
                    'Evento' => (string) ($p['evento'] ?? ''),
                    'Valor da inscrição' => 'R$ ' . number_format((float) ($p['valor'] ?? 0), 2, ',', '.'),
                    'Forma' => $formas[$forma] ?? $forma,
                    'Status' => 'Pago',
                    'Recebido em' => $horarioTexto,
                ],
                'admin/financeiro/pagamentos.php'
            );
        }

        return null;
    }

    /** @param array<string,string> $campos */
    private function mensagem(string $titulo, array $campos, string $destino): array
    {
        $linhas = '';
        foreach ($campos as $campo => $valor) {
            $linhas .= '<tr>'
                . '<td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;color:#475569;width:35%">'
                . $this->esc($campo)
                . '</td><td style="padding:10px 14px;border-bottom:1px solid #e5e7eb;font-weight:bold">'
                . $this->esc($valor)
                . '</td></tr>';
        }

        $link = defined('BASE_URL')
            ? rtrim((string) BASE_URL, '/') . '/' . ltrim($destino, '/')
            : '';
        $botao = $link !== ''
            ? '<p style="padding-top:18px"><a href="' . $this->esc($link)
                . '" style="background:#0d6efd;color:white;text-decoration:none;padding:12px 18px;border-radius:6px">Abrir no Retiro</a></p>'
            : '';

        $html = '<!doctype html><html lang="pt-BR"><head><meta charset="UTF-8"></head>'
            . '<body style="font-family:Arial,sans-serif;background:#f5f7fb;padding:24px;color:#12243b">'
            . '<div style="max-width:680px;margin:0 auto;background:#fff;padding:26px;border:1px solid #dde5ef;border-radius:12px">'
            . '<h2 style="margin-top:0;color:#123d7a">' . $this->esc($titulo) . '</h2>'
            . '<p>O sistema Retiro registrou a seguinte ocorrencia:</p>'
            . '<table style="width:100%;border-collapse:collapse">' . $linhas . '</table>'
            . $botao
            . '<p style="font-size:12px;color:#64748b;padding-top:18px">'
            . 'Mensagem administrativa automática. Não compartilhe dados dos participantes.</p>'
            . '</div></body></html>';

        return [
            'assunto' => '[Retiro] ' . $titulo,
            'html' => $html,
        ];
    }

    private function data(mixed $texto): string
    {
        $valor = trim((string) ($texto ?? ''));
        if ($valor === '') {
            return 'Não informado';
        }
        $ts = strtotime($valor);
        return $ts === false ? 'Não informado' : date('d/m/Y H:i', $ts);
    }

    private function dataSomente(mixed $texto): string
    {
        $ts = strtotime(trim((string) ($texto ?? '')));
        return $ts === false ? 'Não informado' : date('d/m/Y', $ts);
    }

    private function esc(string $valor): string
    {
        return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function marcarEnviado(int $id): void
    {
        $stmt = $this->db->prepare(<<<'SQL'
            UPDATE email_sistema_alertas
            SET enviadoEm = NOW(), ultimoErro = NULL
            WHERE id = :id
        SQL);
        $stmt->execute([':id' => $id]);
    }

    private function marcarFalha(int $id, string $erro): void
    {
        $stmt = $this->db->prepare(<<<'SQL'
            UPDATE email_sistema_alertas
            SET tentativas = tentativas + 1,
                ultimaTentativaEm = NOW(),
                ultimoErro = :erro
            WHERE id = :id
        SQL);
        $stmt->execute([
            ':id' => $id,
            ':erro' => substr($erro, 0, 255),
        ]);
    }
}
