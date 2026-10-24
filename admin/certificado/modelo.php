<?php

declare(strict_types=1);

require_once '../../config/settings.php';

Middleware::moderador();

$pageStyles = [
    THEME_CSS . 'admin/certificado/certificados.css?v=' . VERSION
];

$certificado = new Certificado();
$evento = new Evento();
$idModelo = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
$editando = $idModelo > 0;

$dados = [
    'idModelo' => 0,
    'idEvento' => 0,
    'nome' => '',
    'titulo' => 'CERTIFICADO',
    'texto' => "Certificamos que {{nome}} participou do evento {{evento}}, realizado em {{data_evento}}, com carga horária de {{carga_horaria}}.",
    'cargaHoraria' => 0,
    'localEmissao' => 'Parobé/RS',
    'corTitulo' => '#0d6efd',
    'corTexto' => '#1f2937',
    'imagemFundo' => '',
    'logo' => '',
    'assinatura1Imagem' => '',
    'assinatura1Nome' => '',
    'assinatura1Cargo' => '',
    'assinatura2Imagem' => '',
    'assinatura2Nome' => '',
    'assinatura2Cargo' => '',
    'ativo' => 1
];

if ($editando) {
    $registro = $certificado->buscarModelo($idModelo);

    if (!$registro) {
        Session::flash('danger', 'Modelo de certificado não encontrado.');
        header('Location: index.php');
        exit;
    }

    $dados = array_merge($dados, $registro);
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
                    <i class="fa fa-certificate text-primary me-2"></i>
                    <?= $editando ? 'Editar modelo' : 'Novo modelo de certificado' ?>
                </h2>
                <p class="text-muted mb-0">Defina o visual e o texto do certificado do evento.</p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($editando): ?>
                    <a href="preview.php?id=<?= (int) $dados['idModelo'] ?>" target="_blank" class="btn btn-outline-info">
                        <i class="fa fa-eye me-1"></i> Pré-visualizar
                    </a>
                <?php endif; ?>
                <a href="index.php" class="btn btn-outline-secondary">
                    <i class="fa fa-arrow-left me-1"></i> Voltar
                </a>
            </div>
        </div>

        <form id="formModeloCertificado" enctype="multipart/form-data" autocomplete="off">
            <input type="hidden" name="_token" value="<?= Session::csrf() ?>">
            <input type="hidden" name="idModelo" value="<?= (int) $dados['idModelo'] ?>">

            <div class="row g-4">
                <div class="col-xl-8">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">Conteúdo</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-7">
                                    <label class="form-label" for="idEvento">Evento</label>
                                    <select class="form-select" id="idEvento" name="idEvento" required>
                                        <option value="">Selecione</option>
                                        <?php foreach ($eventos as $item): ?>
                                            <option value="<?= (int) $item['idEvento'] ?>"
                                                <?= (int) $dados['idEvento'] === (int) $item['idEvento'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars((string) $item['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                                — <?= !empty($item['data_inicio']) ? date('d/m/Y', strtotime((string) $item['data_inicio'])) : '-' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="form-text">Cada evento pode possuir um modelo ativo.</div>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label" for="nome">Nome interno do modelo</label>
                                    <input type="text" class="form-control" id="nome" name="nome" required maxlength="150"
                                           value="<?= htmlspecialchars((string) $dados['nome'], ENT_QUOTES, 'UTF-8') ?>"
                                           placeholder="Ex.: Certificado padrão 2026">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="titulo">Título exibido</label>
                                    <input type="text" class="form-control" id="titulo" name="titulo" required maxlength="200"
                                           value="<?= htmlspecialchars((string) $dados['titulo'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="cargaHoraria">Carga horária</label>
                                    <div class="input-group">
                                        <input type="number" class="form-control" id="cargaHoraria" name="cargaHoraria"
                                               min="0" max="999.5" step="0.5" required
                                               value="<?= htmlspecialchars((string) $dados['cargaHoraria'], ENT_QUOTES, 'UTF-8') ?>">
                                        <span class="input-group-text">horas</span>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="texto">Texto do certificado</label>
                                    <textarea class="form-control" id="texto" name="texto" rows="6" required><?= htmlspecialchars((string) $dados['texto'], ENT_QUOTES, 'UTF-8') ?></textarea>
                                    <div class="form-text mt-2">
                                        Variáveis disponíveis:
                                        <code>{{nome}}</code>, <code>{{evento}}</code>, <code>{{data_evento}}</code>,
                                        <code>{{carga_horaria}}</code>, <code>{{local_evento}}</code>,
                                        <code>{{codigo}}</code>, <code>{{data_emissao}}</code> e <code>{{url_validacao}}</code>.
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="localEmissao">Local de emissão</label>
                                    <input type="text" class="form-control" id="localEmissao" name="localEmissao" maxlength="150"
                                           value="<?= htmlspecialchars((string) $dados['localEmissao'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="corTitulo">Cor principal</label>
                                    <input type="color" class="form-control form-control-color w-100" id="corTitulo" name="corTitulo"
                                           value="<?= htmlspecialchars((string) $dados['corTitulo'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label" for="corTexto">Cor do texto</label>
                                    <input type="color" class="form-control form-control-color w-100" id="corTexto" name="corTexto"
                                           value="<?= htmlspecialchars((string) $dados['corTexto'], ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="ativo" name="ativo" value="1"
                                            <?= (int) $dados['ativo'] === 1 ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="ativo">Modelo ativo e disponível para emissão</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">Assinaturas</h5>
                        </div>
                        <div class="card-body">
                            <div class="row g-4">
                                <?php for ($numero = 1; $numero <= 2; $numero++): ?>
                                    <div class="col-md-6">
                                        <div class="border rounded-3 p-3 h-100">
                                            <h6>Assinatura <?= $numero ?></h6>
                                            <div class="mb-3">
                                                <label class="form-label">Imagem da assinatura</label>
                                                <input type="file" class="form-control" name="assinatura<?= $numero ?>Imagem"
                                                       accept=".jpg,.jpeg,.png,.webp">
                                                <?php if (!empty($dados['assinatura' . $numero . 'Imagem'])): ?>
                                                    <div class="arquivo-atual mt-2">
                                                        <img src="<?= BASE_URL . ltrim((string) $dados['assinatura' . $numero . 'Imagem'], '/') ?>" alt="Assinatura atual">
                                                        <label class="form-check mt-2">
                                                            <input class="form-check-input" type="checkbox" name="removerAssinatura<?= $numero ?>" value="1">
                                                            <span class="form-check-label">Remover imagem atual</span>
                                                        </label>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Nome</label>
                                                <input type="text" class="form-control" name="assinatura<?= $numero ?>Nome" maxlength="150"
                                                       value="<?= htmlspecialchars((string) $dados['assinatura' . $numero . 'Nome'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                            <div>
                                                <label class="form-label">Cargo ou função</label>
                                                <input type="text" class="form-control" name="assinatura<?= $numero ?>Cargo" maxlength="150"
                                                       value="<?= htmlspecialchars((string) $dados['assinatura' . $numero . 'Cargo'], ENT_QUOTES, 'UTF-8') ?>">
                                            </div>
                                        </div>
                                    </div>
                                <?php endfor; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-4">
                    <div class="card border-0 shadow-sm mb-4">
                        <div class="card-header bg-white py-3">
                            <h5 class="mb-0">Identidade visual</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-4">
                                <label class="form-label">Imagem de fundo</label>
                                <input type="file" class="form-control" name="imagemFundo" accept=".jpg,.jpeg,.png,.webp">
                                <div class="form-text">Recomendado: 3508 × 2480 px, proporção A4 horizontal.</div>
                                <?php if (!empty($dados['imagemFundo'])): ?>
                                    <div class="arquivo-atual mt-3">
                                        <img class="fundo-preview" src="<?= BASE_URL . ltrim((string) $dados['imagemFundo'], '/') ?>" alt="Fundo atual">
                                        <label class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="removerImagemFundo" value="1">
                                            <span class="form-check-label">Remover fundo atual</span>
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label class="form-label">Logotipo</label>
                                <input type="file" class="form-control" name="logo" accept=".jpg,.jpeg,.png,.webp">
                                <?php if (!empty($dados['logo'])): ?>
                                    <div class="arquivo-atual mt-3">
                                        <img src="<?= BASE_URL . ltrim((string) $dados['logo'], '/') ?>" alt="Logo atual">
                                        <label class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" name="removerLogo" value="1">
                                            <span class="form-check-label">Remover logo atual</span>
                                        </label>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-info">
                        <i class="fa fa-circle-info me-2"></i>
                        O PDF será salvo no servidor e anexado ao e-mail do participante. O código impresso permite validar o documento publicamente.
                    </div>

                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-success btn-lg" id="btnSalvarModelo">
                            <i class="fa fa-save me-1"></i>
                            <?= $editando ? 'Atualizar modelo' : 'Salvar modelo' ?>
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
<?php
$pageScripts = [
    THEME_JS . 'admin/certificado/certificados.js?v=' . VERSION
];
require_once '../includes/footer.php';
?>
