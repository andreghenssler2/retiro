<?php

declare(strict_types=1);

$configPublicaForm =
    new EventoInscricaoPublicaConfig(
        $db
    );

$termosEventoForm =
    !empty($editando)
        ? $configPublicaForm
            ->listarTermos(
                (int) (
                    $dados["idEvento"]
                    ?? 0
                )
            )
        : [];

$camisetasEventoForm =
    !empty($editando)
        ? $configPublicaForm
            ->listarCamisetas(
                (int) (
                    $dados["idEvento"]
                    ?? 0
                )
            )
        : [];

$todosTamanhos = [
    "P",
    "M",
    "G",
    "GG",
    "X1",
    "X2",
    "X3",
    "X4"
];
?>

<div
    class="card shadow-sm border-0 mt-4"
    id="configInscricaoPublicaEvento"
>
    <div class="card-header">
        <h5 class="mb-0">
            <i
                class="fa-solid
                    fa-clipboard-check"
            ></i>
            Formulário público de inscrição
        </h5>
    </div>

    <div class="card-body">

        <div
            class="d-flex flex-wrap
                justify-content-between
                align-items-center gap-2 mb-3"
        >
            <div>
                <h6 class="fw-bold mb-1">
                    Termos e consentimentos
                </h6>

                <p class="text-muted small mb-0">
                    Crie os termos que aparecerão
                    durante a inscrição.
                </p>
            </div>

            <button
                type="button"
                class="btn btn-sm
                    btn-outline-primary"
                id="adicionarTermoEvento"
            >
                <i
                    class="fa-solid
                        fa-plus me-1"
                ></i>
                Adicionar termo
            </button>
        </div>

        <div id="termosEventoContainer">

            <?php foreach (
                $termosEventoForm
                as $indice => $termo
            ): ?>
                <div
                    class="border rounded
                        p-3 mb-3 termo-evento-row"
                >
                    <div
                        class="d-flex
                            justify-content-between
                            align-items-center mb-3"
                    >
                        <strong>
                            Termo/Consentimento
                        </strong>

                        <button
                            type="button"
                            class="btn btn-sm
                                btn-outline-danger"
                            data-remover-termo
                        >
                            <i
                                class="fa-solid
                                    fa-trash"
                            ></i>
                        </button>
                    </div>

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label
                                class="form-label"
                            >
                                Título
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="termos[<?= $indice; ?>][titulo]"
                                value="<?= htmlspecialchars(
                                    (string) (
                                        $termo["titulo"]
                                        ?? ""
                                    ),
                                    ENT_QUOTES
                                    | ENT_SUBSTITUTE,
                                    "UTF-8"
                                ); ?>"
                                maxlength="180"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label
                                class="form-label"
                            >
                                Link do documento
                            </label>

                            <input
                                type="url"
                                class="form-control"
                                name="termos[<?= $indice; ?>][url]"
                                value="<?= htmlspecialchars(
                                    (string) (
                                        $termo["url"]
                                        ?? ""
                                    ),
                                    ENT_QUOTES
                                    | ENT_SUBSTITUTE,
                                    "UTF-8"
                                ); ?>"
                                placeholder="https://..."
                            >
                        </div>

                        <div class="col-12">
                            <label
                                class="form-label"
                            >
                                Descrição
                            </label>

                            <textarea
                                class="form-control"
                                name="termos[<?= $indice; ?>][descricao]"
                                rows="2"
                                maxlength="1000"
                            ><?= htmlspecialchars(
                                (string) (
                                    $termo["descricao"]
                                    ?? ""
                                ),
                                ENT_QUOTES
                                | ENT_SUBSTITUTE,
                                "UTF-8"
                            ); ?></textarea>
                        </div>

                        <div class="col-12">
                            <input
                                type="hidden"
                                name="termos[<?= $indice; ?>][obrigatorio]"
                                value="0"
                            >

                            <div class="form-check">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    name="termos[<?= $indice; ?>][obrigatorio]"
                                    value="1"
                                    id="termoObrigatorio<?= $indice; ?>"
                                    <?= (int) (
                                        $termo[
                                            "obrigatorio"
                                        ]
                                        ?? 0
                                    ) === 1
                                        ? "checked"
                                        : ""; ?>
                                >

                                <label
                                    class="form-check-label"
                                    for="termoObrigatorio<?= $indice; ?>"
                                >
                                    Aceite obrigatório
                                </label>
                            </div>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>

        </div>

        <hr class="my-4">

        <h6 class="fw-bold">
            Tamanhos de camiseta
        </h6>

        <p class="text-muted small">
            Quando “Solicitar camiseta” estiver
            marcado, somente os tamanhos
            selecionados abaixo serão exibidos.
        </p>

        <div class="d-flex flex-wrap gap-3">

            <?php foreach (
                $todosTamanhos
                as $tamanho
            ): ?>
                <div class="form-check">
                    <input
                        class="form-check-input"
                        type="checkbox"
                        name="camisetas[]"
                        value="<?= $tamanho; ?>"
                        id="camisetaEvento<?= $tamanho; ?>"
                        <?= in_array(
                            $tamanho,
                            $camisetasEventoForm,
                            true
                        )
                            ? "checked"
                            : ""; ?>
                    >

                    <label
                        class="form-check-label"
                        for="camisetaEvento<?= $tamanho; ?>"
                    >
                        <?= $tamanho; ?>
                    </label>
                </div>
            <?php endforeach; ?>

        </div>

    </div>
</div>

<script
    src="<?= BASE_URL ?>theme/js/admin/event/inscricao-publica-config.js?v=<?= VERSION; ?>"
></script>
