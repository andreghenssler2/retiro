<?php

require_once 'config.php';
Config::init();

// Conecta automaticamente com o banco correto
$db = Config::getDB();
$hoje = date('Y-m-d');
$time = date('H:i:s');

require __DIR__.'/../mod/titulos/title.php';


require 'headerHTML.php';


?>
