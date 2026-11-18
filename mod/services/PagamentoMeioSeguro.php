<?php
declare(strict_types=1);

/** Exclusão mútua entre a definição manual e a geração de cobrança. */
final class PagamentoMeioSeguro
{
    public static function manual(string $forma): bool
    {
        return in_array($forma, ['Dinheiro', 'Transferencia'], true);
    }

    public static function vinculado(array $p): bool
    {
        return ($p['integracao'] ?? 'Manual') === 'Asaas'
            || trim((string) ($p['asaasPaymentId'] ?? '')) !== '';
    }

    public static function validarManual(array $p, string $forma): void
    {
        if (!self::manual($forma)) {
            return;
        }
        if (self::vinculado($p) || !in_array((string) ($p['formaPagamento'] ?? ''), ['', 'NaoDefinido', $forma], true)) {
            throw new RuntimeException('Já existe um meio de pagamento definido ou cobrança no Asaas. Consulte o financeiro antes de registrar pagamento manual.');
        }
    }

    public static function validarOnline(array $p): void
    {
        if (self::manual((string) ($p['formaPagamento'] ?? ''))) {
            throw new RuntimeException('Este pagamento é manual. Não é permitido gerar ou alterar cobrança no Asaas. Consulte o financeiro.');
        }
    }

    public static function exclusaoConflitante(array $p, string $status, ?string $asaasStatus, ?string $payload): bool
    {
        $dados = json_decode($payload ?? '', true);
        return self::manual((string) ($p['formaPagamento'] ?? ''))
            && self::vinculado($p)
            && ($p['status'] ?? '') === 'Pendente'
            && empty($p['dataPagamento'])
            && $status === 'Cancelado'
            && (strtoupper((string) $asaasStatus) === 'DELETED' || ($dados['event'] ?? '') === 'PAYMENT_DELETED');
    }

    public static function comLock(PDO $db, int $id, callable $acao): mixed
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Pagamento inválido.');
        }
        $banco = (string) $db->query('SELECT DATABASE()')->fetchColumn();
        $chave = 'retiro-meio-' . substr(hash('sha256', $banco . ':' . $id), 0, 45);
        $stmt = $db->prepare('SELECT GET_LOCK(?, 10)');
        $stmt->execute([$chave]);
        if ((int) $stmt->fetchColumn() !== 1) {
            throw new RuntimeException('Pagamento em processamento. Tente novamente em alguns instantes.');
        }
        $stmt->closeCursor();
        try {
            return $acao();
        } finally {
            try {
                $stmt = $db->prepare('SELECT RELEASE_LOCK(?)');
                $stmt->execute([$chave]);
                $stmt->closeCursor();
            } catch (Throwable $e) {
                error_log('Retiro: falha ao liberar lock de pagamento ' . $id);
            }
        }
    }
}
