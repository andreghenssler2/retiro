<?php
    
    session_name('SistemaSession');

    
    session_start();

    header('Content-Type: text/html; charset=utf-8');
    if (!empty($_SERVER['HTTPS']) && ('on' == $_SERVER['HTTPS'])) {
        $https = 'https://';
    } else {
        $https = 'http://';
    }
    setlocale(LC_TIME, 'pt_BR', 'pt_BR.utf-8', 'pt_BR.utf-8', 'portuguese');
    date_default_timezone_set('America/Sao_Paulo');

    $url = $https."$_SERVER[HTTP_HOST]".$_SERVER["REQUEST_URI"];
	$url_host = $https."$_SERVER[HTTP_HOST]"."/";
	$url_caminho = $url_host;
    $url_caminho_c = $url;
	$_SESSION['url_caminho'] = $url_host;

    define('THEMEJS','wp-theme/js/');
    define('THEMECSS','wp-theme/css/');
    define('THEMEIMG','wp-theme/img/');
    define('THEMELIB','lib/');

    // Caminho para salvar PDF's
    define('caminho_salvar','');

    setcookie('act',md5('act'), time()+2*36000,'/');

    require __DIR__.'/../lib/vendor/autoload.php';
