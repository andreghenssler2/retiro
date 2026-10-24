<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json; charset=utf-8");

function responderRecebimento(
    bool $sucesso,
    string $mensagem,
    array $dados = [],
    int $http = 200
): never {
    http_response_code($http);

    echo json_encode(
        array_merge(
            [
                "sucesso" => $sucesso,
                "mensagem" => $mensagem
            ],
            $dados
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/**
 * @return array{caminho:?string,novo:?string}
 */
function processarComprovanteRecebimento(?string $atual): array
{
    if (
        !isset($_FILES["comprovante"])
        || !is_array($_FILES["comprovante"])
        || (int) ($_FILES["comprovante"]["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE
    ) {
        return ["caminho" => $atual, "novo" => null];
    }

    $arquivo = $_FILES["comprovante"];
    $erro = (int) ($arquivo["error"] ?? UPLOAD_ERR_NO_FILE);

    if ($erro !== UPLOAD_ERR_OK) {
        throw new InvalidArgumentException("Não foi possível enviar o comprovante.");
    }

    $temporario = (string) ($arquivo["tmp_name"] ?? "");
    $nomeOriginal = (string) ($arquivo["name"] ?? "");
    $tamanho = (int) ($arquivo["size"] ?? 0);

    if ($temporario === "" || !is_uploaded_file($temporario)) {
        throw new InvalidArgumentException("O comprovante enviado é inválido.");
    }

    if ($tamanho <= 0 || $tamanho > 10 * 1024 * 1024) {
        throw new InvalidArgumentException("O comprovante deve ter no máximo 10 MB.");
    }

    $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
    $mimes = [
        "jpg" => ["image/jpeg"],
        "jpeg" => ["image/jpeg"],
        "png" => ["image/png"],
        "webp" => ["image/webp"],
        "pdf" => ["application/pdf"]
    ];

    if (!array_key_exists($extensao, $mimes)) {
        throw new InvalidArgumentException("Use um comprovante JPG, PNG, WEBP ou PDF.");
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($temporario);

    if (!in_array($mime, $mimes[$extensao], true)) {
        throw new InvalidArgumentException("O conteúdo do comprovante não corresponde ao formato informado.");
    }

    $diretorio = ROOT_PATH
        . DIRECTORY_SEPARATOR
        . "uploads"
        . DIRECTORY_SEPARATOR
        . "comprovantes"
        . DIRECTORY_SEPARATOR
        . "pagamentos";

    if (!is_dir($diretorio) && !mkdir($diretorio, 0755, true) && !is_dir($diretorio)) {
        throw new RuntimeException("Não foi possível criar a pasta de comprovantes.");
    }

    $nome = date("Ymd_His") . "_" . bin2hex(random_bytes(10)) . "." . $extensao;
    $destino = $diretorio . DIRECTORY_SEPARATOR . $nome;

    if (!move_uploaded_file($temporario, $destino)) {
        throw new RuntimeException("Não foi possível salvar o comprovante.");
    }

    return [
        "caminho" => "uploads/comprovantes/pagamentos/" . $nome,
        "novo" => $destino
    ];
}

function caminhoFisicoComprovante(?string $caminho): ?string
{
    $caminho = trim((string) ($caminho ?? ""));

    if ($caminho === "") {
        return null;
    }

    $normalizado = str_replace(
        ["/", "\\"],
        DIRECTORY_SEPARATOR,
        ltrim($caminho, "/\\")
    );

    $fisico = ROOT_PATH . DIRECTORY_SEPARATOR . $normalizado;
    $raizPermitida = realpath(ROOT_PATH . "/uploads/comprovantes/pagamentos");
    $diretorioArquivo = realpath(dirname($fisico));

    if (
        $raizPermitida === false
        || $diretorioArquivo === false
        || $raizPermitida !== $diretorioArquivo
    ) {
        return null;
    }

    return $fisico;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderRecebimento(false, "Método de requisição inválido.", [], 405);
}

$token = (string) ($_POST["_token"] ?? "");

if ($token === "" || !Session::validateCsrf($token)) {
    responderRecebimento(
        false,
        "Token de segurança inválido. Atualize a página e tente novamente.",
        [],
        419
    );
}

$idPagamento = (int) ($_POST["idPagamento"] ?? 0);
$formaPagamentoEnviada = trim((string) ($_POST["formaPagamento"] ?? ""));
$formasAsaas = ["PIX", "Boleto", "Cartao"];
$formasManuais = ["Dinheiro", "Transferencia"];

if ($idPagamento <= 0) {
    responderRecebimento(false, "Pagamento inválido.", [], 422);
}

$arquivoNovo = null;

try {
    $pagamentoModel = new Pagamento($db);
    $atual = $pagamentoModel->buscar($idPagamento);

    if (!$atual) {
        responderRecebimento(false, "Pagamento não encontrado.", [], 404);
    }

    if ((int) ($atual["idInscricao"] ?? 0) <= 0) {
        responderRecebimento(
            false,
            "Somente pagamentos gerados por inscrições podem ser atualizados nesta tela.",
            [],
            422
        );
    }

    $formaAtual = trim((string) ($atual["formaPagamento"] ?? "NaoDefinido"));

    if ($formaAtual === "") {
        $formaAtual = "NaoDefinido";
    }

    $formaPagamento = $formaPagamentoEnviada !== ""
        ? $formaPagamentoEnviada
        : $formaAtual;

    if (
        $formaAtual !== "NaoDefinido"
        && $formaPagamento !== $formaAtual
    ) {
        responderRecebimento(
            false,
            "O meio de pagamento já foi definido como {$formaAtual} e não pode ser alterado.",
            [
                "bloqueado" => true,
                "formaPagamento" => $formaAtual
            ],
            409
        );
    }

    if ($formaAtual !== "NaoDefinido") {
        $formaPagamento = $formaAtual;
    }

    if ($formaPagamento === "NaoDefinido") {
        responderRecebimento(false, "Selecione o meio de pagamento.", [], 422);
    }

    if (!in_array($formaPagamento, array_merge($formasAsaas, $formasManuais), true)) {
        responderRecebimento(false, "Forma de pagamento inválida.", [], 422);
    }

    $statusAtual = trim((string) ($atual["status"] ?? "Pendente"));

    if (in_array($statusAtual, ["Vencido", "Pago", "Cancelado", "Estornado"], true)) {
        $mensagemBloqueio = match ($statusAtual) {
            "Vencido" => "Este boleto está vencido. Consulte os detalhes do pagamento para verificar o prazo de tolerância.",
            "Pago" => "Este pagamento já está confirmado como Pago. Use os detalhes para solicitar um estorno.",
            "Cancelado" => "Este pagamento foi cancelado e não pode mais ser alterado.",
            "Estornado" => "Este pagamento foi estornado e não pode mais ser alterado.",
            default => "Este pagamento não pode mais ser alterado."
        };

        responderRecebimento(
            false,
            $mensagemBloqueio,
            ["bloqueado" => true, "status" => $statusAtual],
            409
        );
    }

    if (in_array($formaPagamento, $formasAsaas, true)) {
        $servicoAsaas = new AsaasPagamentoService($db, $pagamentoModel);
        $atualizado = $servicoAsaas->gerarOuRecuperarCobranca($idPagamento, $formaPagamento);

        $statusAtualizado = (string) ($atualizado["status"] ?? "Pendente");
        $formaAtualizada = (string) ($atualizado["formaPagamento"] ?? $formaPagamento);
        $ambienteAsaas = $servicoAsaas->ambiente();
        $ambienteTexto = $ambienteAsaas === "producao" ? "Produção" : "Sandbox";
        $idCobrancaAsaas = trim((string) ($atualizado["asaasPaymentId"] ?? ""));
        $idClienteAsaas = trim((string) ($atualizado["asaasCustomerId"] ?? ""));

        if ($statusAtualizado === "Pago") {
            $mensagem = "A cobrança já estava paga no Asaas {$ambienteTexto}. O pagamento e a inscrição foram sincronizados.";
        } else {
            $mensagem = match ($formaAtualizada) {
                "PIX" => "Cobrança PIX criada no Asaas {$ambienteTexto}. O QR Code e o código Copia e Cola já estão disponíveis.",
                "Boleto" => "Boleto criado no Asaas {$ambienteTexto} com o vencimento calculado pelo evento.",
                "Cartao" => "Cobrança de cartão criada no Asaas {$ambienteTexto}. Use a página segura do Asaas para realizar o pagamento.",
                default => "Cobrança recuperada ou criada no Asaas {$ambienteTexto}."
            };
        }

        responderRecebimento(
            true,
            $mensagem,
            [
                "pagamento" => $atualizado,
                "acao" => "visualizar",
                "integracao" => "Asaas",
                "asaas" => [
                    "ambiente" => $ambienteAsaas,
                    "paymentId" => $idCobrancaAsaas,
                    "customerId" => $idClienteAsaas,
                    "externalReference" => $servicoAsaas->ultimaReferenciaExterna()
                ]
            ]
        );
    }

    if (trim((string) ($atual["asaasPaymentId"] ?? "")) !== "") {
        throw new RuntimeException(
            "Este pagamento já possui uma cobrança no Asaas. Mantenha o meio integrado ou cancele a cobrança antes de registrar manualmente."
        );
    }

    $comprovanteAtual = trim((string) ($atual["comprovante"] ?? ""));
    $upload = processarComprovanteRecebimento(
        $comprovanteAtual !== "" ? $comprovanteAtual : null
    );
    $arquivoNovo = $upload["novo"];

    $pagamentoModel->atualizarRecebimento(
        $idPagamento,
        [
            "formaPagamento" => $formaPagamento,
            "status" => $_POST["status"] ?? "Pendente",
            "dataPagamento" => $_POST["dataPagamento"] ?? null,
            "observacao" => $_POST["observacao"] ?? null,
            "comprovante" => $upload["caminho"]
        ]
    );

    if (
        $arquivoNovo !== null
        && $comprovanteAtual !== ""
        && $comprovanteAtual !== $upload["caminho"]
    ) {
        $antigo = caminhoFisicoComprovante($comprovanteAtual);

        if ($antigo !== null && is_file($antigo)) {
            @unlink($antigo);
        }
    }

    $atualizado = $pagamentoModel->buscar($idPagamento);
    $statusAtualizado = (string) ($atualizado["status"] ?? "");

    $mensagem = match ($statusAtualizado) {
        "Pago" => "Recebimento manual confirmado e inscrição atualizada.",
        "Cancelado" => "Pagamento cancelado. A inscrição e a presença foram canceladas.",
        "Estornado" => "Pagamento estornado. A inscrição e a presença foram canceladas.",
        default => "Recebimento manual atualizado com sucesso."
    };

    responderRecebimento(
        true,
        $mensagem,
        [
            "pagamento" => $atualizado,
            "acao" => "listar",
            "integracao" => "Manual"
        ]
    );
} catch (InvalidArgumentException $erro) {
    if ($arquivoNovo !== null && is_file($arquivoNovo)) {
        @unlink($arquivoNovo);
    }

    responderRecebimento(false, $erro->getMessage(), [], 422);
} catch (RuntimeException $erro) {
    if ($arquivoNovo !== null && is_file($arquivoNovo)) {
        @unlink($arquivoNovo);
    }

    error_log("Falha ao processar pagamento #{$idPagamento}: " . $erro->getMessage());
    responderRecebimento(false, $erro->getMessage(), [], 422);
} catch (Throwable $erro) {
    if ($arquivoNovo !== null && is_file($arquivoNovo)) {
        @unlink($arquivoNovo);
    }

    error_log(
        "Erro em pagamento-salvar.php: "
        . $erro->getMessage()
        . " | Arquivo: "
        . $erro->getFile()
        . " | Linha: "
        . $erro->getLine()
    );

    responderRecebimento(false, "Não foi possível atualizar o pagamento.", [], 500);
}
