<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: text/html; charset=utf-8");

function escaparViewInscricao(mixed $valor): string
{
    if (is_array($valor) || is_object($valor)) {
        return "";
    }

    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

function mostrarViewInscricao(mixed $valor, string $vazio = "Não informado"): string
{
    if (is_array($valor) || is_object($valor)) {
        return '<span class="text-muted">' . escaparViewInscricao($vazio) . '</span>';
    }

    $texto = trim((string) ($valor ?? ""));

    return $texto !== ""
        ? escaparViewInscricao($texto)
        : '<span class="text-muted">' . escaparViewInscricao($vazio) . '</span>';
}

function dataViewInscricao(mixed $valor, bool $comHora = false): string
{
    $texto = trim((string) ($valor ?? ""));

    if (
        $texto === ""
        || str_starts_with($texto, "0000-00-00")
    ) {
        return '<span class="text-muted">Não informada</span>';
    }

    $formatos = $comHora
        ? ["!Y-m-d H:i:s", "!Y-m-d\\TH:i", "!Y-m-d"]
        : ["!Y-m-d", "!d/m/Y"];

    foreach ($formatos as $formato) {
        $data = DateTime::createFromFormat($formato, $texto);
        $erros = DateTime::getLastErrors();

        if (
            $data instanceof DateTime
            && ($erros === false || (($erros["warning_count"] ?? 0) === 0 && ($erros["error_count"] ?? 0) === 0))
            && (int) $data->format("Y") > 0
        ) {
            return escaparViewInscricao($data->format($comHora ? "d/m/Y H:i" : "d/m/Y"));
        }
    }

    return '<span class="text-muted">Não informada</span>';
}

function moedaViewInscricao(mixed $valor): string
{
    return "R$ " . number_format((float) ($valor ?? 0), 2, ",", ".");
}

function badgeViewInscricao(string $texto, array $classes): string
{
    $texto = trim($texto) !== "" ? trim($texto) : "Não informado";
    $classe = $classes[$texto] ?? "text-bg-secondary";

    return '<span class="badge '
        . escaparViewInscricao($classe)
        . '">'
        . escaparViewInscricao($texto)
        . '</span>';
}

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0): ?>
    <div class="alert alert-warning mb-0">Inscrição inválida.</div>
    <?php exit; ?>
<?php endif;

try {
    $inscricao = new Inscricao($db);
    $dados = $inscricao->buscar($id);

    if (!$dados): ?>
        <div class="alert alert-warning mb-0">Inscrição não encontrada.</div>
        <?php exit; ?>
    <?php endif;

    $status = (string) ($dados["status"] ?? "Pendente");
    $pagamento = (string) ($dados["pagamento"] ?? "Pendente");
    $camisetaAtiva = (int) ($dados["camiseta_ativa"] ?? 0) === 1;
    ?>

    <div class="container-fluid px-0">
        <div class="row g-3 mb-3">
            <div class="col-md-4">
                <div class="border rounded p-3 h-100 bg-light">
                    <small class="text-muted d-block">Inscrição</small>
                    <strong>#<?= (int) $dados["idInscricao"] ?></strong>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100 bg-light">
                    <small class="text-muted d-block">Status</small>
                    <?= badgeViewInscricao($status, [
                        "Pendente" => "text-bg-warning",
                        "Confirmada" => "text-bg-success",
                        "Cancelada" => "text-bg-danger"
                    ]) ?>
                </div>
            </div>
            <div class="col-md-4">
                <div class="border rounded p-3 h-100 bg-light">
                    <small class="text-muted d-block">Pagamento</small>
                    <?= badgeViewInscricao($pagamento, [
                        "Pendente" => "text-bg-warning",
                        "Pago" => "text-bg-success",
                        "Cancelado" => "text-bg-danger",
                        "Estornado" => "text-bg-secondary"
                    ]) ?>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="fa fa-calendar me-1"></i> Evento
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <small class="text-muted d-block">Evento</small>
                        <strong><?= mostrarViewInscricao($dados["titulo"] ?? "") ?></strong>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Data</small>
                        <?= dataViewInscricao($dados["data_inicio"] ?? "") ?>
                    </div>
                    <div class="col-md-8">
                        <small class="text-muted d-block">Local</small>
                        <?= mostrarViewInscricao($dados["local"] ?? "") ?>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Código do evento</small>
                        <?= mostrarViewInscricao($dados["idEvento"] ?? "") ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="fa fa-user me-1"></i> Participante
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Nome</small>
                        <strong><?= mostrarViewInscricao($dados["nome"] ?? "") ?></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">CPF</small>
                        <?= mostrarViewInscricao($dados["cpf"] ?? "") ?>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">RG</small>
                        <?= mostrarViewInscricao($dados["rg"] ?? "") ?>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">E-mail</small>
                        <?= mostrarViewInscricao($dados["email"] ?? "") ?>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Telefone</small>
                        <?= mostrarViewInscricao($dados["telefone"] ?? "") ?>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Nascimento</small>
                        <?= dataViewInscricao($dados["data_nascimento"] ?? "") ?>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Comunidade</small>
                        <?= mostrarViewInscricao($dados["igreja"] ?? "") ?>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Cidade</small>
                        <?= mostrarViewInscricao($dados["cidade"] ?? "") ?>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block">Estado</small>
                        <?= mostrarViewInscricao($dados["estado"] ?? "") ?>
                    </div>
                    <?php if ($camisetaAtiva): ?>
                        <div class="col-md-2">
                            <small class="text-muted d-block">Camiseta</small>
                            <span class="badge text-bg-info">
                                <?= mostrarViewInscricao($dados["camiseta"] ?? "") ?>
                            </span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="fa fa-dollar-sign me-1"></i> Dados financeiros
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Valor</small>
                        <strong><?= moedaViewInscricao($dados["valor"] ?? 0) ?></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Valor recebido</small>
                        <strong><?= moedaViewInscricao($dados["valor_pago"] ?? 0) ?></strong>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Forma</small>
                        <?= mostrarViewInscricao($dados["forma_pagamento"] ?? "") ?>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Código</small>
                        <?= mostrarViewInscricao($dados["codigo_pagamento"] ?? "") ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card mb-3">
            <div class="card-header bg-white fw-semibold">
                <i class="fa fa-check-circle me-1"></i> Controle
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <small class="text-muted d-block">Presença</small>
                        <span class="badge <?= (int) ($dados["presenca"] ?? 0) === 1 ? "text-bg-success" : "text-bg-secondary" ?>">
                            <?= (int) ($dados["presenca"] ?? 0) === 1 ? "Sim" : "Não" ?>
                        </span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Certificado</small>
                        <span class="badge <?= (int) ($dados["certificado"] ?? 0) === 1 ? "text-bg-success" : "text-bg-secondary" ?>">
                            <?= (int) ($dados["certificado"] ?? 0) === 1 ? "Sim" : "Não" ?>
                        </span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Criada em</small>
                        <?= dataViewInscricao($dados["criado_em"] ?? "", true) ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header bg-white fw-semibold">
                <i class="fa fa-comment me-1"></i> Observações
            </div>
            <div class="card-body">
                <?= nl2br(mostrarViewInscricao($dados["observacoes"] ?? "")) ?>
            </div>
        </div>
    </div>

<?php
} catch (Throwable $erro) {
    error_log(
        "Erro em inscricao-view.php: "
        . $erro->getMessage()
        . " | Linha: "
        . $erro->getLine()
    );
    http_response_code(500);
    ?>
    <div class="alert alert-danger mb-0">
        Não foi possível carregar os dados da inscrição.
    </div>
    <?php
}
