<?php

declare(strict_types=1);

final class MigrationRunner
{
    private PDO $db;
    private string $diretorio;

    public function __construct(
        PDO $db,
        string $diretorio
    ) {
        $this->db = $db;
        $this->diretorio = rtrim(
            $diretorio,
            DIRECTORY_SEPARATOR
        );

        $this->db->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $this->db->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );
    }

    public function preparar(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS schema_migrations (
                idMigration VARCHAR(190) NOT NULL,
                descricao VARCHAR(255) NOT NULL,
                checksum CHAR(64) NOT NULL,
                executadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                tempoMs INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (idMigration)
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }

    /**
     * @return array<int,array{
     *   id:string,
     *   arquivo:string,
     *   descricao:string,
     *   checksum:string,
     *   aplicada:bool,
     *   checksumBanco:?string,
     *   executadoEm:?string
     * }>
     */
    public function status(): array
    {
        $this->preparar();

        $aplicadas = $this->aplicadas();
        $resultado = [];

        foreach ($this->arquivos() as $arquivo) {
            $migration =
                $this->carregar(
                    $arquivo
                );

            $id =
                $this->idDoArquivo(
                    $arquivo
                );

            $checksum =
                hash_file(
                    "sha256",
                    $arquivo
                );

            if ($checksum === false) {
                throw new RuntimeException(
                    "Não foi possível calcular checksum: "
                    . $arquivo
                );
            }

            $registro =
                $aplicadas[$id]
                ?? null;

            $resultado[] = [
                "id" => $id,
                "arquivo" => basename($arquivo),
                "descricao" =>
                    (string) (
                        $migration["descricao"]
                        ?? $id
                    ),
                "checksum" => $checksum,
                "aplicada" =>
                    $registro !== null,
                "checksumBanco" =>
                    $registro["checksum"]
                    ?? null,
                "executadoEm" =>
                    $registro["executadoEm"]
                    ?? null
            ];
        }

        return $resultado;
    }

    public function migrar(): int
    {
        $this->preparar();

        $aplicadas =
            $this->aplicadas();

        $executadas = 0;

        foreach ($this->arquivos() as $arquivo) {
            $id =
                $this->idDoArquivo(
                    $arquivo
                );

            $checksum =
                hash_file(
                    "sha256",
                    $arquivo
                );

            if ($checksum === false) {
                throw new RuntimeException(
                    "Não foi possível calcular checksum: "
                    . $arquivo
                );
            }

            if (isset($aplicadas[$id])) {
                $checksumBanco =
                    (string) (
                        $aplicadas[$id][
                            "checksum"
                        ]
                        ?? ""
                    );

                if (
                    $checksumBanco !== ""
                    && !hash_equals(
                        $checksumBanco,
                        $checksum
                    )
                ) {
                    throw new RuntimeException(
                        "Migration já aplicada foi alterada: "
                        . $id
                        . ". Crie uma nova migration "
                        . "em vez de editar uma antiga."
                    );
                }

                continue;
            }

            $migration =
                $this->carregar(
                    $arquivo
                );

            $up =
                $migration["up"]
                ?? null;

            if (!is_callable($up)) {
                throw new RuntimeException(
                    "Migration sem função up(): "
                    . basename($arquivo)
                );
            }

            $descricao =
                trim(
                    (string) (
                        $migration["descricao"]
                        ?? $id
                    )
                );

            if ($descricao === "") {
                $descricao = $id;
            }

            echo
                "[EXECUTANDO] "
                . $id
                . " - "
                . $descricao
                . PHP_EOL;

            $inicio =
                microtime(true);

            /*
             * MySQL/MariaDB pode fazer COMMIT implícito
             * em comandos DDL. Ainda assim iniciamos
             * transação para migrations compostas por
             * DML e só registramos a migration depois
             * que todo o callback termina com sucesso.
             */
            $iniciouTransacao =
                !$this->db->inTransaction();

            if ($iniciouTransacao) {
                $this->db->beginTransaction();
            }

            try {
                $up($this->db);

                $tempoMs =
                    max(
                        0,
                        (int) round(
                            (
                                microtime(true)
                                - $inicio
                            )
                            * 1000
                        )
                    );

                $stmt =
                    $this->db->prepare("
                        INSERT INTO schema_migrations (
                            idMigration,
                            descricao,
                            checksum,
                            executadoEm,
                            tempoMs
                        ) VALUES (
                            :idMigration,
                            :descricao,
                            :checksum,
                            NOW(),
                            :tempoMs
                        )
                    ");

                $stmt->execute([
                    ":idMigration" => $id,
                    ":descricao" => $descricao,
                    ":checksum" => $checksum,
                    ":tempoMs" => $tempoMs
                ]);

                if (
                    $iniciouTransacao
                    && $this->db->inTransaction()
                ) {
                    $this->db->commit();
                }

                echo
                    "[OK] "
                    . $id
                    . " ("
                    . $tempoMs
                    . " ms)"
                    . PHP_EOL;

                $executadas++;
            } catch (Throwable $erro) {
                if (
                    $iniciouTransacao
                    && $this->db->inTransaction()
                ) {
                    $this->db->rollBack();
                }

                throw new RuntimeException(
                    "Falha na migration "
                    . $id
                    . ": "
                    . $erro->getMessage(),
                    0,
                    $erro
                );
            }
        }

        return $executadas;
    }

    /**
     * @return array<string,array{
     *   checksum:string,
     *   executadoEm:string
     * }>
     */
    private function aplicadas(): array
    {
        $stmt =
            $this->db->query("
                SELECT
                    idMigration,
                    checksum,
                    executadoEm
                FROM schema_migrations
                ORDER BY idMigration
            ");

        $resultado = [];

        foreach ($stmt->fetchAll() as $linha) {
            $id =
                (string) (
                    $linha["idMigration"]
                    ?? ""
                );

            if ($id === "") {
                continue;
            }

            $resultado[$id] = [
                "checksum" =>
                    (string) (
                        $linha["checksum"]
                        ?? ""
                    ),
                "executadoEm" =>
                    (string) (
                        $linha["executadoEm"]
                        ?? ""
                    )
            ];
        }

        return $resultado;
    }

    /**
     * @return array<int,string>
     */
    private function arquivos(): array
    {
        if (!is_dir($this->diretorio)) {
            return [];
        }

        $arquivos =
            glob(
                $this->diretorio
                . DIRECTORY_SEPARATOR
                . "[0-9]*_*.php"
            );

        if ($arquivos === false) {
            return [];
        }

        sort(
            $arquivos,
            SORT_STRING
        );

        return array_values(
            array_filter(
                $arquivos,
                "is_file"
            )
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function carregar(
        string $arquivo
    ): array {
        $migration =
            require $arquivo;

        if (!is_array($migration)) {
            throw new RuntimeException(
                "Migration deve retornar array: "
                . basename($arquivo)
            );
        }

        return $migration;
    }

    private function idDoArquivo(
        string $arquivo
    ): string {
        $nome =
            pathinfo(
                $arquivo,
                PATHINFO_FILENAME
            );

        if (
            !preg_match(
                '/^[0-9]{14}_[a-z0-9_]+$/',
                $nome
            )
        ) {
            throw new RuntimeException(
                "Nome de migration inválido: "
                . basename($arquivo)
                . ". Use: AAAAMMDDHHMMSS_descricao.php"
            );
        }

        return $nome;
    }
}
