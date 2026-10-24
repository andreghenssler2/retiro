<?php

require_once __DIR__ . "/../../config/settings.php";

Session::start();
Middleware::auth();

$titulo = Title::getAtual();

?>
<!doctype html>

<html lang="pt-BR">

<head>

    <meta charset="utf-8">

    <meta http-equiv="X-UA-Compatible" content="IE=edge">

    <meta name="viewport" content="width=device-width, initial-scale=1">

    <?php

    HeaderHTML::metaTags(
        $titulo->getNome(),
        $titulo->getDescricao(),
        $titulo->getKeyword(),
        $titulo->getFavicon()
    );

    ?>

    <!-- Bootstrap -->

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

    <!-- Google Fonts -->

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">

    <!-- CSS -->

    <link rel="stylesheet" href="<?= THEME_CSS ?>admin/admin.css">

    <link rel="stylesheet" href="<?= THEME_CSS ?>admin/sidebar.css">

    <?php foreach (($pageStyles ?? []) as $style): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars((string) $style, ENT_QUOTES, 'UTF-8') ?>">
    <?php endforeach; ?>

</head>

<body>

<div class="user-wrapper">