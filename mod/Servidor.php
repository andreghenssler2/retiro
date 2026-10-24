<?php

class Servidor extends Title
{
    private $email;
    private $host;
    private $porta;
    private $usuario;
    private $senha;
    private $encryption;
    private $remetente;

    public function __construct(
        $nome,
        $sigla,
        $descricao,
        $keyword,
        $favicon,
        $imagem,
        $email,
        $host,
        $porta,
        $usuario,
        $senha,
        $encryption,
        $remetente
    ) {

        parent::__construct(
            $nome,
            $sigla,
            $descricao,
            $keyword,
            $favicon,
            $imagem
        );

        $this->email = $email;
        $this->host = $host;
        $this->porta = $porta;
        $this->usuario = $usuario;
        $this->senha = $senha;
        $this->encryption = $encryption;
        $this->remetente = $remetente;
    }

    public static function getAtual(): self
    {
        $titulo = parent::getAtual();

        $db = Config::getDB();

        $sql = "SELECT *
                  FROM email_config
              ORDER BY idEmailConfig DESC
                 LIMIT 1";

        $stmt = $db->prepare($sql);
        $stmt->execute();

        $mail = $stmt->fetch(PDO::FETCH_ASSOC);

        return new self(
            $titulo->getNome(),
            $titulo->getSigla(),
            $titulo->getDescricao(),
            $titulo->getKeyword(),
            $titulo->getFavicon(),
            $titulo->getImagem(),

            $mail['username'] ?? '',
            $mail['host'] ?? '',
            $mail['porta'] ?? 465,
            $mail['username'] ?? '',
            $mail['senha'] ?? '',
            $mail['encryption'] ?? 'ssl',
            $mail['remetente'] ?? $titulo->getNome()
        );
    }

    public function getEmail()
    {
        return $this->email;
    }

    public function getHost()
    {
        return $this->host;
    }

    public function getPorta()
    {
        return $this->porta;
    }

    public function getUsuario()
    {
        return $this->usuario;
    }

    public function getSenha()
    {
        return $this->senha;
    }

    public function getEncryption()
    {
        return $this->encryption;
    }

    public function getRemetente()
    {
        return $this->remetente;
    }
}

?>