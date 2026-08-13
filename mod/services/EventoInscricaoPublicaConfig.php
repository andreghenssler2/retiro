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
}
