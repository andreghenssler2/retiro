<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: no-store");

function dashboardFinanceiroEscapar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

try {
    $lista = (new Dashboard())->pagamentosPendentes();

    if (!$lista): ?>
        <tr>
            <td colspan="5" class="text-center text-muted py-4">
                Nenhum pagamento pendente.
            </td>
        </tr>
    <?php else:
        foreach ($lista as $item):
            $vencimento = trim((string) ($item["vencimento"] ?? ""));
            ?>
            <tr>
                <td><?= dashboardFinanceiroEscapar($item["nome"] ?? "") ?></td>
                <td><?= dashboardFinanceiroEscapar($item["evento"] ?? "") ?></td>
                <td>R$ <?= number_format((float) ($item["valor"] ?? 0), 2, ",", ".") ?></td>
                <td>
                    <?= $vencimento !== ""
                        ? dashboardFinanceiroEscapar(date("d/m/Y", strtotime($vencimento)))
                        : '<span class="text-muted">Sem vencimento</span>' ?>
                </td>
                <td class="text-end">
                    <a
                        href="<?= BASE_URL ?>admin/financeiro/pagamentos.php"
                        class="btn btn-sm btn-outline-primary"
                    >
                        Ver
                    </a>
                </td>
            </tr>
        <?php endforeach;
    endif;
} catch (Throwable $erro) {
    error_log("Erro em dashboard-financeiro.php: " . $erro->getMessage());
    http_response_code(500);
    ?>
    <tr>
        <td colspan="5" class="text-center text-danger py-4">
            Não foi possível carregar os pagamentos.
        </td>
    </tr>
    <?php
}
