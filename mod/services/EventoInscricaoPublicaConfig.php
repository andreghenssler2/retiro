<?php

declare(strict_types=1);

/**
 * Configurações do formulário público de inscrição por evento.
 *
 * Responsável por:
 * - termos e consentimentos;
 * - tamanhos de camiseta disponíveis.
 */
class EventoInscricaoPublicaConfig
{
    private PDO $db;

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

    public function listarTermos(int $idEvento): array
    {
        if ($idEvento <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT
                idTermo,
                idEvento,
                titulo,
                descricao,
                url,
                obrigatorio,
                ordem
            FROM evento_termos
            WHERE idEvento = :idEvento
            ORDER BY ordem ASC, idTermo ASC
        ");

        $stmt->execute([
            ":idEvento" => $idEvento
        ]);

        return $stmt->fetchAll();
    }

    public function listarCamisetas(int $idEvento): array
    {
        if ($idEvento <= 0) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT tamanho
            FROM evento_camiseta_opcoes
            WHERE idEvento = :idEvento
            ORDER BY ordem ASC, idOpcao ASC
        ");

        $stmt->execute([
            ":idEvento" => $idEvento
        ]);

        return array_values(
            array_map(
                static fn(array $linha): string =>
                    (string) $linha["tamanho"],
                $stmt->fetchAll()
            )
        );
    }

    public function salvarTermos(
        int $idEvento,
        array $termos
    ): void {
        if ($idEvento <= 0) {
            throw new InvalidArgumentException(
                "Evento inválido para salvar os termos."
            );
        }

        $iniciouTransacao =
            !$this->db->inTransaction();

        if ($iniciouTransacao) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare("
                DELETE FROM evento_termos
                WHERE idEvento = :idEvento
            ");

            $stmt->execute([
                ":idEvento" => $idEvento
            ]);

            $ordem = 1;

            foreach ($termos as $termo) {
                if (!is_array($termo)) {
                    continue;
                }

                $titulo = trim(
                    (string) ($termo["titulo"] ?? "")
                );

                if ($titulo === "") {
                    continue;
                }

                $descricao = trim(
                    (string) ($termo["descricao"] ?? "")
                );

                $url = trim(
                    (string) ($termo["url"] ?? "")
                );

                if (
                    $url !== ""
                    && !filter_var(
                        $url,
                        FILTER_VALIDATE_URL
                    )
                ) {
                    throw new InvalidArgumentException(
                        "Uma das URLs dos termos é inválida."
                    );
                }

                $stmt = $this->db->prepare("
                    INSERT INTO evento_termos (
                        idEvento,
                        titulo,
                        descricao,
                        url,
                        obrigatorio,
                        ordem
                    ) VALUES (
                        :idEvento,
                        :titulo,
                        :descricao,
                        :url,
                        :obrigatorio,
                        :ordem
                    )
                ");

                $stmt->execute([
                    ":idEvento" => $idEvento,
                    ":titulo" => $titulo,
                    ":descricao" =>
                        $descricao !== ""
                            ? $descricao
                            : null,
                    ":url" =>
                        $url !== ""
                            ? $url
                            : null,
                    ":obrigatorio" =>
                        !empty($termo["obrigatorio"])
                            ? 1
                            : 0,
                    ":ordem" => $ordem++
                ]);
            }

            if ($iniciouTransacao) {
                $this->db->commit();
            }
        } catch (Throwable $erro) {
            if (
                $iniciouTransacao
                && $this->db->inTransaction()
            ) {
                $this->db->rollBack();
            }

            throw $erro;
        }
    }

    public function salvarCamisetas(
        int $idEvento,
        array $tamanhos
    ): void {
        if ($idEvento <= 0) {
            throw new InvalidArgumentException(
                "Evento inválido para salvar camisetas."
            );
        }

        $permitidos = [
            "P",
            "M",
            "G",
            "GG",
            "X1",
            "X2",
            "X3",
            "X4"
        ];

        $tamanhos = array_values(
            array_unique(
                array_filter(
                    array_map(
                        static fn(mixed $valor): string =>
                            strtoupper(
                                trim((string) $valor)
                            ),
                        $tamanhos
                    ),
                    static fn(string $valor): bool =>
                        in_array(
                            $valor,
                            $permitidos,
                            true
                        )
                )
            )
        );

        $iniciouTransacao =
            !$this->db->inTransaction();

        if ($iniciouTransacao) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare("
                DELETE FROM evento_camiseta_opcoes
                WHERE idEvento = :idEvento
            ");

            $stmt->execute([
                ":idEvento" => $idEvento
            ]);

            foreach (
                $tamanhos
                as $indice => $tamanho
            ) {
                $stmt = $this->db->prepare("
                    INSERT INTO
                        evento_camiseta_opcoes (
                            idEvento,
                            tamanho,
                            ordem
                        )
                    VALUES (
                        :idEvento,
                        :tamanho,
                        :ordem
                    )
                ");

                $stmt->execute([
                    ":idEvento" => $idEvento,
                    ":tamanho" => $tamanho,
                    ":ordem" => $indice + 1
                ]);
            }

            if ($iniciouTransacao) {
                $this->db->commit();
            }
        } catch (Throwable $erro) {
            if (
                $iniciouTransacao
                && $this->db->inTransaction()
            ) {
                $this->db->rollBack();
            }

            throw $erro;
        }
    }

    /**
     * Define o valor especial para visitante.
     *
     * NULL = não disponibiliza a opção "Sou visitante".
     * 0.00 = visitante gratuito.
     */
    public function salvarValorVisitante(
        int $idEvento,
        mixed $valor
    ): void {
        if ($idEvento <= 0) {
            throw new InvalidArgumentException(
                "Evento inválido para salvar "
                . "o valor de visitante."
            );
        }

        $valorTexto = trim(
            (string) ($valor ?? "")
        );

        $valorFinal = null;

        if ($valorTexto !== "") {
            $valorTexto = str_replace(
                ["R$", " "],
                "",
                $valorTexto
            );

            if (
                str_contains($valorTexto, ",")
                && str_contains($valorTexto, ".")
            ) {
                $valorTexto = str_replace(
                    ".",
                    "",
                    $valorTexto
                );
            }

            $valorTexto = str_replace(
                ",",
                ".",
                $valorTexto
            );

            if (!is_numeric($valorTexto)) {
                throw new InvalidArgumentException(
                    "Informe um valor válido "
                    . "para visitante."
                );
            }

            $valorFinal = round(
                (float) $valorTexto,
                2
            );

            if ($valorFinal < 0) {
                throw new InvalidArgumentException(
                    "O valor para visitante "
                    . "não pode ser negativo."
                );
            }
        }

        $stmt = $this->db->prepare("
            UPDATE eventos
            SET valor_visitante =
                :valor_visitante
            WHERE idEvento = :idEvento
        ");

        if ($valorFinal === null) {
            $stmt->bindValue(
                ":valor_visitante",
                null,
                PDO::PARAM_NULL
            );
        } else {
            $stmt->bindValue(
                ":valor_visitante",
                number_format(
                    $valorFinal,
                    2,
                    ".",
                    ""
                ),
                PDO::PARAM_STR
            );
        }

        $stmt->bindValue(
            ":idEvento",
            $idEvento,
            PDO::PARAM_INT
        );

        $stmt->execute();
    }



    /**
     * Define quais perguntas de Saúde e Acessibilidade
     * devem aparecer na inscrição deste evento.
     */
    public function salvarPerguntasSaude(
        int $idEvento,
        array $perguntas
    ): void {
        if ($idEvento <= 0) {
            throw new InvalidArgumentException(
                "Evento inválido para salvar "
                . "as perguntas de saúde."
            );
        }

        $restricaoMedicacao =
            !empty(
                $perguntas[
                    "restricao_medicacao"
                ]
            )
                ? 1
                : 0;

        $deficiencia =
            !empty(
                $perguntas[
                    "deficiencia"
                ]
            )
                ? 1
                : 0;

        $acessibilidade =
            !empty(
                $perguntas[
                    "acessibilidade"
                ]
            )
                ? 1
                : 0;

        $restricaoAlimentar =
            !empty(
                $perguntas[
                    "restricao_alimentar"
                ]
            )
                ? 1
                : 0;

        $stmt =
            $this->db->prepare("
                UPDATE eventos
                SET
                    perguntar_restricao_medicacao =
                        :restricao_medicacao,
                    perguntar_deficiencia =
                        :deficiencia,
                    perguntar_acessibilidade =
                        :acessibilidade,
                    perguntar_restricao_alimentar =
                        :restricao_alimentar
                WHERE idEvento = :idEvento
            ");

        $stmt->execute([
            ":restricao_medicacao" =>
                $restricaoMedicacao,

            ":deficiencia" =>
                $deficiencia,

            ":acessibilidade" =>
                $acessibilidade,

            ":restricao_alimentar" =>
                $restricaoAlimentar,

            ":idEvento" =>
                $idEvento
        ]);
    }


}
