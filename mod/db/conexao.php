<?php
/**
 * conexao.php
 * Cria a conexão PDO com o banco de dados, 
 * variando conforme o ambiente (produção ou desenvolvimento)
 */

function conectarBanco($ambiente = 'producao')
{
    // ⚙️ Configurações de banco separadas por ambiente
    if ($ambiente === 'desenvolvimento' ) {
        $db_host = 'localhost';
        $db_nome = 'test';
        $db_usuario = 'root';
        $db_senha = '';
    } else { // produção
        $db_host = '108.167.151.39';
        $db_nome = 'ieclbp28_teste';
        $db_usuario = 'ieclbp28_teste';
        $db_senha = 'Mdp@2024!';
    }

    $db_charset = 'utf8mb4';

    try {
        $dsn = "mysql:host={$db_host};dbname={$db_nome};charset={$db_charset}";

        $pdo = new PDO($dsn, $db_usuario, $db_senha, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => true
        ]);

        return $pdo;

    } catch (PDOException $e) {
        // Log de erro
        $logDir = __DIR__ . '/../../logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $mensagem = '[' . date('Y-m-d H:i:s') . "] [{$ambiente}] Erro DB: " . $e->getMessage() . PHP_EOL;
        file_put_contents($logDir . '/db_errors.log', $mensagem, FILE_APPEND);

        // Exibe erro somente em desenvolvimento
        if ($ambiente === 'desenvolvimento') {
            die("Erro ao conectar: " . htmlspecialchars($e->getMessage()));
        } else {
            die("Erro interno no servidor. Tente novamente mais tarde.");
        }
    }
}
