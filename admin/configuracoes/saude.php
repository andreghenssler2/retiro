<?php

declare(strict_types=1);

require_once __DIR__ . "/../../config/settings.php";

Session::start();
Middleware::admin();

$title = "Saúde do Sistema";
$saude = (new SaudeSistemaService($db))->resumo();

function ssEsc(mixed $v): string
{
    return htmlspecialchars(
        (string) $v,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function ssBadge(string $status): string
{
    return match ($status) {
        "ok" => "text-bg-success",
        "erro" => "text-bg-danger",
        default => "text-bg-warning"
    };
}

function ssRotulo(string $status): string
{
    return match ($status) {
        "ok" => "OK",
        "erro" => "Erro",
        default => "Atenção"
    };
}

function ssData(mixed $v): string
{
    $v = trim((string) $v);

    if ($v === "") {
        return "Ainda não registrado";
    }

    try {
        return (new DateTimeImmutable($v))->format("d/m/Y H:i:s");
    } catch (Throwable) {
        return $v;
    }
}

function ssBytes(float|int $bytes): string
{
    $bytes = max(0, (float) $bytes);
    $unidades = ["B", "KB", "MB", "GB", "TB"];
    $i = 0;

    while ($bytes >= 1024 && $i < count($unidades) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return number_format($bytes, $i === 0 ? 0 : 2, ",", ".")
        . " "
        . $unidades[$i];
}

require_once __DIR__ . "/../includes/header.php";
require_once __DIR__ . "/../includes/navbar.php";
require_once __DIR__ . "/../includes/sidebar.php";

$ap = $saude["aplicacao"];
$dbs = $saude["banco"];
$mig = $saude["migrations"];
$asaas = $saude["asaas"];
$smtp = $saude["smtp"];
$web = $saude["webhook"];
$crons = $saude["crons"];
$disco = $saude["disco"];

$cards = [
    [
        "titulo" => "Aplicação",
        "status" => $ap["status"],
        "valor" => "Versão " . ($ap["versao"] ?? "—"),
        "detalhe" => "Build " . ($ap["build"] ?? 0) . " • PHP " . ($ap["php"] ?? "—")
    ],
    [
        "titulo" => "Banco",
        "status" => $dbs["status"],
        "valor" => $dbs["banco"] ?? "Indisponível",
        "detalhe" => $dbs["versao"] ?? ($dbs["mensagem"] ?? "")
    ],
    [
        "titulo" => "Migrations",
        "status" => $mig["status"],
        "valor" => ($mig["aplicadas"] ?? 0) . "/" . ($mig["total"] ?? 0) . " aplicadas",
        "detalhe" => ($mig["pendentes"] ?? 0) . " pendente(s) • "
            . ($mig["alteradas"] ?? 0) . " alterada(s)"
    ],
    [
        "titulo" => "Disco",
        "status" => $disco["status"],
        "valor" => ssBytes($disco["livre"] ?? 0) . " livres",
        "detalhe" => number_format((float) ($disco["percentualLivre"] ?? 0), 1, ",", ".")
            . "% de "
            . ssBytes($disco["total"] ?? 0)
    ],
];
?>

<div class="content" id="content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold mb-1">
                    <i class="fa-solid fa-heart-pulse text-primary me-2"></i>
                    Saúde do Sistema
                </h2>
                <p class="text-muted mb-0">
                    Diagnóstico local sem expor senhas, tokens ou chaves.
                </p>
            </div>
            <a class="btn btn-outline-primary"
               href="<?= ssEsc(BASE_URL . "admin/configuracoes/saude.php"); ?>">
                <i class="fa-solid fa-rotate me-1"></i>
                Atualizar
            </a>
        </div>

        <div class="alert alert-info">
            Esta tela não envia e-mails e não chama a API do Asaas.
        </div>

        <div class="row g-3 mb-4">
            <?php foreach ($cards as $card): ?>
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card h-100 shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex justify-content-between gap-2">
                                <div>
                                    <div class="text-muted small mb-2">
                                        <?= ssEsc($card["titulo"]); ?>
                                    </div>
                                    <div class="fw-bold fs-5">
                                        <?= ssEsc($card["valor"]); ?>
                                    </div>
                                    <div class="small text-muted mt-1">
                                        <?= ssEsc($card["detalhe"]); ?>
                                    </div>
                                </div>
                                <span class="badge <?= ssBadge($card["status"]); ?> align-self-start">
                                    <?= ssRotulo($card["status"]); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-lg-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Asaas</div>
                    <div class="card-body">
                        <span class="badge <?= ssBadge($asaas["status"] ?? "atencao"); ?>">
                            <?= ssRotulo($asaas["status"] ?? "atencao"); ?>
                        </span>
                        <?php if (isset($asaas["mensagem"])): ?>
                            <div class="mt-3 text-danger">
                                <?= ssEsc($asaas["mensagem"]); ?>
                            </div>
                        <?php else: ?>
                            <dl class="row mt-3 mb-0">
                                <dt class="col-6">Status</dt>
                                <dd class="col-6 text-end">
                                    <?= !empty($asaas["ativo"]) ? "Ativo" : "Desativado"; ?>
                                </dd>
                                <dt class="col-6">Ambiente</dt>
                                <dd class="col-6 text-end">
                                    <?= ssEsc(ucfirst((string) ($asaas["ambiente"] ?? "—"))); ?>
                                </dd>
                                <dt class="col-6">API key</dt>
                                <dd class="col-6 text-end">
                                    <?= !empty($asaas["apiConfigurada"]) ? "Configurada" : "Ausente"; ?>
                                </dd>
                                <dt class="col-6">Webhook</dt>
                                <dd class="col-6 text-end">
                                    <?= !empty($asaas["webhookConfigurado"]) ? "Configurado" : "Ausente"; ?>
                                </dd>
                            </dl>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">SMTP</div>
                    <div class="card-body">
                        <span class="badge <?= ssBadge($smtp["status"] ?? "atencao"); ?>">
                            <?= ssRotulo($smtp["status"] ?? "atencao"); ?>
                        </span>
                        <dl class="row mt-3 mb-0">
                            <dt class="col-5">Ambiente</dt>
                            <dd class="col-7 text-end">
                                <?= ssEsc(ucfirst((string) ($smtp["ambiente"] ?? "—"))); ?>
                            </dd>
                            <dt class="col-5">Servidor</dt>
                            <dd class="col-7 text-end"><?= ssEsc($smtp["host"] ?? "—"); ?></dd>
                            <dt class="col-5">Porta</dt>
                            <dd class="col-7 text-end"><?= (int) ($smtp["porta"] ?? 0); ?></dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="col-12 col-lg-4">
                <div class="card h-100 shadow-sm border-0">
                    <div class="card-header bg-white fw-bold">Webhook Asaas</div>
                    <div class="card-body">
                        <span class="badge <?= ssBadge($web["status"] ?? "atencao"); ?>">
                            <?= ssRotulo($web["status"] ?? "atencao"); ?>
                        </span>
                        <?php if (!empty($web["ultimo"])): ?>
                            <dl class="row mt-3 mb-0">
                                <dt class="col-5">Evento</dt>
                                <dd class="col-7 text-end">
                                    <?= ssEsc($web["ultimo"]["evento"] ?? "—"); ?>
                                </dd>
                                <dt class="col-5">Recebido</dt>
                                <dd class="col-7 text-end">
                                    <?= ssEsc(ssData($web["ultimo"]["recebidoEm"] ?? null)); ?>
                                </dd>
                                <dt class="col-5">Processado</dt>
                                <dd class="col-7 text-end">
                                    <?= ssEsc(ssData($web["ultimo"]["processadoEm"] ?? null)); ?>
                                </dd>
                                <dt class="col-5">Erros 24h</dt>
                                <dd class="col-7 text-end"><?= (int) ($web["erros24h"] ?? 0); ?></dd>
                            </dl>
                        <?php else: ?>
                            <div class="mt-3 text-muted">
                                <?= ssEsc($web["mensagem"] ?? "Nenhum evento registrado."); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold">Rotinas agendadas</div>
            <div class="list-group list-group-flush">
                <?php foreach ($crons as $cron): ?>
                    <div class="list-group-item d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-semibold"><?= ssEsc($cron["rotulo"]); ?></div>
                            <div class="small text-muted">
                                <?= ssEsc(ssData($cron["executadoEm"] ?? null)); ?>
                            </div>
                        </div>
                        <span class="badge <?= ssBadge($cron["status"]); ?>">
                            <?= ssRotulo($cron["status"]); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if (!empty($mig["itensPendentes"]) || !empty($mig["itensAlterados"])): ?>
            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-white fw-bold">Migrations</div>
                <div class="card-body">
                    <?php if (!empty($mig["itensPendentes"])): ?>
                        <strong>Pendentes:</strong>
                        <ul>
                            <?php foreach ($mig["itensPendentes"] as $item): ?>
                                <li><code><?= ssEsc($item); ?></code></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if (!empty($mig["itensAlterados"])): ?>
                        <div class="alert alert-danger mb-0">
                            <strong>Migration aplicada alterada:</strong>
                            <ul class="mb-0">
                                <?php foreach ($mig["itensAlterados"] as $item): ?>
                                    <li><code><?= ssEsc($item); ?></code></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . "/../includes/footer.php"; ?>
