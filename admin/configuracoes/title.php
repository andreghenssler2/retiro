<?php

declare(strict_types=1);

require_once "../../config/settings.php";

Middleware::admin();

$title = "Configuração do site";

function titleEscapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function titleUrlImagem(string $valor, string $padrao): string
{
    $valor = trim(str_replace("\\", "/", $valor));

    if ($valor === "") {
        return THEME_IMG . $padrao;
    }

    if (preg_match("~^https?://~i", $valor)) {
        return $valor;
    }

    $valor = ltrim($valor, "/");

    if (str_starts_with($valor, "theme/img/")) {
        return BASE_URL . $valor;
    }

    if (str_starts_with($valor, "upload/img/")) {
        return BASE_URL . $valor;
    }

    $nomeArquivo = basename($valor);

    if (
        defined("ROOT_PATH")
        && is_file(ROOT_PATH . "/theme/img/" . $nomeArquivo)
    ) {
        return THEME_IMG . rawurlencode($nomeArquivo);
    }

    if (
        defined("ROOT_PATH")
        && defined("ASSETS_IMG")
        && is_file(ROOT_PATH . "/upload/img/" . $nomeArquivo)
    ) {
        return ASSETS_IMG . rawurlencode($nomeArquivo);
    }

    return THEME_IMG . rawurlencode($nomeArquivo);
}

/**
 * @param array<string,array<int,string>> $tiposPermitidos
 */
function titleSalvarUpload(
    string $campo,
    string $prefixo,
    array $tiposPermitidos,
    int $limiteBytes
): ?array {
    if (
        !isset($_FILES[$campo])
        || !is_array($_FILES[$campo])
        || (int) ($_FILES[$campo]["error"] ?? UPLOAD_ERR_NO_FILE)
            === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    $arquivo = $_FILES[$campo];
    $erroUpload = (int) ($arquivo["error"] ?? UPLOAD_ERR_NO_FILE);

    if ($erroUpload !== UPLOAD_ERR_OK) {
        $mensagens = [
            UPLOAD_ERR_INI_SIZE => "O arquivo excede o limite do servidor.",
            UPLOAD_ERR_FORM_SIZE => "O arquivo excede o limite permitido.",
            UPLOAD_ERR_PARTIAL => "O arquivo foi enviado parcialmente.",
            UPLOAD_ERR_NO_TMP_DIR => "A pasta temporária não está disponível.",
            UPLOAD_ERR_CANT_WRITE => "Não foi possível gravar o arquivo.",
            UPLOAD_ERR_EXTENSION => "Uma extensão do PHP bloqueou o envio."
        ];

        throw new RuntimeException(
            $mensagens[$erroUpload]
            ?? "Não foi possível enviar o arquivo."
        );
    }

    $temporario = (string) ($arquivo["tmp_name"] ?? "");
    $tamanho = (int) ($arquivo["size"] ?? 0);
    $nomeOriginal = (string) ($arquivo["name"] ?? "");

    if (
        $temporario === ""
        || !is_uploaded_file($temporario)
    ) {
        throw new RuntimeException(
            "O arquivo enviado não é válido."
        );
    }

    if ($tamanho <= 0 || $tamanho > $limiteBytes) {
        throw new InvalidArgumentException(
            "O arquivo deve possuir no máximo "
            . number_format(
                $limiteBytes / 1024 / 1024,
                0,
                ",",
                "."
            )
            . " MB."
        );
    }

    $extensao = strtolower(
        (string) pathinfo($nomeOriginal, PATHINFO_EXTENSION)
    );

    if (!array_key_exists($extensao, $tiposPermitidos)) {
        throw new InvalidArgumentException(
            "Formato de arquivo não permitido."
        );
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = strtolower((string) $finfo->file($temporario));

    if (!in_array($mime, $tiposPermitidos[$extensao], true)) {
        throw new InvalidArgumentException(
            "O conteúdo do arquivo não corresponde ao formato informado."
        );
    }

    $diretorio = ROOT_PATH . "/theme/img";

    if (
        !is_dir($diretorio)
        && !mkdir($diretorio, 0755, true)
        && !is_dir($diretorio)
    ) {
        throw new RuntimeException(
            "Não foi possível criar a pasta theme/img."
        );
    }

    if (!is_writable($diretorio)) {
        throw new RuntimeException(
            "A pasta theme/img não possui permissão de escrita."
        );
    }

    /*
     * Usa nomes fixos, sem data ou código aleatório:
     * favicon.ico, favicon.png, site-imagem.jpg, site-imagem.webp etc.
     */
    $nomeNovo = $prefixo . "." . $extensao;
    $destino = $diretorio . "/" . $nomeNovo;

    /*
     * Remove versões anteriores do mesmo arquivo com outra extensão.
     * Exemplo: ao trocar favicon.png por favicon.ico, o PNG antigo é removido.
     */
    foreach (glob($diretorio . "/" . $prefixo . ".*") ?: [] as $arquivoAnterior) {
        if (is_file($arquivoAnterior) && realpath($arquivoAnterior) !== realpath($temporario)) {
            @unlink($arquivoAnterior);
        }
    }

    if (!move_uploaded_file($temporario, $destino)) {
        throw new RuntimeException(
            "Não foi possível salvar o arquivo em theme/img."
        );
    }

    @chmod($destino, 0644);

    return [
        "nome" => $nomeNovo,
        "caminho" => $destino
    ];
}

$configuracao = [
    "idTitulo" => 0,
    "nome" => "",
    "sigla" => "",
    "descricao" => "",
    "keyword" => "",
    "favicon" => "",
    "imagem" => ""
];

$mensagemSucesso = Session::getFlash("success");
$mensagemErro = Session::getFlash("error");

try {
    $stmt = $db->query("
        SELECT
            idTitulo,
            nome,
            sigla,
            descricao,
            keyword,
            favicon,
            imagem
        FROM titulo
        ORDER BY idTitulo DESC
        LIMIT 1
    ");

    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (is_array($registro)) {
        $configuracao = array_merge(
            $configuracao,
            $registro
        );
    }
} catch (Throwable $erro) {
    error_log(
        "Erro ao carregar configuração do site: "
        . $erro->getMessage()
    );

    $mensagemErro =
        "Não foi possível carregar as informações do site.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!Session::validateCsrf($_POST["_token"] ?? "")) {
        Session::flash(
            "error",
            "Token de segurança inválido. Atualize a página e tente novamente."
        );

        header(
            "Location: "
            . BASE_URL
            . "admin/configuracoes/title.php"
        );
        exit;
    }

    $idTitulo = max(
        0,
        (int) ($_POST["idTitulo"] ?? $configuracao["idTitulo"])
    );

    $nome = trim((string) ($_POST["nome"] ?? ""));
    $sigla = trim((string) ($_POST["sigla"] ?? ""));
    $descricao = trim((string) ($_POST["descricao"] ?? ""));
    $keyword = trim((string) ($_POST["keyword"] ?? ""));

    $favicon = trim(
        (string) ($configuracao["favicon"] ?? "")
    );
    $imagem = trim(
        (string) ($configuracao["imagem"] ?? "")
    );

    $novosArquivos = [];

    try {
        if ($nome === "") {
            throw new InvalidArgumentException(
                "Informe o nome do site."
            );
        }

        if (mb_strlen($nome, "UTF-8") > 150) {
            throw new InvalidArgumentException(
                "O nome deve possuir no máximo 150 caracteres."
            );
        }

        if ($sigla === "") {
            throw new InvalidArgumentException(
                "Informe a sigla do site."
            );
        }

        if (mb_strlen($sigla, "UTF-8") > 20) {
            throw new InvalidArgumentException(
                "A sigla deve possuir no máximo 20 caracteres."
            );
        }

        if (mb_strlen($descricao, "UTF-8") > 250) {
            throw new InvalidArgumentException(
                "A descrição deve possuir no máximo 250 caracteres."
            );
        }

        if (mb_strlen($keyword, "UTF-8") > 5000) {
            throw new InvalidArgumentException(
                "As palavras-chave ultrapassaram o tamanho permitido."
            );
        }

        $uploadFavicon = titleSalvarUpload(
            "favicon",
            "favicon",
            [
                "ico" => [
                    "image/x-icon",
                    "image/vnd.microsoft.icon",
                    "image/icon",
                    "application/octet-stream"
                ],
                "png" => ["image/png"],
                "jpg" => ["image/jpeg"],
                "jpeg" => ["image/jpeg"],
                "webp" => ["image/webp"]
            ],
            2 * 1024 * 1024
        );

        if ($uploadFavicon !== null) {
            $favicon = (string) $uploadFavicon["nome"];
            $novosArquivos[] =
                (string) $uploadFavicon["caminho"];
        }

        $uploadImagem = titleSalvarUpload(
            "imagem",
            "site-imagem",
            [
                "png" => ["image/png"],
                "jpg" => ["image/jpeg"],
                "jpeg" => ["image/jpeg"],
                "webp" => ["image/webp"]
            ],
            5 * 1024 * 1024
        );

        if ($uploadImagem !== null) {
            $imagem = (string) $uploadImagem["nome"];
            $novosArquivos[] =
                (string) $uploadImagem["caminho"];
        }

        $db->beginTransaction();

        if ($idTitulo > 0) {
            $stmtSalvar = $db->prepare("
                UPDATE titulo
                SET
                    nome = :nome,
                    sigla = :sigla,
                    descricao = :descricao,
                    keyword = :keyword,
                    favicon = :favicon,
                    imagem = :imagem
                WHERE idTitulo = :id
            ");

            $stmtSalvar->execute([
                ":nome" => $nome,
                ":sigla" => $sigla,
                ":descricao" => $descricao !== ""
                    ? $descricao
                    : null,
                ":keyword" => $keyword !== ""
                    ? $keyword
                    : null,
                ":favicon" => $favicon !== ""
                    ? $favicon
                    : null,
                ":imagem" => $imagem !== ""
                    ? $imagem
                    : null,
                ":id" => $idTitulo
            ]);
        } else {
            $stmtSalvar = $db->prepare("
                INSERT INTO titulo (
                    nome,
                    sigla,
                    descricao,
                    keyword,
                    favicon,
                    imagem
                ) VALUES (
                    :nome,
                    :sigla,
                    :descricao,
                    :keyword,
                    :favicon,
                    :imagem
                )
            ");

            $stmtSalvar->execute([
                ":nome" => $nome,
                ":sigla" => $sigla,
                ":descricao" => $descricao !== ""
                    ? $descricao
                    : null,
                ":keyword" => $keyword !== ""
                    ? $keyword
                    : null,
                ":favicon" => $favicon !== ""
                    ? $favicon
                    : null,
                ":imagem" => $imagem !== ""
                    ? $imagem
                    : null
            ]);
        }

        $db->commit();

        Session::flash(
            "success",
            "Informações do site atualizadas com sucesso."
        );
    } catch (Throwable $erro) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        foreach ($novosArquivos as $novoArquivo) {
            if (is_file($novoArquivo)) {
                @unlink($novoArquivo);
            }
        }

        error_log(
            "Erro ao salvar configuração do site: "
            . $erro->getMessage()
        );

        Session::flash(
            "error",
            $erro instanceof InvalidArgumentException
                ? $erro->getMessage()
                : "Não foi possível salvar as informações do site."
        );
    }

    header(
        "Location: "
        . BASE_URL
        . "admin/configuracoes/title.php"
    );
    exit;
}

$faviconUrl = titleUrlImagem(
    (string) $configuracao["favicon"],
    "image2.png"
);

$imagemUrl = titleUrlImagem(
    (string) $configuracao["imagem"],
    "image.png"
);

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";
?>

<div class="content" id="content">

    <div class="mb-4">
        <h2 class="fw-bold mb-1">
            <i class="fa-solid fa-heading text-primary me-2"></i>
            Informações do site
        </h2>

        <p class="text-muted mb-0">
            Altere o nome, a identificação e as imagens utilizadas pelo sistema.
        </p>
    </div>

    <?php if ($mensagemSucesso): ?>
        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >
            <i class="fa-solid fa-circle-check me-1"></i>

            <?= titleEscapar((string) $mensagemSucesso); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Fechar"
            ></button>
        </div>
    <?php endif; ?>

    <?php if ($mensagemErro): ?>
        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >
            <i class="fa-solid fa-circle-exclamation me-1"></i>

            <?= titleEscapar((string) $mensagemErro); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Fechar"
            ></button>
        </div>
    <?php endif; ?>

    <form
        method="post"
        enctype="multipart/form-data"
        autocomplete="off"
    >
        <input
            type="hidden"
            name="_token"
            value="<?= titleEscapar(Session::csrf()); ?>"
        >

        <input
            type="hidden"
            name="idTitulo"
            value="<?= (int) $configuracao["idTitulo"]; ?>"
        >

        <div class="row g-4">

            <div class="col-xl-8">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            Identificação do site
                        </h5>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-md-8">
                                <label
                                    for="nome"
                                    class="form-label"
                                >
                                    Nome do site
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="nome"
                                    name="nome"
                                    maxlength="150"
                                    required
                                    value="<?= titleEscapar(
                                        (string) $configuracao["nome"]
                                    ); ?>"
                                >
                            </div>

                            <div class="col-md-4">
                                <label
                                    for="sigla"
                                    class="form-label"
                                >
                                    Sigla
                                </label>

                                <input
                                    type="text"
                                    class="form-control"
                                    id="sigla"
                                    name="sigla"
                                    maxlength="20"
                                    required
                                    value="<?= titleEscapar(
                                        (string) $configuracao["sigla"]
                                    ); ?>"
                                >
                            </div>

                            <div class="col-12">
                                <label
                                    for="descricao"
                                    class="form-label"
                                >
                                    Descrição
                                </label>

                                <textarea
                                    class="form-control"
                                    id="descricao"
                                    name="descricao"
                                    rows="3"
                                    maxlength="250"
                                ><?= titleEscapar(
                                    (string) $configuracao["descricao"]
                                ); ?></textarea>

                                <div class="form-text">
                                    Usada na descrição das páginas e nos mecanismos de busca.
                                </div>
                            </div>

                            <div class="col-12">
                                <label
                                    for="keyword"
                                    class="form-label"
                                >
                                    Palavras-chave
                                </label>

                                <textarea
                                    class="form-control"
                                    id="keyword"
                                    name="keyword"
                                    rows="3"
                                    maxlength="5000"
                                    placeholder="evento, inscrição, certificado"
                                ><?= titleEscapar(
                                    (string) $configuracao["keyword"]
                                ); ?></textarea>

                                <div class="form-text">
                                    Separe as palavras-chave por vírgulas.
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            Imagens do site
                        </h5>
                    </div>

                    <div class="card-body">
                        <div class="row g-4">

                            <div class="col-md-6">
                                <label
                                    for="favicon"
                                    class="form-label"
                                >
                                    Favicon
                                </label>

                                <input
                                    type="file"
                                    class="form-control"
                                    id="favicon"
                                    name="favicon"
                                    accept=".ico,.png,.jpg,.jpeg,.webp"
                                >

                                <div class="form-text">
                                    ICO, PNG, JPG ou WEBP. Máximo de 2 MB.
                                </div>

                                <div class="mt-3">
                                    <div class="small text-muted mb-2">
                                        Favicon atual
                                    </div>

                                    <div
                                        class="border rounded bg-light d-flex align-items-center justify-content-center"
                                        style="width: 150px; height: auto;"
                                    >
                                        <img
                                            src="<?= titleEscapar($faviconUrl); ?>"
                                            alt="Favicon atual"
                                            style="max-width:100%;object-fit:contain;"
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label
                                    for="imagem"
                                    class="form-label"
                                >
                                    Imagem do site
                                </label>

                                <input
                                    type="file"
                                    class="form-control"
                                    id="imagem"
                                    name="imagem"
                                    accept=".png,.jpg,.jpeg,.webp"
                                >

                                <div class="form-text">
                                    PNG, JPG ou WEBP. Máximo de 5 MB.
                                </div>

                                <div class="mt-3">
                                    <div class="small text-muted mb-2">
                                        Imagem atual
                                    </div>

                                    <div
                                        class="border rounded bg-light d-flex align-items-center justify-content-center p-2"
                                        style="min-height:160px;"
                                    >
                                        <img
                                            src="<?= titleEscapar($imagemUrl); ?>"
                                            alt="Imagem atual do site"
                                            style="max-width:100%;max-height:150px;object-fit:contain;"
                                        >
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="alert alert-info mt-4 mb-0">
                            <i class="fa-solid fa-folder-open me-1"></i>
                            Os arquivos enviados serão armazenados em
                            <strong>theme/img/</strong>.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card shadow-sm border-0 sticky-xl-top" style="top:20px;">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            Resumo
                        </h5>
                    </div>

                    <div class="card-body">
                        <dl class="mb-0">
                            <dt class="text-muted small">
                                Nome atual
                            </dt>
                            <dd class="fw-semibold">
                                <?= titleEscapar(
                                    (string) (
                                        $configuracao["nome"]
                                        ?: "Não informado"
                                    )
                                ); ?>
                            </dd>

                            <dt class="text-muted small mt-3">
                                Sigla atual
                            </dt>
                            <dd class="fw-semibold">
                                <?= titleEscapar(
                                    (string) (
                                        $configuracao["sigla"]
                                        ?: "Não informada"
                                    )
                                ); ?>
                            </dd>

                            <dt class="text-muted small mt-3">
                                Pasta de uploads
                            </dt>
                            <dd class="mb-0">
                                <code>theme/img/</code>
                            </dd>
                        </dl>
                    </div>

                    <div class="card-footer bg-white">
                        <button
                            type="submit"
                            class="btn btn-primary w-100"
                        >
                            <i class="fa-solid fa-floppy-disk me-1"></i>
                            Salvar informações
                        </button>
                    </div>
                </div>
            </div>

        </div>
    </form>

</div>

<?php require_once "../includes/footer.php"; ?>
