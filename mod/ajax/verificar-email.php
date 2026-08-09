<?php

require_once "../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json");

$email=trim($_POST["email"]??"");

$id=(int)($_POST["id"]??0);

$db=Config::getDB();

$sql="

SELECT id

FROM usuarios

WHERE email=?

";

$params=[

$email

];

if($id){

$sql.="

AND id<>?

";

$params[]=$id;

}

$sql.="

LIMIT 1

";

$stmt=$db->prepare($sql);

$stmt->execute($params);

echo json_encode([

"existe"=>$stmt->fetch()?true:false

]);