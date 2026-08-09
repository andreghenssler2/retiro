<?php

declare(strict_types=1);

require_once '../../config/settings.php';

Middleware::moderador();

$pageStyles = [
    THEME_CSS . 'admin/certificado/certificados.css?v=' . VERSION
];

$certificado = new Certificado();
$erroEstrutura = '';
$modelos = [];
$resumo = [
    'modelos' => 0,
    'modelosAtivos' => 0,
    'validos' => 0,
    'enviados' => 0,
    'revogados' => 0
];

try {
    $modelos = $certificado->listarModelos();
    $resumo = $certificado->resumo();
} catch (Throwable $erro) {
    $erroEstrutura = 'A estrutura de certificados ainda não foi instalada. Execute a migração do módulo.';
    error_log('Erro no painel de certificados: ' . $erro->getMessage());
}

require_once '../includes/header.php';
require_once '../includes/navbar.php';
require_once '../includes/sidebar.php';
?>
<div class="content certificado-page" id="content">
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">
                    <i class="fa-solid fa-award text-primary me-2"></i>
                    Certificados
                </h2>
                <p class="text-muted mb-0">Crie o layout por evento e acompanhe os certificados emitidos.</p>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <a href="emitidos.php" class="btn btn-outline-primary">
                    <i class="fa fa-file-circle-check me-1"></i>
                    Certificados emitidos
                </a>
                <a href="modelo.php" class="btn btn-primary <?= $erroEstrutura ? 'disabled' : '' ?>">
                    <i class="fa fa-plus me-1"></i>
                    Novo modelo
                </a>
            </div>
        </div>

        <?php if ($erroEstrutura): ?>
            <div class="alert alert-danger">
                <i class="fa fa-triangle-exclamation me-2"></i>
                <?= htmlspecialchars($erroEstrutura, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="row g-3 mb-4 certificado-resumo">
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted">Modelos ativos</small>
                        <div class="display-6 fw-bold text-primary"><?= (int) $resumo['modelosAtivos'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted">Certificados válidos</small>
                        <div class="display-6 fw-bold text-success"><?= (int) $resumo['validos'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted">Enviados por e-mail</small>
                        <div class="display-6 fw-bold text-info"><?= (int) $resumo['enviados'] ?></div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-lg-3">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted">Revogados</small>
                        <div class="display-6 fw-bold text-danger"><?= (int) $resumo['revogados'] ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Modelos por evento</h5>
                <span class="badge text-bg-secondary"><?= count($modelos) ?></span>
            </div>
            <div class="card-body p-0">
                <?php if (!$modelos): ?>
                    <div class="text-center text-muted p-5">
                        <i class="fa fa-certificate fa-3x mb-3"></i>
                        <div class="fw-semibold">Nenhum modelo cadastrado.</div>
                        <small>Crie um modelo para cada evento que emitirá certificado.</small>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Evento</th>
                                    <th>Modelo</th>
                                    <th>Carga horária</th>
                                    <th>Status</th>
                                    <th class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($modelos as $modelo): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string) $modelo['eventoTitulo'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <small class="text-muted">
                                                <?= !empty($modelo['data_inicio']) ? date('d/m/Y', strtotime((string) $modelo['data_inicio'])) : '-' ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div><?= htmlspecialchars((string) $modelo['nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <small class="text-muted"><?= htmlspecialchars((string) $modelo['titulo'], ENT_QUOTES, 'UTF-8') ?></small>
                                        </td>
                                        <td><?= number_format((float) $modelo['cargaHoraria'], 1, ',', '.') ?> h</td>
                                        <td>
                                            <span class="badge <?= (int) $modelo['ativo'] === 1 ? 'text-bg-success' : 'text-bg-secondary' ?>">
                                                <?= (int) $modelo['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                                            </span>
                                        </td>
                                        <td class="text-end">
                                            <div class="d-inline-flex flex-wrap gap-1 justify-content-end">
                                                <a href="preview.php?id=<?= (int) $modelo['idModelo'] ?>" target="_blank"
                                                   class="btn btn-sm btn-outline-info" title="Visualizar PDF">
                                                    <i class="fa fa-eye"></i>
                                                </a>
                                                <a href="modelo.php?id=<?= (int) $modelo['idModelo'] ?>"
                                                   class="btn btn-sm btn-outline-primary" title="Editar modelo">
                                                    <i class="fa fa-pencil"></i>
                                                </a>
                                                <button type="button" class="btn btn-sm btn-outline-danger btn-excluir-modelo"
                                                        data-id="<?= (int) $modelo['idModelo'] ?>"
                                                        data-nome="<?= htmlspecialchars((string) $modelo['nome'], ENT_QUOTES, 'UTF-8') ?>"
                                                        title="Excluir modelo">
                                                    <i class="fa fa-trash"></i>
                                                </button>
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
