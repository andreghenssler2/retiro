<?php

require_once "../../../config/settings.php";

Middleware::auth();

$usuario=new Usuario();

$retorno=$usuario->listarPaginado(

    $_GET["pesquisa"] ?? "",

    $_GET["perfil"] ?? "",

    $_GET["status"] ?? "",

    $_GET["ordem"] ?? "nome",

    $_GET["direcao"] ?? "ASC",

    (int)($_GET["pagina"] ?? 1),

    (int)($_GET["limite"] ?? 10)

);

header("Content-Type: application/json");

echo json_encode($retorno);