<?php

class Title
{
    private string $nome;
    private string $sigla;
    private string $descricao;
    private string $keyword;
    private string $favicon;
    private string $imagem;

    public function __construct(
        string $nome,
        string $sigla,
        string $descricao,
        string $keyword,
        string $favicon,
        string $imagem
    ) {
        $this->nome = $nome;
        $this->sigla = $sigla;
        $this->descricao = $descricao;
        $this->keyword = $keyword;
        $this->favicon = $favicon;
        $this->imagem = $imagem;
    }

    /**
     * Busca o registro mais recente da tabela titulo.
     */
    public static function getAtual(): self
    {
        $favicon = THEME_IMG . "image2.png";
        $imagem = THEME_IMG . "image.png";
        $nome = "Sistema de Eventos";
        $descricao = "Um sistema completo para gerenciamento de eventos, inscrições e certificados.";
        $keyword = "igreja, evento, culto";
        $sigla = "SE";

        try {
            $db = Config::getDB();

            $stmt = $db->query("
                SELECT
                    nome,
                    sigla,
                    descricao,
                    keyword,
                    favicon,
                    imagem
                FROM titulo
                ORDER BY idTitulo DESC
                LIMIT 1
            ");

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (is_array($row)) {
                $nome = trim((string) ($row["nome"] ?? "")) !== ""
                    ? (string) $row["nome"]
                    : $nome;

                $sigla = trim((string) ($row["sigla"] ?? "")) !== ""
                    ? (string) $row["sigla"]
                    : $sigla;

                $descricao = trim((string) ($row["descricao"] ?? "")) !== ""
                    ? (string) $row["descricao"]
                    : $descricao;

                $keyword = trim((string) ($row["keyword"] ?? "")) !== ""
                    ? (string) $row["keyword"]
                    : $keyword;

                $favicon = self::resolverImagem(
                    (string) ($row["favicon"] ?? ""),
                    "image2.png"
                );

                $imagem = self::resolverImagem(
                    (string) ($row["imagem"] ?? ""),
                    "image.png"
                );
            }
        } catch (Throwable $erro) {
            error_log(
                "Erro ao carregar informações do site: "
                . $erro->getMessage()
            );
        }

        return new self(
            $nome,
            $sigla,
            $descricao,
            $keyword,
            $favicon,
            $imagem
        );
    }

    private static function resolverImagem(
        string $valor,
        string $padrao
    ): string {
        $valor = trim(str_replace("\\", "/", $valor));

        if ($valor === "") {
            return THEME_IMG . $padrao;
        }

        if (preg_match("~^https?://~i", $valor)) {
            return $valor;
        }

        $valor = ltrim($valor, "/");

        if (str_starts_with($valor, "theme/img/")) {
            return BASE_URL . $valor;
        }

        if (str_starts_with($valor, "upload/img/")) {
            return BASE_URL . $valor;
        }

        $nomeArquivo = basename($valor);

        if (
            defined("ROOT_PATH")
            && is_file(
                ROOT_PATH
                . "/theme/img/"
                . $nomeArquivo
            )
        ) {
            return THEME_IMG . rawurlencode($nomeArquivo);
        }

        if (
            defined("ROOT_PATH")
            && defined("ASSETS_IMG")
            && is_file(
                ROOT_PATH
                . "/upload/img/"
                . $nomeArquivo
            )
        ) {
            return ASSETS_IMG . rawurlencode($nomeArquivo);
        }

        return THEME_IMG . rawurlencode($nomeArquivo);
    }

    public function getNome(string $prefixo = ""): string
    {
        return $prefixo === ""
            ? $this->nome
            : $prefixo . " - " . $this->nome;
    }

    public function getSigla(): string
    {
        return $this->sigla;
    }

    public function getDescricao(): string
    {
        return $this->descricao;
    }

    public function getKeyword(): string
    {
        return $this->keyword;
    }

    public function getFavicon(): string
    {
        return $this->favicon;
    }

    public function getImagem(): string
    {
        return $this->imagem;
    }
}
