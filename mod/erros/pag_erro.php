<?php
    require_once '../../settings.php';

    $date_con = "[".date("d/m/y H:i:s")."] ";
    // header('Content-Type: text/html; charset=utf-8');
    $date_d = date('d');
    $date_m = date('m');
    $date_y = date('y');
    $time_h = date('H');
    $time_i = date('i');
    $time_s = date('s');
    if(!file_exists("erros.txt")){
        $arquivo = fopen('erros.txt','a');
        $texto = "";
    }else{  
        // include "erros.txt";
        $arquivo = fopen('erros.txt','a');
        $texto = "";
    }

    
    

    if($erro == 400){
        $texto.= $date_con."400 Erro: Requisicao incorreta. Pagina do Erro: $requisisao_url IP do acesso: $requisisao_addr \n";
    }else if($erro == 401){
        $texto.= $date_con."401 Erro: Autorizacao obrigatória. Pagina do Erro: $requisisao_url IP do acesso: $requisisao_addr \n";
    }else if($erro == 403){
        $texto.= $date_con."403 Erro: de permissao. Pagina do Erro: $requisisao_url IP do acesso: $requisisao_addr \n";
    }else if($erro == 404){
        $texto.= $date_con."404 Erro: pagina nao econtrada. Pagina do Erro: $requisisao_url IP do acesso: $requisisao_addr \n";
    }else if($erro == 500){
        $texto.= $date_con."500 Erro: Carregamento nao concluido. Pagina do Erro: $requisisao_url IP do acesso: $requisisao_addr \n";
    }
    

    if($date_d == 15){
        if($time_h == 16 && $time_i == 0){
            rename("erros.txt","erro- $date_d$date_m$date_y $time_h$time_i$time_s.txt");
            $texto.= $date_con."Erro: Arquivo renomeado \n";
            $arquivo = fopen('erros.txt','a');
        }
    }

    fwrite($arquivo, $texto);
    fclose($arquivo);

    

?>