<?php

declare(strict_types=1);

require_once "../../config/settings.php";

Middleware::admin();

$title = "Configuração bancária";
$configuracaoBancaria = new ConfiguracaoBancaria($db);
$erroEstrutura = null;

try {
    $configuracaoBancaria->instalarEstrutura();
} catch (Throwable $erro) {
    $erroEstrutura = $erro->getMessage();
    error_log("Erro ao preparar configuração bancária: " . $erro->getMessage());
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!Session::validateCsrf($_POST["_token"] ?? null)) {
        Session::flash("error", "Token de segurança inválido. Atualize a página e tente novamente.");
        header("Location: " . BASE_URL . "admin/configuracoes/bancario.php");
        exit;
    }

    try {
        $ativo = isset($_POST["asaas_ativo"]);
        $ambiente = strtolower(trim((string) ($_POST["asaas_ambiente"] ?? "sandbox")));
        $prefixo = trim((string) ($_POST["asaas_referencia_prefixo"] ?? ""));

        if (!in_array($ambiente, ["sandbox", "producao"], true)) {
            throw new InvalidArgumentException("Selecione um ambiente válido.");
        }

        if ($ambiente === "producao" && !isset($_POST["confirmar_producao"])) {
            throw new InvalidArgumentException(
                "Para ativar Produção, confirme que as cobranças serão reais."
            );
        }

        if ($prefixo !== "" && !preg_match('/^[a-zA-Z0-9_-]{3,60}$/', $prefixo)) {
            throw new InvalidArgumentException(
                "O prefixo deve ter de 3 a 60 caracteres e usar somente letras, números, hífen ou sublinhado."
            );
        }

        $credenciais = [
            "sandbox_api" => trim((string) ($_POST["asaas_sandbox_api_key"] ?? "")),
            "sandbox_webhook" => trim((string) ($_POST["asaas_sandbox_webhook_token"] ?? "")),
            "producao_api" => trim((string) ($_POST["asaas_producao_api_key"] ?? "")),
            "producao_webhook" => trim((string) ($_POST["asaas_producao_webhook_token"] ?? "")),
        ];

        $remover = [
            "sandbox_api" => isset($_POST["remover_sandbox_api"]),
            "sandbox_webhook" => isset($_POST["remover_sandbox_webhook"]),
            "producao_api" => isset($_POST["remover_producao_api"]),
            "producao_webhook" => isset($_POST["remover_producao_webhook"]),
        ];

        foreach (["sandbox", "producao"] as $ambienteCredencial) {
            $api = $credenciais[$ambienteCredencial . "_api"];
            $token = $credenciais[$ambienteCredencial . "_webhook"];

            if ($api !== "") {
                $prefixoEsperado = $ambienteCredencial === "producao"
                    ? '$aact_prod_'
                    : '$aact_hmlg_';

                if (!str_starts_with($api, $prefixoEsperado)) {
                    throw new InvalidArgumentException(
                        "A API Key de "
                        . ($ambienteCredencial === "producao" ? "Produção" : "Sandbox")
                        . " deve começar com {$prefixoEsperado}."
                    );
                }
            }

            if ($token !== "") {
                $tamanhoToken = strlen($token);

                if ($tamanhoToken < 32 || $tamanhoToken > 255 || preg_match('/\s/', $token)) {
                    throw new InvalidArgumentException(
                        "O token do webhook de "
                        . ($ambienteCredencial === "producao" ? "Produção" : "Sandbox")
                        . " deve ter entre 32 e 255 caracteres e não pode conter espaços."
                    );
                }
            }
        }

        $credencialFicaraConfigurada = static function (
            string $identificador,
            string $tipo,
            string $ambienteCredencial
        ) use ($credenciais, $remover, $configuracaoBancaria): bool {
            if ($remover[$identificador] ?? false) {
                return false;
            }

            if (($credenciais[$identificador] ?? "") !== "") {
                return true;
            }

            return $configuracaoBancaria->credencialConfigurada($tipo, $ambienteCredencial);
        };

        if ($ativo) {
            $apiConfigurada = $credencialFicaraConfigurada(
                $ambiente . "_api",
                "api",
                $ambiente
            );
            $webhookConfigurado = $credencialFicaraConfigurada(
                $ambiente . "_webhook",
                "webhook",
                $ambiente
            );

            if (!$apiConfigurada || !$webhookConfigurado) {
                throw new InvalidArgumentException(
                    "Para ativar o Asaas em "
                    . ($ambiente === "producao" ? "Produção" : "Sandbox")
                    . ", informe a API Key e o token do webhook desse ambiente."
                );
            }
        }

        $configuracaoBancaria->salvarComCredenciais(
            $ativo,
            $ambiente,
            $prefixo,
            Auth::id(),
            $credenciais,
            $remover
        );

        Session::flash(
            "success",
            $ambiente === "producao"
                ? "Configuração e credenciais salvas. O Asaas está definido para Produção."
                : "Configuração e credenciais salvas. O Asaas está definido para Sandbox."
        );
    } catch (Throwable $erro) {
        Session::flash("error", $erro->getMessage());
    }

    header("Location: " . BASE_URL . "admin/configuracoes/bancario.php");
    exit;
}

$ambienteAtual = $configuracaoBancaria->ambiente();
$integracaoAtiva = $configuracaoBancaria->ativo();
$prefixoAtual = $configuracaoBancaria->prefixoReferencia();

$statusAmbientes = [];
foreach (["sandbox", "producao"] as $ambiente) {
    $apiKey = $configuracaoBancaria->apiKey($ambiente);
    $token = $configuracaoBancaria->webhookToken($ambiente);

    $statusAmbientes[$ambiente] = [
        "api" => $configuracaoBancaria->credencialConfigurada("api", $ambiente),
        "webhook" => $configuracaoBancaria->credencialConfigurada("webhook", $ambiente),
        "api_origem" => $configuracaoBancaria->credencialOrigem("api", $ambiente),
        "webhook_origem" => $configuracaoBancaria->credencialOrigem("webhook", $ambiente),
        "api_tipo_correto" => $apiKey === ""
            || ($ambiente === "sandbox" && str_starts_with($apiKey, '$aact_hmlg_'))
            || ($ambiente === "producao" && str_starts_with($apiKey, '$aact_prod_')),
        "url" => $configuracaoBancaria->apiUrl($ambiente),
    ];
}

$origensRotulo = [
    "banco" => "Banco criptografado",
    "arquivo" => "Arquivo/configuração antiga",
    "ausente" => "Não configurada",
    "removida" => "Removida pelo painel",
];

$webhookUrl = BASE_URL . "api/asaas/webhook.php";
$mensagemSucesso = Session::getFlash("success");
$mensagemErro = Session::getFlash("error");

$pageStyles = [
    THEME_CSS . "admin/configuracoes/bancario.css?v=" . VERSION,
];

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";
?>

<div class="content" id="content">
    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa-solid fa-building-columns text-primary me-1"></i>
                Configuração bancária
            </h2>
            <p class="text-muted mb-0">
                Informe as credenciais, escolha o ambiente e controle a integração com o Asaas.
            </p>
        </div>

        <a href="<?= BASE_URL ?>admin/financeiro/pagamentos.php" class="btn btn-outline-primary">
            <i class="fa-solid fa-money-check-dollar me-1"></i>
            Ver pagamentos
        </a>
    </div>

    <?php if ($mensagemSucesso): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-1"></i>
            <?= htmlspecialchars($mensagemSucesso, ENT_QUOTES, "UTF-8") ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <?php if ($mensagemErro): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-exclamation me-1"></i>
            <?= htmlspecialchars($mensagemErro, ENT_QUOTES, "UTF-8") ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
        </div>
    <?php endif; ?>

    <?php if ($erroEstrutura): ?>
        <div class="alert alert-danger">
            <strong>Não foi possível preparar a configuração bancária.</strong>
            <div class="small mt-1">
                <?= htmlspecialchars($erroEstrutura, ENT_QUOTES, "UTF-8") ?>
            </div>
        </div>
    <?php endif; ?>

    <form method="post" id="formConfiguracaoBancaria" autocomplete="off">
        <input type="hidden" name="_token" value="<?= htmlspecialchars(Session::csrf(), ENT_QUOTES, "UTF-8") ?>">

        <div class="row g-4">
            <div class="col-xl-8">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                            <div>
                                <h5 class="mb-1">Integração Asaas</h5>
                                <small class="text-muted">Controle geral da geração de cobranças.</small>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch"
                                    name="asaas_ativo" id="asaasAtivo"
                                    <?= $integracaoAtiva ? "checked" : "" ?>>
                                <label class="form-check-label fw-semibold" for="asaasAtivo">
                                    Integração ativa
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <label class="form-label fw-semibold mb-3">Ambiente utilizado pelo sistema</label>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="ambiente-card <?= $ambienteAtual === "sandbox" ? "selecionado" : "" ?>"
                                    for="ambienteSandbox">
                                    <input class="form-check-input ambiente-radio" type="radio"
                                        name="asaas_ambiente" id="ambienteSandbox" value="sandbox"
                                        <?= $ambienteAtual === "sandbox" ? "checked" : "" ?>>
                                    <span class="ambiente-icon text-warning">
                                        <i class="fa-solid fa-flask"></i>
                                    </span>
                                    <span>
                                        <strong class="d-block">Sandbox</strong>
                                        <small class="text-muted">
                                            Ambiente de testes. Não gera cobranças reais.
                                        </small>
                                    </span>
                                </label>
                            </div>

                            <div class="col-md-6">
                                <label class="ambiente-card ambiente-producao <?= $ambienteAtual === "producao" ? "selecionado" : "" ?>"
                                    for="ambienteProducao">
                                    <input class="form-check-input ambiente-radio" type="radio"
                                        name="asaas_ambiente" id="ambienteProducao" value="producao"
                                        <?= $ambienteAtual === "producao" ? "checked" : "" ?>>
                                    <span class="ambiente-icon text-success">
                                        <i class="fa-solid fa-shield-halved"></i>
                                    </span>
                                    <span>
                                        <strong class="d-block">Produção</strong>
                                        <small class="text-muted">
                                            Cria clientes e cobranças reais no Asaas.
                                        </small>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div id="confirmacaoProducao" class="alert alert-danger mt-3 mb-0"
                            <?= $ambienteAtual === "producao" ? "" : 'style="display:none"' ?>>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="confirmar_producao"
                                    id="confirmarProducao" <?= $ambienteAtual === "producao" ? "checked" : "" ?>>
                                <label class="form-check-label" for="confirmarProducao">
                                    Confirmo que este ambiente criará <strong>cobranças reais</strong>.
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <?php foreach (["sandbox" => "Sandbox", "producao" => "Produção"] as $ambiente => $rotulo): ?>
                    <?php $status = $statusAmbientes[$ambiente]; ?>
                    <div class="card shadow-sm mb-4 credenciais-card <?= $ambiente === "producao" ? "credenciais-producao" : "" ?>">
                        <div class="card-header bg-white py-3">
                            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
                                <div>
                                    <h5 class="mb-1">
                                        <i class="fa-solid <?= $ambiente === "producao" ? "fa-shield-halved text-success" : "fa-flask text-warning" ?> me-1"></i>
                                        Credenciais de <?= $rotulo ?>
                                    </h5>
                                    <small class="text-muted">
                                        Cole as credenciais do painel Asaas deste ambiente.
                                    </small>
                                </div>
                                <span class="badge <?= $status["api"] && $status["webhook"] && $status["api_tipo_correto"] ? "text-bg-success" : "text-bg-warning" ?>">
                                    <?= $status["api"] && $status["webhook"] && $status["api_tipo_correto"] ? "Completa" : "Revisar" ?>
                                </span>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="credencial-bloco mb-4">
                                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                                    <label for="asaas<?= ucfirst($ambiente) ?>ApiKey" class="form-label fw-semibold mb-0">
                                        API Key
                                    </label>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($origensRotulo[$status["api_origem"]] ?? "", ENT_QUOTES, "UTF-8") ?>
                                    </small>
                                </div>
                                <div class="input-group">
                                    <input type="password" class="form-control campo-credencial"
                                        name="asaas_<?= $ambiente ?>_api_key"
                                        id="asaas<?= ucfirst($ambiente) ?>ApiKey"
                                        spellcheck="false" autocapitalize="none" autocomplete="new-password"
                                        placeholder="<?= $status["api"] ? "Configurada — deixe em branco para manter" : "Cole a API Key" ?>">
                                    <button class="btn btn-outline-secondary alternar-senha" type="button"
                                        data-target="asaas<?= ucfirst($ambiente) ?>ApiKey"
                                        aria-label="Mostrar ou ocultar API Key">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    Deve começar com <code><?= $ambiente === "producao" ? '$aact_prod_' : '$aact_hmlg_' ?></code>.
                                    O campo vazio mantém a chave atual.
                                </div>
                                <?php if ($status["api"]): ?>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input remover-credencial" type="checkbox"
                                            name="remover_<?= $ambiente ?>_api"
                                            id="remover<?= ucfirst($ambiente) ?>Api"
                                            data-target="asaas<?= ucfirst($ambiente) ?>ApiKey">
                                        <label class="form-check-label text-danger" for="remover<?= ucfirst($ambiente) ?>Api">
                                            Remover a API Key salva
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="credencial-bloco">
                                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-2">
                                    <label for="asaas<?= ucfirst($ambiente) ?>WebhookToken" class="form-label fw-semibold mb-0">
                                        Token do webhook
                                    </label>
                                    <small class="text-muted">
                                        <?= htmlspecialchars($origensRotulo[$status["webhook_origem"]] ?? "", ENT_QUOTES, "UTF-8") ?>
                                    </small>
                                </div>
                                <div class="input-group">
                                    <input type="password" class="form-control campo-credencial"
                                        name="asaas_<?= $ambiente ?>_webhook_token"
                                        id="asaas<?= ucfirst($ambiente) ?>WebhookToken"
                                        spellcheck="false" autocapitalize="none" autocomplete="new-password"
                                        placeholder="<?= $status["webhook"] ? "Configurado — deixe em branco para manter" : "Cole o token do webhook" ?>">
                                    <button class="btn btn-outline-secondary alternar-senha" type="button"
                                        data-target="asaas<?= ucfirst($ambiente) ?>WebhookToken"
                                        aria-label="Mostrar ou ocultar token">
                                        <i class="fa-regular fa-eye"></i>
                                    </button>
                                </div>
                                <div class="form-text">
                                    Use exatamente o mesmo token cadastrado no webhook do painel Asaas.
                                </div>
                                <?php if ($status["webhook"]): ?>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input remover-credencial" type="checkbox"
                                            name="remover_<?= $ambiente ?>_webhook"
                                            id="remover<?= ucfirst($ambiente) ?>Webhook"
                                            data-target="asaas<?= ucfirst($ambiente) ?>WebhookToken">
                                        <label class="form-check-label text-danger" for="remover<?= ucfirst($ambiente) ?>Webhook">
                                            Remover o token do webhook salvo
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-1">Identificação das cobranças</h5>
                        <small class="text-muted">Evita colisões entre instalações e ambientes.</small>
                    </div>
                    <div class="card-body">
                        <label for="asaasReferenciaPrefixo" class="form-label">Prefixo da referência externa</label>
                        <input type="text" class="form-control" name="asaas_referencia_prefixo"
                            id="asaasReferenciaPrefixo" maxlength="60"
                            value="<?= htmlspecialchars($prefixoAtual, ENT_QUOTES, "UTF-8") ?>"
                            placeholder="<?php echo Title::getAtual()->getSigla(); ?>">
                        <div class="form-text">
                            Use somente letras, números, hífen ou sublinhado. Quando ficar vazio, o domínio atual será utilizado.
                        </div>
                    </div>
                </div>

                <div class="alert alert-info">
                    <i class="fa-solid fa-lock me-1"></i>
                    As credenciais são criptografadas antes de serem gravadas no banco e nunca são preenchidas novamente no formulário.
                    Faça backup também do arquivo <code>config/.bancario.key</code>.
                </div>

                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary px-4" <?= $erroEstrutura ? "disabled" : "" ?>>
                        <i class="fa-solid fa-floppy-disk me-1"></i>
                        Salvar configuração bancária
                    </button>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="card shadow-sm mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Ambiente em uso</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <div class="status-ambiente <?= $ambienteAtual === "producao" ? "producao" : "sandbox" ?>">
                                <i class="fa-solid <?= $ambienteAtual === "producao" ? "fa-shield-halved" : "fa-flask" ?>"></i>
                            </div>
                            <div>
                                <span class="badge <?= $ambienteAtual === "producao" ? "text-bg-success" : "text-bg-warning" ?> mb-1">
                                    <?= $ambienteAtual === "producao" ? "Produção" : "Sandbox" ?>
                                </span>
                                <div class="small text-muted">
                                    <?= $integracaoAtiva ? "Integração habilitada" : "Integração desativada" ?>
                                </div>
                            </div>
                        </div>

                        <div class="small text-muted mb-1">Endpoint da API</div>
                        <code class="d-block text-break"><?= htmlspecialchars($statusAmbientes[$ambienteAtual]["url"], ENT_QUOTES, "UTF-8") ?></code>
                    </div>
                </div>

                <?php foreach (["sandbox" => "Sandbox", "producao" => "Produção"] as $ambiente => $rotulo): ?>
                    <?php $status = $statusAmbientes[$ambiente]; ?>
                    <div class="card shadow-sm mb-4 <?= $ambienteAtual === $ambiente ? "border-primary" : "" ?>">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <strong><?= $rotulo ?></strong>
                            <?php if ($ambienteAtual === $ambiente): ?>
                                <span class="badge text-bg-primary">Selecionado</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <span>API Key</span>
                                <span class="badge <?= $status["api"] && $status["api_tipo_correto"] ? "text-bg-success" : "text-bg-danger" ?>">
                                    <?= $status["api"] && $status["api_tipo_correto"] ? "Configurada" : "Revisar" ?>
                                </span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <span>Token do webhook</span>
                                <span class="badge <?= $status["webhook"] ? "text-bg-success" : "text-bg-danger" ?>">
                                    <?= $status["webhook"] ? "Configurado" : "Não configurado" ?>
                                </span>
                            </div>

                            <?php if ($status["api"] && !$status["api_tipo_correto"]): ?>
                                <div class="alert alert-warning small mt-3 mb-0">
                                    A chave configurada não corresponde ao ambiente <?= strtolower($rotulo) ?>.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">Webhook</h5>
                    </div>
                    <div class="card-body">
                        <label class="form-label small text-muted">URL cadastrada no Asaas</label>
                        <div class="input-group">
                            <input type="text" class="form-control" id="webhookUrl" readonly
                                value="<?= htmlspecialchars($webhookUrl, ENT_QUOTES, "UTF-8") ?>">
                            <button class="btn btn-outline-secondary" type="button" id="copiarWebhook">
                                <i class="fa-regular fa-copy"></i>
                            </button>
                        </div>
                        <div class="form-text">
                            O token desse webhook deve ser o mesmo informado no formulário do ambiente ativo.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>

    <div class="alert alert-warning mt-4">
        <i class="fa-solid fa-triangle-exclamation me-1"></i>
        <strong>Atenção:</strong> trocar entre Sandbox e Produção não transfere clientes ou cobranças.
        Cada ambiente do Asaas possui registros independentes.
    </div>
</div>

<?php
$pageScripts = [
    THEME_JS . "admin/configuracoes/bancario.js?v=" . VERSION,
];
require_once "../includes/footer.php";
?>
