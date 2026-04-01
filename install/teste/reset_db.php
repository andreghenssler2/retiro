<?php

require_once __DIR__ . '/../../config/config.php';

$pdo = $db;

try {

    $sqlFile = __DIR__ . '/../../mod/db/me.sql';

    $sql = file_get_contents($sqlFile);

    preg_match_all('/CREATE TABLE\s+`?(\w+)`?/i', $sql, $tables);

    foreach ($tables[1] as $table) {

        $pdo->exec("DROP TABLE IF EXISTS `$table`");

    }

    echo "reset";

} catch (Exception $e) {

    echo $e->getMessage();

}