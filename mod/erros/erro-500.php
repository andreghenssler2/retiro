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
        <h3>Erro: 500 </h3>
        <!-- Acesso proibido, verifique sua permissão <br> -->
        <h1>
            <strong>Ops,</strong> Erro interno do Servidor!
        </h1>
        <p>
            A página não pode ser exibida, pois há alguns erro de execução no servidor ou em um script do site. Caso seja o administrador da página, verifique se há falhas na sintaxe de sua aplicação ou alguma regra de seu .htaccess conflitando com o servidor. Você também pode acessar o seu painel e verifica os logs da aplicação dentro dele.
            
            <br>
            <!-- <?php echo $_SERVER["REQUEST_URI"];  ?>-->
            <br><!-- <?php echo $_SERVER["REMOTE_ADDR"];  ?>-->
        </p>
        <p>
            <a href="<?php echo BASE_URL ?>"><?php echo BASE_URL  ?></a>
        </p>
    </body>
</html>
<?php
    ob_end_flush();
?>