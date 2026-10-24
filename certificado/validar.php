<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/settings.php';

$codigo = strtoupper(trim((string) ($_GET['codigo'] ?? $_POST['codigo'] ?? '')));
$registro = false;
$arquivoIntegro = false;
$pesquisado = $codigo !== '';

if ($pesquisado) {
    $certificado = new Certificado();
    $registro = $certificado->buscarPorCodigo($codigo);

    if ($registro) {
        $service = new CertificadoService($certificado);
        $arquivoIntegro = $service->arquivoIntegro($registro);
    }
}

function h(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Validar certificado</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <style>
        body{min-height:100vh;background:linear-gradient(135deg,#eef4ff,#f8fafc 48%,#ffffff);}
        .validacao-card{max-width:760px;border:0;border-radius:1.25rem;box-shadow:0 18px 55px rgba(15,23,42,.12);}
        .codigo{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;letter-spacing:.05em;}
    </style>
</head>
<body class="d-flex align-items-center py-5">
<div class="container">
    <div class="card validacao-card mx-auto">
        <div class="card-body p-4 p-md-5">
            <div class="text-center mb-4">
                <div class="display-5 text-primary mb-3"><i class="fa-solid fa-award"></i></div>
                <h1 class="h2 fw-bold">Validação de certificado</h1>
                <p class="text-muted mb-0">Informe o código impresso no certificado.</p>
            </div>

            <form method="get" class="row g-2 justify-content-center mb-4">
                <div class="col-md-8">
                    <label for="codigo" class="visually-hidden">Código</label>
                    <input type="text" class="form-control form-control-lg text-uppercase codigo"
                           id="codigo" name="codigo" required maxlength="40"
                           value="<?= h($codigo) ?>" placeholder="CERT-2026-XXXXXXXXXX">
                </div>
                <div class="col-md-auto d-grid">
                    <button class="btn btn-primary btn-lg" type="submit">
                        <i class="fa fa-magnifying-glass me-1"></i> Validar
                    </button>
                </div>
            </form>

            <?php if ($pesquisado && !$registro): ?>
                <div class="alert alert-danger mb-0">
                    <i class="fa fa-circle-xmark me-2"></i>
                    Nenhum certificado foi localizado com esse código.
                </div>
            <?php elseif ($registro): ?>
                <?php $revogado = (string) $registro['status'] === 'Revogado'; ?>
                <div class="alert <?= $revogado ? 'alert-danger' : 'alert-success' ?>">
                    <div class="d-flex gap-3 align-items-start">
                        <i class="fa <?= $revogado ? 'fa-ban' : 'fa-circle-check' ?> fa-2x"></i>
                        <div>
                            <h2 class="h5 mb-1">
                                <?= $revogado ? 'Certificado revogado' : 'Certificado válido' ?>
                            </h2>
                            <div>
                                <?= $revogado
                                    ? 'Este documento não possui mais validade.'
                                    : 'O registro foi localizado no sistema e está ativo.' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">Participante</small>
                            <strong><?= h((string) $registro['nomeParticipante']) ?></strong>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">Evento</small>
                            <strong><?= h((string) $registro['eventoTitulo']) ?></strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">Data do evento</small>
                            <strong><?= h((string) $registro['dataEvento']) ?></strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">Carga horária</small>
                            <strong><?= number_format((float) $registro['cargaHoraria'], 1, ',', '.') ?> h</strong>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="border rounded-3 p-3 h-100">
                            <small class="text-muted d-block">Emitido em</small>
                            <strong><?= date('d/m/Y H:i', strtotime((string) $registro['emitidoEm'])) ?></strong>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="border rounded-3 p-3">
                            <small class="text-muted d-block">Código de validação</small>
                            <strong class="codigo"><?= h((string) $registro['codigo']) ?></strong>
                        </div>
                    </div>
                </div>

                <?php if (!$revogado && !$arquivoIntegro): ?>
                    <div class="alert alert-warning mt-3 mb-0">
                        O registro é válido, mas a cópia armazenada não pôde ser verificada no servidor.
                    </div>
                <?php endif; ?>

                <?php if ($revogado && !empty($registro['motivoRevogacao'])): ?>
                    <div class="alert alert-light border mt-3 mb-0">
                        <strong>Motivo da revogação:</strong>
                        <?= h((string) $registro['motivoRevogacao']) ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
</body>
</html>
