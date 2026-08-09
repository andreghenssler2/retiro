<?php

declare(strict_types=1);

/**
 * Notifica o responsável sobre novos registros nos arquivos de log do dia.
 *
 * Uso:
 *   php cron/notificar-logs.php
 *
 * O estado de leitura é salvo em:
 *   storage/log-notificacoes/estado.json
 *
 * O arquivo só avança depois que o e-mail é enviado com sucesso.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Este arquivo deve ser executado somente pelo cron/CLI.');
}

date_default_timezone_set('America/Sao_Paulo');
ini_set('default_charset', 'UTF-8');

$raizProjeto = dirname(__DIR__);
$arquivoConfiguracao = $raizProjeto . '/config/log_notificacao.php';

if (!is_file($arquivoConfiguracao)) {
    fwrite(STDERR, "Configuração não encontrada: {$arquivoConfiguracao}" . PHP_EOL);
    exit(1);
}

$config = require $arquivoConfiguracao;

if (!is_array($config)) {
    fwrite(STDERR, 'A configuração de notificação de logs é inválida.' . PHP_EOL);
    exit(1);
}

if (!(bool) ($config['ativo'] ?? false)) {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] Notificação de logs desativada.' . PHP_EOL);
    exit(0);
}

$emailResponsavel = trim((string) ($config['email_responsavel'] ?? ''));
$nomeResponsavel = trim((string) ($config['nome_responsavel'] ?? 'Responsável pelo sistema'));
$nomeSistema = trim((string) ($config['nome_sistema'] ?? 'Sistema'));

if (!filter_var($emailResponsavel, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, 'O e-mail do responsável é inválido.' . PHP_EOL);
    exit(1);
}

$diretorioEstado = $raizProjeto . '/storage/log-notificacoes';

if (
    !is_dir($diretorioEstado)
    && !mkdir($diretorioEstado, 0750, true)
    && !is_dir($diretorioEstado)
) {
    fwrite(STDERR, "Não foi possível criar: {$diretorioEstado}" . PHP_EOL);
    exit(1);
}

$arquivoLock = $diretorioEstado . '/cron.lock';
$lock = fopen($arquivoLock, 'c+');

if ($lock === false) {
    fwrite(STDERR, 'Não foi possível abrir o arquivo de bloqueio do cron.' . PHP_EOL);
    exit(1);
}

if (!flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDOUT, '[' . date('Y-m-d H:i:s') . '] Outra execução já está em andamento.' . PHP_EOL);
    fclose($lock);
    exit(0);
}

register_shutdown_function(
    static function () use ($lock): void {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
);

$arquivoEstado = $diretorioEstado . '/estado.json';
$estado = carregarEstado($arquivoEstado);
$dataAtual = date('Y-m-d');
$maximoBytes = max(1024, (int) ($config['maximo_bytes_por_execucao'] ?? 512000));
$maximoCorpo = max(1024, (int) ($config['maximo_bytes_no_corpo'] ?? 122880));
$somenteArquivosDoDia = (bool) ($config['somente_arquivos_do_dia'] ?? true);

$extensoes = array_values(array_filter(
    array_map(
        static fn ($item): string => strtolower(trim((string) $item)),
        (array) ($config['extensoes'] ?? ['log'])
    ),
    static fn (string $item): bool => $item !== ''
));

$arquivos = localizarArquivos(
    $raizProjeto,
    (array) ($config['diretorios'] ?? []),
    $extensoes,
    $dataAtual,
    $somenteArquivosDoDia
);

if ($arquivos === []) {
    escreverSaida('Nenhum arquivo de log do dia foi encontrado.');
    exit(0);
}

$fragmentos = [];
$novosOffsets = [];
$totalBytes = 0;

foreach ($arquivos as $arquivo) {
    if ($totalBytes >= $maximoBytes) {
        break;
    }

    $chave = normalizarCaminho($arquivo);
    $tamanho = filesize($arquivo);

    if ($tamanho === false || $tamanho <= 0) {
        continue;
    }

    $offsetAnterior = max(
        0,
        (int) ($estado['arquivos'][$chave]['offset'] ?? 0)
    );

    /*
     * Se o arquivo foi recriado ou reduzido, recomeça do início.
     */
    if ($offsetAnterior > $tamanho) {
        $offsetAnterior = 0;
    }

    $disponivel = $tamanho - $offsetAnterior;

    if ($disponivel <= 0) {
        continue;
    }

    $limiteArquivo = min(
        $disponivel,
        $maximoBytes - $totalBytes
    );

    $conteudo = lerTrechoArquivo(
        $arquivo,
        $offsetAnterior,
        $limiteArquivo
    );

    if ($conteudo === '') {
        continue;
    }

    $bytesLidos = strlen($conteudo);
    $totalBytes += $bytesLidos;

    $fragmentos[] = [
        'arquivo' => $arquivo,
        'nome' => basename($arquivo),
        'conteudo' => normalizarUtf8($conteudo),
        'offset_anterior' => $offsetAnterior,
        'offset_novo' => $offsetAnterior + $bytesLidos,
        'tamanho_total' => $tamanho,
    ];

    $novosOffsets[$chave] = [
        'offset' => $offsetAnterior + $bytesLidos,
        'tamanho' => $tamanho,
        'modificado_em' => date('Y-m-d H:i:s', (int) filemtime($arquivo)),
    ];
}

if ($fragmentos === []) {
    escreverSaida('Nenhum registro novo para notificar.');
    exit(0);
}

$conteudoCompleto = montarConteudoCompleto($fragmentos);
$conteudoProtegido = protegerDadosSensiveis($conteudoCompleto);
$contagens = contarNiveis($conteudoProtegido);
$totalRegistros = array_sum($contagens);

if ($totalRegistros <= 0) {
    /*
     * Mantém pelo menos uma ocorrência quando o formato do arquivo não
     * possuir marcador [NÍVEL].
     */
    $totalRegistros = count($fragmentos);
}

$assunto = sprintf(
    '[%s] %d novo%s registro%s de log - %s',
    $nomeSistema,
    $totalRegistros,
    $totalRegistros === 1 ? '' : 's',
    $totalRegistros === 1 ? '' : 's',
    date('d/m/Y H:i')
);

$resumoHtml = montarResumoHtml($contagens);
$conteudoCorpo = cortarUtf8($conteudoProtegido, $maximoCorpo);
$conteudoFoiCortado = strlen($conteudoProtegido) > strlen($conteudoCorpo);

$html = '<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>' . escaparHtml($assunto) . '</title>
</head>
<body style="margin:0;padding:24px;background:#f4f6f9;font-family:Arial,Helvetica,sans-serif;color:#172033">
    <div style="max-width:900px;margin:0 auto;background:#fff;border:1px solid #dfe5ef;border-radius:14px;overflow:hidden">
        <div style="padding:22px;background:#172033;color:#fff">
            <h2 style="margin:0 0 8px;font-size:22px">Novos registros de log</h2>
            <div>' . escaparHtml($nomeSistema) . ' — ' . date('d/m/Y H:i:s') . '</div>
        </div>

        <div style="padding:24px">
            <p style="margin-top:0">
                Foram encontrados <strong>' . $totalRegistros . '</strong>
                novo' . ($totalRegistros === 1 ? '' : 's') . '
                registro' . ($totalRegistros === 1 ? '' : 's') . '
                desde a última notificação enviada com sucesso.
            </p>

            ' . $resumoHtml . '

            <h3 style="margin-top:24px">Conteúdo</h3>

            <pre style="white-space:pre-wrap;word-break:break-word;background:#111827;color:#e5e7eb;padding:16px;border-radius:10px;font-size:12px;line-height:1.5;overflow:auto">'
                . escaparHtml($conteudoCorpo)
                . '</pre>'

            . ($conteudoFoiCortado
                ? '<p style="color:#9a6700"><strong>Atenção:</strong> o conteúdo foi reduzido no corpo do e-mail. O arquivo anexado contém todo o trecho processado nesta execução.</p>'
                : '')

            . '<p style="color:#64748b;font-size:12px;margin-bottom:0">
                Esta mensagem foi gerada automaticamente pelo cron de logs.
                Dados identificados como senha, token ou credencial foram mascarados no e-mail.
            </p>
        </div>
    </div>
</body>
</html>';

$anexos = [];
$arquivoTemporario = null;

if ($conteudoFoiCortado) {
    $arquivoTemporario = $diretorioEstado
        . '/logs-'
        . date('Ymd-His')
        . '-'
        . bin2hex(random_bytes(3))
        . '.txt';

    if (file_put_contents($arquivoTemporario, $conteudoProtegido, LOCK_EX) !== false) {
        @chmod($arquivoTemporario, 0640);

        $anexos[] = [
            'path' => $arquivoTemporario,
            'name' => 'logs-' . date('Y-m-d-H-i-s') . '.txt',
        ];
    }
}

try {
    $arquivoMail = $raizProjeto . '/mod/mail/Mail.php';

    if (!is_file($arquivoMail)) {
        throw new RuntimeException(
            'A classe Mail não foi encontrada em mod/mail/Mail.php.'
        );
    }

    require_once $arquivoMail;

    if (!class_exists('Mail')) {
        throw new RuntimeException('A classe Mail não foi carregada.');
    }

    $mail = new Mail();

    if (method_exists($mail, 'isConfigured') && !$mail->isConfigured()) {
        $motivo = method_exists($mail, 'getLastError')
            ? trim((string) $mail->getLastError())
            : '';

        throw new RuntimeException(
            $motivo !== ''
                ? $motivo
                : 'Servidor SMTP não configurado ou desativado.'
        );
    }

    $enviado = $mail->send(
        $emailResponsavel,
        $nomeResponsavel,
        $assunto,
        $html,
        $anexos
    );

    if (!$enviado) {
        $motivo = method_exists($mail, 'getLastError')
            ? trim((string) $mail->getLastError())
            : '';

        throw new RuntimeException(
            $motivo !== ''
                ? $motivo
                : 'O PHPMailer não informou o motivo da falha.'
        );
    }

    foreach ($novosOffsets as $chave => $dadosOffset) {
        $estado['arquivos'][$chave] = $dadosOffset;
    }

    $estado['ultima_execucao_com_sucesso'] = date('Y-m-d H:i:s');
    $estado['ultimo_email'] = $emailResponsavel;

    salvarEstado($arquivoEstado, $estado);

    escreverSaida(
        sprintf(
            'Notificação enviada para %s com %d registro(s) e %d byte(s).',
            $emailResponsavel,
            $totalRegistros,
            $totalBytes
        )
    );
} catch (Throwable $erro) {
    /*
     * Não utiliza Log::error aqui. Caso o SMTP esteja indisponível, registrar
     * a falha no mesmo arquivo monitorado criaria um ciclo de notificações.
     */
    fwrite(
        STDERR,
        '[' . date('Y-m-d H:i:s') . '] Falha no cron de logs: '
        . normalizarUtf8($erro->getMessage())
        . PHP_EOL
    );

    exit(1);
} finally {
    if ($arquivoTemporario !== null && is_file($arquivoTemporario)) {
        @unlink($arquivoTemporario);
    }
}

exit(0);

/**
 * @return array<string,mixed>
 */
function carregarEstado(string $arquivo): array
{
    if (!is_file($arquivo)) {
        return [
            'arquivos' => [],
            'ultima_execucao_com_sucesso' => null,
        ];
    }

    $conteudo = file_get_contents($arquivo);

    if ($conteudo === false || trim($conteudo) === '') {
        return [
            'arquivos' => [],
            'ultima_execucao_com_sucesso' => null,
        ];
    }

    $dados = json_decode($conteudo, true);

    if (!is_array($dados)) {
        return [
            'arquivos' => [],
            'ultima_execucao_com_sucesso' => null,
        ];
    }

    $dados['arquivos'] = is_array($dados['arquivos'] ?? null)
        ? $dados['arquivos']
        : [];

    return $dados;
}

/**
 * @param array<string,mixed> $estado
 */
function salvarEstado(string $arquivo, array $estado): void
{
    $json = json_encode(
        $estado,
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_PRETTY_PRINT
        | JSON_INVALID_UTF8_SUBSTITUTE
    );

    if ($json === false) {
        throw new RuntimeException('Não foi possível gerar o estado do cron.');
    }

    $temporario = $arquivo . '.tmp';

    if (file_put_contents($temporario, $json, LOCK_EX) === false) {
        throw new RuntimeException('Não foi possível gravar o estado temporário do cron.');
    }

    @chmod($temporario, 0640);

    if (!rename($temporario, $arquivo)) {
        @unlink($temporario);
        throw new RuntimeException('Não foi possível atualizar o estado do cron.');
    }
}

/**
 * @param string[] $diretoriosRelativos
 * @param string[] $extensoes
 * @return string[]
 */
function localizarArquivos(
    string $raizProjeto,
    array $diretoriosRelativos,
    array $extensoes,
    string $dataAtual,
    bool $somenteDoDia
): array {
    $resultado = [];
    $vistos = [];

    foreach ($diretoriosRelativos as $diretorioRelativo) {
        $diretorioRelativo = trim(
            str_replace('\\', '/', (string) $diretorioRelativo),
            '/'
        );

        if ($diretorioRelativo === '' || str_contains($diretorioRelativo, '..')) {
            continue;
        }

        $diretorio = $raizProjeto . '/' . $diretorioRelativo;

        if (!is_dir($diretorio)) {
            continue;
        }

        $itens = scandir($diretorio);

        if ($itens === false) {
            continue;
        }

        foreach ($itens as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $arquivo = $diretorio . '/' . $item;

            if (!is_file($arquivo) || !is_readable($arquivo)) {
                continue;
            }

            $extensao = strtolower((string) pathinfo($arquivo, PATHINFO_EXTENSION));

            if ($extensoes !== [] && !in_array($extensao, $extensoes, true)) {
                continue;
            }

            if ($somenteDoDia) {
                $nomeContemData = str_contains($item, $dataAtual);
                $modificadoHoje = date('Y-m-d', (int) filemtime($arquivo)) === $dataAtual;

                if (!$nomeContemData && !$modificadoHoje) {
                    continue;
                }
            }

            $real = realpath($arquivo);

            if ($real === false || isset($vistos[$real])) {
                continue;
            }

            $vistos[$real] = true;
            $resultado[] = $real;
        }
    }

    usort(
        $resultado,
        static fn (string $a, string $b): int =>
            ((int) filemtime($a) <=> (int) filemtime($b))
            ?: strcmp($a, $b)
    );

    return $resultado;
}

function lerTrechoArquivo(string $arquivo, int $offset, int $limite): string
{
    $handle = fopen($arquivo, 'rb');

    if ($handle === false) {
        return '';
    }

    try {
        if (fseek($handle, $offset) !== 0) {
            return '';
        }

        $conteudo = '';
        $restante = $limite;

        while ($restante > 0 && !feof($handle)) {
            $bloco = fread($handle, min(8192, $restante));

            if ($bloco === false || $bloco === '') {
                break;
            }

            $conteudo .= $bloco;
            $restante -= strlen($bloco);
        }

        return $conteudo;
    } finally {
        fclose($handle);
    }
}

/**
 * @param array<int,array<string,mixed>> $fragmentos
 */
function montarConteudoCompleto(array $fragmentos): string
{
    $partes = [];

    foreach ($fragmentos as $fragmento) {
        $partes[] = str_repeat('=', 100)
            . PHP_EOL
            . 'ARQUIVO: '
            . (string) $fragmento['nome']
            . PHP_EOL
            . 'TRECHO: bytes '
            . (int) $fragmento['offset_anterior']
            . ' a '
            . (int) $fragmento['offset_novo']
            . PHP_EOL
            . str_repeat('=', 100)
            . PHP_EOL
            . rtrim((string) $fragmento['conteudo'])
            . PHP_EOL;
    }

    return implode(PHP_EOL, $partes);
}

/**
 * @return array<string,int>
 */
function contarNiveis(string $conteudo): array
{
    $niveis = [
        'FATAL' => 0,
        'EXCEPTION' => 0,
        'ERROR' => 0,
        'WARNING' => 0,
        'PHP' => 0,
        'INFO' => 0,
        'OUTROS' => 0,
    ];

    preg_match_all(
        '/\[(INFO|WARNING|ERROR|EXCEPTION|PHP|FATAL)\]/u',
        $conteudo,
        $matches
    );

    foreach (($matches[1] ?? []) as $nivel) {
        $nivel = strtoupper((string) $nivel);

        if (isset($niveis[$nivel])) {
            $niveis[$nivel]++;
        } else {
            $niveis['OUTROS']++;
        }
    }

    return $niveis;
}

/**
 * @param array<string,int> $contagens
 */
function montarResumoHtml(array $contagens): string
{
    $cores = [
        'FATAL' => '#7f1d1d',
        'EXCEPTION' => '#991b1b',
        'ERROR' => '#dc2626',
        'WARNING' => '#d97706',
        'PHP' => '#7c3aed',
        'INFO' => '#2563eb',
        'OUTROS' => '#64748b',
    ];

    $itens = '';

    foreach ($contagens as $nivel => $total) {
        if ($total <= 0) {
            continue;
        }

        $itens .= '<span style="display:inline-block;margin:0 8px 8px 0;padding:7px 11px;border-radius:999px;background:'
            . ($cores[$nivel] ?? '#64748b')
            . ';color:#fff;font-size:12px;font-weight:700">'
            . escaparHtml($nivel)
            . ': '
            . $total
            . '</span>';
    }

    return $itens !== ''
        ? '<div style="margin:18px 0">' . $itens . '</div>'
        : '';
}

function protegerDadosSensiveis(string $conteudo): string
{
    $padroes = [
        '/("(?:senha|password|passwd|token|secret|authorization|api_?key|cookie|csrf)"\s*:\s*)"[^"]*"/iu',
        '/((?:senha|password|passwd|token|secret|authorization|api_?key|cookie|csrf)\s*[=:]\s*)[^\s,;]+/iu',
        '/\$aact_(?:prod|hmlg)_[A-Za-z0-9_\-]+/u',
        '/(Bearer\s+)[A-Za-z0-9._~+\/=-]+/iu',
    ];

    $substituicoes = [
        '$1"[PROTEGIDO]"',
        '$1[PROTEGIDO]',
        '[CHAVE_ASAAS_PROTEGIDA]',
        '$1[PROTEGIDO]',
    ];

    return preg_replace(
        $padroes,
        $substituicoes,
        $conteudo
    ) ?? $conteudo;
}

function normalizarUtf8(string $texto): string
{
    if ($texto === '') {
        return '';
    }

    if (
        function_exists('mb_check_encoding')
        && mb_check_encoding($texto, 'UTF-8')
    ) {
        return $texto;
    }

    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding(
            $texto,
            'UTF-8',
            ['UTF-8', 'Windows-1252', 'ISO-8859-1']
        );
    }

    if (function_exists('iconv')) {
        $convertido = iconv(
            'Windows-1252',
            'UTF-8//IGNORE',
            $texto
        );

        if ($convertido !== false) {
            return $convertido;
        }
    }

    return $texto;
}

function cortarUtf8(string $texto, int $maximoBytes): string
{
    if (strlen($texto) <= $maximoBytes) {
        return $texto;
    }

    $trecho = substr($texto, 0, $maximoBytes);

    if (function_exists('mb_check_encoding')) {
        while ($trecho !== '' && !mb_check_encoding($trecho, 'UTF-8')) {
            $trecho = substr($trecho, 0, -1);
        }
    }

    return rtrim($trecho)
        . PHP_EOL
        . PHP_EOL
        . '[CONTEÚDO REDUZIDO NO CORPO DO E-MAIL]';
}

function escaparHtml(string $texto): string
{
    return htmlspecialchars(
        $texto,
        ENT_QUOTES | ENT_SUBSTITUTE,
        'UTF-8'
    );
}

function normalizarCaminho(string $caminho): string
{
    return str_replace('\\', '/', $caminho);
}

function escreverSaida(string $mensagem): void
{
    fwrite(
        STDOUT,
        '[' . date('Y-m-d H:i:s') . '] '
        . normalizarUtf8($mensagem)
        . PHP_EOL
    );
}
