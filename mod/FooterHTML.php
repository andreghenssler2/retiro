<?php

class FooterHTML
{
    public static function render()
    {
        $config = require __DIR__ . '/../mod/version.php';

        echo "Versão {$config['version']} (Build {$config['build']})";
    }

    public static function versao()
    {
        $config = require __DIR__ . '/../mod/version.php';

        echo "Versão {$config['version']}";
    }
}