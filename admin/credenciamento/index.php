<?php

declare(strict_types=1);

require_once '../../config/settings.php';

Middleware::auth();

$title = 'Credenciamento';
$credenciamento = new Credenciamento($db);
$eventos = $credenciamento->listarEventos();
$idEvento = max(0, (int) ($_GET['evento'] ?? 0));
$eventoSelecionado = $idEvento > 0
    ? $credenciamento->buscarEvento($idEvento)
    : false;

$pageStyles = [
    THEME_CSS . 'admin/credenciamento/credenciamento.css?v=' . VERSION
];

require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '../includes/sidebar.php';
?>

<input type="hidden" id="_tokenCredenciamento" value="<?= Session::csrf(); ?>">
<input type="hidden" id="idEventoCredenciamento" value="<?= $idEvento ?>">

<div class="content" id="content">
    <div class="container-fluid px-0">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">
                    <i class="fa fa-id-card-clip text-primary me-2"></i>
                    Credenciamento
                </h2>
                <p class="text-muted mb-0">
                    Lista de chamada e confirmação de presença dos participantes.
                </p>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="row align-items-end g-3">
                    <div class="col-lg-7">
                        <label for="eventoCredenciamento" class="form-label fw-semibold">
                            Evento
                        </label>
                        <select id="eventoCredenciamento" class="form-select">
                            <option value="0">Selecione um evento</option>
                            <?php foreach ($eventos as $evento): ?>
                                <?php
                                $data = !empty($evento['data_inicio'])
                                    ? date('d/m/Y', strtotime((string) $evento['data_inicio']))
                                    : '-';
                                ?>
                                <option
                                    value="<?= (int) $evento['idEvento'] ?>"
                                    <?= $idEvento === (int) $evento['idEvento'] ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars((string) $evento['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                    — <?= $data ?>
                                    (<?= (int) $evento['totalInscritos'] ?> inscritos)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if ($eventoSelecionado): ?>
                        <div class="col-lg-5">
                            <label for="pesquisaCredenciamento" class="form-label fw-semibold">
                                Localizar participante
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <i class="fa fa-magnifying-glass"></i>
                                </span>
                                <input
                                    type="search"
                                    id="pesquisaCredenciamento"
                                    class="form-control"
                                    placeholder="Nome, CPF, e-mail ou telefone"
                                    autocomplete="off"
                                >
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!$eventoSelecionado): ?>
            <div class="card shadow-sm border-0">
                <div class="card-body text-center py-5">
                    <i class="fa fa-clipboard-list fa-3x text-primary mb-3"></i>
                    <h4>Selecione um evento</h4>
                    <p class="text-muted mb-0">
                        A lista de chamada será carregada com todos os inscritos do evento.
                    </p>
                </div>
            </div>
        <?php else: ?>
            <div id="listaCredenciamento">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary"></div>
                    <div class="text-muted mt-2">Carregando lista de chamada...</div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
$pageScripts = [
    THEME_JS . 'admin/credenciamento/credenciamento.js?v=' . VERSION
];
require_once '../includes/footer.php';
?>
