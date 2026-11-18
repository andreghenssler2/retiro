<?php
declare(strict_types=1);
// Retiro - correção localizada da paginação da autorização presencial.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente terminal.'); }
const RET_PATCH = 'RET_AUTORIZACAO_2_POR_FOLHA_V1';
$raiz = __DIR__;
$arquivo = $raiz . '/admin/relatorios/evento-exportar.php';
$modo = $argv[1] ?? '';
function falha(string $m): never { fwrite(STDERR, '[ERRO] '.$m.PHP_EOL); exit(1); }
function ok(string $m): void { echo '[OK] '.$m.PHP_EOL; }
function conferirBloco(string $codigo, string $seletor): string {
    $regex = '~(?m)^\h*' . preg_quote($seletor, '~') . '\h*\{([^{}]*)\}~';
    if (preg_match_all($regex, $codigo, $m) !== 1) {
        throw new RuntimeException('Bloco CSS ausente ou duplicado: '.$seletor);
    }
    return $m[1][0];
}
function propriedade(string $bloco, string $nome, string $valor): bool {
    return preg_match('~\b'.preg_quote($nome,'~').'\s*:\s*'.preg_quote($valor,'~').'\s*;~', $bloco) === 1;
}
function testarPDF(string $raiz, string $codigo): void {
    $autoload = $raiz.'/lib/vendor/autoload.php';
    if (!is_file($autoload)) throw new RuntimeException('Dompdf não encontrado em lib/vendor.');
    if (!extension_loaded('dom') || !extension_loaded('mbstring')) throw new RuntimeException('PHP precisa das extensões dom e mbstring para testar o Dompdf.');
    require_once $autoload;
    if (!class_exists(\Dompdf\Dompdf::class)) throw new RuntimeException('Dompdf não disponível.');
    if (!preg_match('~<style>(.*?)</style>~s', $codigo, $m)) throw new RuntimeException('Não encontrei o CSS do PDF.');
    $css = $m[1];
    foreach ([1, 2, 3, 12] as $total) {
        $html = '<!doctype html><html><head><meta charset="UTF-8"><style>'.$css.'</style></head><body>';
        for ($i=0; $i<$total; $i+=2) {
            $html .= '<div class="termo-pagina">';
            for ($j=$i; $j<min($total,$i+2); $j++) {
                // Dados fictícios; testa inclusive nomes compridos e dois telefones.
                $nome = 'Participante de Teste com Nome Completo da Silva Oliveira '.($j+1);
                $responsavel = 'Responsável de Teste com Nome Completo dos Santos Pereira';
                $html .= '<div class="termo"><div class="termo-titulo">AUTORIZAÇÃO</div>'
                    .'<p class="termo-paragrafo">Eu, <span class="termo-campo">'.$responsavel.'</span>,</p>'
                    .'<p class="termo-paragrafo">responsável pelo(a) jovem <span class="termo-campo">'.$nome.'</span>, <strong>AUTORIZO</strong> o(a) jovem <span class="termo-campo">'.$nome.'</span> a participar do(a) <span class="termo-campo">Acampamento Paroquial 2026</span>, que acontecerá nos dias <span class="termo-campo">21/11/2026</span> e <span class="termo-campo">22/11/2026</span> no(a) <span class="termo-campo">São Francisco de Paula</span>.</p>'
                    .'<p class="termo-paragrafo">Declaro estar ciente e de acordo com sua participação no referido evento.</p>'
                    .'<div class="termo-assinatura"><div class="termo-assinatura-linha">Assinatura do(a) responsável</div></div>'
                    .'<div class="termo-data"><strong>Data:</strong> 15/11/2026</div><div class="termo-ajuda">Contato do responsável: 00000000000 · 00000000000</div></div>';
                if ($j===$i && $j+1<$total) $html .= '<div class="termo-corte" aria-hidden="true"><div class="termo-corte-linha"></div><span class="termo-corte-texto">CORTE AQUI</span></div>';
            }
            $html .= '</div>';
        }
        $html .= '</body></html>';
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $pdf = new \Dompdf\Dompdf($options);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4', 'portrait');
        $pdf->render();
        $paginas = $pdf->getCanvas()->get_page_count();
        $esperado = (int) ceil($total / 2);
        if ($paginas !== $esperado) throw new RuntimeException("{$total} termos: {$paginas} páginas; esperado {$esperado}.");
        ok("{$total} termos: {$paginas} página(s) no Dompdf.");
        unset($pdf);
    }
}
try {
    if (!in_array($modo, ['', '--verificar', '--testar-pdf'], true) || $argc > 2) falha('Uso: php '.basename(__FILE__).' [--verificar|--testar-pdf]');
    if (!is_file($arquivo) || is_link($arquivo) || !is_file($raiz.'/config/settings.php')) falha('Coloque o instalador na pasta principal do Retiro.');
    $codigo = file_get_contents($arquivo);
    if ($codigo === false) falha('Não foi possível ler o exportador.');
    // Valida a estrutura conhecida sem tocar em modos de exportação, dados ou permissões.
    foreach (['array_chunk($inscricoesAutorizacao, 2)', 'class="termo-pagina"', 'class="termo-corte"', '"autorizacao_pdf"'] as $s) {
        if (!str_contains($codigo,$s)) falha('Layout de autorização incompatível: '.$s);
    }
    $page = conferirBloco($codigo,'@page');
    $termo = conferirBloco($codigo,'.termo');
    $corte = conferirBloco($codigo,'.termo-corte');
    $pagina = conferirBloco($codigo,'.termo-pagina');
    if (!propriedade($page,'margin','10mm') || !propriedade($termo,'padding','6mm') || !propriedade($termo,'border','1px solid #d1d5db') || !propriedade($corte,'height','8mm') || !propriedade($corte,'padding-top','3mm') || !propriedade($pagina,'page-break-after','always')) {
        falha('Medidas do layout foram alteradas. Nenhum arquivo modificado.');
    }
    $ja = propriedade($termo,'height','119mm') && str_contains($codigo, RET_PATCH);
    if (!$ja && !propriedade($termo,'height','131mm')) falha('Altura inesperada. Nenhum arquivo modificado.');
    if ($modo==='--testar-pdf') {
        if (!$ja) falha('Aplique a atualização antes de testar o PDF.');
        testarPDF($raiz,$codigo); exit(0);
    }
    if ($ja) { ok('Correção já instalada. Nenhum arquivo modificado.'); exit(0); }
    $novo = preg_replace_callback('~(?m)^\h*\.termo\h*\{[^{}]*\}~', static function (array $m): string {
        return preg_replace('~\bheight\h*:\h*131mm\h*;~', 'height: 119mm; /* '.RET_PATCH.' */', $m[0], 1);
    }, $codigo, 1, $n);
    if ($n!==1 || $novo===null || $novo===$codigo) falha('Não foi possível preparar a alteração.');
    $novo = str_replace('Dois termos de 131mm e linha de corte de 8mm ocupam 270mm.', 'Dompdf soma o padding e as bordas à altura: dois termos de 119mm + padding/bordas e corte cabem em 277mm úteis.', $novo);
    if (!function_exists('exec')) falha('exec() indisponível; não é possível conferir a sintaxe.');
    $tmp = tempnam(dirname($arquivo), '.ret-aut-');
    if ($tmp===false) falha('Não foi possível criar arquivo temporário.');
    try {
        if (file_put_contents($tmp,$novo)!==strlen($novo)) throw new RuntimeException('Falha ao escrever a prévia.');
        exec(escapeshellarg(PHP_BINARY).' -l '.escapeshellarg($tmp).' 2>&1',$saida,$rc);
        if ($rc!==0) throw new RuntimeException('Sintaxe inválida: '.implode(' ', $saida));
        ok('Compatibilidade e sintaxe PHP verificadas.');
        if ($modo==='--verificar') { ok('Prévia: somente admin/relatorios/evento-exportar.php será alterado.'); }
        else {
            if (file_get_contents($arquivo)!==$codigo) throw new RuntimeException('O arquivo mudou durante a verificação; execute novamente.');
            $dirBackup = dirname($raiz).'/backups-retiro';
            if (!is_dir($dirBackup) && !mkdir($dirBackup,0700,true)) throw new RuntimeException('Não foi possível criar pasta de backup.');
            $backup = $dirBackup.'/evento-exportar-autorizacao-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.php.bak';
            if (!copy($arquivo,$backup) || hash_file('sha256',$backup)!==hash('sha256',$codigo)) throw new RuntimeException('Backup falhou.');
            @chmod($backup,0600);
            $permissoes = fileperms($arquivo);
            if ($permissoes!==false) @chmod($tmp,$permissoes & 0777);
            if (!rename($tmp,$arquivo)) throw new RuntimeException('Não foi possível substituir o arquivo; backup preservado.');
            ok('Backup: '.$backup);
            ok('Correção instalada. Gere novamente o PDF — Autorização presencial.');
        }
    } finally { if (is_file($tmp)) @unlink($tmp); }
} catch (Throwable $e) { falha($e->getMessage()); }
