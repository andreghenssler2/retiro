<?php
/**
 * conexao.php
 * Cria a conexão PDO com o banco de dados, 
 * variando conforme o ambiente (produção ou desenvolvimento)
 */

function conectarBanco($ambiente = 'desenvolvimento')
{
    $configFile = __DIR__ . '/../../config/conn.php';

    if (!file_exists($configFile)) {
        die('Arquivo de configuração do banco não encontrado.');
    }

    $config = require $configFile;

    $db_host = $config['host'];
    $db_nome = $config['database'];
    $db_usuario = $config['username'];
    $db_senha = $config['password'];
    $db_charset = $config['charset'] ?? 'utf8mb4';

    try {

        $dsn = "mysql:host={$db_host};dbname={$db_nome};charset={$db_charset}";

        $pdo = new PDO($dsn, $db_usuario, $db_senha, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_PERSISTENT => true
        ]);

        return $pdo;

    } catch (PDOException $e) {

        $logDir = __DIR__ . '/logs';

        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }

        $mensagem = '[' . date('Y-m-d H:i:s') . '] Erro DB: ' . $e->getMessage() . PHP_EOL;

        file_put_contents($logDir . '/db_errors.log', $mensagem, FILE_APPEND);

        if ($ambiente === 'desenvolvimento') {
            die('Erro ao conectar: ' . htmlspecialchars($e->getMessage()));
        }

        die('Erro interno no servidor. Tente novamente mais tarde.');
    }
}

class Database
{
    private static ?PDO $instance = null;

    public static function connect()
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/conn.php';

            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                $config['host'],
                $config['database'],
                $config['charset']
            );

            try {

                self::$instance = new PDO(
                    $dsn,
                    $config['username'],
                    $config['password'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::ATTR_PERSISTENT => true,
                    ]
                );

            } catch (PDOException $e) {
                $logDir = __DIR__ . '/logs';

                if (!file_exists($logDir)) {
                    mkdir($logDir, 0755, true);
                }

                $mensagem = '[' . date('Y-m-d H:i:s') . '] Erro DB: ' . $e->getMessage() . PHP_EOL;

                file_put_contents($logDir . '/db_errors.log', $mensagem, FILE_APPEND);

                if ($ambiente === 'desenvolvimento') {
                    die('Erro ao conectar: ' . htmlspecialchars($e->getMessage()));
                }

                die('Erro interno no servidor. Tente novamente mais tarde.');
                // die("Erro de conexão: " . $e->getMessage());

            }

        }

        return self::$instance;
    }
}