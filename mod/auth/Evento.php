<?php

require_once __DIR__ . '/../database/db.php';

class Evento
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /*
    |--------------------------------------------------------------------------
    | Buscar por ID
    |--------------------------------------------------------------------------
    */
    /**
     * Gera um slug amigável
     */
    public static function slug(string $texto): string
    {
        // Remove acentos
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);

        // Minúsculas
        $texto = strtolower($texto);

        // Remove caracteres inválidos
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);

        // Remove hífens duplicados
        $texto = preg_replace('/-+/', '-', $texto);

        // Remove hífens do início/fim
        $texto = trim($texto, '-');

        return $texto;
    }
    public function buscar(int $id): array|false
    {
        $sql = "
            SELECT *
            FROM eventos
            WHERE idEvento = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    public function buscarConfiguracao(int $idEvento): array|false
    {
        $sql = "
            SELECT
                idEvento,
                titulo,
                valor,
                valor_inscricao,
                ativo,
                inscricao_aberta,
                camiseta_ativa,
                certificado,
                certificado_ativo,
                pagamento_obrigatorio,
                repassar_taxa_asaas,
                data_inicio,
                data_fim,
                inscricao_inicio,
                inscricao_fim,
                pagamento_fim,
                vagas,
                idade_minima,
                idade_maxima
            FROM eventos
            WHERE idEvento = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$idEvento]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    /*
    |--------------------------------------------------------------------------
    | Buscar por Slug
    |--------------------------------------------------------------------------
    */

    public function buscarPorSlug(string $slug): array|false
    {
        $sql = "
            SELECT *
            FROM eventos
            WHERE slug = ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([$slug]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | Verifica se já existe outro slug
    |--------------------------------------------------------------------------
    */

    public function slugExisteOutro(string $slug, int $id = 0): bool
    {
        $sql = "
            SELECT idEvento
            FROM eventos
            WHERE slug = ?
              AND idEvento <> ?
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $slug,
            $id
        ]);

        return $stmt->fetch() !== false;
    }

    /*
    |--------------------------------------------------------------------------
    | Total de Eventos
    |--------------------------------------------------------------------------
    */

    public function total(): int
    {
        return (int) $this->db
            ->query("SELECT COUNT(*) FROM eventos")
            ->fetchColumn();
    }

    /*
    |--------------------------------------------------------------------------
    | Eventos Ativos
    |--------------------------------------------------------------------------
    */


    /*
|--------------------------------------------------------------------------
| Listar Todos
|--------------------------------------------------------------------------
*/


    /**
     * Lista eventos ativos que ainda não terminaram.
     *
     * Um evento de um único dia pode possuir data_fim nula;
     * neste caso data_inicio também é usada como data final.
     */
    public function listarDisponiveis(): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM eventos
            WHERE ativo = 1
              AND data_inicio IS NOT NULL
              AND COALESCE(
                    data_fim,
                    data_inicio
                  ) >= CURDATE()
            ORDER BY
                data_inicio ASC,
                COALESCE(
                    hora_inicio,
                    '23:59:59'
                ) ASC,
                titulo ASC
        ");

        $stmt->execute();

        return $stmt->fetchAll(
            PDO::FETCH_ASSOC
        );
    }

    public function listar(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM eventos
            ORDER BY data_inicio DESC,
                     hora_inicio DESC,
                     titulo ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | Listagem Paginada
    |--------------------------------------------------------------------------
    */

    public function listarPaginado(

        string $pesquisa = '',

        string $tipo = '',

        string $status = '',

        string $ordem = 'data_inicio',

        string $direcao = 'DESC',

        int $pagina = 1,

        int $limite = 10

    ): array {

        $offset = ($pagina - 1) * $limite;

        $where = [];

        $params = [];

        /*
        |--------------------------------------------------------------------------
        | Pesquisa
        |--------------------------------------------------------------------------
        */

        if ($pesquisa != '') {

            $where[] = "

                (

                    titulo LIKE ?

                    OR descricao_curta LIKE ?

                    OR local LIKE ?

                    OR cidade LIKE ?

                )

            ";

            $params[] = "%{$pesquisa}%";
            $params[] = "%{$pesquisa}%";
            $params[] = "%{$pesquisa}%";
            $params[] = "%{$pesquisa}%";

        }

        /*
        |--------------------------------------------------------------------------
        | Tipo
        |--------------------------------------------------------------------------
        */

        if ($tipo != '') {

            $where[] = "tipo = ?";

            $params[] = $tipo;

        }

        /*
        |--------------------------------------------------------------------------
        | Status
        |--------------------------------------------------------------------------
        */

        if ($status != '') {

            $where[] = "ativo = ?";

            $params[] = $status;

        }

        $sqlWhere = '';

        if (!empty($where)) {

            $sqlWhere = ' WHERE ' . implode(' AND ', $where);

        }

        /*
        |--------------------------------------------------------------------------
        | Colunas permitidas para ordenação
        |--------------------------------------------------------------------------
        */

        $permitidas = [

            'idEvento',

            'titulo',

            'tipo',

            'cidade',

            'valor',

            'vagas',

            'ativo',

            'data_inicio',

            'criado_em'

        ];

        if (!in_array($ordem, $permitidas)) {

            $ordem = 'data_inicio';

        }

        $direcao = strtoupper($direcao);

        if ($direcao != 'ASC') {

            $direcao = 'DESC';

        }

        /*
        |--------------------------------------------------------------------------
        | Consulta
        |--------------------------------------------------------------------------
        */

        $sql = "

            SELECT SQL_CALC_FOUND_ROWS *

            FROM eventos

            {$sqlWhere}

            ORDER BY {$ordem} {$direcao}

            LIMIT {$offset},{$limite}

        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute($params);

        $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $total = (int) $this->db
            ->query("SELECT FOUND_ROWS()")
            ->fetchColumn();

        return [

            "dados" => $dados,

            "total" => $total

        ];

    }
    /*
    |--------------------------------------------------------------------------
    | Salvar Evento
    |--------------------------------------------------------------------------
    */

    public function salvar(array $dados): int|false
    {

        $sql = "
            INSERT INTO eventos
            (

                titulo,
                slug,
                descricao_curta,
                descricao,
                tipo,

                data_inicio,
                data_fim,

                hora_inicio,
                hora_fim,

                local,
                endereco,
                cidade,
                estado,

                valor,
                vagas,

                idade_minima,
                idade_maxima,

                imagem,

                inscricao_inicio,
                inscricao_fim,
                pagamento_fim,

                certificado,
                certificado_ativo,
                inscricao_aberta,
                camiseta_ativa,
                pagamento_obrigatorio,
                repassar_taxa_asaas,
                valor_inscricao,
                ativo,

                criado_por

            )
            VALUES
            (

                :titulo,
                :slug,
                :descricao_curta,
                :descricao,
                :tipo,

                :data_inicio,
                :data_fim,

                :hora_inicio,
                :hora_fim,

                :local,
                :endereco,
                :cidade,
                :estado,

                :valor,
                :vagas,

                :idade_minima,
                :idade_maxima,

                :imagem,

                :inscricao_inicio,
                :inscricao_fim,
                :pagamento_fim,

                :certificado,
                :certificado_ativo,
                :inscricao_aberta,
                :camiseta_ativa,
                :pagamento_obrigatorio,
                :repassar_taxa_asaas,
                :valor_inscricao,
                :ativo,

                :criado_por

            )
        ";

        $stmt = $this->db->prepare($sql);

        $ok = $stmt->execute([

            ':titulo' => $dados['titulo'],

            ':slug' => $dados['slug'],

            ':descricao_curta' => $dados['descricao_curta'],

            ':descricao' => $dados['descricao'],

            ':tipo' => $dados['tipo'],

            ':data_inicio' => $dados['data_inicio'],

            ':data_fim' => $dados['data_fim'] ?: null,

            ':hora_inicio' => $dados['hora_inicio'] ?: null,

            ':hora_fim' => $dados['hora_fim'] ?: null,

            ':local' => $dados['local'],

            ':endereco' => $dados['endereco'],

            ':cidade' => $dados['cidade'],

            ':estado' => $dados['estado'],

            ':valor' => $dados['valor'],

            ':vagas' => $dados['vagas'] ?: null,

            ':idade_minima' => $dados['idade_minima'] ?: null,

            ':idade_maxima' => $dados['idade_maxima'] ?: null,

            ':imagem' => $dados['imagem'] ?? null,

            ':inscricao_inicio' => $dados['inscricao_inicio'] ?: null,

            ':inscricao_fim' => $dados['inscricao_fim'] ?: null,

            ':pagamento_fim' => $dados['pagamento_fim'] ?: null,

            ':certificado' => $dados['certificado'],

            ':certificado_ativo' => $dados['certificado_ativo'] ?? $dados['certificado'],

            ':inscricao_aberta' => $dados['inscricao_aberta'] ?? 1,

            ':camiseta_ativa' => $dados['camiseta_ativa'] ?? 0,

            ':pagamento_obrigatorio' => $dados['pagamento_obrigatorio'] ?? 1,

            ':repassar_taxa_asaas' => $dados['repassar_taxa_asaas'] ?? 0,

            ':valor_inscricao' => $dados['valor_inscricao'] ?? $dados['valor'],

            ':ativo' => $dados['ativo'],

            ':criado_por' => $_SESSION['usuario_id'] ?? null

        ]);

        if (!$ok) {

            return false;

        }

        return (int) $this->db->lastInsertId();

    }
    /*
|--------------------------------------------------------------------------
| Editar Evento
|--------------------------------------------------------------------------
*/

    public function editar(array $dados): bool
    {

        $campos = [];

        $params = [];

        $campos[] = "titulo = :titulo";
        $params[':titulo'] = $dados['titulo'];

        $campos[] = "slug = :slug";
        $params[':slug'] = $dados['slug'];

        $campos[] = "descricao_curta = :descricao_curta";
        $params[':descricao_curta'] = $dados['descricao_curta'];

        $campos[] = "descricao = :descricao";
        $params[':descricao'] = $dados['descricao'];

        $campos[] = "tipo = :tipo";
        $params[':tipo'] = $dados['tipo'];

        $campos[] = "data_inicio = :data_inicio";
        $params[':data_inicio'] = $dados['data_inicio'];

        $campos[] = "data_fim = :data_fim";
        $params[':data_fim'] = $dados['data_fim'] ?: null;

        $campos[] = "hora_inicio = :hora_inicio";
        $params[':hora_inicio'] = $dados['hora_inicio'] ?: null;

        $campos[] = "hora_fim = :hora_fim";
        $params[':hora_fim'] = $dados['hora_fim'] ?: null;

        $campos[] = "local = :local";
        $params[':local'] = $dados['local'];

        $campos[] = "endereco = :endereco";
        $params[':endereco'] = $dados['endereco'];

        $campos[] = "cidade = :cidade";
        $params[':cidade'] = $dados['cidade'];

        $campos[] = "estado = :estado";
        $params[':estado'] = $dados['estado'];

        $campos[] = "valor = :valor";
        $params[':valor'] = $dados['valor'];

        $campos[] = "vagas = :vagas";
        $params[':vagas'] = $dados['vagas'] ?: null;

        $campos[] = "idade_minima = :idade_minima";
        $params[':idade_minima'] = $dados['idade_minima'] ?: null;

        $campos[] = "idade_maxima = :idade_maxima";
        $params[':idade_maxima'] = $dados['idade_maxima'] ?: null;

        $campos[] = "inscricao_inicio = :inscricao_inicio";
        $params[':inscricao_inicio'] = $dados['inscricao_inicio'] ?: null;

        $campos[] = "inscricao_fim = :inscricao_fim";
        $params[':inscricao_fim'] = $dados['inscricao_fim'] ?: null;

        $campos[] = "pagamento_fim = :pagamento_fim";
        $params[':pagamento_fim'] = $dados['pagamento_fim'] ?: null;

        $campos[] = "certificado = :certificado";
        $params[':certificado'] = $dados['certificado'];

        $campos[] = "certificado_ativo = :certificado_ativo";
        $params[':certificado_ativo'] = $dados['certificado_ativo'] ?? $dados['certificado'];

        $campos[] = "inscricao_aberta = :inscricao_aberta";
        $params[':inscricao_aberta'] = $dados['inscricao_aberta'] ?? 1;

        $campos[] = "camiseta_ativa = :camiseta_ativa";
        $params[':camiseta_ativa'] = $dados['camiseta_ativa'] ?? 0;

        $campos[] = "pagamento_obrigatorio = :pagamento_obrigatorio";
        $params[':pagamento_obrigatorio'] = $dados['pagamento_obrigatorio'] ?? 1;

        $campos[] = "repassar_taxa_asaas = :repassar_taxa_asaas";
        $params[':repassar_taxa_asaas'] = $dados['repassar_taxa_asaas'] ?? 0;

        $campos[] = "valor_inscricao = :valor_inscricao";
        $params[':valor_inscricao'] = $dados['valor_inscricao'] ?? $dados['valor'];

        $campos[] = "ativo = :ativo";
        $params[':ativo'] = $dados['ativo'];

        /*
        |--------------------------------------------------------------------------
        | Atualiza imagem somente se enviada
        |--------------------------------------------------------------------------
        */

        if (
            isset($dados['imagem']) &&
            !empty($dados['imagem'])
        ) {

            $campos[] = "imagem = :imagem";

            $params[':imagem'] = $dados['imagem'];

        }

        $params[':idEvento'] = $dados['idEvento'];

        $sql = "
            UPDATE eventos
               SET
                   " . implode(",\n                   ", $campos) . "
             WHERE idEvento = :idEvento
        ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute($params);

    }
    /*
|--------------------------------------------------------------------------
| Excluir Evento
|--------------------------------------------------------------------------
*/

    public function excluir(int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM eventos
            WHERE idEvento = ?
        ");

        return $stmt->execute([$id]);
    }

    /*
    |--------------------------------------------------------------------------
    | Total de Eventos
    |--------------------------------------------------------------------------
    */

    public function totalEventos(): int
    {
        return (int) $this->db
            ->query("
                SELECT COUNT(*)
                FROM eventos
            ")
            ->fetchColumn();
    }

    /*
    |--------------------------------------------------------------------------
    | Listar Eventos Ativos
    |--------------------------------------------------------------------------
    */

    public function listarAtivos(): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM eventos
            WHERE ativo = 1
            ORDER BY data_inicio DESC,
                     hora_inicio DESC,
                     titulo ASC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | Próximos Eventos
    |--------------------------------------------------------------------------
    */

    public function listarProximos(int $limite = 10): array
    {
        $sql = "
            SELECT *
            FROM eventos
            WHERE ativo = 1
              AND data_inicio >= CURDATE()
            ORDER BY data_inicio ASC,
                     hora_inicio ASC
            LIMIT :limite
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(
            ':limite',
            $limite,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | Eventos em Destaque
    |--------------------------------------------------------------------------
    | Caso futuramente exista o campo 'destaque'
    |--------------------------------------------------------------------------
    */

    public function listarDestaque(int $limite = 5): array
    {
        $sql = "
            SELECT *
            FROM eventos
            WHERE ativo = 1
            ORDER BY data_inicio ASC,
                     hora_inicio ASC
            LIMIT :limite
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(
            ':limite',
            $limite,
            PDO::PARAM_INT
        );

        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Alterna o status (ativo/inativo)
     */
    public function alterarStatus(int $id): bool
    {
        $sql = "
        UPDATE eventos
           SET ativo = IF(ativo = 1, 0, 1)
         WHERE idEvento = :id
    ";

        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            ":id" => $id
        ]);
    }
}