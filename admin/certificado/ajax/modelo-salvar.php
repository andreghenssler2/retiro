<?php

declare(strict_types=1);

require_once '../../../config/settings.php';

header('Content-Type: application/json; charset=UTF-8');

Middleware::moderador();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => false, 'msg' => 'Método não permitido.']);
    exit;
}

if (!Session::validateCsrf($_POST['_token'] ?? '')) {
    http_response_code(419);
    echo json_encode(['status' => false, 'msg' => 'Token de segurança inválido.']);
    exit;
}

function salvarImagemCertificado(
    string $campo,
    string $diretorio,
    int $limiteBytes = 5242880
): ?string {
    if (!isset($_FILES[$campo]) || (int) $_FILES[$campo]['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    $arquivo = $_FILES[$campo];

    if ((int) $arquivo['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Falha no envio do arquivo ' . $campo . '.');
    }

    if ((int) $arquivo['size'] <= 0 || (int) $arquivo['size'] > $limiteBytes) {
        throw new RuntimeException('O arquivo ' . $campo . ' ultrapassa o tamanho permitido.');
    }

    $temporario = (string) $arquivo['tmp_name'];

    if (!is_uploaded_file($temporario)) {
        throw new RuntimeException('Upload inválido no campo ' . $campo . '.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($temporario);
    $extensoes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($extensoes[$mime])) {
        throw new RuntimeException('Use somente imagens JPG, PNG ou WEBP.');
    }

    if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
        throw new RuntimeException('Não foi possível criar a pasta de imagens dos certificados.');
    }

    $nome = $campo . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(6)) . '.' . $extensoes[$mime];
    $destino = $diretorio . '/' . $nome;

    if (!move_uploaded_file($temporario, $destino)) {
        throw new RuntimeException('Não foi possível salvar o arquivo ' . $campo . '.');
    }

    @chmod($destino, 0644);

    return 'uploads/certificados/modelos/' . $nome;
}

try {
    $certificado = new Certificado();
    $idModelo = max(0, (int) ($_POST['idModelo'] ?? 0));
    $idEvento = max(0, (int) ($_POST['idEvento'] ?? 0));
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $titulo = trim((string) ($_POST['titulo'] ?? ''));
    $texto = trim((string) ($_POST['texto'] ?? ''));
    $cargaHoraria = (float) str_replace(',', '.', (string) ($_POST['cargaHoraria'] ?? '0'));
    $localEmissao = trim((string) ($_POST['localEmissao'] ?? ''));
    $corTitulo = trim((string) ($_POST['corTitulo'] ?? '#0d6efd'));
    $corTexto = trim((string) ($_POST['corTexto'] ?? '#1f2937'));

    if ($idEvento <= 0) {
        throw new InvalidArgumentException('Selecione o evento.');
    }

    if ($nome === '') {
        throw new InvalidArgumentException('Informe o nome interno do modelo.');
    }

    if ($titulo === '') {
        throw new InvalidArgumentException('Informe o título do certificado.');
    }

    if ($texto === '') {
        throw new InvalidArgumentException('Informe o texto do certificado.');
    }

    if ($cargaHoraria < 0 || $cargaHoraria > 999.5) {
        throw new InvalidArgumentException('A carga horária informada é inválida.');
    }

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $corTitulo)) {
        $corTitulo = '#0d6efd';
    }

    if (!preg_match('/^#[0-9a-fA-F]{6}$/', $corTexto)) {
        $corTexto = '#1f2937';
    }

    if ($certificado->modeloExisteParaEvento($idEvento, $idModelo)) {
        throw new RuntimeException('Este evento já possui um modelo de certificado.');
    }

    $atual = $idModelo > 0 ? $certificado->buscarModelo($idModelo) : false;

    if ($idModelo > 0 && !$atual) {
        throw new RuntimeException('Modelo de certificado não encontrado.');
    }

    $diretorioUploads = dirname(__DIR__, 3) . '/uploads/certificados/modelos';
    $camposImagem = [
        'imagemFundo' => 'removerImagemFundo',
        'logo' => 'removerLogo',
        'assinatura1Imagem' => 'removerAssinatura1',
        'assinatura2Imagem' => 'removerAssinatura2'
    ];
    $imagensNovas = [];

    foreach ($camposImagem as $campo => $campoRemover) {
        $valorAtual = $atual ? (string) ($atual[$campo] ?? '') : '';

        if ((int) ($_POST[$campoRemover] ?? 0) === 1) {
            $valorAtual = '';
        }

        $novoArquivo = salvarImagemCertificado(
            $campo,
            $diretorioUploads,
            str_starts_with($campo, 'assinatura') ? 2097152 : 5242880
        );

        if ($novoArquivo !== null) {
            $valorAtual = $novoArquivo;
            $imagensNovas[] = $novoArquivo;
        }

        $dadosImagem[$campo] = $valorAtual;
    }

    $dados = [
        'idModelo' => $idModelo,
        'idEvento' => $idEvento,
        'nome' => $nome,
        'titulo' => $titulo,
        'texto' => $texto,
        'cargaHoraria' => $cargaHoraria,
        'localEmissao' => $localEmissao,
        'corTitulo' => $corTitulo,
        'corTexto' => $corTexto,
        'imagemFundo' => $dadosImagem['imagemFundo'] ?? '',
        'logo' => $dadosImagem['logo'] ?? '',
        'assinatura1Imagem' => $dadosImagem['assinatura1Imagem'] ?? '',
        'assinatura1Nome' => trim((string) ($_POST['assinatura1Nome'] ?? '')),
        'assinatura1Cargo' => trim((string) ($_POST['assinatura1Cargo'] ?? '')),
        'assinatura2Imagem' => $dadosImagem['assinatura2Imagem'] ?? '',
        'assinatura2Nome' => trim((string) ($_POST['assinatura2Nome'] ?? '')),
        'assinatura2Cargo' => trim((string) ($_POST['assinatura2Cargo'] ?? '')),
        'ativo' => isset($_POST['ativo']) ? 1 : 0,
        'criadoPor' => Auth::id() ?? 0
    ];

    $ok = $idModelo > 0
        ? $certificado->editarModelo($dados)
        : (bool) $certificado->salvarModelo($dados);

    if (!$ok) {
        throw new RuntimeException('Não foi possível salvar o modelo.');
    }

    echo json_encode([
        'status' => true,
        'msg' => $idModelo > 0
            ? 'Modelo atualizado com sucesso.'
            : 'Modelo criado com sucesso.',
        'redirect' => 'index.php'
    ], JSON_UNESCAPED_UNICODE);
} catch (Throwable $erro) {
    error_log('Erro ao salvar modelo de certificado: ' . $erro->getMessage());

    http_response_code($erro instanceof InvalidArgumentException ? 422 : 500);
    echo json_encode([
        'status' => false,
        'msg' => $erro->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
