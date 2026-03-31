<?php
// Arquivo deve enviar ZERO saída antes das configs
ob_start();

require 'configuraction/configuracao.php';
include 'padroes.php';
?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <?php
        $titulo = Title_install::getAtual();
        
        if ($titulo) {
            // Correção: parâmetros separados e SEM reatribuir $titulo repetidamente
            HeaderHTML_install::metaTags(
                "Sistema de Eventos",                // title
                "Um sistema de Eventos Paroquial, comunitário", // description
                "Eventos, comunidades, reuniões",    // keywords
                "",                                   // author
                                                  // additional meta
            );
        }
        
        if(isset($_GET['id'])){
            $id = $_GET['id'];
            
        }
        if($lang != ''){
            header("Location: admin.php?id=2&lang={$lang}");
        } else {
            // echo "Erro ao selecionar o idioma.";
        }
        ?>

        <script src="<?php echo THEME_JS; ?>install/script.js"></script>
    </head>

    <body>

        <div class="header-main">
            <div class="container-fluid">
                <nav class="navbar navbar-light bg-faded">
                </nav>
            </div>
        </div>

        <div class="main" id='page'>
            <div class="container-fluid">
                <header id="page-header" class="row">
                    <div class="col-12 pt-3 pb-3">
                        <div class="card ">
                            <div class="card-body ">
                                <div class="d-sm-flex align-items-center">
                                    <div class="mr-auto">
                                        <div class="page-context-header">
                                            <div class="page-header-headings">
                                                <h5><?php
                                                    
                                                    echo modo_config($id, $lang)?>
                                                </h5>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="header-actions-container flex-shrink-0" data-region="header-actions-container">
                                    </div>
                                </div>
                                <div class="d-flex flex-wrap">
                                    
                                
                                </div>
                            </div>
                        </div>
                    </div>
                </header>
            </div>
            <div class="container-fluid">
                <div class="row">
                    <div class="d-flex row">
                        <div class="d-flex justify-content-center">
                            <div class="">
                                <div class="card" style=''>
                                    <?php
                                    
                                        if(isset($_GET['id'])){
                                            $id = $_GET['id'];
                                            
                                            if($id == 2){
                                                primeira_configuracao($lang);
                                            }else if($id == 1){
                                                idioma($lang);
                                            }
                                        }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- <div class="footer-main mt-2">
            <div class="container-fluid">
                <p class="text-center"><?php echo $titulo->getNome() ?> &copy; <?php echo date("Y"); ?></p>
            </div>
        </div> -->

    </body>
</html>

<?php
ob_end_flush();
?>
