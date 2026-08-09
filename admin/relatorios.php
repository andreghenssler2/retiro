<?php
require_once '../config/settings.php';
Middleware::auth();
header('Location: ' . BASE_URL . 'admin/relatorios/');
exit;
