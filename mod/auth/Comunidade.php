<?php

declare(strict_types=1);

class Comunidade
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db =
            $db
            ?? Database::connect();

        $this->db->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $this->db->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );
    }

    public function listar(
        string $pesquisa = ""
    ): array {
        $pesquisa =
            trim($pesquisa);

        $where = "";
        $params = [];

        if ($pesquisa !== "") {
            $where = "
                WHERE
                    c.nome_comunidade
                        LIKE :pesquisa
                    OR c.descricao
                        LIKE :pesquisa
            ";

            $params[":pesquisa"] =
                "%" . $pesquisa . "%";
        }

        $stmt =
            $this->db->prepare("
                SELECT
                    c.id,
                    c.nome_comunidade,
                    c.descricao,
                    c.imagem,
                    c.ativo,
                    c.criado_em,
                    COUNT(u.id)
                        AS total_usuarios
                FROM minha_comunidade c
                LEFT JOIN usuarios u
                    ON u.idComunidade = c.id
                {$where}
                GROUP BY
                    c.id,
                    c.nome_comunidade,
                    c.descricao,
                    c.imagem,
                    c.ativo,
                    c.criado_em
                ORDER BY
                    c.ativo DESC,
                    c.nome_comunidade ASC
            ");

        $stmt->execute(
            $params
        );

        return $stmt->fetchAll();
    }

    public function buscar(
        int $id
    ): array|false {
        if ($id <= 0) {
            return false;
        }

        $stmt =
            $this->db->prepare("
                SELECT
                    id,
                    nome_comunidade,
                    descricao,
                    imagem,
                    ativo,
                    criado_em
                FROM minha_comunidade
                WHERE id = :id
                LIMIT 1
            ");

        $stmt->execute([
            ":id" => $id
        ]);

        return $stmt->fetch();
    }

    public function salvar(
        array $dados
    ): int {
        $id =
            max(
                0,
                (int) (
                    $dados["id"]
                    ?? 0
                )
            );

        $nome =
            trim(
                (string) (
                    $dados[
                        "nome_comunidade"
                    ]
                    ?? ""
                )
            );

        $descricao =
            trim(
                (string) (
                    $dados["descricao"]
                    ?? ""
                )
            );

        $imagem =
            trim(
                (string) (
                    $dados["imagem"]
                    ?? ""
                )
            );

        $ativo =
            !empty(
                $dados["ativo"]
            )
                ? 1
                : 0;

        if ($nome === "") {
            throw new InvalidArgumentException(
                "Informe o nome da comunidade."
            );
        }

        $tamanhoNome =
            function_exists(
                "mb_strlen"
            )
                ? mb_strlen(
                    $nome,
                    "UTF-8"
                )
                : strlen($nome);

        if ($tamanhoNome > 150) {
            throw new InvalidArgumentException(
                "O nome da comunidade deve possuir "
                . "no máximo 150 caracteres."
            );
        }

        if (
            $this->nomeExiste(
                $nome,
                $id
            )
        ) {
            throw new InvalidArgumentException(
                "Já existe uma comunidade "
                . "com este nome."
            );
        }

        if ($id > 0) {
            $stmt =
                $this->db->prepare("
                    UPDATE minha_comunidade
                    SET
                        nome_comunidade =
                            :nome,
                        descricao =
                            :descricao,
                        imagem =
                            :imagem,
                        ativo =
                            :ativo
                    WHERE id = :id
                ");

            $stmt->execute([
                ":nome" => $nome,
                ":descricao" =>
                    $descricao !== ""
                        ? $descricao
                        : null,
                ":imagem" =>
                    $imagem !== ""
                        ? $imagem
                        : null,
                ":ativo" => $ativo,
                ":id" => $id
            ]);

            return $id;
        }

        $stmt =
            $this->db->prepare("
                INSERT INTO
                    minha_comunidade (
                        nome_comunidade,
                        descricao,
                        imagem,
                        ativo,
                        criado_em
                    )
                VALUES (
                    :nome,
                    :descricao,
                    :imagem,
                    :ativo,
                    NOW()
                )
            ");

        $stmt->execute([
            ":nome" => $nome,
            ":descricao" =>
                $descricao !== ""
                    ? $descricao
                    : null,
            ":imagem" =>
                $imagem !== ""
                    ? $imagem
                    : null,
            ":ativo" => $ativo
        ]);

        return (int)
            $this->db
                ->lastInsertId();
    }

    public function alterarStatus(
        int $id,
        bool $ativo
    ): bool {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                "Comunidade inválida."
            );
        }

        $stmt =
            $this->db->prepare("
                UPDATE minha_comunidade
                SET ativo = :ativo
                WHERE id = :id
            ");

        return $stmt->execute([
            ":ativo" =>
                $ativo ? 1 : 0,
            ":id" => $id
        ]);
    }

    public function excluir(
        int $id
    ): bool {
        if ($id <= 0) {
            throw new InvalidArgumentException(
                "Comunidade inválida."
            );
        }

        $registro =
            $this->buscar($id);

        if (!$registro) {
            throw new RuntimeException(
                "Comunidade não encontrada."
            );
        }

        $totalUsuarios =
            $this->totalUsuarios(
                $id
            );

        if ($totalUsuarios > 0) {
            throw new RuntimeException(
                "Esta comunidade não pode "
                . "ser excluída porque possui "
                . $totalUsuarios
                . " usuário(s) vinculado(s). "
                . "Você pode desativá-la."
            );
        }

        $stmt =
            $this->db->prepare("
                DELETE FROM
                    minha_comunidade
                WHERE id = :id
            ");

        return $stmt->execute([
            ":id" => $id
        ]);
    }

    public function totalUsuarios(
        int $id
    ): int {
        if ($id <= 0) {
            return 0;
        }

        $stmt =
            $this->db->prepare("
                SELECT COUNT(*)
                FROM usuarios
                WHERE idComunidade = :id
            ");

        $stmt->execute([
            ":id" => $id
        ]);

        return (int)
            $stmt->fetchColumn();
    }

    private function nomeExiste(
        string $nome,
        int $ignorarId = 0
    ): bool {
        $sql = "
            SELECT 1
            FROM minha_comunidade
            WHERE
                LOWER(
                    TRIM(
                        nome_comunidade
                    )
                )
                =
                LOWER(
                    TRIM(:nome)
                )
        ";

        $params = [
            ":nome" => $nome
        ];

        if ($ignorarId > 0) {
            $sql .= "
                AND id <> :id
            ";

            $params[":id"] =
                $ignorarId;
        }

        $sql .= "
            LIMIT 1
        ";

        $stmt =
            $this->db->prepare(
                $sql
            );

        $stmt->execute(
            $params
        );

        return $stmt->fetchColumn()
            !== false;
    }
}
