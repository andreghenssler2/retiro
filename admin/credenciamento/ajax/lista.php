<?php

declare(strict_types=1);

require_once '../../../config/settings.php';

Middleware::auth();

$idEvento = max(0, (int) ($_GET['evento'] ?? 0));
$pesquisa = trim((string) ($_GET['pesquisa'] ?? ''));

if ($idEvento <= 0) {
    ?>
    <div class="alert alert-warning mb-0">
        Selecione um evento para carregar a lista de chamada.
    </div>
    <?php
    exit;
}

try {
    $credenciamento = new Credenciamento($db);
    $evento = $credenciamento->buscarEvento($idEvento);

    if (!$evento) {
        throw new RuntimeException('Evento não encontrado.');
    }

    $encerrado = $credenciamento->eventoEncerrado($evento);

    if ($encerrado) {
        $credenciamento->finalizarEvento($idEvento);
    }

    $inscritos = $credenciamento->listarInscritos($idEvento, $pesquisa);
    $resumo = $credenciamento->resumo($idEvento);
    $situacaoEvento = $credenciamento->situacaoEvento($evento);
    $pagamentoObrigatorio =
        (int) ($evento['pagamento_obrigatorio'] ?? 0) === 1;
    $fimEvento = $credenciamento->dataHoraFim($evento);
} catch (Throwable $erro) {
    error_log('Erro no credenciamento: ' . $erro->getMessage());
    ?>
    <div class="alert alert-danger mb-0">
        Não foi possível carregar o credenciamento.
    </div>
    <?php
    exit;
}

function badgePresencaCredenciamento(string $status): string
{
    return match ($status) {
        'Presente' => 'text-bg-success',
        'Ausente' => 'text-bg-danger',
        default => 'text-bg-warning'
    };
}

function badgePagamentoCredenciamento(string $status): string
{
    return match ($status) {
        'Pago' => 'text-bg-success',
        'Vencido' => 'text-bg-danger',
        'Cancelado' => 'text-bg-dark',
        'Estornado' => 'text-bg-secondary',
        default => 'text-bg-warning'
    };
}
?>

<div class="credenciamento-evento card shadow-sm border-0 mb-4">
    <div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-3 align-items-start">
            <div>
                <h4 class="fw-bold mb-1">
                    <?= htmlspecialchars((string) $evento['titulo'], ENT_QUOTES, 'UTF-8') ?>
                </h4>
                <div class="text-muted small">
                    <i class="fa fa-calendar me-1"></i>
                    Encerramento: <?= $fimEvento->format('d/m/Y H:i') ?>
                    <?php if (!empty($evento['local'])): ?>
                        <span class="mx-2">•</span>
                        <i class="fa fa-location-dot me-1"></i>
                        <?= htmlspecialchars((string) $evento['local'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </div>
            </div>

            <span class="badge <?= $encerrado ? 'text-bg-dark' : ($situacaoEvento === 'Em andamento' ? 'text-bg-success' : 'text-bg-primary') ?> fs-6">
                <?= htmlspecialchars($situacaoEvento, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>

        <?php if ($encerrado): ?>
            <div class="alert alert-secondary mt-3 mb-0">
                <i class="fa fa-lock me-1"></i>
                O evento foi encerrado. Participantes não marcados foram registrados como
                <strong>Ausentes</strong> e a presença não pode mais ser alterada.
            </div>
        <?php elseif ($pagamentoObrigatorio): ?>
            <div class="alert alert-info mt-3 mb-0">
                <i class="fa fa-circle-info me-1"></i>
                A presença somente pode ser confirmada para inscrições com pagamento
                <strong>Pago</strong>.
            </div>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3 mb-4 credenciamento-resumo">
    <div class="col-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Inscritos</div>
                <div class="fs-3 fw-bold"><?= $resumo['total'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Presentes</div>
                <div class="fs-3 fw-bold text-success"><?= $resumo['presentes'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Aguardando</div>
                <div class="fs-3 fw-bold text-warning"><?= $resumo['pendentes'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Ausentes</div>
                <div class="fs-3 fw-bold text-danger"><?= $resumo['ausentes'] ?></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-xl">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="text-muted small">Canceladas</div>
                <div class="fs-3 fw-bold text-secondary"><?= $resumo['canceladas'] ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white py-3">
        <div class="d-flex justify-content-between align-items-center gap-3">
            <h5 class="mb-0">
                <i class="fa fa-list-check me-1"></i>
                Lista de chamada
            </h5>
            <span class="text-muted small">
                <?= count($inscritos) ?> registro(s) exibido(s)
            </span>
        </div>
    </div>

    <div class="list-group list-group-flush credenciamento-lista">
        <?php if (!$inscritos): ?>
            <div class="text-center text-muted py-5">
                <i class="fa fa-user-slash fa-3x mb-3"></i>
                <div>Nenhum participante encontrado.</div>
            </div>
        <?php else: ?>
            <?php foreach ($inscritos as $indice => $item): ?>
                <?php
                $statusInscricao = (string) ($item['inscricaoStatus'] ?? 'Pendente');
                $statusPagamento = (string) ($item['pagamentoStatus'] ?? 'Pendente');
                $statusPresenca = (string) ($item['presencaStatus'] ?? 'Pendente');
                $presente =
                    (int) ($item['presenca'] ?? 0) === 1
                    || $statusPresenca === 'Presente';
                $inscricaoCancelada = $statusInscricao === 'Cancelada';
                $pagamentoLiberado =
                    !$pagamentoObrigatorio
                    || $statusPagamento === 'Pago';
                $podeAlterar =
                    !$encerrado
                    && !$inscricaoCancelada
                    && $pagamentoLiberado;

                $motivoBloqueio = '';

                if ($encerrado) {
                    $motivoBloqueio = 'Evento encerrado';
                } elseif ($inscricaoCancelada) {
                    $motivoBloqueio = 'Inscrição cancelada';
                } elseif (!$pagamentoLiberado) {
                    $motivoBloqueio = 'Aguardando pagamento';
                }
                ?>

                <div class="list-group-item credenciamento-item <?= $presente ? 'credenciamento-presente' : '' ?>">
                    <div class="credenciamento-numero">
                        <?= $indice + 1 ?>
                    </div>

                    <div class="credenciamento-participante">
                        <div class="fw-bold nome-participante">
                            <?= htmlspecialchars((string) ($item['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        </div>
                        <div class="text-muted small dados-participante">
                            <?php if (!empty($item['cpf'])): ?>
                                CPF: <?= htmlspecialchars((string) $item['cpf'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                            <?php if (!empty($item['telefone'])): ?>
                                <span class="mx-1">•</span>
                                <?= htmlspecialchars((string) $item['telefone'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                            <?php if (!empty($item['email'])): ?>
                                <span class="mx-1">•</span>
                                <?= htmlspecialchars((string) $item['email'], ENT_QUOTES, 'UTF-8') ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="credenciamento-situacoes">
                        <?php if ($pagamentoObrigatorio): ?>
                            <span class="badge <?= badgePagamentoCredenciamento($statusPagamento) ?>">
                                Pagamento: <?= htmlspecialchars($statusPagamento, ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        <?php else: ?>
                            <span class="badge text-bg-info">Evento gratuito</span>
                        <?php endif; ?>

                        <span class="badge <?= badgePresencaCredenciamento($statusPresenca) ?>">
                            <?= htmlspecialchars($statusPresenca, ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <div class="credenciamento-check">
                        <div class="form-check form-switch m-0">
                            <input
                                class="form-check-input credenciamento-checkbox"
                                type="checkbox"
                                role="switch"
                                id="presenca-<?= (int) $item['idInscricao'] ?>"
                                data-id="<?= (int) $item['idInscricao'] ?>"
                                <?= $presente ? 'checked' : '' ?>
                                <?= !$podeAlterar ? 'disabled' : '' ?>
                            >
                            <label
                                class="form-check-label fw-semibold"
                                for="presenca-<?= (int) $item['idInscricao'] ?>"
                            >
                                <?= $presente ? 'Presente' : 'Confirmar presença' ?>
                            </label>
                        </div>

                        <?php if (!$podeAlterar && $motivoBloqueio !== ''): ?>
                            <small class="text-muted d-block mt-1">
                                <i class="fa fa-lock me-1"></i>
                                <?= htmlspecialchars($motivoBloqueio, ENT_QUOTES, 'UTF-8') ?>
                            </small>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
