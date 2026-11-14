<?php

declare(strict_types=1);

require_once "../config/settings.php";

Middleware::auth();

header("Location: " . BASE_URL . (Auth::isModerador() ? "admin/event/eventos.php" : "admin/dashboard/"));
exit;
