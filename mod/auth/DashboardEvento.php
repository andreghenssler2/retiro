<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/db.php';

class Dashboard
{
    private PDO $db;
    private int $ano = 0;
    private int $mes = 0;
    private int $idEvento = 0;

    public function __construct(?PDO $conexao = null, array $filtros = [])
    {
        $this->db = $conexao ?? Database::connect();

        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->idEvento = max(0, (int) ($filtros['evento'] ?? 0));
        $this->ano = (int) ($filtros['ano'] ?? 0);
        $this->mes = (int) ($filtros['mes'] ?? 0);

        if ($this->ano < 2000 || $this->ano > 2100) {
            $this->ano = 0;
        }

        if ($this->mes < 1 || $this->mes > 12) {
            $this->mes = 0;
        }

        // O filtro por evento é exclusivo e tem prioridade sobre ano/mês.
        if ($this->idEvento > 0) {
            $this->ano = 0;
            $this->mes = 0;
        }
    }

    private function filtroEvento(string $alias = 'e'): array
    {
        $condicoes = [];
        $params = [];

        if ($this->idEvento > 0) {
            $condicoes[] = "{$alias}.idEvento = :filtro_evento";
            $params[':filtro_evento'] = $this->idEvento;
        } else {
            if ($this->ano > 0) {
                $condicoes[] = "YEAR({$alias}.data_inicio) = :filtro_ano";
                $params[':filtro_ano'] = $this->ano;
            }

            if ($this->mes > 0) {
                $condicoes[] = "MONTH({$alias}.data_inicio) = :filtro_mes";
                $params[':filtro_mes'] = $this->mes;
            }
        }

        return [
            'sql' => $condicoes ? ' AND ' . implode(' AND ', $condicoes) : '',
            'params' => $params
        ];
    }

    private function escalar(string $sql, array $params = []): mixed
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    private function consultar(string $sql, array $params = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function totalEventos(): int
    {
        $filtro = $this->filtroEvento('e');

        return (int) $this->escalar(
            "SELECT COUNT(*) FROM eventos e WHERE e.ativo = 1 {$filtro['sql']}",
            $filtro['params']
        );
    }

    public function totalInscritos(): int
    {
        $filtro = $this->filtroEvento('e');

        return (int) $this->escalar(
            "SELECT COUNT(*)
             FROM inscricoes i
             INNER JOIN eventos e ON e.idEvento = i.idEvento
             WHERE 1 = 1 {$filtro['sql']}",
            $filtro['params']
        );
    }

    public function totalConfirmados(): int
    {
        return $this->contarInscricoesPorStatus('Confirmada');
    }

    public function totalPendentes(): int
    {
        return $this->contarInscricoesPorStatus('Pendente');
    }

    public function totalCanceladas(): int
    {
        return $this->contarInscricoesPorStatus('Cancelada');
    }

    public function totalPresencas(): int
    {
        $filtro = $this->filtroEvento('e');

        return (int) $this->escalar(
            "SELECT COUNT(*)
             FROM inscricoes i
             INNER JOIN eventos e ON e.idEvento = i.idEvento
             WHERE i.presenca = 1
               AND i.status <> 'Cancelada'
               {$filtro['sql']}",
            $filtro['params']
        );
    }

    public function totalReceitas(): float
    {
        $filtro = $this->filtroEvento('e');

        return (float) $this->escalar(
            "SELECT COALESCE(SUM(p.valor), 0)
             FROM pagamentos p
             INNER JOIN eventos e ON e.idEvento = p.idEvento
             WHERE p.status = 'Pago'
               {$filtro['sql']}",
            $filtro['params']
        );
    }

    public function totalPendenteFinanceiro(): float
    {
        $filtro = $this->filtroEvento('e');

        return (float) $this->escalar(
            "SELECT COALESCE(SUM(p.valor), 0)
             FROM pagamentos p
             INNER JOIN eventos e ON e.idEvento = p.idEvento
             WHERE p.status IN ('Pendente', 'Vencido')
               {$filtro['sql']}",
            $filtro['params']
        );
    }

    public function totalDespesas(): float
    {
        return 0.0;
    }

    public function saldo(): float
    {
        return $this->totalReceitas();
    }

    private function contarInscricoesPorStatus(string $status): int
    {
        $filtro = $this->filtroEvento('e');
        $params = $filtro['params'];
        $params[':status_inscricao'] = $status;

        return (int) $this->escalar(
            "SELECT COUNT(*)
             FROM inscricoes i
             INNER JOIN eventos e ON e.idEvento = i.idEvento
             WHERE i.status = :status_inscricao
               {$filtro['sql']}",
            $params
        );
    }

    public function camisetas(): array
    {
        $filtro = $this->filtroEvento('e');

        return $this->consultar(
            "SELECT i.camiseta, COUNT(*) AS total
             FROM inscricoes i
             INNER JOIN eventos e ON e.idEvento = i.idEvento
             WHERE i.camiseta IS NOT NULL
               AND TRIM(i.camiseta) <> ''
               AND i.status <> 'Cancelada'
               {$filtro['sql']}
             GROUP BY i.camiseta
             ORDER BY FIELD(i.camiseta, 'PP', 'P', 'M', 'G', 'GG', 'XGG'), i.camiseta",
            $filtro['params']
        );
    }

    public function cidades(): array
    {
        $filtro = $this->filtroEvento('e');

        return $this->consultar(
            "SELECT i.cidade, COUNT(*) AS total
             FROM inscricoes i
             INNER JOIN eventos e ON e.idEvento = i.idEvento
             WHERE i.cidade IS NOT NULL
               AND TRIM(i.cidade) <> ''
               AND i.status <> 'Cancelada'
               {$filtro['sql']}
             GROUP BY i.cidade
             ORDER BY total DESC, i.cidade ASC
             LIMIT 10",
            $filtro['params']
        );
    }

    public function ultimasInscricoes(int $limite = 10): array
    {
        $limite = max(1, min(50, $limite));
        $filtro = $this->filtroEvento('e');

        return $this->consultar(
            "SELECT
                i.idInscricao,
                i.nome,
                i.cidade,
                i.status,
                COALESCE(p.status, i.pagamento, 'Pendente') AS pagamento,
                i.criado_em,
                e.titulo AS evento
             FROM inscricoes i
             INNER JOIN eventos e ON e.idEvento = i.idEvento
             LEFT JOIN pagamentos p ON p.idInscricao = i.idInscricao
             WHERE 1 = 1
               {$filtro['sql']}
             ORDER BY i.criado_em DESC, i.idInscricao DESC
             LIMIT {$limite}",
            $filtro['params']
        );
    }

    public function pagamentosPendentes(int $limite = 10): array
    {
        $limite = max(1, min(50, $limite));
        $filtro = $this->filtroEvento('e');

        return $this->consultar(
            "SELECT
                p.idPagamento,
                p.idInscricao,
                p.participante AS nome,
                p.valor,
                p.dataVencimento AS vencimento,
                p.codigo,
                p.formaPagamento,
                e.titulo AS evento
             FROM pagamentos p
             INNER JOIN eventos e ON e.idEvento = p.idEvento
             WHERE p.status IN ('Pendente', 'Vencido')
               {$filtro['sql']}
             ORDER BY
                p.dataVencimento IS NULL,
                p.dataVencimento ASC,
                p.criadoEm ASC
             LIMIT {$limite}",
            $filtro['params']
        );
    }

    public function pagamentosPorStatus(): array
    {
        $status = ['Pendente', 'Vencido', 'Pago', 'Cancelado', 'Estornado'];
        $resultado = [];

        foreach ($status as $item) {
            $resultado[$item] = [
                'status' => $item,
                'total' => 0,
                'valor' => 0.0
            ];
        }

        $filtro = $this->filtroEvento('e');
        $linhas = $this->consultar(
            "SELECT
                p.status,
                COUNT(*) AS total,
                COALESCE(SUM(p.valor), 0) AS valor
             FROM pagamentos p
             INNER JOIN eventos e ON e.idEvento = p.idEvento
             WHERE 1 = 1
               {$filtro['sql']}
             GROUP BY p.status",
            $filtro['params']
        );

        foreach ($linhas as $linha) {
            $nomeStatus = (string) ($linha['status'] ?? '');

            if (!isset($resultado[$nomeStatus])) {
                continue;
            }

            $resultado[$nomeStatus] = [
                'status' => $nomeStatus,
                'total' => (int) ($linha['total'] ?? 0),
                'valor' => (float) ($linha['valor'] ?? 0)
            ];
        }

        return array_values($resultado);
    }

    public function financeiroMensal(): array
    {
        $filtro = $this->filtroEvento('e');
        $periodoPadrao = $this->semFiltro()
            ? " AND COALESCE(p.dataPagamento, p.atualizadoEm, p.criadoEm) >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)"
            : '';

        return $this->consultar(
            "SELECT
                DATE_FORMAT(COALESCE(p.dataPagamento, p.atualizadoEm, p.criadoEm), '%m/%Y') AS mes,
                YEAR(COALESCE(p.dataPagamento, p.atualizadoEm, p.criadoEm)) AS ano_ordem,
                MONTH(COALESCE(p.dataPagamento, p.atualizadoEm, p.criadoEm)) AS mes_ordem,
                SUM(CASE WHEN p.status = 'Pago' THEN p.valor ELSE 0 END) AS receita,
                SUM(CASE WHEN p.status IN ('Pendente', 'Vencido') THEN p.valor ELSE 0 END) AS pendente
             FROM pagamentos p
             INNER JOIN eventos e ON e.idEvento = p.idEvento
             WHERE 1 = 1
               {$filtro['sql']}
               {$periodoPadrao}
             GROUP BY ano_ordem, mes_ordem, mes
             ORDER BY ano_ordem, mes_ordem",
            $filtro['params']
        );
    }

    public function inscricoesMensal(): array
    {
        $filtro = $this->filtroEvento('e');
        $periodoPadrao = $this->semFiltro()
            ? " AND i.criado_em >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)"
            : '';

        return $this->consultar(
            "SELECT
                DATE_FORMAT(i.criado_em, '%m/%Y') AS mes,
                YEAR(i.criado_em) AS ano_ordem,
                MONTH(i.criado_em) AS mes_ordem,
                COUNT(*) AS total
             FROM inscricoes i
             INNER JOIN eventos e ON e.idEvento = i.idEvento
             WHERE 1 = 1
               {$filtro['sql']}
               {$periodoPadrao}
             GROUP BY ano_ordem, mes_ordem, mes
             ORDER BY ano_ordem, mes_ordem",
            $filtro['params']
        );
    }

    private function semFiltro(): bool
    {
        return $this->idEvento === 0 && $this->ano === 0 && $this->mes === 0;
    }
}
