<?php
declare(strict_types=1);

header('Content-Type: application/json');

$response = [
    'status'  => 'error',
    'message' => 'Erro desconhecido'
];


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(403);
    exit;
}

$host = $_POST['host'];
$db   = $_POST['nomebanco'];
$user = $_POST['user'];
$pass = $_POST['senha'] ?? '';
$port = $_POST['porta'] ?? '';
$charset = 'utf8mb4';
$conectarBanco = 'producao' ?? 'desenvolvimento';

if (!$db || !$user) {
    $response['message'] = 'Dados do banco incompletos';
    echo json_encode($response);
    exit;
}


$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);

    $pdo->query('SELECT 1');

    $response['status']  = 'ok';
    $response['message'] = 'Conexão com o banco realizada com sucesso';

} catch (PDOException $e) {

    // LOG INTERNO
    error_log(
        '[' . date('Y-m-d H:i:s') . '] INSTALL DB ERROR: ' . $e->getMessage()
    );

    // MENSAGEM CONTROLADA
    $response['message'] = 'Falha na conexão com o banco de dados';
}

echo json_encode($response);
