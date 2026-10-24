<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: text/html; charset=utf-8");
header("Cache-Control: no-store");

function dashboardEscapar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
}

function dashboardBadgeInscricao(string $status): string
{
    $classes = [
        "Pendente" => "bg-warning text-dark",
        "Confirmada" => "bg-success",
        "Cancelada" => "bg-danger"
    ];

    $classe = $classes[$status] ?? "bg-secondary";

    return '<span class="badge ' . $classe . '">' . dashboardEscapar($status) . '</span>';
}

function dashboardBadgePagamento(string $status): string
{
    $classes = [
        "Pendente" => "bg-warning text-dark",
        "Pago" => "bg-success",
        "Cancelado" => "bg-danger",
        "Estornado" => "bg-dark"
    ];

    $classe = $classes[$status] ?? "bg-secondary";

    return '<span class="badge ' . $classe . '">' . dashboardEscapar($status) . '</span>';
}

try {
    $lista = (new Dashboard())->ultimasInscricoes();

    if (!$lista): ?>
        <tr>
            <td colspan="6" class="text-center text-muted py-4">
                Nenhuma inscrição encontrada.
            </td>
        </tr>
    <?php else:
        foreach ($lista as $item): ?>
            <tr>
                <td>#<?= (int) $item["idInscricao"] ?></td>
                <td><?= dashboardEscapar($item["nome"] ?? "") ?></td>
                <td><?= dashboardEscapar($item["evento"] ?? "") ?></td>
                <td><?= dashboardBadgeInscricao((string) ($item["status"] ?? "")) ?></td>
                <td><?= dashboardBadgePagamento((string) ($item["pagamento"] ?? "")) ?></td>
                <td><?= dashboardEscapar(date("d/m/Y H:i", strtotime((string) $item["criado_em"]))) ?></td>
            </tr>
        <?php endforeach;
    endif;
} catch (Throwable $erro) {
    error_log("Erro em dashboard-inscricoes.php: " . $erro->getMessage());
    http_response_code(500);
    ?>
    <tr>
        <td colspan="6" class="text-center text-danger py-4">
            Não foi possível carregar as inscrições.
        </td>
    </tr>
    <?php
}
