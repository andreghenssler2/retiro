<?php

declare(strict_types=1);

require_once __DIR__ . '/../database/db.php';

/**
 * Centraliza os relatórios administrativos do sistema.
 */
class RelatorioGeral
{
    private PDO $db;

    /** @var array<string, string> */
    private const TIPOS = [
        'financeiro' => 'Financeiro',
        'pagamentos' => 'Pagamentos',
        'eventos' => 'Eventos',
        'usuarios' => 'Usuários',
        'inscricoes' => 'Inscrições',
    ];

    public function __construct(?PDO $conexao = null)
    {
        $this->db = $conexao ?? Database::connect();
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>
     */
    public function gerar(array $entrada, bool $paraPdf = false): array
    {
        $filtros = $this->normalizarFiltros($entrada, $paraPdf);

        return match ($filtros['tipo']) {
            'financeiro' => $this->financeiro($filtros),
            'pagamentos' => $this->pagamentos($filtros),
            'eventos' => $this->eventos($filtros),
            'usuarios' => $this->usuarios($filtros),
            'inscricoes' => $this->inscricoes($filtros),
            default => throw new InvalidArgumentException('Tipo de relatório inválido.'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizarFiltros(array $entrada, bool $paraPdf): array
    {
        $tipo = strtolower(trim((string) ($entrada['tipo'] ?? 'financeiro')));
        if (!isset(self::TIPOS[$tipo])) {
            $tipo = 'financeiro';
        }

        $inicio = $this->normalizarData((string) ($entrada['dataInicio'] ?? ''), 'data inicial');
        $fim = $this->normalizarData((string) ($entrada['dataFim'] ?? ''), 'data final');

        if ($inicio > $fim) {
            throw new InvalidArgumentException('A data inicial não pode ser maior que a data final.');
        }

        $limite = (int) ($entrada['limite'] ?? 100);
        $limitesPermitidos = [25, 50, 100, 250, 500];
        if (!in_array($limite, $limitesPermitidos, true)) {
            $limite = 100;
        }

        if ($paraPdf) {
            $limite = 5000;
        }

        return [
            'tipo' => $tipo,
            'tipoTitulo' => self::TIPOS[$tipo],
            'dataInicio' => $inicio->format('Y-m-d'),
            'dataFim' => $fim->format('Y-m-d'),
            'dataInicioFormatada' => $inicio->format('d/m/Y'),
            'dataFimFormatada' => $fim->format('d/m/Y'),
            'idEvento' => max(0, (int) ($entrada['idEvento'] ?? 0)),
            'status' => trim((string) ($entrada['status'] ?? '')),
            'statusPagamento' => trim((string) ($entrada['statusPagamento'] ?? '')),
            'formaPagamento' => trim((string) ($entrada['formaPagamento'] ?? '')),
            'integracao' => trim((string) ($entrada['integracao'] ?? '')),
            'situacaoEvento' => trim((string) ($entrada['situacaoEvento'] ?? '')),
            'tipoEvento' => trim((string) ($entrada['tipoEvento'] ?? '')),
            'situacaoUsuario' => trim((string) ($entrada['situacaoUsuario'] ?? '')),
            'perfilUsuario' => max(0, (int) ($entrada['perfilUsuario'] ?? 0)),
            'presenca' => trim((string) ($entrada['presenca'] ?? '')),
            'pesquisa' => trim((string) ($entrada['pesquisa'] ?? '')),
            'limite' => $limite,
        ];
    }

    private function normalizarData(string $valor, string $nome): DateTimeImmutable
    {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', trim($valor));
        $erros = DateTimeImmutable::getLastErrors();

        if (!$data || (is_array($erros) && ($erros['warning_count'] > 0 || $erros['error_count'] > 0))) {
            throw new InvalidArgumentException('Informe uma ' . $nome . ' válida.');
        }

        return $data;
    }

    /**
     * @param array<int, mixed> $parametros
     * @return array<int, array<string, mixed>>
     */
    private function consultar(string $sql, array $parametros = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param array<int, mixed> $parametros
     * @return array<string, mixed>
     */
    private function consultarUm(string $sql, array $parametros = []): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parametros);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);
        return is_array($linha) ? $linha : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function financeiro(array $f): array
    {
        $dataReferencia = "DATE(CASE WHEN p.status = 'Pago' AND p.dataPagamento IS NOT NULL THEN p.dataPagamento WHEN p.dataVencimento IS NOT NULL THEN p.dataVencimento ELSE p.criadoEm END)";
        $where = ["{$dataReferencia} BETWEEN ? AND ?"];
        $params = [$f['dataInicio'], $f['dataFim']];

        $this->filtroPagamentoComum($f, $where, $params, 'p', 'e');

        $whereSql = implode(' AND ', $where);
        $valor = 'COALESCE(NULLIF(p.valorCobrancaAsaas, 0), p.valor, 0)';

        $resumo = $this->consultarUm(
            "SELECT
                COUNT(*) AS quantidade,
                COUNT(DISTINCT p.idEvento) AS eventos,
                COALESCE(SUM(CASE WHEN p.status IN ('Pago','Pendente','Vencido') THEN {$valor} ELSE 0 END), 0) AS previsto,
                COALESCE(SUM(CASE WHEN p.status = 'Pago' THEN {$valor} ELSE 0 END), 0) AS recebido,
                COALESCE(SUM(CASE WHEN p.status IN ('Pendente','Vencido') THEN {$valor} ELSE 0 END), 0) AS pendente,
                COALESCE(SUM(CASE WHEN p.status = 'Cancelado' THEN {$valor} ELSE 0 END), 0) AS cancelado,
                COALESCE(SUM(CASE WHEN p.status = 'Estornado' THEN {$valor} ELSE 0 END), 0) AS estornado
             FROM pagamentos p
             INNER JOIN eventos e ON e.idEvento = p.idEvento
             WHERE {$whereSql}",
            $params
        );

        $linhas = $this->consultar(
            "SELECT
                e.titulo AS evento,
                COUNT(*) AS pagamentos,
                COALESCE(SUM(CASE WHEN p.status = 'Pago' THEN {$valor} ELSE 0 END), 0) AS recebido,
                COALESCE(SUM(CASE WHEN p.status IN ('Pendente','Vencido') THEN {$valor} ELSE 0 END), 0) AS pendente,
                COALESCE(SUM(CASE WHEN p.status = 'Cancelado' THEN {$valor} ELSE 0 END), 0) AS cancelado,
                COALESCE(SUM(CASE WHEN p.status = 'Estornado' THEN {$valor} ELSE 0 END), 0) AS estornado,
                COALESCE(SUM(CASE WHEN p.status IN ('Pago','Pendente','Vencido') THEN {$valor} ELSE 0 END), 0) AS previsto
             FROM pagamentos p
             INNER JOIN eventos e ON e.idEvento = p.idEvento
             WHERE {$whereSql}
             GROUP BY e.idEvento, e.titulo
             ORDER BY previsto DESC, e.titulo ASC
             LIMIT " . (int) $f['limite'],
            $params
        );

        $grafico = [
            'titulo' => 'Valores por situação',
            'labels' => ['Recebido', 'Pendente/vencido', 'Cancelado', 'Estornado'],
            'valores' => [
                (float) ($resumo['recebido'] ?? 0),
                (float) ($resumo['pendente'] ?? 0),
                (float) ($resumo['cancelado'] ?? 0),
                (float) ($resumo['estornado'] ?? 0),
            ],
            'formato' => 'moeda',
        ];

        return $this->montarResposta(
            $f,
            'Relatório financeiro',
            'Resumo de valores por evento no período selecionado.',
            [
                $this->card('Previsto', $resumo['previsto'] ?? 0, 'moeda', 'fa-sack-dollar', 'primary'),
                $this->card('Recebido', $resumo['recebido'] ?? 0, 'moeda', 'fa-circle-check', 'success'),
                $this->card('Pendente/vencido', $resumo['pendente'] ?? 0, 'moeda', 'fa-clock', 'warning'),
                $this->card('Cancelado/estornado', (float) ($resumo['cancelado'] ?? 0) + (float) ($resumo['estornado'] ?? 0), 'moeda', 'fa-ban', 'danger'),
            ],
            $grafico,
            [
                $this->coluna('evento', 'Evento'),
                $this->coluna('pagamentos', 'Pagamentos', 'inteiro', 'text-center'),
                $this->coluna('previsto', 'Previsto', 'moeda', 'text-end'),
                $this->coluna('recebido', 'Recebido', 'moeda', 'text-end'),
                $this->coluna('pendente', 'Pendente', 'moeda', 'text-end'),
                $this->coluna('cancelado', 'Cancelado', 'moeda', 'text-end'),
                $this->coluna('estornado', 'Estornado', 'moeda', 'text-end'),
            ],
            $linhas,
            (int) ($resumo['eventos'] ?? count($linhas))
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function pagamentos(array $f): array
    {
        $dataReferencia = "DATE(CASE WHEN p.status = 'Pago' AND p.dataPagamento IS NOT NULL THEN p.dataPagamento WHEN p.dataVencimento IS NOT NULL THEN p.dataVencimento ELSE p.criadoEm END)";
        $where = ["{$dataReferencia} BETWEEN ? AND ?"];
        $params = [$f['dataInicio'], $f['dataFim']];
        $this->filtroPagamentoComum($f, $where, $params, 'p', 'e');
        $whereSql = implode(' AND ', $where);
        $valor = 'COALESCE(NULLIF(p.valorCobrancaAsaas, 0), p.valor, 0)';

        $resumo = $this->consultarUm(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN p.status = 'Pago' THEN {$valor} ELSE 0 END), 0) AS recebido,
                COALESCE(SUM(CASE WHEN p.status IN ('Pendente','Vencido') THEN {$valor} ELSE 0 END), 0) AS pendente,
                COALESCE(SUM(CASE WHEN p.status IN ('Cancelado','Estornado') THEN {$valor} ELSE 0 END), 0) AS encerrado
             FROM pagamentos p
             INNER JOIN eventos e ON e.idEvento = p.idEvento
             WHERE {$whereSql}",
            $params
        );

        $linhas = $this->consultar(
            "SELECT
                p.codigo,
                p.participante,
                e.titulo AS evento,
                p.formaPagamento,
                p.integracao,
                p.status,
                {$valor} AS valor,
                p.dataVencimento,
                p.dataPagamento,
                p.criadoEm
             FROM pagamentos p
             INNER JOIN eventos e ON e.idEvento = p.idEvento
             WHERE {$whereSql}
             ORDER BY COALESCE(p.dataPagamento, p.dataVencimento, p.criadoEm) DESC, p.idPagamento DESC
             LIMIT " . (int) $f['limite'],
            $params
        );

        $contagens = $this->consultar(
            "SELECT p.status AS rotulo, COUNT(*) AS total
             FROM pagamentos p
             INNER JOIN eventos e ON e.idEvento = p.idEvento
             WHERE {$whereSql}
             GROUP BY p.status
             ORDER BY total DESC",
            $params
        );

        return $this->montarResposta(
            $f,
            'Relatório de pagamentos',
            'Lista detalhada das cobranças e recebimentos.',
            [
                $this->card('Pagamentos', $resumo['total'] ?? 0, 'inteiro', 'fa-receipt', 'primary'),
                $this->card('Recebido', $resumo['recebido'] ?? 0, 'moeda', 'fa-circle-check', 'success'),
                $this->card('Pendente/vencido', $resumo['pendente'] ?? 0, 'moeda', 'fa-clock', 'warning'),
                $this->card('Cancelado/estornado', $resumo['encerrado'] ?? 0, 'moeda', 'fa-ban', 'danger'),
            ],
            $this->graficoDeLinhas($contagens, 'Pagamentos por situação', 'inteiro'),
            [
                $this->coluna('codigo', 'Código'),
                $this->coluna('participante', 'Participante'),
                $this->coluna('evento', 'Evento'),
                $this->coluna('formaPagamento', 'Forma'),
                $this->coluna('integracao', 'Integração'),
                $this->coluna('status', 'Situação', 'status'),
                $this->coluna('valor', 'Valor', 'moeda', 'text-end'),
                $this->coluna('dataVencimento', 'Vencimento', 'data'),
                $this->coluna('dataPagamento', 'Pagamento', 'datahora'),
            ],
            $linhas,
            (int) ($resumo['total'] ?? 0)
        );
    }

    /**
     * @param array<int, string> $where
     * @param array<int, mixed> $params
     */
    private function filtroPagamentoComum(array $f, array &$where, array &$params, string $p, string $e): void
    {
        if ($f['idEvento'] > 0) {
            $where[] = "{$p}.idEvento = ?";
            $params[] = $f['idEvento'];
        }

        $statusPermitidos = ['Pendente', 'Vencido', 'Pago', 'Cancelado', 'Estornado'];
        if (in_array($f['status'], $statusPermitidos, true)) {
            $where[] = "{$p}.status = ?";
            $params[] = $f['status'];
        }

        $formas = ['NaoDefinido', 'PIX', 'Cartao', 'Boleto', 'Dinheiro', 'Transferencia'];
        if (in_array($f['formaPagamento'], $formas, true)) {
            $where[] = "{$p}.formaPagamento = ?";
            $params[] = $f['formaPagamento'];
        }

        if (in_array($f['integracao'], ['Manual', 'Asaas'], true)) {
            $where[] = "{$p}.integracao = ?";
            $params[] = $f['integracao'];
        }

        if ($f['pesquisa'] !== '') {
            $busca = '%' . $f['pesquisa'] . '%';
            $where[] = "({$p}.participante LIKE ? OR {$p}.email LIKE ? OR {$p}.codigo LIKE ? OR {$e}.titulo LIKE ?)";
            array_push($params, $busca, $busca, $busca, $busca);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function eventos(array $f): array
    {
        $where = ['e.data_inicio BETWEEN ? AND ?'];
        $params = [$f['dataInicio'], $f['dataFim']];

        if ($f['idEvento'] > 0) {
            $where[] = 'e.idEvento = ?';
            $params[] = $f['idEvento'];
        }

        $tipos = ['Retiro', 'Congresso', 'Acampamento', 'Curso', 'Encontro', 'Culto', 'Outro'];
        if (in_array($f['tipoEvento'], $tipos, true)) {
            $where[] = 'e.tipo = ?';
            $params[] = $f['tipoEvento'];
        }

        $situacoes = ['Criados', 'EmAndamento', 'Cancelados', 'Executados'];
        if (in_array($f['situacaoEvento'], $situacoes, true)) {
            $where[] = $this->sqlSituacaoEvento($f['situacaoEvento']);
        }

        if ($f['pesquisa'] !== '') {
            $busca = '%' . $f['pesquisa'] . '%';
            $where[] = '(e.titulo LIKE ? OR e.local LIKE ? OR e.cidade LIKE ?)';
            array_push($params, $busca, $busca, $busca);
        }

        $whereSql = implode(' AND ', $where);
        $situacaoSql = $this->caseSituacaoEvento();

        $linhas = $this->consultar(
            "SELECT
                e.idEvento,
                e.titulo,
                e.tipo,
                e.data_inicio,
                e.data_fim,
                e.local,
                e.cidade,
                {$situacaoSql} AS situacao,
                COUNT(DISTINCT i.idInscricao) AS inscricoes,
                COALESCE(SUM(CASE WHEN p.status = 'Pago' THEN COALESCE(NULLIF(p.valorCobrancaAsaas, 0), p.valor, 0) ELSE 0 END), 0) AS recebido
             FROM eventos e
             LEFT JOIN inscricoes i ON i.idEvento = e.idEvento
             LEFT JOIN pagamentos p ON p.idInscricao = i.idInscricao
             WHERE {$whereSql}
             GROUP BY e.idEvento, e.titulo, e.tipo, e.data_inicio, e.data_fim, e.local, e.cidade, e.ativo
             ORDER BY e.data_inicio DESC, e.titulo ASC
             LIMIT " . (int) $f['limite'],
            $params
        );

        $contagens = ['Criados' => 0, 'Em andamento' => 0, 'Cancelados' => 0, 'Executados' => 0];
        $totalInscricoes = 0;
        $totalRecebido = 0.0;
        foreach ($linhas as $linha) {
            $rotulo = (string) ($linha['situacao'] ?? '');
            if (isset($contagens[$rotulo])) {
                $contagens[$rotulo]++;
            }
            $totalInscricoes += (int) ($linha['inscricoes'] ?? 0);
            $totalRecebido += (float) ($linha['recebido'] ?? 0);
        }

        $graficoLinhas = [];
        foreach ($contagens as $rotulo => $total) {
            $graficoLinhas[] = ['rotulo' => $rotulo, 'total' => $total];
        }

        return $this->montarResposta(
            $f,
            'Relatório de eventos',
            'Eventos classificados pela data de realização e situação ativa.',
            [
                $this->card('Eventos', count($linhas), 'inteiro', 'fa-calendar-days', 'primary'),
                $this->card('Inscrições', $totalInscricoes, 'inteiro', 'fa-clipboard-check', 'info'),
                $this->card('Recebido', $totalRecebido, 'moeda', 'fa-sack-dollar', 'success'),
                $this->card('Cancelados', $contagens['Cancelados'], 'inteiro', 'fa-calendar-xmark', 'danger'),
            ],
            $this->graficoDeLinhas($graficoLinhas, 'Eventos por situação', 'inteiro'),
            [
                $this->coluna('idEvento', '#', 'inteiro', 'text-center'),
                $this->coluna('titulo', 'Evento'),
                $this->coluna('tipo', 'Tipo'),
                $this->coluna('situacao', 'Situação', 'status'),
                $this->coluna('data_inicio', 'Início', 'data'),
                $this->coluna('data_fim', 'Fim', 'data'),
                $this->coluna('local', 'Local'),
                $this->coluna('inscricoes', 'Inscrições', 'inteiro', 'text-center'),
                $this->coluna('recebido', 'Recebido', 'moeda', 'text-end'),
            ],
            $linhas,
            count($linhas),
            'Criados representa eventos futuros; Em andamento considera hoje entre início e fim; Executados são eventos finalizados; Cancelados são eventos inativos.'
        );
    }

    private function caseSituacaoEvento(): string
    {
        return "CASE
            WHEN e.ativo = 0 THEN 'Cancelados'
            WHEN CURDATE() < e.data_inicio THEN 'Criados'
            WHEN CURDATE() BETWEEN e.data_inicio AND COALESCE(e.data_fim, e.data_inicio) THEN 'Em andamento'
            ELSE 'Executados'
        END";
    }

    private function sqlSituacaoEvento(string $situacao): string
    {
        return match ($situacao) {
            'Criados' => "e.ativo = 1 AND e.data_inicio > CURDATE()",
            'EmAndamento' => "e.ativo = 1 AND CURDATE() BETWEEN e.data_inicio AND COALESCE(e.data_fim, e.data_inicio)",
            'Cancelados' => 'e.ativo = 0',
            'Executados' => "e.ativo = 1 AND COALESCE(e.data_fim, e.data_inicio) < CURDATE()",
            default => '1 = 1',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function usuarios(array $f): array
    {
        $where = ['DATE(u.created_at) BETWEEN ? AND ?'];
        $params = [$f['dataInicio'], $f['dataFim']];

        if ($f['situacaoUsuario'] === 'Ativos') {
            $where[] = 'u.ativo = 1';
        } elseif ($f['situacaoUsuario'] === 'Inativos') {
            $where[] = 'u.ativo = 0';
        }

        if (in_array($f['perfilUsuario'], [1, 2, 3], true)) {
            $where[] = 'u.tipo = ?';
            $params[] = $f['perfilUsuario'];
        }

        if ($f['pesquisa'] !== '') {
            $busca = '%' . $f['pesquisa'] . '%';
            $where[] = '(u.nome LIKE ? OR u.email LIKE ? OR u.cpf LIKE ?)';
            array_push($params, $busca, $busca, $busca);
        }

        $whereSql = implode(' AND ', $where);

        $linhas = $this->consultar(
            "SELECT
                u.id,
                u.nome,
                u.email,
                u.telefone,
                u.cpf,
                COALESCE(mc.nome_comunidade, '-') AS comunidade,
                CASE u.tipo WHEN 1 THEN 'Administrador' WHEN 2 THEN 'Moderador' ELSE 'Usuário normal' END AS perfil,
                CASE WHEN u.ativo = 1 THEN 'Ativo' ELSE 'Inativo' END AS situacao,
                u.created_at,
                u.ultimo_login,
                COUNT(DISTINCT i.idInscricao) AS inscricoes
             FROM usuarios u
             LEFT JOIN minha_comunidade mc ON mc.id = u.idComunidade
             LEFT JOIN inscricoes i ON i.idUsuario = u.id
             WHERE {$whereSql}
             GROUP BY u.id, u.nome, u.email, u.telefone, u.cpf, mc.nome_comunidade, u.tipo, u.ativo, u.created_at, u.ultimo_login
             ORDER BY u.nome ASC
             LIMIT " . (int) $f['limite'],
            $params
        );

        $ativos = 0;
        $inativos = 0;
        $administradores = 0;
        $moderadores = 0;
        foreach ($linhas as $linha) {
            ($linha['situacao'] ?? '') === 'Ativo' ? $ativos++ : $inativos++;
            if (($linha['perfil'] ?? '') === 'Administrador') {
                $administradores++;
            } elseif (($linha['perfil'] ?? '') === 'Moderador') {
                $moderadores++;
            }
        }

        return $this->montarResposta(
            $f,
            'Relatório de usuários',
            'Usuários cadastrados por situação e perfil de acesso.',
            [
                $this->card('Usuários', count($linhas), 'inteiro', 'fa-users', 'primary'),
                $this->card('Ativos', $ativos, 'inteiro', 'fa-user-check', 'success'),
                $this->card('Inativos', $inativos, 'inteiro', 'fa-user-slash', 'danger'),
                $this->card('Administradores/moderadores', $administradores + $moderadores, 'inteiro', 'fa-user-shield', 'info'),
            ],
            [
                'titulo' => 'Usuários por situação',
                'labels' => ['Ativos', 'Inativos'],
                'valores' => [$ativos, $inativos],
                'formato' => 'inteiro',
            ],
            [
                $this->coluna('id', '#', 'inteiro', 'text-center'),
                $this->coluna('nome', 'Nome'),
                $this->coluna('email', 'E-mail'),
                $this->coluna('telefone', 'Telefone'),
                $this->coluna('comunidade', 'Comunidade'),
                $this->coluna('perfil', 'Perfil'),
                $this->coluna('situacao', 'Situação', 'status'),
                $this->coluna('inscricoes', 'Inscrições', 'inteiro', 'text-center'),
                $this->coluna('created_at', 'Cadastro', 'datahora'),
                $this->coluna('ultimo_login', 'Último acesso', 'datahora'),
            ],
            $linhas,
            count($linhas)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function inscricoes(array $f): array
    {
        $where = ['DATE(i.criado_em) BETWEEN ? AND ?'];
        $params = [$f['dataInicio'], $f['dataFim']];

        if ($f['idEvento'] > 0) {
            $where[] = 'i.idEvento = ?';
            $params[] = $f['idEvento'];
        }

        if (in_array($f['status'], ['Pendente', 'Confirmada', 'Cancelada'], true)) {
            $where[] = 'i.status = ?';
            $params[] = $f['status'];
        }

        if (in_array($f['statusPagamento'], ['Pendente', 'Vencido', 'Pago', 'Cancelado', 'Estornado'], true)) {
            $where[] = 'COALESCE(p.status, i.pagamento) = ?';
            $params[] = $f['statusPagamento'];
        }

        if ($f['presenca'] === 'Sim') {
            $where[] = 'i.presenca = 1';
        } elseif ($f['presenca'] === 'Nao') {
            $where[] = 'i.presenca = 0';
        }

        if ($f['pesquisa'] !== '') {
            $busca = '%' . $f['pesquisa'] . '%';
            $where[] = '(i.nome LIKE ? OR i.email LIKE ? OR i.cpf LIKE ? OR e.titulo LIKE ?)';
            array_push($params, $busca, $busca, $busca, $busca);
        }

        $whereSql = implode(' AND ', $where);

        $resumo = $this->consultarUm(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN i.status = 'Confirmada' THEN 1 ELSE 0 END) AS confirmadas,
                SUM(CASE WHEN i.status = 'Pendente' THEN 1 ELSE 0 END) AS pendentes,
                SUM(CASE WHEN i.status = 'Cancelada' THEN 1 ELSE 0 END) AS canceladas,
                SUM(CASE WHEN i.presenca = 1 THEN 1 ELSE 0 END) AS presentes
             FROM inscricoes i
             INNER JOIN eventos e ON e.idEvento = i.idEvento
             LEFT JOIN pagamentos p ON p.idInscricao = i.idInscricao
             WHERE {$whereSql}",
            $params
        );

        $linhas = $this->consultar(
            "SELECT
                i.idInscricao,
                i.nome,
                i.email,
                i.cpf,
                e.titulo AS evento,
                i.status,
                COALESCE(p.status, i.pagamento) AS statusPagamento,
                i.camiseta,
                CASE WHEN i.presenca = 1 THEN 'Sim' ELSE 'Não' END AS presenca,
                i.valor,
                i.valor_pago,
                i.criado_em
             FROM inscricoes i
             INNER JOIN eventos e ON e.idEvento = i.idEvento
             LEFT JOIN pagamentos p ON p.idInscricao = i.idInscricao
             WHERE {$whereSql}
             ORDER BY i.criado_em DESC, i.idInscricao DESC
             LIMIT " . (int) $f['limite'],
            $params
        );

        $contagens = [
            ['rotulo' => 'Confirmadas', 'total' => (int) ($resumo['confirmadas'] ?? 0)],
            ['rotulo' => 'Pendentes', 'total' => (int) ($resumo['pendentes'] ?? 0)],
            ['rotulo' => 'Canceladas', 'total' => (int) ($resumo['canceladas'] ?? 0)],
        ];

        return $this->montarResposta(
            $f,
            'Relatório de inscrições',
            'Inscrições por evento, situação do cadastro e pagamento.',
            [
                $this->card('Inscrições', $resumo['total'] ?? 0, 'inteiro', 'fa-clipboard-list', 'primary'),
                $this->card('Confirmadas', $resumo['confirmadas'] ?? 0, 'inteiro', 'fa-circle-check', 'success'),
                $this->card('Pendentes', $resumo['pendentes'] ?? 0, 'inteiro', 'fa-clock', 'warning'),
                $this->card('Canceladas', $resumo['canceladas'] ?? 0, 'inteiro', 'fa-ban', 'danger'),
            ],
            $this->graficoDeLinhas($contagens, 'Inscrições por situação', 'inteiro'),
            [
                $this->coluna('idInscricao', '#', 'inteiro', 'text-center'),
                $this->coluna('nome', 'Participante'),
                $this->coluna('email', 'E-mail'),
                $this->coluna('evento', 'Evento'),
                $this->coluna('status', 'Inscrição', 'status'),
                $this->coluna('statusPagamento', 'Pagamento', 'status'),
                $this->coluna('camiseta', 'Camiseta'),
                $this->coluna('presenca', 'Presença', 'status'),
                $this->coluna('valor', 'Valor', 'moeda', 'text-end'),
                $this->coluna('valor_pago', 'Pago', 'moeda', 'text-end'),
                $this->coluna('criado_em', 'Inscrição em', 'datahora'),
            ],
            $linhas,
            (int) ($resumo['total'] ?? 0)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function montarResposta(
        array $filtros,
        string $titulo,
        string $descricao,
        array $cards,
        array $grafico,
        array $colunas,
        array $linhas,
        int $total,
        string $observacao = ''
    ): array {
        return [
            'titulo' => $titulo,
            'descricao' => $descricao,
            'periodo' => $filtros['dataInicioFormatada'] . ' a ' . $filtros['dataFimFormatada'],
            'filtros' => $filtros,
            'cards' => $cards,
            'grafico' => $grafico,
            'colunas' => $colunas,
            'linhas' => $linhas,
            'total' => $total,
            'exibidos' => count($linhas),
            'limitado' => $total > count($linhas),
            'observacao' => $observacao,
        ];
    }

    /** @return array<string, mixed> */
    private function card(string $rotulo, mixed $valor, string $formato, string $icone, string $cor): array
    {
        return compact('rotulo', 'valor', 'formato', 'icone', 'cor');
    }

    /** @return array<string, mixed> */
    private function coluna(string $chave, string $rotulo, string $formato = 'texto', string $classe = ''): array
    {
        return compact('chave', 'rotulo', 'formato', 'classe');
    }

    /**
     * @param array<int, array<string, mixed>> $linhas
     * @return array<string, mixed>
     */
    private function graficoDeLinhas(array $linhas, string $titulo, string $formato): array
    {
        $labels = [];
        $valores = [];
        foreach ($linhas as $linha) {
            $labels[] = (string) ($linha['rotulo'] ?? '');
            $valores[] = (float) ($linha['total'] ?? 0);
        }

        return compact('titulo', 'labels', 'valores', 'formato');
    }
}
