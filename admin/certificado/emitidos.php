<?php

declare(strict_types=1);

require_once '../../config/settings.php';

Middleware::moderador();

$pageStyles = [
    THEME_CSS . 'admin/certificado/certificados.css?v=' . VERSION
];

$certificado = new Certificado();
$evento = new Evento();
$pesquisa = trim((string) ($_GET['pesquisa'] ?? ''));
$idEvento = max(0, (int) ($_GET['evento'] ?? 0));
$status = trim((string) ($_GET['status'] ?? ''));
$registros = [];
$erroEstrutura = '';

try {
    $registros = $certificado->listarEmitidos($pesquisa, $idEvento, $status);
} catch (Throwable $erro) {
    $erroEstrutura = 'Não foi possível consultar os certificados emitidos. Confirme a migração do módulo.';
    error_log('Erro em certificados emitidos: ' . $erro->getMessage());
}

$eventos = $evento->listar();

require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '../includes/sidebar.php';
?>
<div class="content certificado-page" id="content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">
                    <i class="fa fa-file-circle-check text-success me-2"></i>
                    Certificados emitidos
                </h2>
                <p class="text-muted mb-0">Consulte, baixe, reenvie ou revogue certificados.</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left me-1"></i> Modelos
            </a>
        </div>

        <?php if ($erroEstrutura): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($erroEstrutura, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="get" class="row g-3 align-items-end">
                    <div class="col-lg-5">
                        <label class="form-label" for="pesquisa">Pesquisa</label>
                        <input type="search" class="form-control" id="pesquisa" name="pesquisa"
                               value="<?= htmlspecialchars($pesquisa, ENT_QUOTES, 'UTF-8') ?>"
                               placeholder="Nome, e-mail, evento ou código">
                    </div>
                    <div class="col-lg-4">
                        <label class="form-label" for="evento">Evento</label>
                        <select class="form-select" id="evento" name="evento">
                            <option value="0">Todos</option>
                            <?php foreach ($eventos as $item): ?>
                                <option value="<?= (int) $item['idEvento'] ?>" <?= $idEvento === (int) $item['idEvento'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $item['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-2">
                        <label class="form-label" for="status">Status</label>
                        <select class="form-select" id="status" name="status">
                            <option value="">Todos</option>
                            <?php foreach (['Emitido', 'Enviado', 'Revogado'] as $opcao): ?>
                                <option value="<?= $opcao ?>" <?= $status === $opcao ? 'selected' : '' ?>><?= $opcao ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-lg-1 d-grid">
                        <button class="btn btn-primary" type="submit" title="Filtrar"><i class="fa fa-filter"></i></button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Resultados</h5>
                <span class="badge text-bg-secondary"><?= count($registros) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!$registros): ?>
                    <div class="text-center text-muted p-5">
                        <i class="fa fa-file-pdf fa-3x mb-3"></i>
                        <div>Nenhum certificado encontrado.</div>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Participante</th>
                                    <th>Evento</th>
                                    <th>Código</th>
                                    <th>Emissão</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($registros as $registro): ?>
                                    <?php
                                    $statusRegistro = (string) $registro['status'];
                                    $classeStatus = match ($statusRegistro) {
                                        'Enviado' => 'text-bg-success',
                                        'Emitido' => 'text-bg-warning',
                                        'Revogado' => 'text-bg-danger',
                                        default => 'text-bg-secondary'
                                    };
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string) $registro['nomeParticipante'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars((string) $registro['emailDestino'], ENT_QUOTES, 'UTF-8') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars((string) $registro['eventoTitulo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><code><?= htmlspecialchars((string) $registro['codigo'], ENT_QUOTES, 'UTF-8') ?></code></td>
                                        <td>
                                            <?= date('d/m/Y H:i', strtotime((string) $registro['emitidoEm'])) ?>
                                            <?php if (!empty($registro['enviadoEm'])): ?>
                                                <small class="d-block text-muted">Enviado <?= date('d/m/Y H:i', strtotime((string) $registro['enviadoEm'])) ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><span class="badge <?= $classeStatus ?>"><?= $statusRegistro ?></span></td>
                                        <td class="text-end">
                                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                                <a href="baixar.php?id=<?= (int) $registro['idCertificado'] ?>"
                                                   class="btn btn-sm btn-outline-primary" title="Baixar PDF">
                                                    <i class="fa fa-download"></i>
                                                </a>
                                                <a href="<?= BASE_URL ?>certificado/validar.php?codigo=<?= rawurlencode((string) $registro['codigo']) ?>"
                                                   target="_blank" class="btn btn-sm btn-outline-info" title="Validar">
                                                    <i class="fa fa-shield-halved"></i>
                                                </a>
                                                <?php if ($statusRegistro !== 'Revogado'): ?>
                                                    <button type="button" class="btn btn-sm btn-outline-success btn-reenviar-certificado"
                                                            data-id="<?= (int) $registro['idCertificado'] ?>"
                                                            data-nome="<?= htmlspecialchars((string) $registro['nomeParticipante'], ENT_QUOTES, 'UTF-8') ?>"
                                                            title="Reenviar por e-mail">
                                                        <i class="fa fa-envelope"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-danger btn-revogar-certificado"
                                                            data-id="<?= (int) $registro['idCertificado'] ?>"
                                                            data-codigo="<?= htmlspecialchars((string) $registro['codigo'], ENT_QUOTES, 'UTF-8') ?>"
                                                            title="Revogar certificado">
                                                        <i class="fa fa-ban"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php
$pageInlineScripts = [
    'window.CERTIFICADO_CSRF = ' . json_encode(Session::csrf(), JSON_UNESCAPED_UNICODE) . ';'
];
$pageScripts = [
    THEME_JS . 'admin/certificado/certificados.js?v=' . VERSION
];
require_once '../includes/footer.php';
?>
