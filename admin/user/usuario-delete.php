<?php

require_once "../../config/settings.php";

Middleware::auth();

Session::start();

$usuario = new Usuario();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id) {

    Session::flash(
        "danger",
        "Usuário inválido."
    );

    header("Location: usuarios.php");
    exit;

}

/*
|--------------------------------------------------------------------------
| Busca usuário
|--------------------------------------------------------------------------
*/

$dados = $usuario->buscar($id);

if (!$dados) {

    Session::flash(
        "danger",
        "Usuário não encontrado."
    );

    header("Location: usuarios.php");
    exit;

}

/*
|--------------------------------------------------------------------------
| Não permite excluir o próprio usuário
|--------------------------------------------------------------------------
*/

if (isset($_SESSION['usuario']['id']) && $_SESSION['usuario']['id'] == $id) {

    Session::flash(
        "warning",
        "Você não pode excluir seu próprio usuário."
    );

    header("Location: usuarios.php");
    exit;

}

/*
|--------------------------------------------------------------------------
| Remove foto
|--------------------------------------------------------------------------
*/

if (!empty($dados['foto'])) {

    $arquivo = ROOT_PATH . "/uploads/usuarios/" . $dados['foto'];

    if (file_exists($arquivo)) {

        @unlink($arquivo);

    }

}

/*
|--------------------------------------------------------------------------
| Exclui registro
|--------------------------------------------------------------------------
*/

if ($usuario->excluir($id)) {

    Session::flash(
        "success",
        "Usuário excluído com sucesso."
    );

} else {

    Session::flash(
        "danger",
        "Não foi possível excluir o usuário."
    );

}

header("Location: usuarios.php");
exit;