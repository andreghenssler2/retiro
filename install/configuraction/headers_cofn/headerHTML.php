<?php
require_once "config.php";

class HeaderHTML_install {

    public static function css() {
        echo '        <link rel="stylesheet" href="' . THEME_CSS . 'style.all.css">';
        echo "<link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css' rel='stylesheet' integrity='sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB' crossorigin='anonymous'>\n";
        echo '        <link rel="stylesheet" href="' . THEME_CSS . 'all.css">';
        echo '        <link rel="stylesheet" href="' . THEME_CSS . 'v6/css/all.min.css">';
        echo '        <link rel="stylesheet" href="' . THEME_CSS . 'v7/css/all.min.css">';
    }

    public static function javascript() {
        echo "<script type='text/javascript' src='https://code.jquery.com/jquery-3.6.0.min.js'></script>
        <script type='text/javascript' src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.1/jquery.min.js'></script>
        <!--<script type='text/javascript' src='https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js'></script>-->
        <script src='https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.10/jquery.mask.js'></script>";

        echo "\n          <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js' integrity='sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI' crossorigin='anonymous'></script>\n";
        echo "          <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js'></script>\n";
        echo "        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>\n";
        echo '        <script src="' . THEME_JS . 'script.js"></script>';
    }

    public static function metaTags($titulo, $descricao, $palavrasChave,$image) {
        echo "<title>{$titulo}</title>\n";
        echo "        <meta charset='" . Config_install::CHARSET . "'>\n";
        echo "        <meta http-equiv='X-UA-Compatible' content='IE=edge'>\n";
        echo "        <meta name='viewport' content='width=device-width, initial-scale=1.0'>\n";
        echo "        <meta name='description' content='{$descricao}'>\n";
        echo "        <meta name='keywords' content='{$palavrasChave}'>\n";
        echo "        <link rel='icon' href='{$image}' type='image/x-icon'>\n";
       
        // Open Graph
        echo "        <meta property='og:title' content='" . $titulo . "'>\n";
        echo "        <meta property='og:description' content='" . $descricao . "'>\n";
        echo "        <meta property='og:type' content='website'>\n";
        // echo "        <meta property='og:url' content='" . Config::BASE_URL . "'>\n";
        echo "        <meta property='og:image' content='" . $image . "'>\n";

        // Twitter Cards
        echo "        <meta name='twitter:card' content='summary_large_image'>\n";
        echo "        <meta name='twitter:title' content='" . $titulo . "'>\n";
        echo "        <meta name='twitter:description' content='" . $descricao . "'>\n";
        echo "        <meta name='twitter:image' content='" . $image . "'>\n";
        echo "        ";
        self::css();
        
        echo "\n        ";
        self::javascript();
 /*
        */ 
     }

}

?>
