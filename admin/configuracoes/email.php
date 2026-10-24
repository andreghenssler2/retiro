<?php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

require_once "../../config/settings.php";
require_once "../../lib/vendor/autoload.php";

Middleware::admin();

$title = "Configuração de e-mail";

$mensagemSucesso = Session::getFlash("success");
$mensagemErro = Session::getFlash("error");

$emailTeste = "";
$nomeTeste = (string) (
    $_SESSION["user"]["nome"]
    ?? "Administrador"
);

try {
    $stmtUsuarioTeste = $db->prepare("
        SELECT
            nome,
            email
        FROM usuarios
        WHERE id = :id
        LIMIT 1
    ");

    $stmtUsuarioTeste->execute([
        ":id" => (int) (Auth::id() ?? 0)
    ]);

    $usuarioTeste = $stmtUsuarioTeste->fetch(
        PDO::FETCH_ASSOC
    );

    if (is_array($usuarioTeste)) {
        $nomeTeste = trim(
            (string) (
                $usuarioTeste["nome"]
                ?? $nomeTeste
            )
        );

        $emailTeste = trim(
            (string) (
                $usuarioTeste["email"]
                ?? ""
            )
        );
    }
} catch (Throwable $erroUsuarioTeste) {
    error_log(
        "Erro ao carregar destinatário do teste SMTP: "
        . $erroUsuarioTeste->getMessage()
    );
}

/*
|--------------------------------------------------------------------------
| Ambiente atualmente usado pelos envios
|--------------------------------------------------------------------------
|
| A coluna "ativo" continua identificando o tipo:
|   1 = Produção
|   0 = Sandbox
|
| A coluna "selecionado" indica qual das duas configurações
| está realmente em uso pelo sistema.
|
*/
$ambienteAtivo = "producao";

try {
    $stmtAmbienteAtivo = $db->query("
        SELECT ativo
        FROM email_config
        WHERE selecionado = 1
        ORDER BY idEmailConfig DESC
        LIMIT 1
    ");

    $registroAtivo = $stmtAmbienteAtivo->fetch(PDO::FETCH_ASSOC);

    if (is_array($registroAtivo)) {
        $ambienteAtivo = (int) $registroAtivo["ativo"] === 0
            ? "sandbox"
            : "producao";
    }
} catch (Throwable $erroAmbiente) {
    error_log(
        "Erro ao identificar ambiente ativo de e-mail: "
        . $erroAmbiente->getMessage()
    );

    $mensagemErro =
        "Não foi possível identificar o ambiente ativo de e-mail. "
        . "Confirme se a migração da coluna selecionado foi executada.";
}

/*
|--------------------------------------------------------------------------
| Ambiente que está sendo visualizado/editado
|--------------------------------------------------------------------------
|
| Quando não existe ?ambiente=..., abre automaticamente o ambiente
| que está selecionado no banco.
|
*/
$ambiente = strtolower(
    trim(
        (string) (
            $_GET["ambiente"]
            ?? $ambienteAtivo
        )
    )
);

if (!in_array($ambiente, ["producao", "sandbox"], true)) {
    $ambiente = $ambienteAtivo;
}

$tipoAmbiente = $ambiente === "producao" ? 1 : 0;

$rotuloAmbiente = $tipoAmbiente === 1
    ? "Produção"
    : "Sandbox (teste)";

$rotuloAmbienteAtivo = $ambienteAtivo === "sandbox"
    ? "Sandbox (teste)"
    : "Produção";

$configuracao = [
    "idEmailConfig" => 0,
    "host" => "",
    "username" => "",
    "senha" => "",
    "porta" => $tipoAmbiente === 1 ? 465 : 587,
    "encryption" => $tipoAmbiente === 1 ? "ssl" : "tls",
    "remetente" => "",
    "ativo" => $tipoAmbiente,
    "selecionado" => 0
];

try {
    $stmt = $db->prepare("
        SELECT
            idEmailConfig,
            host,
            username,
            senha,
            porta,
            encryption,
            remetente,
            ativo,
            selecionado
        FROM email_config
        WHERE ativo = :ativo
        ORDER BY idEmailConfig DESC
        LIMIT 1
    ");

    $stmt->execute([
        ":ativo" => $tipoAmbiente
    ]);

    $registro = $stmt->fetch(PDO::FETCH_ASSOC);

    if (is_array($registro)) {
        $configuracao = array_merge(
            $configuracao,
            $registro
        );
    }
} catch (Throwable $erro) {
    error_log(
        "Erro ao carregar configuração de e-mail: "
        . $erro->getMessage()
    );

    $mensagemErro =
        "Não foi possível carregar a configuração de e-mail.";
}

/*
|--------------------------------------------------------------------------
| POST
|--------------------------------------------------------------------------
*/
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $acao = trim(
        (string) ($_POST["acao"] ?? "salvar")
    );

    if (!Session::validateCsrf($_POST["_token"] ?? "")) {
        Session::flash(
            "error",
            "Token de segurança inválido. Atualize a página e tente novamente."
        );

        header(
            "Location: "
            . BASE_URL
            . "admin/configuracoes/email.php"
        );
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Alterar ambiente usado nos envios
    |--------------------------------------------------------------------------
    */
    if ($acao === "selecionar_ambiente") {
        $novoAmbiente = strtolower(
            trim(
                (string) (
                    $_POST["ambiente_ativo"]
                    ?? "producao"
                )
            )
        );

        if (!in_array(
            $novoAmbiente,
            ["producao", "sandbox"],
            true
        )) {
            $novoAmbiente = "producao";
        }

        $novoTipo = $novoAmbiente === "producao"
            ? 1
            : 0;

        $novoRotulo = $novoTipo === 1
            ? "Produção"
            : "Sandbox (teste)";

        try {
            /*
             * Só permite ativar um ambiente que já possua
             * uma configuração SMTP salva.
             */
            $stmtSelecionar = $db->prepare("
                SELECT idEmailConfig
                FROM email_config
                WHERE ativo = :ativo
                ORDER BY idEmailConfig DESC
                LIMIT 1
            ");

            $stmtSelecionar->execute([
                ":ativo" => $novoTipo
            ]);

            $idSelecionado = (int) (
                $stmtSelecionar->fetchColumn()
                ?: 0
            );

            if ($idSelecionado <= 0) {
                throw new InvalidArgumentException(
                    "Salve primeiro a configuração SMTP de "
                    . $novoRotulo
                    . " antes de ativá-la."
                );
            }

            $db->beginTransaction();

            $db->exec("
                UPDATE email_config
                SET selecionado = 0
            ");

            $stmtAtivar = $db->prepare("
                UPDATE email_config
                SET selecionado = 1
                WHERE idEmailConfig = :id
            ");

            $stmtAtivar->execute([
                ":id" => $idSelecionado
            ]);

            $db->commit();

            Session::flash(
                "success",
                "Ambiente de envio alterado para "
                . $novoRotulo
                . "."
            );
        } catch (Throwable $erro) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            error_log(
                "Erro ao alterar ambiente de e-mail: "
                . $erro->getMessage()
            );

            Session::flash(
                "error",
                $erro instanceof InvalidArgumentException
                    ? $erro->getMessage()
                    : "Não foi possível alterar o ambiente de envio."
            );
        }

        header(
            "Location: "
            . BASE_URL
            . "admin/configuracoes/email.php?ambiente="
            . rawurlencode($novoAmbiente)
        );
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Testar configuração SMTP salva
    |--------------------------------------------------------------------------
    |
    | O teste usa o ambiente informado no botão, mesmo que ele não seja
    | o ambiente atualmente selecionado para os envios do sistema.
    |
    */
    if ($acao === "testar_email") {
        $ambienteTeste = strtolower(
            trim(
                (string) (
                    $_POST["ambiente_teste"]
                    ?? "producao"
                )
            )
        );

        if (!in_array(
            $ambienteTeste,
            ["producao", "sandbox"],
            true
        )) {
            $ambienteTeste = "producao";
        }

        $tipoTeste = $ambienteTeste === "producao"
            ? 1
            : 0;

        $rotuloTeste = $tipoTeste === 1
            ? "Produção"
            : "Sandbox (teste)";

        try {
            if (
                $emailTeste === ""
                || filter_var(
                    $emailTeste,
                    FILTER_VALIDATE_EMAIL
                ) === false
            ) {
                throw new InvalidArgumentException(
                    "O administrador logado não possui "
                    . "um e-mail válido para receber o teste."
                );
            }

            $stmtConfigTeste = $db->prepare("
                SELECT
                    idEmailConfig,
                    host,
                    username,
                    senha,
                    porta,
                    encryption,
                    remetente
                FROM email_config
                WHERE ativo = :ativo
                ORDER BY idEmailConfig DESC
                LIMIT 1
            ");

            $stmtConfigTeste->execute([
                ":ativo" => $tipoTeste
            ]);

            $configTeste = $stmtConfigTeste->fetch(
                PDO::FETCH_ASSOC
            );

            if (!is_array($configTeste)) {
                throw new InvalidArgumentException(
                    "Nenhuma configuração SMTP de "
                    . $rotuloTeste
                    . " foi encontrada. Salve a configuração primeiro."
                );
            }

            $hostTeste = trim(
                (string) (
                    $configTeste["host"]
                    ?? ""
                )
            );
            $usuarioSmtpTeste = trim(
                (string) (
                    $configTeste["username"]
                    ?? ""
                )
            );
            $senhaTeste = (string) (
                $configTeste["senha"]
                ?? ""
            );
            $portaTeste = (int) (
                $configTeste["porta"]
                ?? 0
            );
            $criptografiaTeste = strtolower(
                trim(
                    (string) (
                        $configTeste["encryption"]
                        ?? ""
                    )
                )
            );
            $remetenteTeste = trim(
                (string) (
                    $configTeste["remetente"]
                    ?? "Sistema de Eventos"
                )
            );

            if (
                $hostTeste === ""
                || $usuarioSmtpTeste === ""
                || $senhaTeste === ""
                || $portaTeste <= 0
            ) {
                throw new InvalidArgumentException(
                    "A configuração SMTP de "
                    . $rotuloTeste
                    . " está incompleta."
                );
            }

            if (!in_array(
                $criptografiaTeste,
                ["ssl", "tls", ""],
                true
            )) {
                throw new InvalidArgumentException(
                    "A criptografia SMTP configurada é inválida."
                );
            }

            $mailTeste = new PHPMailer(true);

            $mailTeste->isSMTP();
            $mailTeste->Host = $hostTeste;
            $mailTeste->Port = $portaTeste;
            $mailTeste->SMTPAuth = true;
            $mailTeste->Username = $usuarioSmtpTeste;
            $mailTeste->Password = $senhaTeste;
            $mailTeste->CharSet = "UTF-8";
            $mailTeste->Timeout = 20;

            if ($criptografiaTeste !== "") {
                $mailTeste->SMTPSecure =
                    $criptografiaTeste;
            }

            $mailTeste->setFrom(
                $usuarioSmtpTeste,
                $remetenteTeste !== ""
                    ? $remetenteTeste
                    : Title::getAtual()->getNome()
            );

            $mailTeste->addReplyTo(
                $usuarioSmtpTeste,
                $remetenteTeste !== ""
                    ? $remetenteTeste
                    : Title::getAtual()->getNome()
            );

            $mailTeste->addAddress(
                $emailTeste,
                $nomeTeste
            );

            $mailTeste->isHTML(true);
            $mailTeste->Subject =
                "Teste de e-mail SMTP - "
                . $rotuloTeste;

            $dataTeste = date(
                "d/m/Y H:i:s"
            );

            $mailTeste->Body = "
                <div style=\"font-family:Arial,sans-serif;line-height:1.6\">
                    <h2>Teste de configuração de e-mail</h2>
                    <p>
                        Se você recebeu esta mensagem, a configuração
                        SMTP de <strong>"
                        . htmlspecialchars(
                            $rotuloTeste,
                            ENT_QUOTES,
                            "UTF-8"
                        )
                        . "</strong> está funcionando.
                    </p>
                    <p>
                        <strong>Data do teste:</strong> "
                        . htmlspecialchars(
                            $dataTeste,
                            ENT_QUOTES,
                            "UTF-8"
                        )
                        . "
                    </p>
                    <p>
                        <strong>Servidor:</strong> "
                        . htmlspecialchars(
                            $hostTeste,
                            ENT_QUOTES,
                            "UTF-8"
                        )
                        . ":"
                        . $portaTeste
                        . "
                    </p>
                    <hr>
                    <small>
                        Mensagem enviada automaticamente pelo
                        Sistema de Eventos.
                    </small>
                </div>
            ";

            $mailTeste->AltBody =
                "Teste de configuração SMTP - "
                . $rotuloTeste
                . PHP_EOL
                . "Data: "
                . $dataTeste
                . PHP_EOL
                . "Servidor: "
                . $hostTeste
                . ":"
                . $portaTeste;

            $mailTeste->send();

            Session::flash(
                "success",
                "E-mail de teste de "
                . $rotuloTeste
                . " enviado com sucesso para "
                . $emailTeste
                . "."
            );
        } catch (Throwable $erroTeste) {
            $mensagemSmtp = isset($mailTeste)
                && $mailTeste instanceof PHPMailer
                && trim($mailTeste->ErrorInfo) !== ""
                    ? trim($mailTeste->ErrorInfo)
                    : $erroTeste->getMessage();

            error_log(
                "Erro no teste SMTP de "
                . $rotuloTeste
                . ": "
                . $mensagemSmtp
            );

            Session::flash(
                "error",
                "Falha no teste SMTP de "
                . $rotuloTeste
                . ": "
                . $mensagemSmtp
            );
        }

        header(
            "Location: "
            . BASE_URL
            . "admin/configuracoes/email.php?ambiente="
            . rawurlencode($ambienteTeste)
        );
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Salvar configuração SMTP
    |--------------------------------------------------------------------------
    */
    $ambientePost = strtolower(
        trim(
            (string) (
                $_POST["ambiente"]
                ?? "producao"
            )
        )
    );

    if (!in_array(
        $ambientePost,
        ["producao", "sandbox"],
        true
    )) {
        $ambientePost = "producao";
    }

    $tipoSalvar = $ambientePost === "producao"
        ? 1
        : 0;

    $rotuloSalvar = $tipoSalvar === 1
        ? "Produção"
        : "Sandbox (teste)";

    $host = trim((string) ($_POST["host"] ?? ""));
    $username = trim(
        (string) ($_POST["username"] ?? "")
    );
    $senhaInformada = (string) (
        $_POST["senha"] ?? ""
    );
    $porta = (int) ($_POST["porta"] ?? 0);
    $encryption = strtolower(
        trim(
            (string) (
                $_POST["encryption"]
                ?? ""
            )
        )
    );
    $remetente = trim(
        (string) ($_POST["remetente"] ?? Title::getAtual()->getNome())
    );

    try {
        if ($host === "") {
            throw new InvalidArgumentException(
                "Informe o host do servidor SMTP."
            );
        }

        if (strlen($host) > 100) {
            throw new InvalidArgumentException(
                "O host deve possuir no máximo 100 caracteres."
            );
        }

        if ($username === "") {
            throw new InvalidArgumentException(
                "Informe o usuário do servidor SMTP."
            );
        }

        if (strlen($username) > 100) {
            throw new InvalidArgumentException(
                "O usuário deve possuir no máximo 100 caracteres."
            );
        }

        if ($porta < 1 || $porta > 65535) {
            throw new InvalidArgumentException(
                "Informe uma porta SMTP válida."
            );
        }

        if (!in_array(
            $encryption,
            ["ssl", "tls", ""],
            true
        )) {
            throw new InvalidArgumentException(
                "Selecione uma criptografia válida."
            );
        }

        if ($remetente === "") {
            throw new InvalidArgumentException(
                "Informe o nome do remetente."
            );
        }

        if (strlen($remetente) > 100) {
            throw new InvalidArgumentException(
                "O remetente deve possuir no máximo 100 caracteres."
            );
        }

        $stmtExistente = $db->prepare("
            SELECT
                idEmailConfig,
                senha,
                selecionado
            FROM email_config
            WHERE ativo = :ativo
            ORDER BY idEmailConfig DESC
            LIMIT 1
        ");

        $stmtExistente->execute([
            ":ativo" => $tipoSalvar
        ]);

        $existente = $stmtExistente->fetch(
            PDO::FETCH_ASSOC
        );

        $idEmailConfig = is_array($existente)
            ? (int) (
                $existente["idEmailConfig"]
                ?? 0
            )
            : 0;

        $senha = $senhaInformada;

        if (
            $idEmailConfig > 0
            && $senha === ""
        ) {
            $senha = (string) (
                $existente["senha"]
                ?? ""
            );
        }

        if ($senha === "") {
            throw new InvalidArgumentException(
                "Informe a senha do servidor SMTP."
            );
        }

        if (strlen($senha) > 100) {
            throw new InvalidArgumentException(
                "A senha deve possuir no máximo 100 caracteres."
            );
        }

        if ($idEmailConfig > 0) {
            /*
             * Não altera "selecionado" ao editar os dados.
             * Dessa forma, editar Produção não muda o ambiente
             * que está atualmente em uso, e vice-versa.
             */
            $stmtSalvar = $db->prepare("
                UPDATE email_config
                SET
                    host = :host,
                    username = :username,
                    senha = :senha,
                    porta = :porta,
                    encryption = :encryption,
                    remetente = :remetente
                WHERE idEmailConfig = :id
            ");

            $stmtSalvar->execute([
                ":host" => $host,
                ":username" => $username,
                ":senha" => $senha,
                ":porta" => $porta,
                ":encryption" => $encryption,
                ":remetente" => $remetente,
                ":id" => $idEmailConfig
            ]);
        } else {
            /*
             * Se ainda não existe nenhuma configuração selecionada,
             * a primeira configuração criada passa a ser a ativa.
             */
            $temSelecionada = (int) $db->query("
                SELECT COUNT(*)
                FROM email_config
                WHERE selecionado = 1
            ")->fetchColumn() > 0;

            $selecionadoNovo = $temSelecionada
                ? 0
                : 1;

            $stmtSalvar = $db->prepare("
                INSERT INTO email_config (
                    host,
                    username,
                    senha,
                    porta,
                    encryption,
                    remetente,
                    ativo,
                    selecionado
                ) VALUES (
                    :host,
                    :username,
                    :senha,
                    :porta,
                    :encryption,
                    :remetente,
                    :ativo,
                    :selecionado
                )
            ");

            $stmtSalvar->execute([
                ":host" => $host,
                ":username" => $username,
                ":senha" => $senha,
                ":porta" => $porta,
                ":encryption" => $encryption,
                ":remetente" => $remetente,
                ":ativo" => $tipoSalvar,
                ":selecionado" => $selecionadoNovo
            ]);
        }

        Session::flash(
            "success",
            "Configuração de e-mail de "
            . $rotuloSalvar
            . " salva com sucesso."
        );
    } catch (Throwable $erro) {
        error_log(
            "Erro ao salvar configuração de e-mail: "
            . $erro->getMessage()
        );

        Session::flash(
            "error",
            $erro instanceof InvalidArgumentException
                ? $erro->getMessage()
                : "Não foi possível salvar a configuração de e-mail."
        );
    }

    header(
        "Location: "
        . BASE_URL
        . "admin/configuracoes/email.php?ambiente="
        . rawurlencode($ambientePost)
    );
    exit;
}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";
?>

<div class="content" id="content">

    <div
        class="d-flex flex-column flex-lg-row
            justify-content-between align-items-lg-center
            gap-3 mb-4"
    >
        <div>
            <h2 class="fw-bold mb-1">
                <i
                    class="fa-solid fa-envelope
                        text-primary me-2"
                ></i>
                Configuração de e-mail
            </h2>

            <p class="text-muted mb-0">
                Informe os dados do servidor SMTP
                utilizado pelo sistema.
            </p>
        </div>

        <form
            method="post"
            id="formAmbienteEmail"
            class="border rounded-3 bg-white
                px-3 py-2 shadow-sm"
        >
            <input
                type="hidden"
                name="_token"
                value="<?= htmlspecialchars(
                    Session::csrf(),
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>"
            >

            <input
                type="hidden"
                name="acao"
                value="selecionar_ambiente"
            >

            <input
                type="hidden"
                name="ambiente_ativo"
                id="ambienteAtivoValor"
                value="<?= htmlspecialchars(
                    $ambienteAtivo,
                    ENT_QUOTES,
                    "UTF-8"
                ); ?>"
            >

            <div class="form-check form-switch mb-0">
                <input
                    class="form-check-input"
                    type="checkbox"
                    role="switch"
                    id="ambienteSandbox"
                    <?= $ambienteAtivo === "sandbox"
                        ? "checked"
                        : ""; ?>
                >

                <label
                    class="form-check-label fw-semibold"
                    for="ambienteSandbox"
                >
                    Envios Sandbox (teste)
                </label>
            </div>

            <small class="text-muted d-block mt-1">
                Desmarcado: envio padrão (produção).
            </small>
        </form>
    </div>

    <?php if ($mensagemSucesso): ?>
        <div
            class="alert alert-success
                alert-dismissible fade show"
            role="alert"
        >
            <i
                class="fa-solid
                    fa-circle-check me-1"
            ></i>

            <?= htmlspecialchars(
                (string) $mensagemSucesso,
                ENT_QUOTES,
                "UTF-8"
            ); ?>

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
            class="alert alert-danger
                alert-dismissible fade show"
            role="alert"
        >
            <i
                class="fa-solid
                    fa-circle-exclamation me-1"
            ></i>

            <?= htmlspecialchars(
                (string) $mensagemErro,
                ENT_QUOTES,
                "UTF-8"
            ); ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Fechar"
            ></button>
        </div>
    <?php endif; ?>

    <div
        class="alert <?= $ambienteAtivo === "sandbox"
            ? "alert-warning"
            : "alert-success"; ?>"
    >
        <div class="d-flex align-items-start gap-2">
            <i
                class="fa-solid <?= $ambienteAtivo === "sandbox"
                    ? "fa-flask"
                    : "fa-server"; ?> mt-1"
            ></i>

            <div>
                <strong>
                    Ambiente de envio ativo:
                    <?= htmlspecialchars(
                        $rotuloAmbienteAtivo,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </strong>

                <?php if ($ambiente !== $ambienteAtivo): ?>
                    <div class="small mt-1">
                        Você está editando a configuração
                        de <?= htmlspecialchars(
                            $rotuloAmbiente,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <div
                class="d-flex justify-content-between
                    align-items-center gap-3 flex-wrap"
            >
                <h5 class="mb-0">
                    Dados do servidor SMTP
                </h5>

                <div
                    class="d-flex flex-wrap
                        align-items-center gap-2"
                >
                    <span
                        class="badge <?= $tipoAmbiente === 1
                            ? "text-bg-success"
                            : "text-bg-warning"; ?>"
                    >
                        <?= htmlspecialchars(
                            $rotuloAmbiente,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </span>

                    <form
                        method="post"
                        class="m-0"
                    >
                        <input
                            type="hidden"
                            name="_token"
                            value="<?= htmlspecialchars(
                                Session::csrf(),
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>"
                        >

                        <input
                            type="hidden"
                            name="acao"
                            value="testar_email"
                        >

                        <input
                            type="hidden"
                            name="ambiente_teste"
                            value="<?= htmlspecialchars(
                                $ambiente,
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>"
                        >

                        <button
                            type="submit"
                            class="btn btn-sm
                                btn-outline-primary"
                            <?= (int) $configuracao["idEmailConfig"] <= 0
                                ? "disabled"
                                : ""; ?>
                            title="Testar a configuração SMTP salva deste ambiente"
                        >
                            <i
                                class="fa-solid
                                    fa-paper-plane me-1"
                            ></i>
                            Testar envio
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="card-body">

            <div
                class="alert alert-light
                    border d-flex flex-wrap
                    justify-content-between
                    align-items-center gap-2"
            >
                <div>
                    <i
                        class="fa-solid
                            fa-envelope-circle-check
                            text-primary me-1"
                    ></i>
                    <strong>E-mail de teste:</strong>

                    <?php if ($emailTeste !== ""): ?>
                        <?= htmlspecialchars(
                            $emailTeste,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    <?php else: ?>
                        <span class="text-danger">
                            não cadastrado
                        </span>
                    <?php endif; ?>
                </div>

                <small class="text-muted">
                    O botão testa a configuração salva
                    de <?= htmlspecialchars(
                        $rotuloAmbiente,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?> sem alterar o ambiente ativo.
                </small>
            </div>

            <form
                method="post"
                autocomplete="off"
            >
                <input
                    type="hidden"
                    name="_token"
                    value="<?= htmlspecialchars(
                        Session::csrf(),
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                >

                <input
                    type="hidden"
                    name="acao"
                    value="salvar"
                >

                <input
                    type="hidden"
                    name="ambiente"
                    value="<?= htmlspecialchars(
                        $ambiente,
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>"
                >

                <div class="row g-3">

                    <div class="col-md-8">
                        <label
                            for="host"
                            class="form-label"
                        >
                            Host
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="host"
                            name="host"
                            maxlength="100"
                            required
                            value="<?= htmlspecialchars(
                                (string) $configuracao["host"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>"
                            placeholder="mail.exemplo.com.br"
                        >
                    </div>

                    <div class="col-md-4">
                        <label
                            for="porta"
                            class="form-label"
                        >
                            Porta
                        </label>

                        <input
                            type="number"
                            class="form-control"
                            id="porta"
                            name="porta"
                            min="1"
                            max="65535"
                            required
                            value="<?= (int) $configuracao["porta"]; ?>"
                        >
                    </div>

                    <div class="col-md-6">
                        <label
                            for="username"
                            class="form-label"
                        >
                            Usuário
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="username"
                            name="username"
                            maxlength="100"
                            required
                            autocomplete="username"
                            value="<?= htmlspecialchars(
                                (string) $configuracao["username"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>"
                        >
                    </div>

                    <div class="col-md-6">
                        <label
                            for="senha"
                            class="form-label"
                        >
                            Senha
                        </label>

                        <input
                            type="password"
                            class="form-control"
                            id="senha"
                            name="senha"
                            maxlength="100"
                            autocomplete="new-password"
                            <?= (int) $configuracao["idEmailConfig"] === 0
                                ? "required"
                                : ""; ?>
                        >

                        <?php if (
                            (int) $configuracao["idEmailConfig"] > 0
                        ): ?>
                            <div class="form-text">
                                Deixe em branco para manter
                                a senha atual desta configuração.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label
                            for="encryption"
                            class="form-label"
                        >
                            Criptografia
                        </label>

                        <select
                            class="form-select"
                            id="encryption"
                            name="encryption"
                        >
                            <option
                                value="ssl"
                                <?= strtolower(
                                    (string) $configuracao["encryption"]
                                ) === "ssl"
                                    ? "selected"
                                    : ""; ?>
                            >
                                SSL
                            </option>

                            <option
                                value="tls"
                                <?= strtolower(
                                    (string) $configuracao["encryption"]
                                ) === "tls"
                                    ? "selected"
                                    : ""; ?>
                            >
                                TLS
                            </option>

                            <option
                                value=""
                                <?= trim(
                                    (string) $configuracao["encryption"]
                                ) === ""
                                    ? "selected"
                                    : ""; ?>
                            >
                                Nenhuma
                            </option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label
                            for="remetente"
                            class="form-label"
                        >
                            Remetente
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="remetente"
                            name="remetente"
                            maxlength="100"
                            required
                            value="<?= htmlspecialchars(
                                (string) $configuracao["remetente"],
                                ENT_QUOTES,
                                "UTF-8"
                            ); ?>"
                            placeholder="Sistema de Eventos"
                        >
                    </div>

                </div>

                <div
                    class="d-flex
                        justify-content-end mt-4"
                >
                    <button
                        type="submit"
                        class="btn <?= $tipoAmbiente === 1
                            ? "btn-success"
                            : "btn-warning"; ?>"
                    >
                        <i
                            class="fa-solid
                                fa-floppy-disk me-1"
                        ></i>
                        Salvar
                        <?= htmlspecialchars(
                            $rotuloAmbiente,
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </button>
                </div>

            </form>
        </div>
    </div>

</div>

<script>
document.addEventListener(
    "DOMContentLoaded",
    function () {
        const seletor = document.getElementById(
            "ambienteSandbox"
        );
        const valor = document.getElementById(
            "ambienteAtivoValor"
        );
        const formulario = document.getElementById(
            "formAmbienteEmail"
        );

        if (!seletor || !valor || !formulario) {
            return;
        }

        seletor.addEventListener(
            "change",
            function () {
                valor.value = seletor.checked
                    ? "sandbox"
                    : "producao";

                seletor.disabled = true;
                formulario.submit();
            }
        );
    }
);
</script>

<?php
require_once "../includes/footer.php";
?>
