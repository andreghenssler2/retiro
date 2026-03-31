<?php

// Arquivo deve enviar ZERO saída antes das configs
ob_start();

require_once __DIR__ . "/../../config/settings.php";

?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <?php
            $titulo = Title::getAtual();
            if ($titulo) {
                HeaderHTML::metaTags(
                    $titulo->getNome(),
                    $titulo->getDescricao(),
                    $titulo->getKeyword(),
                    $titulo->getFavicon(),
                    $titulo->getImagem()
                );
            }
        ?>
    </head>

<body>
    <h3>Erro: 404</h3>
    <h1><strong>Ops,</strong> Não encontramos essa página!</h1>

    <p>
        Parece que a página que você está procurando foi movida ou nunca existiu.<br>
        Verifique se digitou o endereço corretamente ou seguiu um link válido.
    </p>

    <br>

    <a href="<?php echo BASE_URL; ?>"><?php echo BASE_URL; ?></a>
</body>
</html>

<?php
    ob_end_flush();
?>
