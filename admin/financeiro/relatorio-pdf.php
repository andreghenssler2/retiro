<?php

declare(strict_types=1);

ob_start();

require_once '../../config/settings.php';

Middleware::auth();

use Dompdf\Dompdf;
use Dompdf\Options;

$extensoesObrigatorias = ['mbstring', 'dom'];
$extensoesAusentes = array_values(array_filter(
    $extensoesObrigatorias,
    static fn(string $extensao): bool => !extension_loaded($extensao)
));

if ($extensoesAusentes) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo 'Para gerar o PDF, habilite no PHP as extensões: '
        . htmlspecialchars(implode(', ', $extensoesAusentes), ENT_QUOTES, 'UTF-8')
        . '. No XAMPP, habilite essas extensões no php.ini conforme a sua versão do PHP e reinicie o Apache.';
    exit;
}

function pdfEscapar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function pdfMoeda(mixed $valor): string
{
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
}

function pdfData(mixed $valor): string
{
    $texto = trim((string) $valor);
    if ($texto === '') {
        return '-';
    }

    $timestamp = strtotime($texto);
    return $timestamp !== false ? date('d/m/Y', $timestamp) : $texto;
}

function pdfClasseStatus(string $status): string
{
    return [
        'Pago' => 'status-pago',
        'Pendente' => 'status-pendente',
        'Vencido' => 'status-vencido',
        'Cancelado' => 'status-cancelado',
        'Estornado' => 'status-estornado'
    ][$status] ?? 'status-outro';
}

$dataInicio = trim((string) ($_GET['dataInicio'] ?? ''));
$dataFim = trim((string) ($_GET['dataFim'] ?? ''));
$idEvento = filter_var(
    $_GET['idEvento'] ?? 0,
    FILTER_VALIDATE_INT,
    ['options' => ['default' => 0, 'min_range' => 0]]
);

try {
    $relatorio = (new Financeiro($db))->relatorio(
        $dataInicio,
        $dataFim,
        (int) $idEvento
    );
} catch (InvalidArgumentException $erro) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code(422);
    echo pdfEscapar($erro->getMessage());
    exit;
} catch (Throwable $erro) {
    error_log('Erro ao gerar PDF financeiro: ' . $erro->getMessage());
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo 'Não foi possível gerar o relatório financeiro.';
    exit;
}

if (!class_exists(Dompdf::class)) {
    if (ob_get_length()) {
        ob_end_clean();
    }
    http_response_code(500);
    echo 'Dompdf não está instalado. Execute composer install na pasta lib.';
    exit;
}

$resumo = $relatorio['resumo'] ?? [];
$periodo = $relatorio['periodo'] ?? [];
$serie = is_array($relatorio['serie'] ?? null) ? $relatorio['serie'] : [];
$formas = is_array($relatorio['formas'] ?? null) ? $relatorio['formas'] : [];
$eventos = is_array($relatorio['eventos'] ?? null) ? $relatorio['eventos'] : [];
$movimentos = is_array($relatorio['movimentos'] ?? null) ? $relatorio['movimentos'] : [];

$eventoSelecionado = 'Todos os eventos';
if ((int) $idEvento > 0) {
    foreach ($eventos as $itemEvento) {
        if ((int) ($itemEvento['idEvento'] ?? 0) === (int) $idEvento) {
            $eventoSelecionado = (string) ($itemEvento['evento'] ?? $eventoSelecionado);
            break;
        }
    }
}

$canceladoEstornado = (float) ($resumo['cancelado'] ?? 0) + (float) ($resumo['estornado'] ?? 0);
$maiorBarra = 0.0;
foreach ($serie as $itemSerie) {
    $maiorBarra = max(
        $maiorBarra,
        (float) ($itemSerie['recebido'] ?? 0),
        (float) ($itemSerie['pendente'] ?? 0)
    );
}

ob_start();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px 28px 28px; }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: DejaVu Sans, sans-serif;
            font-size: 9px;
            color: #1f2937;
        }
        h1, h2, h3, p { margin: 0; }
        .cabecalho {
            border-bottom: 2px solid #0d6efd;
            padding-bottom: 10px;
            margin-bottom: 12px;
        }
        .cabecalho h1 { font-size: 20px; color: #0f172a; }
        .cabecalho p { margin-top: 4px; color: #64748b; }
        .meta {
            width: 100%;
            margin-top: 9px;
            border-collapse: collapse;
        }
        .meta td { padding: 3px 0; vertical-align: top; }
        .meta .rotulo { width: 85px; color: #64748b; font-weight: bold; }
        .cards { width: 100%; border-collapse: separate; border-spacing: 6px; margin: 0 -6px 12px; }
        .cards td {
            width: 25%;
            border: 1px solid #dbe3ed;
            border-radius: 6px;
            padding: 9px;
            vertical-align: top;
        }
        .card-label { color: #64748b; font-size: 8px; text-transform: uppercase; font-weight: bold; }
        .card-valor { font-size: 14px; font-weight: bold; margin-top: 4px; }
        .verde { color: #198754; }
        .amarelo { color: #9a6700; }
        .vermelho { color: #dc3545; }
        .secao { margin-top: 12px; page-break-inside: avoid; }
        .secao h2 {
            font-size: 12px;
            color: #0f172a;
            border-bottom: 1px solid #dbe3ed;
            padding-bottom: 5px;
            margin-bottom: 7px;
        }
        table.dados { width: 100%; border-collapse: collapse; }
        table.dados th {
            background: #eef3f8;
            color: #475569;
            border: 1px solid #dbe3ed;
            padding: 5px;
            text-align: left;
            font-size: 8px;
        }
        table.dados td {
            border: 1px solid #dbe3ed;
            padding: 5px;
            vertical-align: top;
        }
        .direita { text-align: right !important; }
        .centro { text-align: center !important; }
        .nowrap { white-space: nowrap; }
        .grafico { width: 100%; border-collapse: collapse; }
        .grafico td { padding: 2px 3px; vertical-align: middle; }
        .grafico .rotulo { width: 50px; color: #475569; white-space: nowrap; }
        .grafico .tipo { width: 55px; color: #64748b; }
        .grafico .valor { width: 75px; text-align: right; white-space: nowrap; }
        .trilho { width: 100%; height: 8px; background: #edf2f7; }
        .barra-recebido { height: 8px; background: #198754; }
        .barra-pendente { height: 8px; background: #ffc107; }
        .status {
            display: inline-block;
            padding: 2px 5px;
            border-radius: 3px;
            color: #fff;
            font-size: 7px;
            font-weight: bold;
        }
        .status-pago { background: #198754; }
        .status-pendente { background: #c58a00; }
        .status-vencido { background: #b42318; }
        .status-cancelado { background: #dc3545; }
        .status-estornado { background: #212529; }
        .status-outro { background: #6c757d; }
        .rodape {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -18px;
            color: #64748b;
            font-size: 7px;
            border-top: 1px solid #dbe3ed;
            padding-top: 4px;
        }
        .rodape .pagina:after { content: counter(page); }
        .quebra { page-break-before: always; }
    </style>
</head>
<body>
    <div class="cabecalho">
        <h1>Relatório financeiro</h1>
        <p>Sistema de Eventos - IECLB</p>
        <table class="meta">
            <tr>
                <td class="rotulo">Período:</td>
                <td><?= pdfEscapar($periodo['inicioFormatado'] ?? '') ?> até <?= pdfEscapar($periodo['fimFormatado'] ?? '') ?></td>
                <td class="rotulo">Evento:</td>
                <td><?= pdfEscapar($eventoSelecionado) ?></td>
            </tr>
            <tr>
                <td class="rotulo">Emitido em:</td>
                <td><?= date('d/m/Y H:i:s') ?></td>
                <td class="rotulo">Registros:</td>
                <td><?= (int) ($resumo['quantidade'] ?? 0) ?></td>
            </tr>
        </table>
    </div>

    <table class="cards">
        <tr>
            <td>
                <div class="card-label">Previsto</div>
                <div class="card-valor"><?= pdfMoeda($resumo['previsto'] ?? 0) ?></div>
            </td>
            <td>
                <div class="card-label">Recebido</div>
                <div class="card-valor verde"><?= pdfMoeda($resumo['recebido'] ?? 0) ?></div>
            </td>
            <td>
                <div class="card-label">Pendente/vencido</div>
                <div class="card-valor amarelo"><?= pdfMoeda($resumo['pendente'] ?? 0) ?></div>
            </td>
            <td>
                <div class="card-label">Cancelado/estornado</div>
                <div class="card-valor vermelho"><?= pdfMoeda($canceladoEstornado) ?></div>
            </td>
        </tr>
    </table>

    <div class="secao">
        <h2>Evolução financeira</h2>
        <?php if (!$serie): ?>
            <p>Nenhum valor encontrado para o período.</p>
        <?php else: ?>
            <table class="grafico">
                <?php foreach ($serie as $item): ?>
                    <?php
                    $valorRecebido = (float) ($item['recebido'] ?? 0);
                    $valorPendente = (float) ($item['pendente'] ?? 0);
                    $larguraRecebido = $maiorBarra > 0 ? ($valorRecebido / $maiorBarra) * 100 : 0;
                    $larguraPendente = $maiorBarra > 0 ? ($valorPendente / $maiorBarra) * 100 : 0;
                    ?>
                    <tr>
                        <td class="rotulo" rowspan="2"><?= pdfEscapar($item['rotulo'] ?? '') ?></td>
                        <td class="tipo">Recebido</td>
                        <td><div class="trilho"><div class="barra-recebido" style="width: <?= number_format($larguraRecebido, 2, '.', '') ?>%;"></div></div></td>
                        <td class="valor"><?= pdfMoeda($valorRecebido) ?></td>
                    </tr>
                    <tr>
                        <td class="tipo">Pendente/vencido</td>
                        <td><div class="trilho"><div class="barra-pendente" style="width: <?= number_format($larguraPendente, 2, '.', '') ?>%;"></div></div></td>
                        <td class="valor"><?= pdfMoeda($valorPendente) ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>

    <div class="secao">
        <h2>Recebimentos por forma de pagamento</h2>
        <table class="dados">
            <thead>
                <tr><th>Forma</th><th class="centro">Quantidade</th><th class="direita">Valor</th></tr>
            </thead>
            <tbody>
                <?php if (!$formas): ?>
                    <tr><td colspan="3" class="centro">Nenhum recebimento no período.</td></tr>
                <?php else: ?>
                    <?php foreach ($formas as $item): ?>
                        <tr>
                            <td><?= pdfEscapar($item['forma'] ?? '') ?></td>
                            <td class="centro"><?= (int) ($item['quantidade'] ?? 0) ?></td>
                            <td class="direita"><?= pdfMoeda($item['valor'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="secao">
        <h2>Resumo por evento</h2>
        <table class="dados">
            <thead>
                <tr>
                    <th>Evento</th>
                    <th class="centro">Quantidade</th>
                    <th class="direita">Recebido</th>
                    <th class="direita">Pendente/vencido</th>
                    <th class="direita">Cancelado/estornado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$eventos): ?>
                    <tr><td colspan="5" class="centro">Nenhum evento encontrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($eventos as $item): ?>
                        <tr>
                            <td><?= pdfEscapar($item['evento'] ?? '') ?></td>
                            <td class="centro"><?= (int) ($item['quantidade'] ?? 0) ?></td>
                            <td class="direita"><?= pdfMoeda($item['recebido'] ?? 0) ?></td>
                            <td class="direita"><?= pdfMoeda($item['pendente'] ?? 0) ?></td>
                            <td class="direita"><?= pdfMoeda($item['cancelado'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="secao quebra">
        <h2>Movimentações do período</h2>
        <table class="dados">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Código</th>
                    <th>Participante</th>
                    <th>Evento</th>
                    <th>Forma</th>
                    <th>Status</th>
                    <th class="direita">Valor</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$movimentos): ?>
                    <tr><td colspan="7" class="centro">Nenhuma movimentação encontrada.</td></tr>
                <?php else: ?>
                    <?php foreach ($movimentos as $item): ?>
                        <?php $status = (string) ($item['status'] ?? ''); ?>
                        <tr>
                            <td class="nowrap"><?= pdfData($item['dataReferencia'] ?? '') ?></td>
                            <td class="nowrap"><?= pdfEscapar($item['codigo'] ?? '') ?></td>
                            <td><?= pdfEscapar($item['participante'] ?? '') ?></td>
                            <td><?= pdfEscapar($item['evento'] ?? '') ?></td>
                            <td><?= pdfEscapar($item['forma'] ?? '') ?></td>
                            <td><span class="status <?= pdfClasseStatus($status) ?>"><?= pdfEscapar($status) ?></span></td>
                            <td class="direita nowrap"><?= pdfMoeda($item['valor'] ?? 0) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="rodape">
        Relatório financeiro - Emitido por <?= pdfEscapar($_SESSION['user']['nome'] ?? 'Administrador') ?>
        <span style="float:right">Página <span class="pagina"></span></span>
    </div>
</body>
</html>
<?php
$html = (string) ob_get_clean();

$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isRemoteEnabled', false);
$options->set('isHtml5ParserEnabled', true);
$options->setChroot(ROOT_PATH);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

if (ob_get_length()) {
    ob_end_clean();
}

$nomeArquivo = sprintf(
    'relatorio-financeiro-%s-a-%s.pdf',
    preg_replace('/[^0-9-]/', '', (string) ($periodo['inicio'] ?? date('Y-m-d'))),
    preg_replace('/[^0-9-]/', '', (string) ($periodo['fim'] ?? date('Y-m-d')))
);

$dompdf->stream($nomeArquivo, ['Attachment' => true]);
exit;
