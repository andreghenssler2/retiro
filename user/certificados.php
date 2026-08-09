<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/settings.php";

Session::start();
Auth::requireLogin();

$tipoUsuario = (int) Auth::tipo();

if (!in_array($tipoUsuario, [2, 3], true)) {
    header(
        "Location: "
        . BASE_URL
        . "admin/certificado/emitidos.php"
    );
    exit;
}

$pageStyles = [
    THEME_CSS
    . "user/certificados.css?v="
    . VERSION
];

$idUsuario = (int) (Auth::id() ?? 0);

$certificados = [];
$erro = "";

try {
    $stmt = $db->prepare("
        SELECT
            idCertificado,
            codigo,
            eventoTitulo,
            cargaHoraria,
            dataEvento,
            status,
            emitidoEm,
            enviadoEm
        FROM certificados_emitidos
        WHERE idUsuario = :idUsuario
        ORDER BY emitidoEm DESC, idCertificado DESC
    ");

    $stmt->execute([
        ":idUsuario" => $idUsuario
    ]);

    $certificados = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );
} catch (Throwable $excecao) {
    $erro =
        "Não foi possível consultar seus certificados. "
        . "Tente novamente mais tarde.";

    error_log(
        "Erro ao listar certificados do usuário "
        . $idUsuario
        . ": "
        . $excecao->getMessage()
    );
}

function certificadoUserEscapar(string $valor): string
{
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

require_once __DIR__
    . "/../admin/includes/header.php";
require_once __DIR__
    . "/../admin/includes/navbar.php";
require_once __DIR__
    . "/../admin/includes/sidebar.php";
?>

<div
    class="content certificados-page"
    id="content"
>
    <div class="container-fluid">

        <div
            class="d-flex flex-wrap
                justify-content-between
                align-items-center gap-3 mb-4"
        >
            <div>
                <h2 class="fw-bold mb-1">
                    <i
                        class="fa-solid fa-award
                            text-primary me-2"
                    ></i>
                    Meus certificados
                </h2>

                <p class="text-muted mb-0">
                    Consulte, baixe e valide os
                    certificados emitidos para você.
                </p>
            </div>

            <span
                class="badge rounded-pill
                    text-bg-primary fs-6"
            >
                <?= count($certificados); ?>
                certificado<?= count($certificados) === 1
                    ? ""
                    : "s"; ?>
            </span>
        </div>

        <?php if ($erro !== ""): ?>
            <div class="alert alert-danger">
                <i
                    class="fa-solid
                        fa-circle-exclamation me-1"
                ></i>
                <?= certificadoUserEscapar($erro); ?>
            </div>
        <?php endif; ?>

        <?php if ($certificados === []): ?>

            <div class="card border-0 shadow-sm">
                <div
                    class="card-body
                        text-center text-muted p-5"
                >
                    <i
                        class="fa-solid
                            fa-file-circle-check
                            fa-4x mb-3"
                    ></i>

                    <h5>
                        Nenhum certificado disponível
                    </h5>

                    <p class="mb-0">
                        Os certificados aparecerão aqui
                        após a emissão pelo responsável
                        do evento.
                    </p>
                </div>
            </div>

        <?php else: ?>

            <div class="row g-4">

                <?php foreach (
                    $certificados
                    as $registro
                ): ?>
                    <?php
                    $status = (string) (
                        $registro["status"] ?? ""
                    );

                    $classeStatus = match ($status) {
                        "Enviado" => "text-bg-success",
                        "Emitido" => "text-bg-warning",
                        "Revogado" => "text-bg-danger",
                        default => "text-bg-secondary"
                    };
                    ?>

                    <div class="col-md-6 col-xl-4">
                        <article
                            class="card
                                certificado-user-card
                                border-0 shadow-sm h-100"
                        >
                            <div
                                class="card-body
                                    d-flex flex-column p-4"
                            >

                                <div
                                    class="d-flex
                                        justify-content-between
                                        align-items-start
                                        gap-2 mb-3"
                                >
                                    <div
                                        class="certificado-user-icone"
                                    >
                                        <i
                                            class="fa-solid
                                                fa-award"
                                        ></i>
                                    </div>

                                    <span
                                        class="badge
                                            <?= $classeStatus; ?>"
                                    >
                                        <?= certificadoUserEscapar(
                                            $status
                                        ); ?>
                                    </span>
                                </div>

                                <h5 class="mb-3">
                                    <?= certificadoUserEscapar(
                                        (string) (
                                            $registro["eventoTitulo"]
                                            ?? ""
                                        )
                                    ); ?>
                                </h5>

                                <div
                                    class="certificado-user-detalhes"
                                >

                                    <?php if (
                                        !empty(
                                            $registro["dataEvento"]
                                        )
                                    ): ?>
                                        <p
                                            class="text-muted
                                                mb-2"
                                        >
                                            <i
                                                class="fa-solid
                                                    fa-calendar-days
                                                    me-1"
                                            ></i>

                                            <?= certificadoUserEscapar(
                                                (string) $registro["dataEvento"]
                                            ); ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (
                                        (float) (
                                            $registro["cargaHoraria"]
                                            ?? 0
                                        ) > 0
                                    ): ?>
                                        <p
                                            class="text-muted
                                                mb-2"
                                        >
                                            <i
                                                class="fa-solid
                                                    fa-clock me-1"
                                            ></i>

                                            <?= number_format(
                                                (float) $registro["cargaHoraria"],
                                                0,
                                                ",",
                                                "."
                                            ); ?>

                                            hora<?= (float) $registro["cargaHoraria"] === 1.0
                                                ? ""
                                                : "s"; ?>
                                        </p>
                                    <?php endif; ?>

                                    <?php if (
                                        !empty(
                                            $registro["emitidoEm"]
                                        )
                                    ): ?>
                                        <p
                                            class="text-muted
                                                mb-3"
                                        >
                                            <i
                                                class="fa-solid
                                                    fa-file-signature
                                                    me-1"
                                            ></i>

                                            Emitido em

                                            <?= date(
                                                "d/m/Y",
                                                strtotime(
                                                    (string) $registro["emitidoEm"]
                                                )
                                            ); ?>
                                        </p>
                                    <?php endif; ?>

                                </div>

                                <div
                                    class="certificado-user-codigo
                                        mb-4"
                                >
                                    <small
                                        class="text-muted d-block"
                                    >
                                        Código do certificado
                                    </small>

                                    <code>
                                        <?= certificadoUserEscapar(
                                            (string) (
                                                $registro["codigo"]
                                                ?? ""
                                            )
                                        ); ?>
                                    </code>
                                </div>

                                <div
                                    class="d-grid
                                        gap-2 mt-auto"
                                >
                                    <?php if (
                                        $status !== "Revogado"
                                    ): ?>
                                        <a
                                            href="<?= BASE_URL ?>user/certificado-baixar.php?id=<?= (int) $registro["idCertificado"]; ?>"
                                            class="btn btn-primary"
                                        >
                                            <i
                                                class="fa-solid
                                                    fa-download me-1"
                                            ></i>
                                            Baixar PDF
                                        </a>
                                    <?php else: ?>
                                        <button
                                            type="button"
                                            class="btn btn-secondary"
                                            disabled
                                        >
                                            <i
                                                class="fa-solid
                                                    fa-ban me-1"
                                            ></i>
                                            Certificado revogado
                                        </button>
                                    <?php endif; ?>

                                    <a
                                        href="<?= BASE_URL ?>certificado/validar.php?codigo=<?= rawurlencode(
                                            (string) $registro["codigo"]
                                        ); ?>"
                                        target="_blank"
                                        rel="noopener"
                                        class="btn
                                            btn-outline-primary"
                                    >
                                        <i
                                            class="fa-solid
                                                fa-shield-halved
                                                me-1"
                                        ></i>
                                        Validar certificado
                                    </a>
                                </div>

                            </div>
                        </article>
                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

        <div class="alert alert-info mt-4 mb-0">
            <i
                class="fa-solid
                    fa-circle-info me-1"
            ></i>
            Certificados revogados permanecem
            disponíveis para consulta, mas não
            podem ser baixados.
        </div>

    </div>
</div>

<?php
require_once __DIR__
    . "/../admin/includes/footer.php";
?>