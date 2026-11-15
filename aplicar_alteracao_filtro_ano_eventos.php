<?php
declare(strict_types=1);
// Retiro - filtro anual na listagem administrativa de eventos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Somente terminal.'); }
const MARCA_ANO = 'RETIRO_FILTRO_ANO_EVENTOS_V1';
function trocaAno(string $texto, string $antigo, string $novo, string $rotulo): string {
    if (substr_count($texto, $antigo) !== 1) { throw new RuntimeException("Trecho incompatível: $rotulo. Nenhum arquivo será alterado na prévia."); }
    return str_replace($antigo, $novo, $texto);
}
function preparaAno(string $texto, string $tipo): string {
    $crlf = str_contains($texto, "\r\n");
    $s = str_replace("\r\n", "\n", $texto);
    if (str_contains($s, MARCA_ANO)) return $texto;
    $t = static function(string $antigo, string $novo, string $label) use (&$s, $tipo): void { $s = trocaAno($s, $antigo, $novo, "$tipo / $label"); };
    if ($tipo === 'modelo') {
        $t('    public function listarPaginado(', <<<'NEW'
    // RETIRO_FILTRO_ANO_EVENTOS_V1
    public static function normalizarAnoFiltro(mixed $entrada): ?int
    {
        if ($entrada === '') return null; // todos os anos
        $atual = (int) date('Y');
        if (!is_string($entrada) && !is_int($entrada)) return $atual;
        $texto = (string) $entrada;
        return preg_match('/^[0-9]{4}$/D', $texto) && (int) $texto >= 1000 && (int) $texto <= 9998
            ? (int) $texto : $atual;
    }

    public function listarAnosFiltro(): array
    {
        $anos = $this->db->query("SELECT DISTINCT YEAR(data_inicio) AS ano FROM eventos WHERE data_inicio IS NOT NULL AND YEAR(data_inicio) BETWEEN 1000 AND 9998 ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN);
        $anos = array_map('intval', $anos);
        $anos[] = (int) date('Y');
        $anos = array_values(array_unique($anos));
        rsort($anos, SORT_NUMERIC);
        return $anos;
    }

    public function listarPaginado(
NEW, 'métodos de ano');
        $t("        int \$limite = 10\n\n    ): array {", "        int \$limite = 10,\n\n        ?int \$ano = null\n\n    ): array {", 'parâmetro opcional');
        // Âncora limitada ao método de paginação para evitar modificar outras consultas.
        $start = strpos($s, '    public function listarPaginado(');
        $antes = substr($s, 0, $start); $depois = substr($s, $start);
        $fim = strpos($depois, '    public function ', 10);
        $resto = $fim === false ? '' : substr($depois, $fim);
        $metodo = $fim === false ? $depois : substr($depois, 0, $fim);
        $metodo = trocaAno($metodo, "        \$sqlWhere = '';", <<<'NEW'
        // Intervalo de datas: mantém a consulta compatível com índices.
        if ($ano !== null) {
            if ($ano < 1000 || $ano > 9998) throw new InvalidArgumentException('Ano inválido.');
            $where[] = 'data_inicio >= ? AND data_inicio < ?';
            $params[] = sprintf('%04d-01-01', $ano);
            $params[] = sprintf('%04d-01-01', $ano + 1);
        }

        $sqlWhere = '';
NEW, 'condição por ano');
        $s = $antes . $metodo . $resto;
    } elseif ($tipo === 'pagina' || $tipo === 'ajax') {
        $nova = <<<'NEW'
$status = trim($_GET["status"] ?? "");
// RETIRO_FILTRO_ANO_EVENTOS_V1
$ano = Evento::normalizarAnoFiltro($_GET['ano'] ?? null);
NEW;
        if ($tipo === 'pagina') $nova .= <<<'NEW'

$anosFiltro = $evento->listarAnosFiltro();
if ($ano !== null && !in_array($ano, $anosFiltro, true)) {
    $anosFiltro[] = $ano;
    rsort($anosFiltro, SORT_NUMERIC);
}
NEW;
        $t('$status = trim($_GET["status"] ?? "");', $nova, 'leitura dos filtros');
        $old = $tipo === 'pagina' ? "    \$paginaA,\n\n    \$limite\n\n);" : "    \$paginaAtual,\n    \$limite\n);";
        $new = $tipo === 'pagina' ? "    \$paginaA,\n\n    \$limite,\n\n    \$ano\n\n);" : "    \$paginaAtual,\n    \$limite,\n    \$ano\n);";
        $t($old,$new,'consulta paginada');
        $pag = $tipo === 'pagina' ? '$paginaA' : '$paginaAtual';
        $t('$totalPaginas = max(1, ceil($total / $limite));', '$totalPaginas = max(1, (int) ceil($total / $limite));' . "\nif ($pag > \$totalPaginas) {\n    $pag = \$totalPaginas;\n    \$lista = \$evento->listarPaginado(\$pesquisa, \$tipo, \$status, 'data_inicio', 'DESC', $pag, \$limite, \$ano);\n    \$eventos = \$lista['dados'];\n    \$total = \$lista['total'];\n    \$totalPaginas = max(1, (int) ceil(\$total / \$limite));\n}", 'limite da página');
        if ($tipo === 'ajax') {
            $t('<div class="table-responsive">', <<<'NEW'
<div id="metaFiltroAnoEventos" hidden data-total="<?= (int) $total ?>" data-pagina="<?= (int) $paginaAtual ?>" data-registros="<?= count($eventos) ?>" data-paginas="<?= (int) $totalPaginas ?>"></div>
<div class="table-responsive">
NEW, 'metadados de contagem');
        } else {
            $t('<form method="GET" id="formFiltroEventos">' . "\n\n                <div class=\"row\">", '<form method="GET" id="formFiltroEventos">' . "\n\n                <div class=\"row g-3\">", 'espaçamento');
            $t('                    <div class="col-md-4">' . "\n\n                        <label class=\"form-label\">\n\n                            Pesquisa", '                    <div class="col-md-4">' . "\n\n                        <label class=\"form-label\">\n\n                            Pesquisa", 'campo pesquisa');
            $t('                    <div class="col-md-3">' . "\n\n                        <label class=\"form-label\">\n\n                            Tipo", <<<'NEW'
                    <div class="col-md-3">
                        <label class="form-label" for="ano">Ano</label>
                        <select name="ano" id="ano" class="form-select">
                            <option value="" <?= $ano === null ? 'selected' : '' ?>>Todos os anos</option>
                            <?php foreach ($anosFiltro as $anoOpcao): ?>
                                <option value="<?= (int) $anoOpcao ?>" <?= $ano === $anoOpcao ? 'selected' : '' ?>><?= (int) $anoOpcao ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-3">

                        <label class="form-label">

                            Tipo
NEW, 'seletor Ano');
            foreach (['$total'=>'eventosTotalFiltro','$paginaA'=>'eventosPaginaFiltro','count($eventos)'=>'eventosRegistrosFiltro','$totalPaginas'=>'eventosPaginasFiltro'] as $expressao=>$id) {
                $t("<h2>\n\n                        <?= $expressao ?>", "<h2 id=\"$id\">\n\n                        <?= $expressao ?>", 'indicador '.$id);
            }
            $t('<div class="mt-4">' . "\n\n            <nav>", '<div class="mt-4" id="paginacaoInicialEventos">' . "\n\n            <nav>", 'paginação inicial');
            $t('&status=<?= urlencode($status) ?>">', '&status=<?= urlencode($status) ?>&amp;ano=<?= urlencode((string) $ano) ?>">', 'link de página');
        }
    } elseif ($tipo === 'js') {
        $t('    let tempo;' . "\n\n    carregarEventos();", <<<'NEW'
    let tempo;
    // RETIRO_FILTRO_ANO_EVENTOS_V1
    let requisicaoEventos = null;
    let sequenciaEventos = 0;
    carregarEventos(Number($('#eventosPaginaFiltro').text()) || 1);
NEW, 'inicialização');
        $t('    function carregarEventos(pagina = 1) {' . "\n\n        $.ajax({", <<<'NEW'
    function carregarEventos(pagina = 1) {
        const sequencia = ++sequenciaEventos;
        if (requisicaoEventos) requisicaoEventos.abort();
        requisicaoEventos = $.ajax({
NEW, 'controle de requisições');
        $t('                status: $("#status").val()', '                status: $("#status").val(),' . "\n                ano: $(\"#ano\").val()", 'parâmetro AJAX ano');
        // O PHP de uma página sem JS ainda preserva os filtros pelo href.
        $t('            success: function (html) {' . "\n                $(\"#listatabela\").html('');\n                $(\"#listaEventos\").html(html);", <<<'NEW'
            success: function (html) {
                if (sequencia !== sequenciaEventos) return;
                $("#listatabela").empty().hide();
                $("#paginacaoInicialEventos").hide();
                $("#listaEventos").html(html);
                const meta = $("#metaFiltroAnoEventos");
                if (meta.length) {
                    $("#eventosTotalFiltro").text(meta.attr('data-total'));
                    $("#eventosPaginaFiltro").text(meta.attr('data-pagina'));
                    $("#eventosRegistrosFiltro").text(meta.attr('data-registros'));
                    $("#eventosPaginasFiltro").text(meta.attr('data-paginas'));
                    if (window.history && window.history.replaceState) {
                        const url = new URL(window.location.href);
                        for (const campo of ['pesquisa', 'tipo', 'status', 'ano']) url.searchParams.set(campo, $('#' + campo).val() || '');
                        url.searchParams.set('pagina', meta.attr('data-pagina'));
                        window.history.replaceState(null, '', url.toString());
                    }
                }
NEW, 'atualização da tabela e indicadores');
        $t('    $("#tipo,#status").on("change", function () {', '    $("#tipo,#status,#ano").on("change", function () {' . "\n        clearTimeout(tempo);", 'evento de alteração');
    } else throw new RuntimeException('Tipo inválido.');
    return $crlf ? str_replace("\n", "\r\n", $s) : $s;
}
function validaSintaxeAno(string $arquivo, bool $php): void {
    if (!$php) return;
    if (!function_exists('exec')) throw new RuntimeException('exec indisponível para validar sintaxe PHP.');
    $out = []; $code = 1;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($arquivo) . ' 2>&1', $out, $code);
    if ($code !== 0) throw new RuntimeException(implode("\n", $out));
}
function mainAno(array $argv): int {
    $args = array_slice($argv,1);
    if (array_diff($args, ['--verificar'])) { fwrite(STDERR, "Uso: php aplicar_alteracao_filtro_ano_eventos.php [--verificar]\n"); return 2; }
    $previa = in_array('--verificar',$args,true);
    $raiz = realpath(__DIR__);
    $mapa = ['mod/auth/Evento.php'=>'modelo','admin/event/eventos.php'=>'pagina','admin/event/ajax/eventos-lista.php'=>'ajax','theme/js/admin/event/js/eventos.js'=>'js'];
    $temp = []; $orig = []; $novos = []; $mudados = []; $backup = null;
    try {
        if (!is_file($raiz.'/config/settings.php')) throw new RuntimeException('Coloque o instalador na pasta principal do Retiro.');
        echo "RETIRO - FILTRO DE EVENTOS POR ANO\n";
        foreach ($mapa as $rel=>$tipo) {
            $p = $raiz.'/'.$rel;
            if (!is_file($p) || is_link($p)) throw new RuntimeException('Arquivo ausente ou link simbólico: '.$rel);
            $orig[$rel] = file_get_contents($p);
            if ($orig[$rel] === false) throw new RuntimeException('Não foi possível ler '.$rel);
            $novo = preparaAno($orig[$rel], $tipo);
            if ($novo === $orig[$rel]) { echo "[JÁ ATUALIZADO] $rel\n"; continue; }
            $tmp = tempnam(sys_get_temp_dir(),'ret_ano_');
            if ($tmp === false) throw new RuntimeException('Falha ao criar arquivo temporário.');
            $temp[] = $tmp;
            if (file_put_contents($tmp,$novo) !== strlen($novo)) throw new RuntimeException('Falha ao gravar teste.');
            validaSintaxeAno($tmp,$tipo !== 'js');
            $novos[$rel] = $novo;
            echo "[OK] Compatível: $rel\n";
        }
        if ($novos === []) { echo "[OK] Atualização já instalada.\n"; return 0; }
        if ($previa) { echo "[PRÉVIA] Nenhum arquivo do Retiro alterado.\n"; return 0; }
        $backup = dirname($raiz).'/backups-retiro/filtro-ano-'.date('Ymd-His').'-'.bin2hex(random_bytes(4));
        if (!mkdir($backup,0700,true)) throw new RuntimeException('Não foi possível criar backup fora da raiz do projeto.');
        foreach ($novos as $rel=>$novo) {
            $p = $backup.'/'.$rel;
            if (!is_dir(dirname($p)) && !mkdir(dirname($p),0700,true)) throw new RuntimeException('Falha ao preparar backup.');
            if (file_put_contents($p,$orig[$rel],LOCK_EX) !== strlen($orig[$rel])) throw new RuntimeException('Falha no backup.');
            @chmod($p,0600);
            if (hash_file('sha256',$p) !== hash('sha256',$orig[$rel])) throw new RuntimeException('Backup não conferiu.');
        }
        foreach ($novos as $rel=>$novo) {
            $p = $raiz.'/'.$rel;
            if (file_get_contents($p) !== $orig[$rel]) throw new RuntimeException('Arquivo alterado durante a instalação: '.$rel);
            $tmp = tempnam(dirname($p),'.ano-');
            if ($tmp === false) throw new RuntimeException('Falha ao criar candidato: '.$rel);
            $temp[] = $tmp;
            if (file_put_contents($tmp,$novo,LOCK_EX) !== strlen($novo)) throw new RuntimeException('Falha ao preparar '.$rel);
            @chmod($tmp, fileperms($p) & 0777);
            if (!rename($tmp,$p)) throw new RuntimeException('Falha ao substituir '.$rel);
            $mudados[] = $rel;
            echo "[APLICADO] $rel\n";
        }
        echo "[OK] Filtro anual instalado. Backup: $backup\n";
        return 0;
    } catch (Throwable $e) {
        fwrite(STDERR,"[ERRO] ".$e->getMessage()."\n");
        foreach (array_reverse($mudados) as $rel) {
            $p = $raiz.'/'.$rel;
            if (file_get_contents($p) !== $novos[$rel]) { fwrite(STDERR,"[ATENÇÃO] Arquivo alterado externamente; restaure manualmente $rel\n"); continue; }
            if (file_put_contents($p,$orig[$rel],LOCK_EX) !== strlen($orig[$rel])) fwrite(STDERR,"[ATENÇÃO] Falha na restauração: $rel\n");
            else echo "[RESTAURADO] $rel\n";
        }
        if ($backup !== null) echo "Backup: $backup\n";
        return 1;
    } finally { foreach ($temp as $p) if (is_file($p)) @unlink($p); }
}
exit(mainAno($argv));
