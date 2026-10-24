<?php

declare(strict_types=1);

/**
 * Roteador central do sidebar.
 *
 * Tipo 1: Administrador
 * Tipo 2: Moderador
 * Tipo 3: Participante
 */

Session::start();

if (!Auth::check()) {
    return;
}

if (Auth::isAdmin()) {
    require_once __DIR__ . "/../admin/includes/sidebar.php";
    return;
}

require_once __DIR__ . "/../user/includes/sidebar.php";
