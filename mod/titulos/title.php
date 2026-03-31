<?php
require_once __DIR__ . '/../../config/settings.php';

class Title
{
    private $nome;
    private $descricao;
    private $keyword;
    private $favicon;
    private $imagem;

    public function __construct($nome, $descricao, $keyword, $favicon, $imagem)
    {
        $this->nome      = $nome;
        $this->descricao = $descricao;
        $this->keyword   = $keyword;
        $this->favicon   = $favicon;
        $this->imagem    = $imagem;
    }

    /**
     * Busca o registro mais recente da tabela 'titulo'.
     * Caso não exista nenhum, retorna valores padrão.
     */
    public static function getAtual(){
        // Valores padrão
        $favicon   = THEME_IMG . 'image.png';
        $imagem    = THEME_IMG . 'logo_370x120.png';
        $nome      = 'Sistema de Eventos';
        $descricao = 'Descrição do site que vem do banco.';
        $keyword   = 'igreja, evento, culto';

        try {
            $db = Config::getDB();

            $sql = "SELECT nome, descricao, keyword, favicon, imagem
                    FROM titulo
                    ORDER BY idTitulo DESC
                    LIMIT 1";

            $stmt = $db->prepare($sql);
            $stmt->execute();

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                $favicon   = !empty($row['favicon']) ? ASSETS_IMG.$row['favicon'] : $favicon;
                $imagem    = !empty($row['imagem'])  ? ASSETS_IMG.$row['imagem']  : $imagem;
                $nome      = !empty($row['nome'])    ? $row['nome']               : $nome;
                $descricao = !empty($row['descricao']) ? $row['descricao']        : $descricao;
                $keyword   = !empty($row['keyword']) ? $row['keyword']            : $keyword;
            }

        } catch (PDOException $e) {
            // Se a tabela não existir, ignora e usa os padrões
            // Opcional: log do erro
            // error_log($e->getMessage());
        }

        return new self($nome, $descricao, $keyword, $favicon, $imagem);
    }

    // Getters
    public function getNome()      { return $this->nome; }
    public function getDescricao() { return $this->descricao; }
    public function getKeyword()   { return $this->keyword; }
    public function getFavicon()   { return $this->favicon; }
    public function getImagem()    { return $this->imagem; }
}
?>
