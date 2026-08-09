<?php

require_once __DIR__ . '/../database/db.php';

class Geral
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Busca uma comunidade pelo nome ou ID.
     */
    public function buscarComunidade(string $buscar): array|false
    {
        $sql = "
            SELECT
                id,
                nome_comunidade
            FROM minha_comunidade
            WHERE nome_comunidade = :nome
               OR id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':nome' => trim($buscar),
            ':id' => (int) $buscar
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Lista todas as comunidades.
     */
    public function listarComunidade(): array
    {
        $sql = "
            SELECT
                id,
                nome_comunidade
            FROM minha_comunidade
            ORDER BY nome_comunidade
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lista somente as comunidades ativas.
     */
    public function listarComunidadesAtivas(): array
    {
        $sql = "
            SELECT
                id,
                nome_comunidade
            FROM minha_comunidade
            WHERE ativo = 1
            ORDER BY nome_comunidade
        ";

        return $this->db
            ->query($sql)
            ->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca uma comunidade ativa pelo ID.
     */
    public function buscarComunidadeAtivaPorId(
        int $idComunidade
    ): array|false {

        if ($idComunidade <= 0) {
            return false;
        }

        $sql = "
            SELECT
                id,
                nome_comunidade
            FROM minha_comunidade
            WHERE id = :idComunidade
              AND ativo = 1
            LIMIT 1
        ";

        $stmt = $this->db->prepare(
            $sql
        );

        $stmt->execute([
            ":idComunidade" => $idComunidade
        ]);

        return $stmt->fetch(
            PDO::FETCH_ASSOC
        );
    }

    /**
     * Busca a comunidade vinculada ao usuário.
     */
    public function buscarComunidadePorUsuario(
        int $idUsuario
    ): array|false {

        if ($idUsuario <= 0) {
            return false;
        }

        $sql = "
            SELECT
                comunidade.id,
                comunidade.nome_comunidade
            FROM usuarios AS usuario
            INNER JOIN minha_comunidade AS comunidade
                ON comunidade.id = usuario.idComunidade
            WHERE usuario.id = :idUsuario
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':idUsuario' => $idUsuario
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Busca uma comunidade diretamente pelo ID.
     */
    public function buscarComunidadePorId(
        int $idComunidade
    ): array|false {

        if ($idComunidade <= 0) {
            return false;
        }

        $sql = "
            SELECT
                id,
                nome_comunidade
            FROM minha_comunidade
            WHERE id = :idComunidade
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':idComunidade' => $idComunidade
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}