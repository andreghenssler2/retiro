<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/db.php';

class Credenciamento
{
    private PDO $db;

    public function __construct(?PDO $conexao = null)
    {
        $this->db = $conexao ?? Database::connect();
    }

    public function listarEventos(): array
    {
        $sql = "
            SELECT
                e.idEvento,
                e.titulo,
                e.data_inicio,
                e.data_fim,
                e.hora_inicio,
                e.hora_fim,
                e.local,
                e.cidade,
                e.estado,
                e.ativo,
                e.pagamento_obrigatorio,
                COUNT(i.idInscricao) AS totalInscritos
            FROM eventos e
            LEFT JOIN inscricoes i
                ON i.idEvento = e.idEvento
               AND i.status <> 'Cancelada'
            GROUP BY
                e.idEvento,
                e.titulo,
                e.data_inicio,
                e.data_fim,
                e.hora_inicio,
                e.hora_fim,
                e.local,
                e.cidade,
                e.estado,
                e.ativo,
                e.pagamento_obrigatorio
            ORDER BY
                e.data_inicio DESC,
                e.hora_inicio DESC,
                e.titulo ASC
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    public function buscarEvento(int $idEvento): array|false
    {
        if ($idEvento <= 0) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT
                idEvento,
                titulo,
                data_inicio,
                data_fim,
                hora_inicio,
                hora_fim,
                local,
                endereco,
                cidade,
                estado,
                ativo,
                pagamento_obrigatorio
            FROM eventos
            WHERE idEvento = :idEvento
            LIMIT 1
        ");

        $stmt->execute([':idEvento' => $idEvento]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function buscarInscricao(int $idInscricao): array|false
    {
        if ($idInscricao <= 0) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT
                i.idInscricao,
                i.idEvento,
                i.idUsuario,
                i.status AS inscricaoStatus,
                i.pagamento AS pagamentoInscricao,
                i.presenca,
                i.presenca_status AS presencaStatus,
                e.titulo AS eventoTitulo,
                e.data_inicio,
                e.data_fim,
                e.hora_inicio,
                e.hora_fim,
                e.pagamento_obrigatorio,
                COALESCE(p.status, i.pagamento, 'Pendente') AS pagamentoStatus
            FROM inscricoes i
            INNER JOIN eventos e
                ON e.idEvento = i.idEvento
            LEFT JOIN pagamentos p
                ON p.idInscricao = i.idInscricao
            WHERE i.idInscricao = :idInscricao
            LIMIT 1
        ");

        $stmt->execute([':idInscricao' => $idInscricao]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function listarInscritos(
        int $idEvento,
        string $pesquisa = ''
    ): array {
        if ($idEvento <= 0) {
            return [];
        }

        $wherePesquisa = '';
        $params = [':idEvento' => $idEvento];
        $pesquisa = trim($pesquisa);

        if ($pesquisa !== '') {
            $wherePesquisa = "
                AND (
                    COALESCE(u.nome, i.nome) LIKE :pesquisaNome
                    OR COALESCE(u.cpf, i.cpf) LIKE :pesquisaCpf
                    OR COALESCE(u.email, i.email) LIKE :pesquisaEmail
                    OR COALESCE(u.telefone, i.telefone) LIKE :pesquisaTelefone
                )
            ";

            $busca = '%' . $pesquisa . '%';
            $params[':pesquisaNome'] = $busca;
            $params[':pesquisaCpf'] = $busca;
            $params[':pesquisaEmail'] = $busca;
            $params[':pesquisaTelefone'] = $busca;
        }

        $stmt = $this->db->prepare("
            SELECT
                i.idInscricao,
                i.idUsuario,
                i.status AS inscricaoStatus,
                i.pagamento AS pagamentoInscricao,
                i.presenca,
                COALESCE(
                    NULLIF(i.presenca_status, ''),
                    IF(i.presenca = 1, 'Presente', 'Pendente')
                ) AS presencaStatus,
                i.presenca_registrada_em AS presencaRegistradaEm,
                i.presenca_finalizada_em AS presencaFinalizadaEm,
                COALESCE(u.nome, i.nome) AS nome,
                COALESCE(u.cpf, i.cpf) AS cpf,
                COALESCE(u.email, i.email) AS email,
                COALESCE(u.telefone, i.telefone) AS telefone,
                COALESCE(p.status, i.pagamento, 'Pendente') AS pagamentoStatus,
                p.formaPagamento,
                p.codigo AS codigoPagamento
            FROM inscricoes i
            LEFT JOIN usuarios u
                ON u.id = i.idUsuario
            LEFT JOIN pagamentos p
                ON p.idInscricao = i.idInscricao
            WHERE i.idEvento = :idEvento
            {$wherePesquisa}
            ORDER BY
                CASE WHEN i.status = 'Cancelada' THEN 1 ELSE 0 END,
                COALESCE(u.nome, i.nome) ASC
        ");

        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resumo(int $idEvento): array
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE
                    WHEN status <> 'Cancelada'
                     AND presenca_status = 'Presente'
                    THEN 1 ELSE 0
                END) AS presentes,
                SUM(CASE
                    WHEN status <> 'Cancelada'
                     AND presenca_status = 'Ausente'
                    THEN 1 ELSE 0
                END) AS ausentes,
                SUM(CASE
                    WHEN status <> 'Cancelada'
                     AND (presenca_status = 'Pendente' OR presenca_status IS NULL)
                    THEN 1 ELSE 0
                END) AS pendentes,
                SUM(CASE WHEN status = 'Cancelada' THEN 1 ELSE 0 END) AS canceladas
            FROM inscricoes
            WHERE idEvento = :idEvento
        ");

        $stmt->execute([':idEvento' => $idEvento]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($dados['total'] ?? 0),
            'presentes' => (int) ($dados['presentes'] ?? 0),
            'ausentes' => (int) ($dados['ausentes'] ?? 0),
            'pendentes' => (int) ($dados['pendentes'] ?? 0),
            'canceladas' => (int) ($dados['canceladas'] ?? 0)
        ];
    }

    public function dataHoraFim(array $evento): DateTimeImmutable
    {
        $data = trim((string) ($evento['data_fim'] ?? ''));

        if ($data === '') {
            $data = trim((string) ($evento['data_inicio'] ?? ''));
        }

        if ($data === '') {
            throw new RuntimeException('O evento não possui uma data válida.');
        }

        $hora = trim((string) ($evento['hora_fim'] ?? ''));

        if ($hora === '' || $hora === '00:00:00') {
            $hora = '23:59:59';
        }

        return new DateTimeImmutable($data . ' ' . $hora);
    }

    public function eventoEncerrado(array $evento): bool
    {
        return new DateTimeImmutable('now') >= $this->dataHoraFim($evento);
    }

    public function situacaoEvento(array $evento): string
    {
        $agora = new DateTimeImmutable('now');
        $dataInicio = trim((string) ($evento['data_inicio'] ?? ''));
        $horaInicio = trim((string) ($evento['hora_inicio'] ?? '')) ?: '00:00:00';

        if ($dataInicio === '') {
            return 'Indefinido';
        }

        $inicio = new DateTimeImmutable($dataInicio . ' ' . $horaInicio);
        $fim = $this->dataHoraFim($evento);

        if ($agora >= $fim) {
            return 'Encerrado';
        }

        if ($agora >= $inicio) {
            return 'Em andamento';
        }

        return 'Agendado';
    }

    public function registrarPresenca(
        int $idInscricao,
        bool $presente,
        int $idResponsavel = 0
    ): array {
        if ($idInscricao <= 0) {
            throw new InvalidArgumentException('Inscrição inválida.');
        }

        $registroInicial = $this->buscarInscricao($idInscricao);

        if (!$registroInicial) {
            throw new RuntimeException('Inscrição não encontrada.');
        }

        if ($this->eventoEncerrado($registroInicial)) {
            $this->finalizarEvento((int) $registroInicial['idEvento']);
            throw new RuntimeException(
                'O evento já foi encerrado. O status de presença não pode mais ser alterado.'
            );
        }

        $iniciouTransacao = !$this->db->inTransaction();

        if ($iniciouTransacao) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare("
                SELECT
                    i.idInscricao,
                    i.status AS inscricaoStatus,
                    i.presenca,
                    i.presenca_status AS presencaStatus,
                    i.pagamento AS pagamentoInscricao,
                    e.idEvento,
                    e.titulo,
                    e.data_inicio,
                    e.data_fim,
                    e.hora_inicio,
                    e.hora_fim,
                    e.pagamento_obrigatorio,
                    COALESCE(p.status, i.pagamento, 'Pendente') AS pagamentoStatus
                FROM inscricoes i
                INNER JOIN eventos e
                    ON e.idEvento = i.idEvento
                LEFT JOIN pagamentos p
                    ON p.idInscricao = i.idInscricao
                WHERE i.idInscricao = :idInscricao
                LIMIT 1
                FOR UPDATE
            ");

            $stmt->execute([':idInscricao' => $idInscricao]);
            $registro = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$registro) {
                throw new RuntimeException('Inscrição não encontrada.');
            }

            if ((string) $registro['inscricaoStatus'] === 'Cancelada') {
                throw new RuntimeException(
                    'A presença não pode ser alterada porque a inscrição está cancelada.'
                );
            }

            if ($this->eventoEncerrado($registro)) {
                throw new RuntimeException(
                    'O evento já foi encerrado. O status de presença não pode mais ser alterado.'
                );
            }

            $pagamentoObrigatorio =
                (int) ($registro['pagamento_obrigatorio'] ?? 0) === 1;
            $pagamentoStatus = (string) ($registro['pagamentoStatus'] ?? 'Pendente');

            if ($presente && $pagamentoObrigatorio && $pagamentoStatus !== 'Pago') {
                throw new RuntimeException(
                    'A presença só pode ser confirmada depois que o pagamento estiver como Pago.'
                );
            }

            $stmtAtualizar = $this->db->prepare("
                UPDATE inscricoes
                SET
                    presenca = :presenca,
                    presenca_status = :presencaStatus,
                    presenca_registrada_em = :registradaEm,
                    presenca_registrada_por = :registradaPor,
                    presenca_finalizada_em = NULL
                WHERE idInscricao = :idInscricao
                LIMIT 1
            ");

            $stmtAtualizar->execute([
                ':presenca' => $presente ? 1 : 0,
                ':presencaStatus' => $presente ? 'Presente' : 'Pendente',
                ':registradaEm' => $presente ? date('Y-m-d H:i:s') : null,
                ':registradaPor' => $presente && $idResponsavel > 0
                    ? $idResponsavel
                    : null,
                ':idInscricao' => $idInscricao
            ]);

            if ($iniciouTransacao) {
                $this->db->commit();
            }

            return [
                'idInscricao' => $idInscricao,
                'presenca' => $presente ? 1 : 0,
                'presencaStatus' => $presente ? 'Presente' : 'Pendente',
                'msg' => $presente
                    ? 'Presença confirmada com sucesso.'
                    : 'Presença removida. O participante voltou para Aguardando credenciamento.'
            ];
        } catch (Throwable $erro) {
            if ($iniciouTransacao && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $erro;
        }
    }

    public function alternarPresenca(
        int $idInscricao,
        int $idResponsavel = 0
    ): array {
        $registro = $this->buscarInscricao($idInscricao);

        if (!$registro) {
            throw new RuntimeException('Inscrição não encontrada.');
        }

        $presenteAtual =
            (int) ($registro['presenca'] ?? 0) === 1
            || (string) ($registro['presencaStatus'] ?? '') === 'Presente';

        return $this->registrarPresenca(
            $idInscricao,
            !$presenteAtual,
            $idResponsavel
        );
    }

    public function finalizarEvento(int $idEvento): int
    {
        $evento = $this->buscarEvento($idEvento);

        if (!$evento || !$this->eventoEncerrado($evento)) {
            return 0;
        }

        $stmt = $this->db->prepare("
            UPDATE inscricoes
            SET
                presenca = 0,
                presenca_status = 'Ausente',
                presenca_finalizada_em = COALESCE(
                    presenca_finalizada_em,
                    NOW()
                )
            WHERE idEvento = :idEvento
              AND status <> 'Cancelada'
              AND presenca = 0
              AND (
                    presenca_status = 'Pendente'
                    OR presenca_status IS NULL
                    OR presenca_status = ''
              )
        ");

        $stmt->execute([':idEvento' => $idEvento]);

        return $stmt->rowCount();
    }

    public function finalizarEventosEncerrados(): array
    {
        $eventos = $this->listarEventos();
        $eventosFinalizados = 0;
        $ausenciasRegistradas = 0;

        foreach ($eventos as $evento) {
            if (!$this->eventoEncerrado($evento)) {
                continue;
            }

            $alterados = $this->finalizarEvento((int) $evento['idEvento']);

            if ($alterados > 0) {
                $eventosFinalizados++;
                $ausenciasRegistradas += $alterados;
            }
        }

        return [
            'eventosFinalizados' => $eventosFinalizados,
            'ausenciasRegistradas' => $ausenciasRegistradas
        ];
    }
}
