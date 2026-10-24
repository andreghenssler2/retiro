<?php

require_once "../../config/settings.php";

Middleware::auth();

header("Location: pagamentos.php");
exit;
