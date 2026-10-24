<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/db.php';

/**
 * Consultas do relatório financeiro.
 *
 * O fluxo financeiro atual usa a tabela pagamentos como fonte oficial.
 */
class Financeiro
{
    private PDO $db;

    public function __construct(?PDO $conexao = null)
    {
        $this->db = $conexao ?? Database::connect();

        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * Monta o relatório financeiro completo para o período informado.
     *
     * A data de referência é:
     * - pagamento: dataPagamento;
     * - pendente: dataVencimento;
     * - sem as datas anteriores: criadoEm.
     *
     * @return array<string, mixed>
     */
    public function relatorio(string $dataInicio, string $dataFim, int $idEvento = 0): array
    {
        [$inicio, $fim] = $this->normalizarPeriodo($dataInicio, $dataFim);

        $dias = (int) $inicio->diff($fim)->format('%a') + 1;
        $agruparPorMes = $dias > 31;

        return [
            'periodo' => [
                'inicio' => $inicio->format('Y-m-d'),
                'fim' => $fim->format('Y-m-d'),
                'inicioFormatado' => $inicio->format('d/m/Y'),
                'fimFormatado' => $fim->format('d/m/Y'),
                'agrupamento' => $agruparPorMes ? 'mensal' : 'diario'
            ],
            'resumo' => $this->resumo($inicio, $fim, $idEvento),
            'serie' => $this->serieFinanceira($inicio, $fim, $idEvento, $agruparPorMes),
            'formas' => $this->recebimentosPorForma($inicio, $fim, $idEvento),
            'eventos' => $this->resumoPorEvento($inicio, $fim, $idEvento),
            'movimentos' => $this->movimentos($inicio, $fim, $idEvento)
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function resumo(DateTimeImmutable $inicio, DateTimeImmutable $fim, int $idEvento): array
    {
        [$where, $parametros] = $this->filtroPeriodo($inicio, $fim, $idEvento);

        $sql = "
            SELECT
                COUNT(*) AS quantidade,
                COALESCE(SUM(CASE WHEN p.status = 'Pago' THEN p.valor ELSE 0 END), 0) AS recebido,
                COALESCE(SUM(CASE WHEN p.status IN ('Pendente', 'Vencido') THEN p.valor ELSE 0 END), 0) AS pendente,
                COALESCE(SUM(CASE WHEN p.status = 'Cancelado' THEN p.valor ELSE 0 END), 0) AS cancelado,
                COALESCE(SUM(CASE WHEN p.status = 'Estornado' THEN p.valor ELSE 0 END), 0) AS estornado,
                SUM(CASE WHEN p.status = 'Pago' THEN 1 ELSE 0 END) AS quantidadePago,
                SUM(CASE WHEN p.status IN ('Pendente', 'Vencido') THEN 1 ELSE 0 END) AS quantidadePendente,
                SUM(CASE WHEN p.status = 'Cancelado' THEN 1 ELSE 0 END) AS quantidadeCancelado,
                SUM(CASE WHEN p.status = 'Estornado' THEN 1 ELSE 0 END) AS quantidadeEstornado
            FROM pagamentos p
            {$where}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);
        $linha = $stmt->fetch() ?: [];

        $recebido = (float) ($linha['recebido'] ?? 0);
        $pendente = (float) ($linha['pendente'] ?? 0);

        return [
            'quantidade' => (int) ($linha['quantidade'] ?? 0),
            'recebido' => $recebido,
            'pendente' => $pendente,
            'previsto' => $recebido + $pendente,
            'cancelado' => (float) ($linha['cancelado'] ?? 0),
            'estornado' => (float) ($linha['estornado'] ?? 0),
            'quantidadePago' => (int) ($linha['quantidadePago'] ?? 0),
            'quantidadePendente' => (int) ($linha['quantidadePendente'] ?? 0),
            'quantidadeCancelado' => (int) ($linha['quantidadeCancelado'] ?? 0),
            'quantidadeEstornado' => (int) ($linha['quantidadeEstornado'] ?? 0)
        ];
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    private function serieFinanceira(
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
        int $idEvento,
        bool $mensal
    ): array {
        [$where, $parametros] = $this->filtroPeriodo($inicio, $fim, $idEvento);
        $dataReferencia = $this->expressaoDataReferencia();

        if ($mensal) {
            $chaveSql = "DATE_FORMAT({$dataReferencia}, '%Y-%m')";
            $rotuloSql = "DATE_FORMAT({$dataReferencia}, '%m/%Y')";
        } else {
            $chaveSql = "DATE_FORMAT({$dataReferencia}, '%Y-%m-%d')";
            $rotuloSql = "DATE_FORMAT({$dataReferencia}, '%d/%m')";
        }

        $sql = "
            SELECT
                {$chaveSql} AS chave,
                {$rotuloSql} AS rotulo,
                COALESCE(SUM(CASE WHEN p.status = 'Pago' THEN p.valor ELSE 0 END), 0) AS recebido,
                COALESCE(SUM(CASE WHEN p.status IN ('Pendente', 'Vencido') THEN p.valor ELSE 0 END), 0) AS pendente
            FROM pagamentos p
            {$where}
            GROUP BY chave, rotulo
            ORDER BY chave
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);

        $consultado = [];
        foreach ($stmt->fetchAll() as $linha) {
            $consultado[(string) $linha['chave']] = [
                'chave' => (string) $linha['chave'],
                'rotulo' => (string) $linha['rotulo'],
                'recebido' => (float) $linha['recebido'],
                'pendente' => (float) $linha['pendente']
            ];
        }

        return $this->preencherSerie($inicio, $fim, $mensal, $consultado);
    }

    /**
     * @param array<string, array<string, int|float|string>> $consultado
     * @return array<int, array<string, int|float|string>>
     */
    private function preencherSerie(
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
        bool $mensal,
        array $consultado
    ): array {
        $resultado = [];

        if ($mensal) {
            $cursor = $inicio->modify('first day of this month');
            $limite = $fim->modify('first day of this month');

            while ($cursor <= $limite) {
                $chave = $cursor->format('Y-m');
                $resultado[] = $consultado[$chave] ?? [
                    'chave' => $chave,
                    'rotulo' => $cursor->format('m/Y'),
                    'recebido' => 0.0,
                    'pendente' => 0.0
                ];
                $cursor = $cursor->modify('+1 month');
            }

            return $resultado;
        }

        $cursor = $inicio;
        while ($cursor <= $fim) {
            $chave = $cursor->format('Y-m-d');
            $resultado[] = $consultado[$chave] ?? [
                'chave' => $chave,
                'rotulo' => $cursor->format('d/m'),
                'recebido' => 0.0,
                'pendente' => 0.0
            ];
            $cursor = $cursor->modify('+1 day');
        }

        return $resultado;
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    private function recebimentosPorForma(
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
        int $idEvento
    ): array {
        [$where, $parametros] = $this->filtroPeriodo($inicio, $fim, $idEvento);

        $sql = "
            SELECT
                p.formaPagamento AS forma,
                COUNT(*) AS quantidade,
                COALESCE(SUM(p.valor), 0) AS valor
            FROM pagamentos p
            {$where}
              AND p.status = 'Pago'
            GROUP BY p.formaPagamento
            ORDER BY valor DESC, forma ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);

        return array_map(
            static fn(array $linha): array => [
                'forma' => self::nomeForma((string) ($linha['forma'] ?? '')),
                'codigo' => (string) ($linha['forma'] ?? ''),
                'quantidade' => (int) ($linha['quantidade'] ?? 0),
                'valor' => (float) ($linha['valor'] ?? 0)
            ],
            $stmt->fetchAll()
        );
    }

    /**
     * @return array<int, array<string, int|float|string>>
     */
    private function resumoPorEvento(
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
        int $idEvento
    ): array {
        [$where, $parametros] = $this->filtroPeriodo($inicio, $fim, $idEvento);

        $sql = "
            SELECT
                e.idEvento,
                e.titulo AS evento,
                COUNT(p.idPagamento) AS quantidade,
                COALESCE(SUM(CASE WHEN p.status = 'Pago' THEN p.valor ELSE 0 END), 0) AS recebido,
                COALESCE(SUM(CASE WHEN p.status IN ('Pendente', 'Vencido') THEN p.valor ELSE 0 END), 0) AS pendente,
                COALESCE(SUM(CASE WHEN p.status IN ('Cancelado', 'Estornado') THEN p.valor ELSE 0 END), 0) AS cancelado
            FROM pagamentos p
            INNER JOIN eventos e ON e.idEvento = p.idEvento
            {$where}
            GROUP BY e.idEvento, e.titulo
            ORDER BY recebido DESC, evento ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);

        return array_map(
            static fn(array $linha): array => [
                'idEvento' => (int) ($linha['idEvento'] ?? 0),
                'evento' => (string) ($linha['evento'] ?? ''),
                'quantidade' => (int) ($linha['quantidade'] ?? 0),
                'recebido' => (float) ($linha['recebido'] ?? 0),
                'pendente' => (float) ($linha['pendente'] ?? 0),
                'cancelado' => (float) ($linha['cancelado'] ?? 0)
            ],
            $stmt->fetchAll()
        );
    }

    /**
     * @return array<int, array<string, int|float|string|null>>
     */
    private function movimentos(
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
        int $idEvento
    ): array {
        [$where, $parametros] = $this->filtroPeriodo($inicio, $fim, $idEvento);
        $dataReferencia = $this->expressaoDataReferencia();

        $sql = "
            SELECT
                p.idPagamento,
                p.codigo,
                p.participante,
                p.descricao,
                p.formaPagamento,
                p.status,
                p.valor,
                p.dataVencimento,
                p.dataPagamento,
                {$dataReferencia} AS dataReferencia,
                e.titulo AS evento
            FROM pagamentos p
            INNER JOIN eventos e ON e.idEvento = p.idEvento
            {$where}
            ORDER BY dataReferencia DESC, p.idPagamento DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);

        return array_map(
            static fn(array $linha): array => [
                'idPagamento' => (int) ($linha['idPagamento'] ?? 0),
                'codigo' => (string) ($linha['codigo'] ?? ''),
                'participante' => (string) ($linha['participante'] ?? ''),
                'evento' => (string) ($linha['evento'] ?? ''),
                'descricao' => (string) ($linha['descricao'] ?? ''),
                'forma' => self::nomeForma((string) ($linha['formaPagamento'] ?? '')),
                'formaCodigo' => (string) ($linha['formaPagamento'] ?? ''),
                'status' => (string) ($linha['status'] ?? ''),
                'valor' => (float) ($linha['valor'] ?? 0),
                'dataVencimento' => $linha['dataVencimento'] ?? null,
                'dataPagamento' => $linha['dataPagamento'] ?? null,
                'dataReferencia' => $linha['dataReferencia'] ?? null
            ],
            $stmt->fetchAll()
        );
    }

    /**
     * @return array{0: string, 1: array<string, string|int>}
     */
    private function filtroPeriodo(
        DateTimeImmutable $inicio,
        DateTimeImmutable $fim,
        int $idEvento
    ): array {
        $dataReferencia = $this->expressaoDataReferencia();

        $where = "
            WHERE DATE({$dataReferencia}) BETWEEN :dataInicio AND :dataFim
        ";

        $parametros = [
            ':dataInicio' => $inicio->format('Y-m-d'),
            ':dataFim' => $fim->format('Y-m-d')
        ];

        if ($idEvento > 0) {
            $where .= " AND p.idEvento = :idEvento";
            $parametros[':idEvento'] = $idEvento;
        }

        return [$where, $parametros];
    }

    private function expressaoDataReferencia(): string
    {
        return 'COALESCE(p.dataPagamento, p.dataVencimento, p.criadoEm)';
    }

    /**
     * @return array{0: DateTimeImmutable, 1: DateTimeImmutable}
     */
    private function normalizarPeriodo(string $dataInicio, string $dataFim): array
    {
        $inicio = DateTimeImmutable::createFromFormat('!Y-m-d', trim($dataInicio));
        $fim = DateTimeImmutable::createFromFormat('!Y-m-d', trim($dataFim));

        if (!$inicio || $inicio->format('Y-m-d') !== trim($dataInicio)) {
            throw new InvalidArgumentException('A data inicial é inválida.');
        }

        if (!$fim || $fim->format('Y-m-d') !== trim($dataFim)) {
            throw new InvalidArgumentException('A data final é inválida.');
        }

        if ($inicio > $fim) {
            throw new InvalidArgumentException('A data inicial não pode ser maior que a data final.');
        }

        if ((int) $inicio->diff($fim)->format('%a') > 3660) {
            throw new InvalidArgumentException('O período máximo permitido é de 10 anos.');
        }

        return [$inicio, $fim];
    }

    public static function nomeForma(string $forma): string
    {
        return [
            'NaoDefinido' => 'A definir',
            'PIX' => 'PIX',
            'Cartao' => 'Cartão de crédito',
            'Boleto' => 'Boleto',
            'Dinheiro' => 'Dinheiro',
            'Transferencia' => 'Transferência'
        ][$forma] ?? ($forma !== '' ? $forma : 'Não informada');
    }
}
