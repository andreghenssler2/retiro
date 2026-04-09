<?php

function modo_config($id = null, $lang = 'pt_br')
{



    $id = $id ?? ($_GET['id'] ?? 1);



    $listas = [

        'pt_br' => [

            1 => 'Idioma',

            2 => 'Informações',

            3 => 'Configuração',

            4 => 'Caminho do sistema',
            '4a' => 'Criando banco de dados',

            5 => 'Dados de Usuário',

            6 => 'Dados da Empresa',

            7 => 'Cadastrar Evento'

        ],

        'en_us' => [

            1 => 'Language',

            2 => 'Information',

            3 => 'Settings',

            4 => 'System Path',
            '4a' => 'Creating the Database',


            5 => 'User Data',

            6 => 'Company Data',

            7 => 'Create Event'

        ],

        'es_es' => [

            1 => 'Idioma',

            2 => 'Información',

            3 => 'Configuración',

            4 => 'Ruta del sistema',
            '4a' => 'Creación de la Base de Datos',


            5 => 'Datos del usuario',

            6 => 'Datos de la empresa',

            7 => 'Crear evento'

        ]

    ];



    // Fallback de idioma

    if (!isset($listas[$lang])) {

        $lang = 'pt_br';

    }



    // Se o ID não existir na lista, redireciona para 1

    if (!isset($listas[$lang][$id])) {

        header("Location: ?id=1&lang={$lang}");

        exit;

    }



    echo $listas[$lang][$id];

}



function idioma($idioma = "pt_br")
{

    if ($idioma == "pt_br") {

        require 'idioma/pt_br.php';

    } elseif ($idioma == "en_us") {

        require 'idioma/en_us.php';

    } elseif ($idioma == "es_es") {

        require 'idioma/es_es.php';

    } else {

        require 'idioma/pt_br.php'; // padrão

    }

    require 'idioma.php';



}





function primeira_configuracao($idioma = "pt_br")
{

    if ($idioma == "pt_br") {

        require 'idioma/pt_br.php';

    } elseif ($idioma == "en_us") {

        require 'idioma/en_us.php';

    } elseif ($idioma == "es_es") {

        require 'idioma/es_es.php';

    } else {

        require 'idioma/pt_br.php'; // padrão

    }

    require 'informacoes.php';



}

function config_banco_install($idioma = "pt_br")
{

    if ($idioma == "pt_br") {

        require 'idioma/pt_br.php';

    } elseif ($idioma == "en_us") {

        require 'idioma/en_us.php';

    } elseif ($idioma == "es_es") {

        require 'idioma/es_es.php';

    } else {

        require 'idioma/pt_br.php'; // padrão

    }

    require 'config_banco.php';

}



function progress_install($id)
{

    $progress_id = [

        1 => 10,

        2 => 29,

        3 => 40,

        4 => 55,

        '4a' => 62,

        5 => 73.6,

        6 => 87.5,

        7 => 100

    ];



    echo '<div class="progress" style="height: 20px;">

                <div class="progress-bar progress-bar-striped progress-bar-animated bg-success"

                    role="progressbar"

                    style="width: ' . $progress_id[$id] . '%;"

                    aria-valuenow="' . $progress_id[$id] . '"

                    aria-valuemin="0"

                    aria-valuemax="100">

                    ' . $progress_id[$id] . '% Concluído

                </div>

            </div>';

}



function caminho_sistema($idioma = "pt_br")
{

    if ($idioma == "pt_br") {

        require 'idioma/pt_br.php';

    } elseif ($idioma == "en_us") {

        require 'idioma/en_us.php';

    } elseif ($idioma == "es_es") {

        require 'idioma/es_es.php';

    } else {

        require 'idioma/pt_br.php'; // padrão

    }

    $caminho = str_replace("\\", "/", getcwd());

    include 'config_caminho.php';

    return $caminho;

}





?>