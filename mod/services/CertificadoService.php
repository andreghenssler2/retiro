<?php

declare(strict_types=1);

use Dompdf\Dompdf;
use Dompdf\Options;

class CertificadoService
{
    private Certificado $certificado;
    private string $raizProjeto;
    private string $diretorioStorage;
    private string $ultimoErroEmail = '';
    private string $ultimoRetornoSmtp = '';

    public function __construct(?Certificado $certificado = null)
    {
        $this->certificado = $certificado ?? new Certificado();
        $this->raizProjeto = dirname(__DIR__, 2);
        $this->diretorioStorage = $this->raizProjeto . '/storage/certificados';
    }

    public function emitirPorInscricao(
        int $idInscricao,
        int $idResponsavel = 0
    ): array {
        $dados = $this->certificado->buscarDadosInscricao($idInscricao);

        if (!$dados) {
            throw new RuntimeException('Inscrição não encontrada.');
        }

        if ((string) ($dados['inscricaoStatus'] ?? '') === 'Cancelada') {
            throw new RuntimeException('A inscrição está cancelada e não pode receber certificado.');
        }

        if ((int) ($dados['presenca'] ?? 0) !== 1) {
            throw new RuntimeException(
                'O certificado ficará disponível somente depois da confirmação da presença.'
            );
        }

        $eventoEmiteCertificado =
            (int) ($dados['eventoCertificado'] ?? 0) === 1
            || (int) ($dados['eventoCertificadoAtivo'] ?? 0) === 1;

        if (!$eventoEmiteCertificado) {
            throw new RuntimeException('Este evento não está configurado para emitir certificados.');
        }

        $email = trim((string) ($dados['email'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException(
                'O participante não possui um e-mail válido para receber o certificado.'
            );
        }

        $modelo = $this->certificado->buscarModeloPorEvento(
            (int) $dados['idEvento']
        );

        if (!$modelo) {
            throw new RuntimeException(
                'Crie e ative o modelo deste evento em Certificados antes de emitir.'
            );
        }

        $emitido = $this->certificado->buscarAtivoPorInscricao($idInscricao);
        $foiReenvio = $emitido !== false;

        if (!$emitido) {
            $emitido = $this->criarCertificado(
                $dados,
                $modelo,
                $idResponsavel
            );
        } else {
            /*
             * Sempre recria a cópia ao reenviar. Assim, certificados
             * emitidos com um layout antigo passam a usar o modelo atual
             * sem alterar o código público de validação.
             */
            $emitido = $this->regenerarArquivo(
                $emitido,
                $dados,
                $modelo
            );
        }

        $arquivoAbsoluto = $this->resolverArquivo(
            (string) $emitido['arquivo']
        );

        if (!$arquivoAbsoluto || !is_file($arquivoAbsoluto)) {
            throw new RuntimeException('O arquivo do certificado não pôde ser localizado.');
        }

        $urlValidacao = $this->urlValidacao((string) $emitido['codigo']);
        $enviado = $this->enviarEmail(
            $emitido,
            $dados,
            $arquivoAbsoluto,
            $urlValidacao
        );

        $this->certificado->marcarInscricaoCertificada($idInscricao, true);

        if ($enviado) {
            $this->certificado->marcarEnviado(
                (int) $emitido['idCertificado'],
                $email
            );
        }

        $mensagem = $enviado
            ? ($foiReenvio
                ? 'Certificado reenviado para o participante.'
                : 'Certificado emitido, armazenado e enviado para o participante.')
            : 'O certificado foi emitido e armazenado, mas o e-mail não pôde ser enviado.';

        if (!$enviado && $this->ultimoErroEmail !== '') {
            $mensagem .= ' Motivo: ' . $this->ultimoErroEmail;
        }

        return [
            'status' => true,
            'enviado' => $enviado,
            'reenvio' => $foiReenvio,
            'idCertificado' => (int) $emitido['idCertificado'],
            'codigo' => (string) $emitido['codigo'],
            'urlValidacao' => $urlValidacao,
            'erroEmail' => $enviado ? null : $this->ultimoErroEmail,
            'respostaSmtp' => $enviado ? $this->ultimoRetornoSmtp : null,
            'msg' => $mensagem
        ];
    }

    public function reenviarPorId(
        int $idCertificado,
        int $idResponsavel = 0
    ): array {
        $emitido = $this->certificado->buscarEmitido($idCertificado);

        if (!$emitido) {
            throw new RuntimeException('Certificado não encontrado.');
        }

        if ((string) $emitido['status'] === 'Revogado') {
            throw new RuntimeException('Um certificado revogado não pode ser reenviado.');
        }

        return $this->emitirPorInscricao(
            (int) $emitido['idInscricao'],
            $idResponsavel
        );
    }

    public function revogar(
        int $idCertificado,
        string $motivo,
        int $idResponsavel = 0
    ): bool {
        $emitido = $this->certificado->buscarEmitido($idCertificado);

        if (!$emitido) {
            throw new RuntimeException('Certificado não encontrado.');
        }

        if ((string) $emitido['status'] === 'Revogado') {
            throw new RuntimeException('O certificado já está revogado.');
        }

        $motivo = trim($motivo);

        if ($motivo === '') {
            throw new InvalidArgumentException('Informe o motivo da revogação.');
        }

        $ok = $this->certificado->revogar(
            $idCertificado,
            $motivo,
            $idResponsavel
        );

        if ($ok) {
            $this->certificado->marcarInscricaoCertificada(
                (int) $emitido['idInscricao'],
                false
            );
        }

        return $ok;
    }

    public function gerarPreview(int $idModelo): string
    {
        $modelo = $this->certificado->buscarModelo($idModelo);

        if (!$modelo) {
            throw new RuntimeException('Modelo de certificado não encontrado.');
        }

        $codigo = 'CERT-PREVIA-2026';
        $dados = [
            'nome' => 'NOME DO PARTICIPANTE',
            'eventoTitulo' => (string) ($modelo['eventoTitulo'] ?? 'Evento'),
            'data_inicio' => (string) ($modelo['data_inicio'] ?? date('Y-m-d')),
            'data_fim' => (string) ($modelo['data_fim'] ?? ''),
            'eventoLocal' => (string) ($modelo['eventoLocal'] ?? ''),
            'eventoCidade' => (string) ($modelo['eventoCidade'] ?? ''),
            'eventoEstado' => (string) ($modelo['eventoEstado'] ?? '')
        ];

        return $this->renderizarPdf(
            $modelo,
            $dados,
            $codigo,
            $this->urlValidacao($codigo),
            date('d/m/Y')
        );
    }

    public function arquivoAbsolutoDeRegistro(array $emitido): ?string
    {
        return $this->resolverArquivo((string) ($emitido['arquivo'] ?? ''));
    }

    public function arquivoIntegro(array $emitido): bool
    {
        $arquivo = $this->arquivoAbsolutoDeRegistro($emitido);

        if (!$arquivo || !is_file($arquivo)) {
            return false;
        }

        $hashEsperado = strtolower((string) ($emitido['hashArquivo'] ?? ''));

        return $hashEsperado !== ''
            && hash_equals($hashEsperado, strtolower(hash_file('sha256', $arquivo)));
    }

    private function criarCertificado(
        array $dados,
        array $modelo,
        int $idResponsavel
    ): array {
        $codigo = $this->gerarCodigo();
        $tokenDownload = bin2hex(random_bytes(32));
        $dataEmissao = date('d/m/Y');
        $urlValidacao = $this->urlValidacao($codigo);

        $pdf = $this->renderizarPdf(
            $modelo,
            $dados,
            $codigo,
            $urlValidacao,
            $dataEmissao
        );

        $arquivoRelativo = $this->salvarPdf(
            $pdf,
            $codigo,
            $tokenDownload
        );
        $arquivoAbsoluto = $this->resolverArquivo($arquivoRelativo);

        if (!$arquivoAbsoluto) {
            throw new RuntimeException('Não foi possível salvar o certificado no servidor.');
        }

        $hashArquivo = hash_file('sha256', $arquivoAbsoluto);
        $idCertificado = $this->certificado->registrarEmitido([
            'idModelo' => (int) $modelo['idModelo'],
            'idInscricao' => (int) $dados['idInscricao'],
            'idEvento' => (int) $dados['idEvento'],
            'idUsuario' => (int) $dados['idUsuario'],
            'codigo' => $codigo,
            'tokenDownload' => $tokenDownload,
            'arquivo' => $arquivoRelativo,
            'hashArquivo' => $hashArquivo,
            'nomeParticipante' => (string) $dados['nome'],
            'emailDestino' => (string) $dados['email'],
            'eventoTitulo' => (string) $dados['eventoTitulo'],
            'cargaHoraria' => (float) $modelo['cargaHoraria'],
            'dataEvento' => $this->formatarPeriodoEvento(
                (string) $dados['data_inicio'],
                (string) ($dados['data_fim'] ?? '')
            ),
            'emitidoPor' => $idResponsavel
        ]);

        if (!$idCertificado) {
            @unlink($arquivoAbsoluto);
            throw new RuntimeException('Não foi possível registrar o certificado no banco de dados.');
        }

        $emitido = $this->certificado->buscarEmitido($idCertificado);

        if (!$emitido) {
            throw new RuntimeException('O certificado foi criado, mas não pôde ser recuperado.');
        }

        return $emitido;
    }

    private function regenerarArquivo(
        array $emitido,
        array $dados,
        array $modelo
    ): array {
        $codigo = (string) $emitido['codigo'];
        $token = (string) ($emitido['tokenDownload'] ?? bin2hex(random_bytes(32)));
        $pdf = $this->renderizarPdf(
            $modelo,
            $dados,
            $codigo,
            $this->urlValidacao($codigo),
            date('d/m/Y', strtotime((string) $emitido['emitidoEm']))
        );

        $arquivoRelativo = $this->salvarPdf($pdf, $codigo, $token);
        $arquivoAbsoluto = $this->resolverArquivo($arquivoRelativo);

        if (!$arquivoAbsoluto) {
            throw new RuntimeException('Não foi possível regenerar o arquivo do certificado.');
        }

        $hash = hash_file('sha256', $arquivoAbsoluto);
        $this->certificado->atualizarArquivo(
            (int) $emitido['idCertificado'],
            $arquivoRelativo,
            $hash
        );

        $emitidoAtualizado = $this->certificado->buscarEmitido(
            (int) $emitido['idCertificado']
        );

        return $emitidoAtualizado ?: $emitido;
    }

    private function enviarEmail(
        array $emitido,
        array $dados,
        string $arquivoAbsoluto,
        string $urlValidacao
    ): bool {
        $this->ultimoErroEmail = '';
        $this->ultimoRetornoSmtp = '';

        $nome = trim((string) ($dados['nome'] ?? ''));
        $email = trim((string) ($dados['email'] ?? ''));
        $evento = trim((string) ($dados['eventoTitulo'] ?? ''));
        $codigo = trim((string) ($emitido['codigo'] ?? ''));
        $dataEvento = trim((string) ($emitido['dataEvento'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->ultimoErroEmail = 'O e-mail do participante é inválido.';
            return false;
        }

        if (!is_file($arquivoAbsoluto)) {
            $this->ultimoErroEmail = 'O arquivo PDF do certificado não foi encontrado.';
            return false;
        }

        if (!is_readable($arquivoAbsoluto)) {
            $this->ultimoErroEmail = 'O arquivo PDF existe, mas não possui permissão de leitura.';
            return false;
        }

        $tamanhoArquivo = filesize($arquivoAbsoluto);

        if ($tamanhoArquivo === false) {
            $this->ultimoErroEmail = 'Não foi possível verificar o tamanho do PDF.';
            return false;
        }

        /*
         * O servidor SMTP informado no diagnóstico aceita mensagens de até
         * 50 MB. O limite preventivo de 35 MB considera o aumento causado
         * pela codificação Base64 do anexo.
         */
        if ($tamanhoArquivo > 35 * 1024 * 1024) {
            $this->ultimoErroEmail = sprintf(
                'O PDF possui %.2f MB e excede o limite seguro para envio por e-mail.',
                $tamanhoArquivo / 1024 / 1024
            );
            return false;
        }

        $template = $this->raizProjeto . '/mod/mail/templates/certificado_emitido.php';

        if (!is_file($template) || !is_readable($template)) {
            $this->ultimoErroEmail = 'O modelo do e-mail de certificado não foi encontrado.';
            return false;
        }

        ob_start();

        try {
            include $template;
            $html = (string) ob_get_clean();
        } catch (Throwable $erro) {
            ob_end_clean();
            $this->ultimoErroEmail = 'Erro ao montar o conteúdo do e-mail: ' . $erro->getMessage();
            return false;
        }

        if (trim($html) === '') {
            $this->ultimoErroEmail = 'O conteúdo do e-mail ficou vazio.';
            return false;
        }

        try {
            $mail = new Mail();

            if (method_exists($mail, 'isConfigured') && !$mail->isConfigured()) {
                $this->ultimoErroEmail = method_exists($mail, 'getLastError')
                    ? trim((string) $mail->getLastError())
                    : 'Servidor SMTP não configurado ou desativado.';
                return false;
            }

            $enviado = $mail->send(
                $email,
                $nome,
                'Seu certificado - ' . $evento,
                $html,
                [[
                    'path' => $arquivoAbsoluto,
                    'name' => 'certificado-' . $this->slugArquivo($nome) . '.pdf'
                ]]
            );

            if (method_exists($mail, 'getLastSmtpReply')) {
                $this->ultimoRetornoSmtp = trim(
                    (string) $mail->getLastSmtpReply()
                );
            }

            if (!$enviado) {
                $this->ultimoErroEmail = method_exists($mail, 'getLastError')
                    ? trim((string) $mail->getLastError())
                    : '';

                if ($this->ultimoErroEmail === '') {
                    $this->ultimoErroEmail = 'O PHPMailer não informou o motivo da falha.';
                }

                error_log(
                    'Falha ao enviar certificado por e-mail'
                    . ' | inscrição=' . (int) ($dados['idInscricao'] ?? 0)
                    . ' | certificado=' . (int) ($emitido['idCertificado'] ?? 0)
                    . ' | destinatário=' . $email
                    . ' | arquivo=' . $arquivoAbsoluto
                    . ' | tamanho=' . $tamanhoArquivo
                    . ' | erro=' . $this->ultimoErroEmail
                );
            }

            return $enviado;
        } catch (Throwable $erro) {
            $this->ultimoErroEmail = $erro->getMessage();

            error_log(
                'Erro ao enviar certificado por e-mail'
                . ' | inscrição=' . (int) ($dados['idInscricao'] ?? 0)
                . ' | certificado=' . (int) ($emitido['idCertificado'] ?? 0)
                . ' | destinatário=' . $email
                . ' | arquivo=' . $arquivoAbsoluto
                . ' | tamanho=' . $tamanhoArquivo
                . ' | erro=' . $erro->getMessage()
            );

            return false;
        }
    }

    private function renderizarPdf(
        array $modelo,
        array $dados,
        string $codigo,
        string $urlValidacao,
        string $dataEmissao
    ): string {
        if (!class_exists(Dompdf::class)) {
            throw new RuntimeException(
                'Dompdf não está instalado. Execute composer install na pasta lib.'
            );
        }

        if (!function_exists('mb_internal_encoding')) {
            throw new RuntimeException(
                'A extensão PHP mbstring precisa estar habilitada para gerar certificados em PDF.'
            );
        }

        $nomeTexto = trim((string) ($dados['nome'] ?? ''));
        $eventoTexto = trim(
            (string) ($dados['eventoTitulo'] ?? $modelo['eventoTitulo'] ?? '')
        );

        $nome = htmlspecialchars($nomeTexto, ENT_QUOTES, 'UTF-8');
        $evento = htmlspecialchars($eventoTexto, ENT_QUOTES, 'UTF-8');
        $dataEvento = htmlspecialchars(
            $this->formatarPeriodoEvento(
                (string) ($dados['data_inicio'] ?? ''),
                (string) ($dados['data_fim'] ?? '')
            ),
            ENT_QUOTES,
            'UTF-8'
        );
        $cargaHoraria = $this->formatarCargaHoraria(
            (float) ($modelo['cargaHoraria'] ?? 0)
        );
        $localEvento = trim(implode(', ', array_filter([
            (string) ($dados['eventoLocal'] ?? ''),
            trim(
                (string) ($dados['eventoCidade'] ?? '')
                . (!empty($dados['eventoEstado']) ? '/' . $dados['eventoEstado'] : '')
            )
        ])));
        $localEvento = htmlspecialchars($localEvento, ENT_QUOTES, 'UTF-8');

        $textoOriginal = trim((string) ($modelo['texto'] ?? ''));
        $texto = htmlspecialchars($textoOriginal, ENT_QUOTES, 'UTF-8');
        $texto = strtr($texto, [
            '{{nome}}' => '<span class="nome-participante">' . $nome . '</span>',
            '{{evento}}' => '<strong>' . $evento . '</strong>',
            '{{data_evento}}' => $dataEvento,
            '{{carga_horaria}}' => '<strong>' . $cargaHoraria . '</strong>',
            '{{local_evento}}' => $localEvento,
            '{{codigo}}' => htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8'),
            '{{data_emissao}}' => htmlspecialchars($dataEmissao, ENT_QUOTES, 'UTF-8'),
            '{{url_validacao}}' => htmlspecialchars($urlValidacao, ENT_QUOTES, 'UTF-8')
        ]);
        $texto = nl2br($texto, false);

        $corTitulo = $this->corSegura((string) ($modelo['corTitulo'] ?? '#0d6efd'));
        $corTexto = $this->corSegura((string) ($modelo['corTexto'] ?? '#1f2937'));
        $imagemFundo = $this->imagemDataUri((string) ($modelo['imagemFundo'] ?? ''));
        $logo = $this->imagemDataUri((string) ($modelo['logo'] ?? ''));
        $assinatura1 = $this->imagemDataUri((string) ($modelo['assinatura1Imagem'] ?? ''));
        $assinatura2 = $this->imagemDataUri((string) ($modelo['assinatura2Imagem'] ?? ''));

        $backgroundCss = $imagemFundo
            ? "background-image:url('{$imagemFundo}');background-size:cover;background-position:center center;"
            : 'background-color:#f8fafc;';

        $tituloTexto = trim((string) ($modelo['titulo'] ?? 'CERTIFICADO'));
        $titulo = htmlspecialchars($tituloTexto, ENT_QUOTES, 'UTF-8');
        $localEmissao = htmlspecialchars(
            trim((string) ($modelo['localEmissao'] ?? '')),
            ENT_QUOTES,
            'UTF-8'
        );

        $tamanhoTitulo = mb_strlen($tituloTexto, 'UTF-8') > 28 ? '20pt' : '24pt';
        $tamanhoNome = match (true) {
            mb_strlen($nomeTexto, 'UTF-8') > 45 => '19pt',
            mb_strlen($nomeTexto, 'UTF-8') > 34 => '21pt',
            default => '24pt'
        };
        /*
         * Calcula o tamanho usando o texto já preenchido. O modelo pode
         * parecer curto por conter variáveis, mas ficar muito maior depois
         * de inserir nome, evento, período e carga horária.
         */
        $textoParaMedida = strtr($textoOriginal, [
            '{{nome}}' => $nomeTexto,
            '{{evento}}' => $eventoTexto,
            '{{data_evento}}' => html_entity_decode($dataEvento, ENT_QUOTES, 'UTF-8'),
            '{{carga_horaria}}' => $cargaHoraria,
            '{{local_evento}}' => html_entity_decode($localEvento, ENT_QUOTES, 'UTF-8'),
            '{{codigo}}' => $codigo,
            '{{data_emissao}}' => $dataEmissao,
            '{{url_validacao}}' => $urlValidacao
        ]);
        $textoParaMedida = preg_replace('/\s+/u', ' ', $textoParaMedida) ?: $textoParaMedida;
        $comprimentoTexto = mb_strlen($textoParaMedida, 'UTF-8');

        $tamanhoTexto = match (true) {
            $comprimentoTexto > 700 => '9.8pt',
            $comprimentoTexto > 520 => '10.5pt',
            $comprimentoTexto > 360 => '11.3pt',
            $comprimentoTexto > 220 => '12pt',
            default => '13pt'
        };

        $possuiLogo = $logo !== '';
        $tituloTop = $possuiLogo ? '33mm' : '18mm';
        $textoTop = $possuiLogo ? '50mm' : '37mm';
        $textoAltura = $possuiLogo ? '75mm' : '88mm';

        $assinatura1Nome = trim((string) ($modelo['assinatura1Nome'] ?? ''));
        $assinatura1Cargo = trim((string) ($modelo['assinatura1Cargo'] ?? ''));
        $assinatura2Nome = trim((string) ($modelo['assinatura2Nome'] ?? ''));
        $assinatura2Cargo = trim((string) ($modelo['assinatura2Cargo'] ?? ''));

        $temAssinatura1 = $assinatura1 !== ''
            || $assinatura1Nome !== ''
            || $assinatura1Cargo !== '';
        $temAssinatura2 = $assinatura2 !== ''
            || $assinatura2Nome !== ''
            || $assinatura2Cargo !== '';

        if ($temAssinatura1 && $temAssinatura2) {
            $assinaturasHtml = '<div class="assinaturas-duplas">'
                . $this->htmlAssinatura($assinatura1, $assinatura1Nome, $assinatura1Cargo)
                . $this->htmlAssinatura($assinatura2, $assinatura2Nome, $assinatura2Cargo)
                . '</div>';
        } elseif ($temAssinatura1 || $temAssinatura2) {
            $assinaturasHtml = '<div class="assinatura-unica">'
                . ($temAssinatura1
                    ? $this->htmlAssinatura($assinatura1, $assinatura1Nome, $assinatura1Cargo)
                    : $this->htmlAssinatura($assinatura2, $assinatura2Nome, $assinatura2Cargo))
                . '</div>';
        } else {
            $assinaturasHtml = '';
        }

        $html = '<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
@page{size:A4 landscape;margin:0;}
html,body{margin:0;padding:0;width:100%;height:100%;overflow:hidden;}
*{box-sizing:border-box;}
body{font-family:DejaVu Sans,sans-serif;color:' . $corTexto . ';}
.certificado{position:fixed;top:0;right:0;bottom:0;left:0;overflow:hidden;' . $backgroundCss . '}
.moldura-externa{position:absolute;top:7mm;right:7mm;bottom:7mm;left:7mm;border:1.05mm solid ' . $corTitulo . ';}
.moldura-interna{position:absolute;top:10mm;right:10mm;bottom:10mm;left:10mm;border:.28mm solid ' . $corTitulo . ';opacity:.65;}
.logo-area{position:absolute;top:9mm;left:24mm;right:24mm;height:22mm;text-align:center;overflow:hidden;}
.logo{height:20mm;max-width:58mm;object-fit:contain;}
.titulo-area{position:absolute;top:' . $tituloTop . ';left:24mm;right:24mm;text-align:center;}
.titulo{font-size:' . $tamanhoTitulo . ';line-height:1.12;font-weight:700;letter-spacing:2mm;color:' . $corTitulo . ';}
.titulo-linha{width:38mm;margin:3mm auto 0;border-top:.45mm solid ' . $corTitulo . ';}
.texto-area{position:absolute;top:' . $textoTop . ';left:32mm;width:233mm;height:' . $textoAltura . ';overflow:hidden;text-align:center;}
.texto{display:block;width:100%;max-width:100%;margin:0 auto;text-align:center;font-size:' . $tamanhoTexto . ';line-height:1.48;white-space:normal;word-wrap:break-word;overflow-wrap:break-word;}
.texto strong{white-space:normal;}
.nome-participante{display:block;width:100%;max-width:220mm;margin:4mm auto;font-size:' . $tamanhoNome . ';line-height:1.15;font-weight:700;color:' . $corTitulo . ';white-space:normal;word-wrap:break-word;overflow-wrap:break-word;}
.emissao{position:absolute;top:128mm;left:30mm;right:30mm;text-align:center;font-size:10pt;}
.assinaturas{position:absolute;top:143mm;left:25mm;right:25mm;height:37mm;overflow:hidden;}
.assinaturas-duplas{display:table;width:100%;table-layout:fixed;}
.assinaturas-duplas .assinatura{display:table-cell;width:50%;padding:0 12mm;vertical-align:bottom;text-align:center;}
.assinatura-unica{width:92mm;margin:0 auto;text-align:center;}
.assinatura-unica .assinatura{width:100%;padding:0 5mm;text-align:center;}
.assinatura-img{height:15mm;max-width:62mm;object-fit:contain;display:block;margin:0 auto 1mm;}
.assinatura-espaco{height:16mm;}
.linha{border-top:.3mm solid #4b5563;padding-top:2mm;min-height:10mm;}
.assinatura-nome{font-size:10pt;font-weight:700;}
.assinatura-cargo{font-size:8.5pt;color:#4b5563;margin-top:1mm;}
.validacao{position:absolute;left:22mm;right:22mm;bottom:8mm;font-size:6.8pt;color:#4b5563;text-align:center;line-height:1.35;}
.validacao-url{word-break:break-all;}
.codigo{font-weight:700;letter-spacing:.25mm;color:' . $corTexto . ';}
</style>
</head>
<body>
<div class="certificado">
<div class="moldura-externa"></div>
<div class="moldura-interna"></div>'
. ($logo !== ''
    ? '<div class="logo-area"><img class="logo" src="' . $logo . '" alt="Logo"></div>'
    : '') .
'<div class="titulo-area"><div class="titulo">' . $titulo . '</div><div class="titulo-linha"></div></div>
<div class="texto-area"><div class="texto">' . $texto . '</div></div>
<div class="emissao">' . ($localEmissao !== '' ? $localEmissao . ', ' : '') . $dataEmissao . '.</div>'
. ($assinaturasHtml !== '' ? '<div class="assinaturas">' . $assinaturasHtml . '</div>' : '') .
'<div class="validacao">Documento verificável em <span class="validacao-url">'
. htmlspecialchars($urlValidacao, ENT_QUOTES, 'UTF-8')
. '</span><br><span class="codigo">Código: '
. htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8')
. '</span></div>
</div>
</body>
</html>';

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    private function salvarPdf(
        string $pdf,
        string $codigo,
        string $token
    ): string {
        $subdiretorio = date('Y/m');
        $diretorio = $this->diretorioStorage . '/' . $subdiretorio;

        if (!is_dir($diretorio) && !mkdir($diretorio, 0750, true) && !is_dir($diretorio)) {
            throw new RuntimeException('Não foi possível criar a pasta de certificados.');
        }

        $nomeArquivo = strtolower($codigo)
            . '-'
            . substr($token, 0, 12)
            . '.pdf';
        $nomeArquivo = preg_replace('/[^a-z0-9.-]/', '-', $nomeArquivo);
        $arquivoAbsoluto = $diretorio . '/' . $nomeArquivo;

        if (file_put_contents($arquivoAbsoluto, $pdf, LOCK_EX) === false) {
            throw new RuntimeException('Não foi possível gravar o PDF do certificado.');
        }

        @chmod($arquivoAbsoluto, 0640);

        return 'storage/certificados/' . $subdiretorio . '/' . $nomeArquivo;
    }

    private function resolverArquivo(string $relativo): ?string
    {
        $relativo = ltrim(str_replace('\\', '/', $relativo), '/');

        if ($relativo === '' || str_contains($relativo, '..')) {
            return null;
        }

        $arquivo = $this->raizProjeto . '/' . $relativo;
        $diretorioEsperado = realpath($this->diretorioStorage);
        $diretorioArquivo = realpath(dirname($arquivo));

        if (!$diretorioEsperado || !$diretorioArquivo) {
            return null;
        }

        if (!str_starts_with($diretorioArquivo, $diretorioEsperado)) {
            return null;
        }

        return $arquivo;
    }

    private function imagemDataUri(string $caminhoRelativo): string
    {
        $caminhoRelativo = ltrim(str_replace('\\', '/', $caminhoRelativo), '/');

        if ($caminhoRelativo === '' || str_contains($caminhoRelativo, '..')) {
            return '';
        }

        $arquivo = $this->raizProjeto . '/' . $caminhoRelativo;

        if (!is_file($arquivo) || !is_readable($arquivo)) {
            return '';
        }

        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($arquivo);

        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true)) {
            return '';
        }

        $conteudo = file_get_contents($arquivo);

        return $conteudo === false
            ? ''
            : 'data:' . $mime . ';base64,' . base64_encode($conteudo);
    }

    private function htmlAssinatura(
        string $imagem,
        string $nome,
        string $cargo
    ): string {
        $nome = trim($nome);
        $cargo = trim($cargo);

        if ($imagem === '' && $nome === '' && $cargo === '') {
            return '<div class="assinatura"></div>';
        }

        return '<div class="assinatura">'
            . ($imagem !== ''
                ? '<img class="assinatura-img" src="' . $imagem . '" alt="Assinatura">'
                : '<div class="assinatura-espaco"></div>')
            . '<div class="linha">'
            . '<div class="assinatura-nome">'
            . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8')
            . '</div>'
            . '<div class="assinatura-cargo">'
            . htmlspecialchars($cargo, ENT_QUOTES, 'UTF-8')
            . '</div></div></div>';
    }

    private function gerarCodigo(): string
    {
        do {
            $codigo = 'CERT-'
                . date('Y')
                . '-'
                . strtoupper(bin2hex(random_bytes(5)));
        } while ($this->certificado->codigoExiste($codigo));

        return $codigo;
    }

    private function urlValidacao(string $codigo): string
    {
        return rtrim((string) BASE_URL, '/')
            . '/certificado/validar.php?codigo='
            . rawurlencode($codigo);
    }

    private function formatarPeriodoEvento(
        string $inicio,
        string $fim
    ): string {
        $inicioTimestamp = strtotime($inicio);

        if (!$inicioTimestamp) {
            return '-';
        }

        $inicioFormatado = date('d/m/Y', $inicioTimestamp);
        $fimTimestamp = $fim !== '' ? strtotime($fim) : false;

        if (!$fimTimestamp || date('Y-m-d', $fimTimestamp) === date('Y-m-d', $inicioTimestamp)) {
            return $inicioFormatado;
        }

        return $inicioFormatado . ' a ' . date('d/m/Y', $fimTimestamp);
    }

    private function formatarCargaHoraria(float $horas): string
    {
        $formatado = number_format($horas, $horas == floor($horas) ? 0 : 1, ',', '.');

        return $formatado . ($horas === 1.0 ? ' hora' : ' horas');
    }

    private function corSegura(string $cor): string
    {
        return preg_match('/^#[0-9a-fA-F]{6}$/', $cor)
            ? strtolower($cor)
            : '#1f2937';
    }

    private function slugArquivo(string $texto): string
    {
        $texto = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto) ?: $texto;
        $texto = strtolower($texto);
        $texto = preg_replace('/[^a-z0-9]+/', '-', $texto) ?: 'participante';

        return trim($texto, '-') ?: 'participante';
    }
}