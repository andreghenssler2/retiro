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

$valorVisitanteEventoForm =
    array_key_exists(
        "valor_visitante",
        $dados
    )
    && $dados["valor_visitante"]
        !== null
    && $dados["valor_visitante"]
        !== ""
        ? number_format(
            (float) $dados[
                "valor_visitante"
            ],
            2,
            ".",
            ""
        )
        : "";

$eventoEditandoSaude =
    !empty($editando);

$perguntasSaudeEventoForm = [
    "restricao_medicacao" =>
        $eventoEditandoSaude
            ? (
                (int) (
                    $dados[
                        "perguntar_restricao_medicacao"
                    ]
                    ?? 1
                ) === 1
            )
            : false,

    "deficiencia" =>
        $eventoEditandoSaude
            ? (
                (int) (
                    $dados[
                        "perguntar_deficiencia"
                    ]
                    ?? 1
                ) === 1
            )
            : false,

    "acessibilidade" =>
        $eventoEditandoSaude
            ? (
                (int) (
                    $dados[
                        "perguntar_acessibilidade"
                    ]
                    ?? 1
                ) === 1
            )
            : false,

    "restricao_alimentar" =>
        $eventoEditandoSaude
            ? (
                (int) (
                    $dados[
                        "perguntar_restricao_alimentar"
                    ]
                    ?? 1
                ) === 1
            )
            : false
];

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

        <div class="row g-3 align-items-end">
            <div class="col-md-5">
                <label
                    class="form-label fw-bold"
                    for="valor_visitante"
                >
                    Valor para visitante
                </label>

                <div class="input-group">
                    <span class="input-group-text">
                        R$
                    </span>

                    <input
                        type="number"
                        class="form-control"
                        id="valor_visitante"
                        name="valor_visitante"
                        min="0"
                        step="0.01"
                        value="<?= htmlspecialchars(
                            $valorVisitanteEventoForm,
                            ENT_QUOTES
                            | ENT_SUBSTITUTE,
                            "UTF-8"
                        ); ?>"
                        placeholder="Ex.: 150,00"
                    >
                </div>
            </div>

            <div class="col-md-7">
                <div class="alert alert-light border mb-0">
                    <i
                        class="fa-solid
                            fa-person-walking
                            text-primary me-1"
                    ></i>

                    Preencha somente quando o evento
                    possuir um valor diferente para
                    <strong>visitantes</strong>.

                    <div class="small text-muted mt-1">
                        Em branco: a opção
                        <strong>Visitante</strong> usa o
                        mesmo valor normal do evento.
                        Valor 0,00: visitante participa
                        gratuitamente.
                    </div>
                </div>
            </div>
        </div>

        <hr class="my-4">

        <div
            class="d-flex flex-wrap
                justify-content-between
                align-items-start
                gap-3"
        >
            <div>
                <h6 class="fw-bold mb-1">
                    Saúde e Acessibilidade
                </h6>

                <p class="text-muted small mb-0">
                    Marque somente as perguntas
                    necessárias para este evento.
                </p>
            </div>

            <span class="badge text-bg-light border">
                Configuração por evento
            </span>
        </div>

        <div class="row g-3 mt-1">

            <div class="col-md-6">
                <div
                    class="form-check
                        border rounded p-3"
                >
                    <input
                        class="form-check-input
                            ms-0 me-2"
                        type="checkbox"
                        name="perguntas_saude[restricao_medicacao]"
                        id="perguntaRestricaoMedicacao"
                        value="1"
                        <?= !empty(
                            $perguntasSaudeEventoForm[
                                "restricao_medicacao"
                            ]
                        )
                            ? "checked"
                            : ""; ?>
                    >

                    <label
                        class="form-check-label
                            fw-semibold"
                        for="perguntaRestricaoMedicacao"
                    >
                        Restrição a medicação
                    </label>

                    <div
                        class="small
                            text-muted mt-1"
                    >
                        Pergunta se possui restrição
                        e solicita detalhes quando
                        a resposta for Sim.
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div
                    class="form-check
                        border rounded p-3"
                >
                    <input
                        class="form-check-input
                            ms-0 me-2"
                        type="checkbox"
                        name="perguntas_saude[deficiencia]"
                        id="perguntaDeficiencia"
                        value="1"
                        <?= !empty(
                            $perguntasSaudeEventoForm[
                                "deficiencia"
                            ]
                        )
                            ? "checked"
                            : ""; ?>
                    >

                    <label
                        class="form-check-label
                            fw-semibold"
                        for="perguntaDeficiencia"
                    >
                        Deficiência
                    </label>

                    <div
                        class="small
                            text-muted mt-1"
                    >
                        Exibe o tipo de deficiência
                        e o campo para detalhes.
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div
                    class="form-check
                        border rounded p-3"
                >
                    <input
                        class="form-check-input
                            ms-0 me-2"
                        type="checkbox"
                        name="perguntas_saude[acessibilidade]"
                        id="perguntaAcessibilidade"
                        value="1"
                        <?= !empty(
                            $perguntasSaudeEventoForm[
                                "acessibilidade"
                            ]
                        )
                            ? "checked"
                            : ""; ?>
                    >

                    <label
                        class="form-check-label
                            fw-semibold"
                        for="perguntaAcessibilidade"
                    >
                        Recurso de acessibilidade
                    </label>

                    <div
                        class="small
                            text-muted mt-1"
                    >
                        Pergunta se a pessoa precisa
                        de algum recurso específico.
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div
                    class="form-check
                        border rounded p-3"
                >
                    <input
                        class="form-check-input
                            ms-0 me-2"
                        type="checkbox"
                        name="perguntas_saude[restricao_alimentar]"
                        id="perguntaRestricaoAlimentar"
                        value="1"
                        <?= !empty(
                            $perguntasSaudeEventoForm[
                                "restricao_alimentar"
                            ]
                        )
                            ? "checked"
                            : ""; ?>
                    >

                    <label
                        class="form-check-label
                            fw-semibold"
                        for="perguntaRestricaoAlimentar"
                    >
                        Restrição alimentar
                    </label>

                    <div
                        class="small
                            text-muted mt-1"
                    >
                        Pergunta sobre restrições
                        alimentares e solicita
                        a descrição.
                    </div>
                </div>
            </div>

        </div>

        <div
            class="alert alert-light
                border mt-3 mb-0"
        >
            <i
                class="fa-solid
                    fa-circle-info
                    text-primary me-1"
            ></i>

            Se nenhuma pergunta estiver marcada,
            a etapa
            <strong>Saúde e Acessibilidade</strong>
            será ignorada automaticamente
            durante a inscrição.
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
