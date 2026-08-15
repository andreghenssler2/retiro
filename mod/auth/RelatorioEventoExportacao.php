<?php

declare(strict_types=1);

require_once __DIR__ . "/../database/db.php";

/**
 * Dados para os relatórios/exportações de um evento específico.
 */
final class RelatorioEventoExportacao
{
    private PDO $db;

    /** @var array<string, array<string, bool>> */
    private array $cacheColunas = [];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();

        $this->db->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $this->db->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );
    }

    public function evento(int $idEvento): array
    {
        if ($idEvento <= 0) {
            throw new InvalidArgumentException(
                "Selecione um evento."
            );
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM eventos
            WHERE idEvento = :idEvento
            LIMIT 1
        ");

        $stmt->execute([
            ":idEvento" => $idEvento
        ]);

        $evento = $stmt->fetch();

        if (!$evento) {
            throw new RuntimeException(
                "Evento não encontrado."
            );
        }

        return $evento;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function inscricoes(int $idEvento): array
    {
        $evento = $this->evento($idEvento);

        $visitanteSql = $this->possuiColuna(
            "inscricao_dados_adicionais",
            "visitante"
        )
            ? "ida.visitante"
            : "0";

        $selectPagamentoFim = $this->possuiColuna(
            "eventos",
            "pagamento_fim"
        )
            ? "e.pagamento_fim"
            : "NULL";

        $perguntaMedicacao = $this->campoEventoOuPadrao(
            "perguntar_restricao_medicacao",
            "1"
        );

        $perguntaDeficiencia = $this->campoEventoOuPadrao(
            "perguntar_deficiencia",
            "1"
        );

        $perguntaAcessibilidade = $this->campoEventoOuPadrao(
            "perguntar_acessibilidade",
            "1"
        );

        $perguntaAlimentar = $this->campoEventoOuPadrao(
            "perguntar_restricao_alimentar",
            "1"
        );

        $sql = "
            SELECT
                i.idInscricao,
                i.idEvento,
                i.idUsuario,
                i.nome,
                i.cpf,
                i.rg,
                i.email,
                i.telefone,
                i.sexo,
                i.data_nascimento,
                i.cidade AS cidade_inscricao,
                i.estado AS estado_inscricao,
                i.camiseta,
                i.observacoes,
                i.contato_emergencia,
                i.telefone_emergencia,
                i.status AS status_inscricao,
                i.pagamento AS status_pagamento_inscricao,
                i.presenca,
                i.presenca_status,
                i.certificado,
                i.valor AS valor_inscricao,
                i.valor_pago,
                i.forma_pagamento AS forma_pagamento_inscricao,
                i.criado_em AS inscricao_criada_em,

                e.titulo AS evento_titulo,
                e.tipo AS evento_tipo,
                e.data_inicio AS evento_data_inicio,
                e.hora_inicio AS evento_hora_inicio,
                e.data_fim AS evento_data_fim,
                e.hora_fim AS evento_hora_fim,
                e.local AS evento_local,
                e.cidade AS evento_cidade,
                e.estado AS evento_estado,
                e.pagamento_obrigatorio,
                {$selectPagamentoFim} AS pagamento_fim,
                {$perguntaMedicacao} AS perguntar_restricao_medicacao,
                {$perguntaDeficiencia} AS perguntar_deficiencia,
                {$perguntaAcessibilidade} AS perguntar_acessibilidade,
                {$perguntaAlimentar} AS perguntar_restricao_alimentar,

                COALESCE(
                    NULLIF(TRIM(u.nome), ''),
                    i.nome
                ) AS participante_nome_atual,
                COALESCE(
                    NULLIF(TRIM(u.email), ''),
                    i.email
                ) AS participante_email_atual,
                COALESCE(
                    NULLIF(TRIM(u.telefone), ''),
                    i.telefone
                ) AS participante_telefone_atual,
                u.nacionalidade,
                u.genero AS usuario_genero,
                u.pais AS usuario_pais,
                u.cep AS usuario_cep,

                ida.genero,
                ida.pais,
                ida.cep,
                ida.logradouro,
                ida.numero,
                ida.complemento,
                ida.bairro,
                ida.cidade,
                ida.estado,
                ida.idComunidade,
                {$visitanteSql} AS visitante,
                ida.restricao_medicacao,
                ida.medicacao_detalhes,
                ida.deficiencia,
                ida.deficiencia_detalhes,
                ida.precisa_acessibilidade,
                ida.acessibilidade_detalhes,
                ida.restricao_alimentar,
                ida.alimentar_detalhes,

                COALESCE(
                    mc_inscricao.nome_comunidade,
                    mc_usuario.nome_comunidade,
                    '-'
                ) AS comunidade,

                p.idPagamento,
                p.codigo AS pagamento_codigo,
                p.formaPagamento AS pagamento_forma,
                p.integracao AS pagamento_integracao,
                p.status AS pagamento_status,
                p.valor AS pagamento_valor,
                p.valorCobrancaAsaas,
                p.valorTaxaRepassada,
                p.dataVencimento,
                p.dataPagamento,
                p.asaasStatus,
                p.asaasPaymentId,
                p.asaasCustomerId

            FROM inscricoes i

            INNER JOIN eventos e
                ON e.idEvento = i.idEvento

            LEFT JOIN usuarios u
                ON u.id = i.idUsuario

            LEFT JOIN inscricao_dados_adicionais ida
                ON ida.idInscricao = i.idInscricao

            LEFT JOIN minha_comunidade mc_inscricao
                ON mc_inscricao.id = ida.idComunidade

            LEFT JOIN minha_comunidade mc_usuario
                ON mc_usuario.id = u.idComunidade

            LEFT JOIN pagamentos p
                ON p.idPagamento = (
                    SELECT MAX(p2.idPagamento)
                    FROM pagamentos p2
                    WHERE p2.idInscricao = i.idInscricao
                )

            WHERE i.idEvento = :idEvento

            ORDER BY
                i.nome ASC,
                i.idInscricao ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ":idEvento" => $idEvento
        ]);

        $registros = $stmt->fetchAll();

        foreach ($registros as &$registro) {
            $registro["prazo_pagamento_relatorio"] =
                $this->prazoPagamento(
                    $registro,
                    $evento
                );

            $registro["situacao_relatorio"] =
                $this->situacaoInscricao(
                    $registro,
                    $evento
                );
        }
        unset($registro);

        return $registros;
    }

    public function situacaoInscricao(
        array $inscricao,
        array $evento
    ): string {
        $statusInscricao = trim(
            (string) (
                $inscricao["status_inscricao"]
                ?? ""
            )
        );

        if ($statusInscricao === "Cancelada") {
            return "Cancelada";
        }

        $pagamentoObrigatorio =
            (int) (
                $evento["pagamento_obrigatorio"]
                ?? $inscricao["pagamento_obrigatorio"]
                ?? 1
            ) === 1;

        $valor = round(
            (float) (
                $inscricao["valor_inscricao"]
                ?? 0
            ),
            2
        );

        if (
            !$pagamentoObrigatorio
            || $valor <= 0
        ) {
            return "Confirmada";
        }

        $statusPagamento = trim(
            (string) (
                $inscricao["pagamento_status"]
                ?? $inscricao[
                    "status_pagamento_inscricao"
                ]
                ?? "Pendente"
            )
        );

        $prazo = $this->prazoPagamento(
            $inscricao,
            $evento
        );

        $dataPagamentoTexto = trim(
            (string) (
                $inscricao["dataPagamento"]
                ?? ""
            )
        );

        $dataPagamento = $this->dataHora(
            $dataPagamentoTexto
        );

        if ($statusPagamento === "Pago") {
            /*
             * Se a data de pagamento não estiver disponível,
             * o status Pago continua sendo considerado válido.
             */
            if (
                !$prazo
                || !$dataPagamento
                || $dataPagamento <= $prazo
            ) {
                return "Confirmada";
            }

            return "Inscrição não confirmada";
        }

        if (
            in_array(
                $statusPagamento,
                ["Cancelado", "Estornado"],
                true
            )
        ) {
            return "Inscrição não confirmada";
        }

        $agora = new DateTimeImmutable(
            "now",
            new DateTimeZone(
                "America/Sao_Paulo"
            )
        );

        if (
            $prazo
            && $agora > $prazo
        ) {
            return "Inscrição não confirmada";
        }

        return "Aguardando pagamento";
    }

    public function prazoPagamento(
        array $inscricao,
        array $evento
    ): ?DateTimeImmutable {
        $texto = trim(
            (string) (
                $inscricao["pagamento_fim"]
                ?? $evento["pagamento_fim"]
                ?? ""
            )
        );

        $prazo = $this->dataHora($texto);

        if ($prazo) {
            return $prazo;
        }

        $inicio = trim(
            (string) (
                $inscricao["evento_data_inicio"]
                ?? $evento["data_inicio"]
                ?? ""
            )
        );

        if ($inicio === "") {
            return null;
        }

        try {
            return (new DateTimeImmutable(
                $inicio,
                new DateTimeZone(
                    "America/Sao_Paulo"
                )
            ))
                ->modify("-1 day")
                ->setTime(23, 59, 59);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Retorna somente as perguntas habilitadas no evento.
     *
     * @return array<string, string>
     */
    public function perguntasSaude(array $evento): array
    {
        $perguntas = [];

        if (
            (int) (
                $evento[
                    "perguntar_restricao_medicacao"
                ]
                ?? 1
            ) === 1
        ) {
            $perguntas["medicacao"] =
                "Restrição a medicação";
        }

        if (
            (int) (
                $evento["perguntar_deficiencia"]
                ?? 1
            ) === 1
        ) {
            $perguntas["deficiencia"] =
                "Deficiência";
        }

        if (
            (int) (
                $evento[
                    "perguntar_acessibilidade"
                ]
                ?? 1
            ) === 1
        ) {
            $perguntas["acessibilidade"] =
                "Recurso de acessibilidade";
        }

        if (
            (int) (
                $evento[
                    "perguntar_restricao_alimentar"
                ]
                ?? 1
            ) === 1
        ) {
            $perguntas["alimentacao"] =
                "Restrição alimentar";
        }

        return $perguntas;
    }

    /**
     * Colunas úteis do XLSX de um evento.
     *
     * @return array<string, string>
     */
    public function colunasXlsx(): array
    {
        return [
            "idInscricao" => "ID inscrição",
            "nome" => "Nome",
            "cpf" => "CPF",
            "rg" => "RG",
            "email" => "E-mail",
            "telefone" => "Telefone",
            "data_nascimento" => "Nascimento",
            "genero" => "Gênero",
            "comunidade" => "Comunidade/Paróquia",
            "visitante" => "Visitante",
            "cep" => "CEP",
            "logradouro" => "Logradouro",
            "numero" => "Número",
            "complemento" => "Complemento",
            "bairro" => "Bairro",
            "cidade" => "Cidade",
            "estado" => "UF",
            "camiseta" => "Camiseta",
            "restricao_medicacao" => "Restrição medicação",
            "medicacao_detalhes" => "Detalhes medicação",
            "deficiencia" => "Deficiência",
            "deficiencia_detalhes" => "Detalhes deficiência",
            "precisa_acessibilidade" => "Precisa acessibilidade",
            "acessibilidade_detalhes" => "Detalhes acessibilidade",
            "restricao_alimentar" => "Restrição alimentar",
            "alimentar_detalhes" => "Detalhes alimentação",
            "observacoes" => "Observações",
            "status_inscricao" => "Status inscrição",
            "situacao_relatorio" => "Situação no relatório",
            "pagamento_status" => "Status pagamento",
            "pagamento_forma" => "Forma pagamento",
            "valor_inscricao" => "Valor inscrição",
            "pagamento_valor" => "Valor pagamento",
            "valor_pago" => "Valor pago",
            "dataVencimento" => "Vencimento cobrança",
            "prazo_pagamento_relatorio" => "Prazo do evento",
            "dataPagamento" => "Data pagamento",
            "pagamento_integracao" => "Integração",
            "asaasStatus" => "Status Asaas",
            "asaasPaymentId" => "ID cobrança Asaas",
            "presenca_status" => "Presença",
            "inscricao_criada_em" => "Inscrição realizada em",
        ];
    }

    public function formatar(
        string $campo,
        mixed $valor
    ): string {
        if ($valor instanceof DateTimeInterface) {
            return $valor->format(
                "d/m/Y H:i"
            );
        }

        if ($valor === null || $valor === "") {
            return "";
        }

        if (
            in_array(
                $campo,
                [
                    "restricao_medicacao",
                    "precisa_acessibilidade",
                    "restricao_alimentar",
                    "visitante"
                ],
                true
            )
        ) {
            return (int) $valor === 1
                ? "Sim"
                : "Não";
        }

        if (
            in_array(
                $campo,
                [
                    "valor_inscricao",
                    "pagamento_valor",
                    "valor_pago"
                ],
                true
            )
        ) {
            return "R$ "
                . number_format(
                    (float) $valor,
                    2,
                    ",",
                    "."
                );
        }

        if (
            in_array(
                $campo,
                [
                    "data_nascimento",
                    "dataVencimento"
                ],
                true
            )
        ) {
            $timestamp = strtotime(
                (string) $valor
            );

            return $timestamp
                ? date("d/m/Y", $timestamp)
                : (string) $valor;
        }

        if (
            in_array(
                $campo,
                [
                    "dataPagamento",
                    "inscricao_criada_em"
                ],
                true
            )
        ) {
            $timestamp = strtotime(
                (string) $valor
            );

            return $timestamp
                ? date(
                    "d/m/Y H:i",
                    $timestamp
                )
                : (string) $valor;
        }

        return (string) $valor;
    }

    private function dataHora(
        string $texto
    ): ?DateTimeImmutable {
        $texto = trim($texto);

        if ($texto === "") {
            return null;
        }

        $fuso = new DateTimeZone(
            "America/Sao_Paulo"
        );

        foreach (
            [
                "Y-m-d H:i:s",
                "Y-m-d H:i",
                "Y-m-d"
            ]
            as $formato
        ) {
            $data = DateTimeImmutable::createFromFormat(
                "!" . $formato,
                $texto,
                $fuso
            );

            if ($data instanceof DateTimeImmutable) {
                if ($formato === "Y-m-d") {
                    $data = $data->setTime(
                        23,
                        59,
                        59
                    );
                }

                return $data;
            }
        }

        try {
            return new DateTimeImmutable(
                $texto,
                $fuso
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function campoEventoOuPadrao(
        string $campo,
        string $padrao
    ): string {
        return $this->possuiColuna(
            "eventos",
            $campo
        )
            ? "e.`{$campo}`"
            : $padrao;
    }

    private function possuiColuna(
        string $tabela,
        string $coluna
    ): bool {
        if (
            !preg_match(
                '/^[A-Za-z0-9_]+$/',
                $tabela
            )
            || !preg_match(
                '/^[A-Za-z0-9_]+$/',
                $coluna
            )
        ) {
            return false;
        }

        if (
            isset(
                $this->cacheColunas[$tabela][$coluna]
            )
        ) {
            return $this->cacheColunas[
                $tabela
            ][$coluna];
        }

        $stmt = $this->db->query(
            "SHOW COLUMNS FROM `{$tabela}` LIKE "
            . $this->db->quote($coluna)
        );

        $existe = $stmt->fetch() !== false;

        $this->cacheColunas[$tabela][$coluna] =
            $existe;

        return $existe;
    }
}
