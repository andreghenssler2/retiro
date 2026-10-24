<?php

    class tempo_logado{
        function tempo_inicio(){
            // Define o tempo máximo de sessão em segundos (exemplo: 3600 segundos = 60 minutos/1hrs)
            $tempo_maximo = 5400; // 30min
            // $tempo_maximo = 60; // 1min

            $_SESSION['registro'] = time(); // Pega o tempo atual
            $_SESSION['limite'] = $tempo_maximo; // Tempo limite da sessão

            echo $_SESSION['registro'];
        }

        function avisar(){
            // Verifica se existe a sessão que foi criada
            if(isset($_SESSION['registro'])){
                // Tempo atual
                $tempo_atual = time();
                // Tempo que já se passou desde que a sessão foi criada
                $tempo_passado = $tempo_atual - $_SESSION['registro'];
                // Verifica se já passou o tempo limite
                if($tempo_passado > $_SESSION['limite']){
                    // Se passou destroi a sessão e redireciona para a página de login
                    header("Location: ".$_SESSION['url_caminho']."/login/logout.php");
                }else{
                    // Se não passou, atualiza o tempo da sessão
                    $_SESSION['registro'] = time();
                }
            }
        }

        function tempo_atual(){
            // Verifica se existe a sessão que foi criada
            if(isset($_SESSION['registro'])){
                // Tempo atual
                $tempo_atual = time();
                // Tempo que já se passou desde que a sessão foi criada
                $tempo_passado = $tempo_atual - $_SESSION['registro'];
                // Verifica se já passou o tempo limite
                $token_logout = $_SESSION['token'];
                if($tempo_passado > $_SESSION['limite']){
                    // Se passou destroi a sessão e redireciona para a página de login
                    header("Location: ".$_SESSION['url_caminho']."/login/logout.php?sesskey=$token_logout");
                }
            }
        }
        
    }
    
?>