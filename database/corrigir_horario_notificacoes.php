<?php

declare(strict_types=1);

/**
 * Corrige notificações antigas de pagamentos que ficaram com hora 00:00.
 * Apenas substitui esse horário quando o recebimento possui hora registrada.
 *
 * Uso:
 *   php database/corrigir_horario_notificacoes.php
 *   php database/corrigir_horario_notificacoes.php --executar
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Uso permitido apenas via terminal.');
}

$raiz = dirname(__DIR__);
$config = require $raiz . '/config/conn.php';

$pdo = new PDO(
    sprintf('mysql:host=%s;dbname=%s;charset=%s',
        $config['host'],
        $config['database'],
        $config['charset'] ?? 'utf8mb4'
    ),
    $config['username'],
    $config['password'],
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$filtro = <<<'SQL'
FROM notificacoes n
INNER JOIN pagamentos p
    ON n.tipo = 'pagamento'
   AND n.idReferencia = p.idPagamento
WHERE p.status = 'Pago'
  AND p.recebidoEm IS NOT NULL
  AND TIME(p.recebidoEm) <> '00:00:00'
  AND TIME(n.criadoEm) = '00:00:00'
SQL;

$total = (int) $pdo->query('SELECT COUNT(*) ' . $filtro)->fetchColumn();

echo "Notificacoes de pagamento com horario corrigivel: {$total}" . PHP_EOL;

if ($total === 0) {
    exit(0);
}

$preview = $pdo->query(
    'SELECT n.idNotificacao, n.criadoEm AS anterior, '
    . 'p.recebidoEm AS correto '
    . $filtro . ' ORDER BY n.idNotificacao DESC LIMIT 15'
)->fetchAll();

foreach ($preview as $linha) {
    printf(
        "#%d | %s -> %s\n",
        $linha['idNotificacao'],
        $linha['anterior'],
        $linha['correto']
    );
}

if (!in_array('--executar', $argv, true)) {
    echo PHP_EOL . 'PRÉVIA: nenhum registro foi alterado.' . PHP_EOL;
    echo 'Para corrigir, execute com --executar.' . PHP_EOL;
    exit(0);
}

fwrite(STDOUT, PHP_EOL . 'Digite CORRIGIR para confirmar: ');
$confirmacao = trim((string) fgets(STDIN));

if ($confirmacao !== 'CORRIGIR') {
    fwrite(STDERR, "Cancelado. Nenhum registro alterado.\n");
    exit(1);
}

try {
    $pdo->beginTransaction();

    $alterados = $pdo->exec(
        'UPDATE notificacoes n '
        . 'INNER JOIN pagamentos p '
        . "ON n.tipo = 'pagamento' AND n.idReferencia = p.idPagamento "
        . 'SET n.criadoEm = p.recebidoEm '
        . "WHERE p.status = 'Pago' "
        . 'AND p.recebidoEm IS NOT NULL '
        . "AND TIME(p.recebidoEm) <> '00:00:00' "
        . "AND TIME(n.criadoEm) = '00:00:00'"
    );

    $pdo->commit();
    echo "Notificacoes corrigidas: {$alterados}" . PHP_EOL;
} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Falha: " . $erro->getMessage() . PHP_EOL);
    exit(1);
}
