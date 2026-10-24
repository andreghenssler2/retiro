<?php

declare(strict_types=1);

ob_start();
require_once '../../config/settings.php';
Middleware::auth();

use Dompdf\Dompdf;
use Dompdf\Options;

function rpdfEscapar(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function rpdfFormatar(mixed $valor, string $formato): string
{
    if ($valor === null || $valor === '') {
        return '-';
    }

    return match ($formato) {
        'moeda' => 'R$ ' . number_format((float) $valor, 2, ',', '.'),
        'inteiro' => number_format((int) $valor, 0, ',', '.'),
        'data' => (($t = strtotime((string) $valor)) !== false ? date('d/m/Y', $t) : (string) $valor),
        'datahora' => (($t = strtotime((string) $valor)) !== false ? date('d/m/Y H:i', $t) : (string) $valor),
        default => (string) $valor,
    };
}

try {
    $relatorio = (new RelatorioGeral($db))->gerar($_GET, true);
} catch (InvalidArgumentException $erro) {
    if (ob_get_length()) ob_end_clean();
    http_response_code(422);
    echo rpdfEscapar($erro->getMessage());
    exit;
} catch (Throwable $erro) {
    error_log('Erro ao gerar PDF dos relatórios: ' . $erro->getMessage());
    if (ob_get_length()) ob_end_clean();
    http_response_code(500);
    echo 'Não foi possível gerar o PDF.';
    exit;
}

if (!class_exists(Dompdf::class)) {
    if (ob_get_length()) ob_end_clean();
    http_response_code(500);
    echo 'Dompdf não está instalado.';
    exit;
}

$cards = is_array($relatorio['cards'] ?? null) ? $relatorio['cards'] : [];
$colunas = is_array($relatorio['colunas'] ?? null) ? $relatorio['colunas'] : [];
$linhas = is_array($relatorio['linhas'] ?? null) ? $relatorio['linhas'] : [];
$grafico = is_array($relatorio['grafico'] ?? null) ? $relatorio['grafico'] : [];
$maximo = 0.0;
foreach (($grafico['valores'] ?? []) as $v) $maximo = max($maximo, (float) $v);

ob_start();
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<style>
@page{margin:24px 26px 30px}*{box-sizing:border-box}body{font-family:DejaVu Sans,sans-serif;font-size:8px;color:#1f2937;margin:0}h1,h2,p{margin:0}.cab{border-bottom:2px solid #0d6efd;padding-bottom:9px;margin-bottom:10px}.cab h1{font-size:18px}.cab p{color:#64748b;margin-top:3px}.meta{width:100%;margin-top:7px;border-collapse:collapse}.meta td{padding:2px 0}.rot{color:#64748b;font-weight:bold;width:75px}.cards{width:100%;border-collapse:separate;border-spacing:5px;margin:0 -5px 10px}.cards td{width:25%;border:1px solid #dbe3ed;padding:8px}.card-r{color:#64748b;text-transform:uppercase;font-size:7px;font-weight:bold}.card-v{font-size:13px;font-weight:bold;margin-top:3px}.sec{margin-top:10px}.sec h2{font-size:11px;border-bottom:1px solid #dbe3ed;padding-bottom:4px;margin-bottom:6px}.dados{width:100%;border-collapse:collapse}.dados th{background:#eef3f8;color:#475569;text-align:left;border:1px solid #dbe3ed;padding:4px;font-size:7px}.dados td{border:1px solid #dbe3ed;padding:4px;vertical-align:top}.direita{text-align:right!important}.centro{text-align:center!important}.status{font-weight:bold}.bar-table{width:100%;border-collapse:collapse}.bar-table td{padding:2px}.bar-label{width:110px}.bar-value{width:80px;text-align:right}.track{height:8px;background:#edf2f7}.bar{height:8px;background:#0d6efd}.obs{font-size:7px;color:#64748b;margin-top:8px}.rodape{position:fixed;bottom:-17px;left:0;right:0;border-top:1px solid #dbe3ed;padding-top:3px;color:#64748b;font-size:7px}.pagina:after{content:counter(page)}
</style>
</head>
<body>
<div class="cab">
    <h1><?= rpdfEscapar($relatorio['titulo'] ?? 'Relatório') ?></h1>
    <p><?= rpdfEscapar($relatorio['descricao'] ?? '') ?></p>
    <table class="meta"><tr><td class="rot">Período:</td><td><?= rpdfEscapar($relatorio['periodo'] ?? '') ?></td><td class="rot">Emitido:</td><td><?= date('d/m/Y H:i:s') ?></td></tr><tr><td class="rot">Registros:</td><td><?= (int) ($relatorio['total'] ?? 0) ?></td><td class="rot">Usuário:</td><td><?= rpdfEscapar($_SESSION['user']['nome'] ?? 'Administrador') ?></td></tr></table>
</div>
<table class="cards"><tr>
<?php foreach (array_slice($cards, 0, 4) as $card): ?>
<td><div class="card-r"><?= rpdfEscapar($card['rotulo'] ?? '') ?></div><div class="card-v"><?= rpdfEscapar(rpdfFormatar($card['valor'] ?? 0, (string) ($card['formato'] ?? 'texto'))) ?></div></td>
<?php endforeach; ?>
</tr></table>
<?php if (!empty($grafico['labels'])): ?>
<div class="sec"><h2><?= rpdfEscapar($grafico['titulo'] ?? 'Resumo') ?></h2><table class="bar-table">
<?php foreach ($grafico['labels'] as $idx => $label): $v=(float)($grafico['valores'][$idx]??0); $w=$maximo>0?($v/$maximo)*100:0; ?>
<tr><td class="bar-label"><?= rpdfEscapar($label) ?></td><td><div class="track"><div class="bar" style="width:<?= number_format($w,2,'.','') ?>%"></div></div></td><td class="bar-value"><?= rpdfEscapar(rpdfFormatar($v,(string)($grafico['formato']??'inteiro'))) ?></td></tr>
<?php endforeach; ?>
</table></div>
<?php endif; ?>
<div class="sec"><h2>Dados do relatório</h2><table class="dados"><thead><tr>
<?php foreach ($colunas as $coluna): ?><th class="<?= rpdfEscapar($coluna['classe'] ?? '') ?>"><?= rpdfEscapar($coluna['rotulo'] ?? '') ?></th><?php endforeach; ?>
</tr></thead><tbody>
<?php if (!$linhas): ?><tr><td colspan="<?= max(1,count($colunas)) ?>" class="centro">Nenhum registro encontrado.</td></tr><?php else: foreach ($linhas as $linha): ?><tr>
<?php foreach ($colunas as $coluna): $chave=(string)($coluna['chave']??''); ?><td class="<?= rpdfEscapar($coluna['classe'] ?? '') ?>"><?= rpdfEscapar(rpdfFormatar($linha[$chave]??null,(string)($coluna['formato']??'texto'))) ?></td><?php endforeach; ?>
</tr><?php endforeach; endif; ?>
</tbody></table></div>
<?php if (!empty($relatorio['observacao'])): ?><p class="obs"><?= rpdfEscapar($relatorio['observacao']) ?></p><?php endif; ?>
<div class="rodape">Sistema de Eventos — Relatório administrativo <span style="float:right">Página <span class="pagina"></span></span></div>
</body></html>
<?php
$html = ob_get_clean();
$options = new Options();
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'DejaVu Sans');
$dompdf = new Dompdf($options);
$dompdf->loadHtml($html, 'UTF-8');
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
if (ob_get_length()) ob_end_clean();
$nome = 'relatorio-' . preg_replace('/[^a-z0-9-]+/i', '-', (string) ($_GET['tipo'] ?? 'geral')) . '-' . date('Ymd-His') . '.pdf';
$dompdf->stream($nome, ['Attachment' => true]);
