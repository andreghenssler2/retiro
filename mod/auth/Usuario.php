<?php
require_once __DIR__ . '/../database/db.php';
class Usuario
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Busca usuário pelo e-mail
     */
    /**
     * Busca usuário pelo ID
     */
    public function buscar(int $id): array|false
    {
        $sql = "SELECT *
            FROM usuarios
            WHERE id = ?
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function buscarPorEmail(string $email): array|false
    {
        $email = self::normalizarEmail($email);

        $sql = "
            SELECT *
            FROM usuarios
            WHERE LOWER(TRIM(email)) = :email
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ":email" => $email
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se o e-mail existe
     */
    public function emailExiste(string $email): bool
    {
        return $this->buscarPorEmail($email) !== false;
    }


    /**
     * Normaliza o e-mail antes de consultar ou salvar.
     */
    public static function normalizarEmail(
        string $email
    ): string {

        $email = trim($email);

        return function_exists(
            "mb_strtolower"
        )
            ? mb_strtolower(
                $email,
                "UTF-8"
            )
            : strtolower($email);
    }

    /**
     * Mantém somente os 11 números do CPF.
     */
    public static function normalizarCpf(
        string $cpf
    ): string {

        return substr(
            preg_replace(
                "/\D+/",
                "",
                $cpf
            ),
            0,
            11
        );
    }

    /**
     * Valida os dígitos verificadores do CPF.
     */
    public static function cpfValido(
        string $cpf
    ): bool {

        $cpf = self::normalizarCpf(
            $cpf
        );

        if (
            strlen($cpf) !== 11
            || preg_match(
                '/^(\d)\1{10}$/',
                $cpf
            )
        ) {
            return false;
        }

        for (
            $tamanho = 9;
            $tamanho < 11;
            $tamanho++
        ) {

            $soma = 0;

            for (
                $indice = 0;
                $indice < $tamanho;
                $indice++
            ) {

                $soma +=
                    (int) $cpf[$indice]
                    * (
                        $tamanho
                        + 1
                        - $indice
                    );
            }

            $digito = (
                10
                * $soma
            ) % 11;

            if ($digito === 10) {
                $digito = 0;
            }

            if (
                $digito
                !== (int) $cpf[$tamanho]
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Busca um usuário pelo CPF normalizado.
     */
    public function buscarPorCpf(
        string $cpf
    ): array|false {

        $cpf = self::normalizarCpf($cpf);

        $stmt = $this->db->prepare(
            "
                SELECT *
                FROM usuarios
                WHERE REPLACE(
                    REPLACE(
                        REPLACE(
                            REPLACE(TRIM(cpf), '.', ''),
                            '-',
                            ''
                        ),
                        ' ',
                        ''
                    ),
                    '/',
                    ''
                ) = :cpf
                LIMIT 1
            "
        );

        $stmt->execute([
            ":cpf" => $cpf
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica se o CPF já está cadastrado.
     */
    public function cpfExiste(
        string $cpf
    ): bool {

        return $this->buscarPorCpf(
            $cpf
        ) !== false;
    }

    /**
     * Salva token para recuperação
     */
    /**
     * Tokens de recuperação são armazenados por hash.
     */
    public static function hashTokenRecuperacao(
        string $token
    ): string {
        return hash(
            'sha256',
            $token
        );
    }
    public function salvarTokenRecuperacao(
        string $email,
        string $token,
        string $expira
    ): bool {

        $token = self::hashTokenRecuperacao(
            $token
        );

        $sql = "UPDATE usuarios
                   SET reset_token = ?,
                       reset_expira = ?
                 WHERE email = ?";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $token,
            $expira,
            $email
        ]);
    }

    /**
     * Busca usuário pelo token
     */
    public function buscarPorToken(string $token): array|false
    {

        $token = self::hashTokenRecuperacao(
            $token
        );
        $sql = "SELECT *
            FROM usuarios
            WHERE reset_token = ?
            LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$token]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Atualiza senha
     */
    public function alterarSenha(int $id, string $senha): bool
    {
        $senha = password_hash($senha, PASSWORD_DEFAULT);

        $sql = "UPDATE usuarios
            SET senha=?,
                reset_token=NULL,
                reset_expira=NULL
            WHERE id=?";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            $senha,
            $id
        ]);
    }

    /**
     * Cria usuário.
     */
    public function cadastrar(
        array $dados
    ): int|false {

        $sql = "
            INSERT INTO usuarios
            (
                nome,
                email,
                telefone,
                cpf,
                senha,
                tipo,
                ativo,
                idComunidade,
                logradouro,
                numero,
                bairro,
                cidade,
                estado,
                ultimo_login
            )
            VALUES
            (
                :nome,
                :email,
                :telefone,
                :cpf,
                :senha,
                3,
                1,
                :idComunidade,
                :logradouro,
                :numero,
                :bairro,
                :cidade,
                :estado,
                NULL
            )
        ";

        $stmt = $this->db->prepare(
            $sql
        );

        $ok = $stmt->execute([
            ":nome" => trim(
                (string) $dados["nome"]
            ),
            ":email" => self::normalizarEmail(
                (string) $dados["email"]
            ),
            ":telefone" => trim(
                (string) $dados["telefone"]
            ),
            ":cpf" => self::normalizarCpf(
                (string) $dados["cpf"]
            ),
            ":senha" => password_hash(
                (string) $dados["senha"],
                PASSWORD_DEFAULT
            ),
            ":idComunidade" => (int) (
                $dados["comunidade"]
                ?? $dados["idComunidade"]
                ?? 0
            ),
            ":logradouro" => trim(
                (string) $dados["logradouro"]
            ),
            ":numero" => trim(
                (string) $dados["numero"]
            ),
            ":bairro" => trim(
                (string) $dados["bairro"]
            ),
            ":cidade" => trim(
                (string) $dados["cidade"]
            ),
            ":estado" => strtoupper(
                trim(
                    (string) (
                        $dados["estado"]
                        ?? "RS"
                    )
                )
            )
        ]);

        if (!$ok) {
            return false;
        }

        return (int) $this->db
            ->lastInsertId();
    }

    /**
     * Verifica se existe outro usuário com o mesmo e-mail.
     */
    public function emailExisteOutro(
        string $email,
        int $id
    ): bool {

        $email = self::normalizarEmail($email);

        $sql = "
            SELECT id
            FROM usuarios
            WHERE LOWER(TRIM(email)) = :email
              AND id <> :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ":email" => $email,
            ":id" => $id
        ]);

        return $stmt->fetch() !== false;
    }

    /**
     * Verifica se existe outro usuário com o mesmo CPF.
     */
    public function cpfExisteOutro(
        string $cpf,
        int $id
    ): bool {

        $cpf = self::normalizarCpf($cpf);

        $sql = "
            SELECT id
            FROM usuarios
            WHERE REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(TRIM(cpf), '.', ''),
                        '-',
                        ''
                    ),
                    ' ',
                    ''
                ),
                '/',
                ''
            ) = :cpf
              AND id <> :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ":cpf" => $cpf,
            ":id" => $id
        ]);

        return $stmt->fetch() !== false;
    }

    /**
     * Salva um usuário criado pelo painel administrativo.
     */
    public function salvar(array $dados): bool
    {
        $sql = "
            INSERT INTO usuarios
            (
                nome,
                email,
                telefone,
                cpf,
                senha,
                foto,
                tipo,
                ativo,
                idComunidade,
                logradouro,
                numero,
                bairro,
                cidade,
                estado,
                ultimo_login
            )
            VALUES
            (
                :nome,
                :email,
                :telefone,
                :cpf,
                :senha,
                :foto,
                :tipo,
                :ativo,
                :idComunidade,
                :logradouro,
                :numero,
                :bairro,
                :cidade,
                :estado,
                NULL
            )
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ":nome" => trim((string) $dados["nome"]),
            ":email" => self::normalizarEmail(
                (string) $dados["email"]
            ),
            ":telefone" => trim(
                (string) $dados["telefone"]
            ),
            ":cpf" => self::normalizarCpf(
                (string) $dados["cpf"]
            ),
            ":senha" => password_hash(
                (string) $dados["senha"],
                PASSWORD_DEFAULT
            ),
            ":foto" => trim(
                (string) ($dados["foto"] ?? "")
            ),
            ":tipo" => (int) $dados["tipo"],
            ":ativo" => (int) $dados["ativo"],
            ":idComunidade" => (int) (
                $dados["comunidade"]
                ?? $dados["idComunidade"]
                ?? 0
            ),
            ":logradouro" => trim(
                (string) $dados["logradouro"]
            ),
            ":numero" => trim(
                (string) $dados["numero"]
            ),
            ":bairro" => trim(
                (string) $dados["bairro"]
            ),
            ":cidade" => trim(
                (string) $dados["cidade"]
            ),
            ":estado" => strtoupper(
                trim((string) ($dados["estado"] ?? "RS"))
            )
        ]);
    }

    /**
     * Atualiza um usuário pelo painel administrativo.
     */
    public function editar(array $dados): bool
    {
        $id = (int) ($dados["id"] ?? 0);

        if ($id <= 0) {
            throw new InvalidArgumentException(
                "ID do usuário inválido."
            );
        }

        $nome = trim((string) ($dados["nome"] ?? ""));
        $email = self::normalizarEmail(
            (string) ($dados["email"] ?? "")
        );
        $telefone = trim(
            (string) ($dados["telefone"] ?? "")
        );
        $cpf = self::normalizarCpf(
            (string) ($dados["cpf"] ?? "")
        );
        $tipo = (int) ($dados["tipo"] ?? 0);
        $ativo = (int) ($dados["ativo"] ?? 0);
        $idComunidade = (int) (
            $dados["comunidade"]
            ?? $dados["idComunidade"]
            ?? 0
        );

        $logradouro = trim(
            (string) ($dados["logradouro"] ?? "")
        );
        $numero = trim(
            (string) ($dados["numero"] ?? "")
        );
        $bairro = trim(
            (string) ($dados["bairro"] ?? "")
        );
        $cidade = trim(
            (string) ($dados["cidade"] ?? "")
        );
        $estado = strtoupper(
            trim((string) ($dados["estado"] ?? "RS"))
        );

        if ($nome === "") {
            throw new InvalidArgumentException(
                "O nome é obrigatório."
            );
        }

        if (
            $email === ""
            || !filter_var($email, FILTER_VALIDATE_EMAIL)
        ) {
            throw new InvalidArgumentException(
                "Informe um e-mail válido."
            );
        }

        if (!self::cpfValido($cpf)) {
            throw new InvalidArgumentException(
                "Informe um CPF válido."
            );
        }

        if (!in_array($tipo, [1, 2, 3], true)) {
            throw new InvalidArgumentException(
                "O tipo de usuário é inválido."
            );
        }

        if (!in_array($ativo, [0, 1], true)) {
            throw new InvalidArgumentException(
                "O status do usuário é inválido."
            );
        }

        if ($idComunidade <= 0) {
            throw new InvalidArgumentException(
                "A comunidade é obrigatória."
            );
        }

        if (
            $logradouro === ""
            || $numero === ""
            || $bairro === ""
            || $cidade === ""
            || strlen($estado) !== 2
        ) {
            throw new InvalidArgumentException(
                "Preencha o endereço completo."
            );
        }

        $campos = [
            "nome = :nome",
            "email = :email",
            "telefone = :telefone",
            "cpf = :cpf",
            "tipo = :tipo",
            "ativo = :ativo",
            "idComunidade = :idComunidade",
            "logradouro = :logradouro",
            "numero = :numero",
            "bairro = :bairro",
            "cidade = :cidade",
            "estado = :estado"
        ];

        $params = [
            ":nome" => $nome,
            ":email" => $email,
            ":telefone" => $telefone,
            ":cpf" => $cpf,
            ":tipo" => $tipo,
            ":ativo" => $ativo,
            ":idComunidade" => $idComunidade,
            ":logradouro" => $logradouro,
            ":numero" => $numero,
            ":bairro" => $bairro,
            ":cidade" => $cidade,
            ":estado" => $estado,
            ":id" => $id
        ];

        $foto = trim(
            (string) ($dados["foto"] ?? "")
        );

        if ($foto !== "") {
            $campos[] = "foto = :foto";
            $params[":foto"] = $foto;
        }

        $senha = (string) ($dados["senha"] ?? "");

        if ($senha !== "") {
            $campos[] = "senha = :senha";
            $params[":senha"] = password_hash(
                $senha,
                PASSWORD_DEFAULT
            );
        }

        $sql = "
            UPDATE usuarios
            SET " . implode(",
", $campos) . "
            WHERE id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);
    }

    /**
     * Excluir usuário
     */
    public function excluir(int $id): bool
    {
        $stmt = $this->db->prepare("
        DELETE FROM usuarios
        WHERE id=?
    ");

        return $stmt->execute([$id]);
    }
    /**
     * Lista usuários
     */
    public function listar(): array
    {
        $stmt = $this->db->query("
        SELECT *
        FROM usuarios
        ORDER BY nome
    ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    public function listarPaginado(
        string $pesquisa = '',
        string $perfil = '',
        string $status = '',
        string $ordem = 'nome',
        string $direcao = 'ASC',
        int $pagina = 1,
        int $limite = 10
    ): array {

        $offset = ($pagina - 1) * $limite;

        $where = [];
        $params = [];

        if ($pesquisa != '') {
            $where[] = "(nome LIKE ? OR email LIKE ?)";
            $params[] = "%$pesquisa%";
            $params[] = "%$pesquisa%";
        }

        if ($perfil != '') {
            $where[] = "tipo=?";
            $params[] = $perfil;
        }

        if ($status != '') {
            $where[] = "ativo=?";
            $params[] = $status;
        }

        $sqlWhere = '';

        if (count($where)) {
            $sqlWhere = ' WHERE ' . implode(' AND ', $where);
        }

        $sql = "SELECT SQL_CALC_FOUND_ROWS *
          FROM usuarios
          $sqlWhere
          ORDER BY $ordem $direcao
          LIMIT $offset,$limite";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = $this->db
            ->query("SELECT FOUND_ROWS()")
            ->fetchColumn();

        return [
            "dados" => $dados,
            "total" => $total
        ];

    }
    /**
     * Pesquisa usuários (Inscrições)
     */
    // public function pesquisar(string $texto): array
    // {
    //     $sql = "
    //         SELECT
    //             id,
    //             nome,
    //             cpf,
    //             email
    //         FROM usuarios
    //         WHERE
    //             nome LIKE :busca
    //             OR cpf LIKE :busca
    //             OR email LIKE :busca
    //         ORDER BY nome
    //         LIMIT 20
    //     ";

    //     $stmt = $this->db->prepare($sql);

    //     $stmt->bindValue(
    //         ":busca",
    //         "%{$texto}%"
    //     );

    //     $stmt->execute();

    //     return $stmt->fetchAll(PDO::FETCH_ASSOC);
    // }

    public function pesquisar(string $texto): array
    {
        $texto = trim($texto);

        $comprimento = function_exists("mb_strlen")
            ? mb_strlen($texto, "UTF-8")
            : strlen($texto);

        if ($comprimento < 2) {
            return [];
        }

        $sql = "
            SELECT
                id,
                nome,
                cpf,
                email
            FROM usuarios
            WHERE
                nome LIKE :busca_nome
                OR cpf LIKE :busca_cpf
                OR email LIKE :busca_email
            ORDER BY nome
            LIMIT 20
        ";

        $stmt = $this->db->prepare($sql);
        $busca = "%{$texto}%";

        $stmt->execute([
            ":busca_nome" => $busca,
            ":busca_cpf" => $busca,
            ":busca_email" => $busca,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca usuário para inscrição
     */
    public function buscarInscricao(int $id): array|false
    {
        if ($id <= 0) {
            return false;
        }

        $sql = "
            SELECT
                us.id,
                us.nome,
                us.telefone,
                us.cpf,
                us.email,
                us.idComunidade,
                comunidade.nome_comunidade AS igreja
            FROM usuarios us
            LEFT JOIN minha_comunidade comunidade
                ON comunidade.id = us.idComunidade
            WHERE us.id = :id
              AND us.ativo = 1
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([":id" => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}