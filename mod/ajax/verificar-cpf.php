<?php

require_once "../../config/settings.php";

Middleware::auth();

header('Content-Type: application/json; charset=utf-8');

$cpf = preg_replace('/\D/', '', $_POST['cpf'] ?? '');
$id  = (int)($_POST['id'] ?? 0);

if (strlen($cpf) != 11) {

    echo json_encode([
        "existe" => false
    ]);

    exit;
}

$db = Config::getDB();

/*
|--------------------------------------------------------------------------
| Procura o CPF ignorando pontos, traços e espaços
|--------------------------------------------------------------------------
*/

$sql = "
SELECT id
FROM usuarios
WHERE REPLACE(
        REPLACE(
            REPLACE(
                REPLACE(TRIM(cpf),'.','')
            ,'-','')
        ,' ','')
    ,'/','') = ?
";

$params = [$cpf];

if ($id > 0) {

    $sql .= " AND id <> ?";

    $params[] = $id;
}

$sql .= " LIMIT 1";

$stmt = $db->prepare($sql);
$stmt->execute($params);

echo json_encode([
    "existe" => $stmt->fetch(PDO::FETCH_ASSOC) ? true : false
]);