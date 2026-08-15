<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/settings.php";

Session::start();
Auth::requireLogin();

if (Auth::isAdmin()) {
    header("Location: " . BASE_URL . "admin/financeiro/pagamentos.php");
    exit;
}

$idUsuario = (int) (Auth::id() ?? 0);
$pagamentos = [];

if ($idUsuario > 0) {
    $stmt = $db->prepare("
        SELECT
            p.idPagamento,
            p.codigo,
            p.formaPagamento,
            p.valor,
            p.status,
            p.dataVencimento,
            p.dataPagamento,
                p.recebidoEm,
            e.idEvento,
            e.titulo,
            e.slug,
            e.data_inicio,
            e.local
        FROM pagamentos p
        INNER JOIN inscricoes i
            ON i.idInscricao = p.idInscricao
        INNER JOIN eventos e
            ON e.idEvento = i.idEvento
        WHERE i.idUsuario = :idUsuario
        ORDER BY p.idPagamento DESC
    ");

    $stmt->execute([":idUsuario" => $idUsuario]);
    $pagamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function meusPagEscapar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, "UTF-8");
}

function meusPagMoeda(mixed $valor): string
{
    return "R$ " . number_format((float) $valor, 2, ",", ".");
}

function meusPagData(mixed $valor, bool $hora = false): string
{
    $texto = trim((string) ($valor ?? ""));
    if ($texto === "") {
        return "-";
    }

    $timestamp = strtotime($texto);
    if ($timestamp === false) {
        return $texto;
    }

    return date($hora ? "d/m/Y H:i" : "d/m/Y", $timestamp);
}

function meusPagStatusClasse(string $status): string
{
    return match ($status) {
        "Pago" => "success",
        "Vencido", "Cancelado", "Estornado" => "danger",
        default => "warning"
    };
}

function meusPagForma(mixed $forma): string
{
    return match ((string) $forma) {
        "PIX" => "PIX",
        "Cartao" => "Cartão de crédito",
        "Boleto" => "Boleto",
        "Dinheiro" => "Dinheiro",
        "Transferencia" => "Transferência",
        default => "Não definida"
    };
}

$total = count($pagamentos);
$pagos = 0;
$pendentes = 0;
$totalPago = 0.0;

foreach ($pagamentos as $pagamento) {
    $status = (string) ($pagamento["status"] ?? "");

    if ($status === "Pago") {
        $pagos++;
        $totalPago += (float) ($pagamento["valor"] ?? 0);
    } elseif (in_array($status, ["Pendente", "Vencido"], true)) {
        $pendentes++;
    }
}

require_once __DIR__ . "/../admin/includes/header.php";
require_once __DIR__ . "/../admin/includes/navbar.php";
require_once __DIR__ . "/../includes/sidebar.php";
?>

<div class="content" id="content">
    <div class="container-fluid">

        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">
                    <i class="fa-solid fa-credit-card text-primary me-2"></i>
                    Meus Pagamentos
                </h2>
                <p class="text-muted mb-0">
                    Consulte os pagamentos relacionados às suas inscrições.
                </p>
            </div>

            <a href="<?= BASE_URL ?>user/eventos.php" class="btn btn-outline-primary">
                <i class="fa-solid fa-calendar-days me-1"></i>
                Meus eventos
            </a>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted">Total</small>
                        <div class="display-6 fw-bold"><?= $total; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted">Pagos</small>
                        <div class="display-6 fw-bold text-success"><?= $pagos; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted">Pendentes</small>
                        <div class="display-6 fw-bold text-warning"><?= $pendentes; ?></div>
                    </div>
                </div>
            </div>

            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted">Total pago</small>
                        <div class="h3 fw-bold text-primary mb-0"><?= meusPagMoeda($totalPago); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($pagamentos === []): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-body text-center p-5">
                    <i class="fa-regular fa-credit-card fa-4x text-muted mb-3"></i>
                    <h3 class="h5">Nenhum pagamento encontrado</h3>
                    <p class="text-muted">Seus pagamentos aparecerão aqui quando uma inscrição possuir cobrança.</p>
                    <a href="<?= BASE_URL ?>eventos/" class="btn btn-primary">Ver eventos</a>
                </div>
            </div>
        <?php else: ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3">
                    <strong>Histórico de pagamentos</strong>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Evento</th>
                                <th>Código</th>
                                <th>Forma</th>
                                <th>Valor</th>
                                <th>Vencimento</th>
                                <th>Status</th>
                                <th>Pagamento</th>
                                <th class="text-end">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($pagamentos as $pagamento): ?>
                            <?php
                            $status = (string) ($pagamento["status"] ?? "Pendente");
                            $idPagamento = (int) ($pagamento["idPagamento"] ?? 0);
                            $slug = trim((string) ($pagamento["slug"] ?? ""));
                            $urlEvento = $slug !== ""
                                ? BASE_URL . "eventos/" . rawurlencode($slug)
                                : BASE_URL . "eventos/detalhe.php?id=" . (int) ($pagamento["idEvento"] ?? 0);
                            ?>
                            <tr>
                                <td>
                                    <a href="<?= meusPagEscapar($urlEvento); ?>" class="text-decoration-none fw-semibold">
                                        <?= meusPagEscapar($pagamento["titulo"] ?? "Evento"); ?>
                                    </a>
                                    <div class="small text-muted">
                                        <?= meusPagData($pagamento["data_inicio"] ?? ""); ?>
                                        <?php if (!empty($pagamento["local"])): ?>
                                            · <?= meusPagEscapar($pagamento["local"]); ?>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td><code><?= meusPagEscapar($pagamento["codigo"] ?? "-"); ?></code></td>
                                <td><?= meusPagEscapar(meusPagForma($pagamento["formaPagamento"] ?? "")); ?></td>
                                <td class="fw-semibold"><?= meusPagMoeda($pagamento["valor"] ?? 0); ?></td>
                                <td><?= meusPagData($pagamento["dataVencimento"] ?? ""); ?></td>
                                <td>
                                    <span class="badge text-bg-<?= meusPagStatusClasse($status); ?>">
                                        <?= meusPagEscapar($status); ?>
                                    </span>
                                </td>
                                <td><?php
                                    /*
                                     * HORA_RECEBIMENTO_MEUS_V1_1
                                     */
                                    $dataRecebimentoUsuario =
                                        $pagamento[
                                            "recebidoEm"
                                        ]
                                        ?? $pagamento[
                                            "dataPagamento"
                                        ]
                                        ?? "";
                                    ?>

                                    <?= meusPagData(
                                        $dataRecebimentoUsuario,
                                        true
                                    ); ?></td>
                                <td class="text-end">
                                    <?php if (in_array($status, ["Pendente", "Vencido"], true) && $idPagamento > 0): ?>
                                        <a href="<?= BASE_URL ?>eventos/pagamento.php?id=<?= $idPagamento; ?>" class="btn btn-sm btn-primary">
                                            <i class="fa-solid fa-money-check-dollar me-1"></i>
                                            Pagar
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted small">—</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php require_once __DIR__ . "/../admin/includes/footer.php"; ?>
