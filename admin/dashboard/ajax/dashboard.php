<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");

function nomeMesDashboard(int $mes): string
{
    $meses = [
        1 => 'Janeiro',
        2 => 'Fevereiro',
        3 => 'Março',
        4 => 'Abril',
        5 => 'Maio',
        6 => 'Junho',
        7 => 'Julho',
        8 => 'Agosto',
        9 => 'Setembro',
        10 => 'Outubro',
        11 => 'Novembro',
        12 => 'Dezembro'
    ];

    return $meses[$mes] ?? '';
}

try {
    $ano = (int) ($_GET['ano'] ?? 0);
    $mes = (int) ($_GET['mes'] ?? 0);
    $idEvento = max(0, (int) ($_GET['evento'] ?? 0));

    if ($ano < 2000 || $ano > 2100) {
        $ano = 0;
    }

    if ($mes < 1 || $mes > 12) {
        $mes = 0;
    }

    $eventoSelecionado = null;

    if ($idEvento > 0) {
        $ano = 0;
        $mes = 0;

        $evento = new Evento();
        $eventoSelecionado = $evento->buscar($idEvento);

        if (!$eventoSelecionado) {
            $idEvento = 0;
        }
    }

    $descricaoFiltro = 'Todos os dados';

    if ($idEvento > 0 && $eventoSelecionado) {
        $descricaoFiltro = 'Evento: ' . (string) $eventoSelecionado['titulo'];
    } elseif ($ano > 0 && $mes > 0) {
        $descricaoFiltro = nomeMesDashboard($mes) . ' de ' . $ano;
    } elseif ($ano > 0) {
        $descricaoFiltro = 'Ano: ' . $ano;
    } elseif ($mes > 0) {
        $descricaoFiltro = nomeMesDashboard($mes) . ' — todos os anos';
    }

    $dashboard = new Dashboard($db, [
        'ano' => $ano,
        'mes' => $mes,
        'evento' => $idEvento
    ]);

    echo json_encode([
        "status" => true,
        "filtro" => [
            "ano" => $ano,
            "mes" => $mes,
            "evento" => $idEvento,
            "descricao" => $descricaoFiltro
        ],
        "cards" => [
            "eventos" => $dashboard->totalEventos(),
            "inscritos" => $dashboard->totalInscritos(),
            "confirmados" => $dashboard->totalConfirmados(),
            "pendentes" => $dashboard->totalPendentes(),
            "canceladas" => $dashboard->totalCanceladas(),
            "presencas" => $dashboard->totalPresencas(),
            "recebido" => $dashboard->totalReceitas(),
            "aReceber" => $dashboard->totalPendenteFinanceiro()
        ],
        "camisetas" => $dashboard->camisetas(),
        "cidades" => $dashboard->cidades(),
        "ultimos" => $dashboard->ultimasInscricoes(),
        "pendentesFinanceiro" => $dashboard->pagamentosPendentes(),
        "pagamentosStatus" => $dashboard->pagamentosPorStatus(),
        "financeiroMensal" => $dashboard->financeiroMensal(),
        "inscricoesMensal" => $dashboard->inscricoesMensal(),
        "atualizadoEm" => date("Y-m-d H:i:s")
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $erro) {
    error_log(
        "Erro no dashboard: "
        . $erro->getMessage()
        . " | Arquivo: "
        . $erro->getFile()
        . " | Linha: "
        . $erro->getLine()
    );

    http_response_code(500);

    echo json_encode([
        "status" => false,
        "msg" => "Não foi possível carregar os dados do dashboard."
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
