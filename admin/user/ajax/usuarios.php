<?php

require_once "../../../config/settings.php";

Middleware::auth();

$db = Config::getDB();

$pesquisa = trim($_GET['pesquisa'] ?? '');
$perfil = trim($_GET['perfil'] ?? '');
$status = trim($_GET['status'] ?? '');

$sql = "

SELECT *

FROM usuarios

WHERE 1=1

";

$params = [];

if ($pesquisa != "") {

    $sql .= "

    AND (

        nome LIKE ?

        OR email LIKE ?

    )

    ";

    $params[] = "%{$pesquisa}%";
    $params[] = "%{$pesquisa}%";

}

if ($perfil != "") {

    $sql .= " AND tipo=?";

    $params[] = $perfil;

}

if ($status != "") {

    $sql .= " AND ativo=?";

    $params[] = $status;

}

$sql .= " ORDER BY nome";

$stmt = $db->prepare($sql);

$stmt->execute($params);

$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

$html = "";

foreach ($usuarios as $u) {

    $perfilBadge = $u['tipo'] == 1
        ? '<span class="badge bg-danger">Administrador</span>'
        : '<span class="badge bg-secondary">Usuário</span>';

    $statusBadge = $u['ativo']
        ? '<span class="badge bg-success">Ativo</span>'
        : '<span class="badge bg-danger">Inativo</span>';

    $ultimo = "-";

    if (!empty($u["ultimo_login"])) {

        $ultimo = date("d/m/Y H:i", strtotime($u["ultimo_login"]));

    }

    $html .= "

<tr>

<td>{$u['id']}</td>

<td>

<strong>" . htmlspecialchars($u['nome']) . "</strong>

<br>

<small class='text-muted'>" . htmlspecialchars($u['email']) . "</small>

</td>

<td>{$perfilBadge}</td>

<td>{$ultimo}</td>

<td>{$statusBadge}</td>

<td>

<a href='usuario.php?id={$u['id']}'
class='btn btn-primary btn-sm'>

<i class='fa fa-pencil'></i>

</a>

<a href='usuario-view.php?id={$u['id']}'
class='btn btn-info btn-sm text-white'>

<i class='fa fa-eye'></i>

</a>

<button
class='btn btn-danger btn-sm btnExcluir'
data-id='{$u['id']}'>

<i class='fa fa-trash'></i>

</button>

</td>

</tr>

";

}

echo json_encode([

    "html" => $html

]);