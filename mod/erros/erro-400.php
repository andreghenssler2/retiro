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
        <h3>Erro: 400 </h3>
        <!-- Acesso proibido, verifique sua permissão <br> -->
        <h1>
            <strong>Ops,</strong> Requisição incorreta!
        </h1>
        <p>
            O servidor WEB não conseguiu atender a requisição. É possível que o cabeçalho HTTP da requisição (onde há a descrição do recurso que deseja obter do servidor) estejá na formatação. Caso seja o Administrador verifique a formatação.
            
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