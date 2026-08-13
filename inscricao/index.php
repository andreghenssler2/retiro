<?php

declare(strict_types=1);

require_once __DIR__
    . "/../config/settings.php";

Session::start();

$idEvento = (int) (
    $_REQUEST["evento"]
    ?? $_REQUEST["idEvento"]
    ?? 0
);

$service =
    new InscricaoPublicaService(
        $db
    );

try {
    $evento =
        $service->buscarEvento(
            $idEvento
        );

    $termos =
        $service->termos(
            $idEvento
        );

    $camisetas =
        $service->camisetas(
            $idEvento
        );

    $comunidades =
        $service->comunidades();
} catch (Throwable $erro) {
    http_response_code(404);

    exit(
        htmlspecialchars(
            $erro->getMessage(),
            ENT_QUOTES | ENT_SUBSTITUTE,
            "UTF-8"
        )
    );
}

function inscricaoPubEscapar(
    string $valor
): string {
    return htmlspecialchars(
        $valor,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

function inscricaoPubImagem(
    array $evento
): string {
    $imagem = trim(
        (string) (
            $evento["imagem"]
            ?? ""
        )
    );

    return $imagem !== ""
        ? BASE_URL
            . "uploads/eventos/"
            . rawurlencode(
                basename($imagem)
            )
        : THEME_IMG
            . "sem-imagem.png";
}

$valor = (float) (
    $evento["valor_inscricao"]
    ?? $evento["valor"]
    ?? 0
);

$eventoPago =
    (int) (
        $evento["pagamento_obrigatorio"]
        ?? 1
    ) === 1
    && $valor > 0;

$temCamiseta =
    (int) (
        $evento["camiseta_ativa"]
        ?? 0
    ) === 1
    && $camisetas !== [];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <title>
        Inscrição —
        <?= inscricaoPubEscapar(
            (string) $evento["titulo"]
        ); ?>
    </title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="<?= THEME_CSS ?>inscricao/publica.css?v=<?= VERSION ?>"
    >
</head>

<body class="inscricao-publica">

    <header class="inscricao-publica-topo">
        <div class="container py-3">
            <a
                href="<?= BASE_URL ?>"
                class="text-white text-decoration-none fw-semibold"
            >
                <i class="fa-solid fa-calendar-check me-2"></i>
                Inscrição de Evento
            </a>
        </div>
    </header>

    <main class="container py-4">

        <section
            class="card border-0 shadow-sm mb-4 evento-resumo"
        >
            <div class="card-body p-3 p-md-4">
                <div class="row g-3 align-items-center">

                    <div class="col-4 col-md-2">
                        <img
                            src="<?= inscricaoPubEscapar(
                                inscricaoPubImagem(
                                    $evento
                                )
                            ); ?>"
                            alt="<?= inscricaoPubEscapar(
                                (string) $evento[
                                    "titulo"
                                ]
                            ); ?>"
                            class="evento-resumo-imagem"
                        >
                    </div>

                    <div class="col-8 col-md-7">
                        <h1 class="h5 fw-bold mb-2">
                            <?= inscricaoPubEscapar(
                                (string) $evento[
                                    "titulo"
                                ]
                            ); ?>
                        </h1>

                        <?php if (
                            !empty(
                                $evento[
                                    "descricao_curta"
                                ]
                            )
                        ): ?>
                            <p class="text-muted small mb-2">
                                <?= inscricaoPubEscapar(
                                    (string) $evento[
                                        "descricao_curta"
                                    ]
                                ); ?>
                            </p>
                        <?php endif; ?>

                        <div class="small text-muted">
                            <i
                                class="fa-regular
                                    fa-calendar me-1"
                            ></i>

                            <?= !empty(
                                $evento["data_inicio"]
                            )
                                ? date(
                                    "d/m/Y",
                                    strtotime(
                                        (string) $evento[
                                            "data_inicio"
                                        ]
                                    )
                                )
                                : "A definir"; ?>

                            <?php if (
                                !empty(
                                    $evento[
                                        "hora_inicio"
                                    ]
                                )
                            ): ?>
                                às
                                <?= substr(
                                    (string) $evento[
                                        "hora_inicio"
                                    ],
                                    0,
                                    5
                                ); ?>
                            <?php endif; ?>
                        </div>

                        <?php if (
                            !empty(
                                $evento["local"]
                            )
                        ): ?>
                            <div
                                class="small text-muted mt-1"
                            >
                                <i
                                    class="fa-solid
                                        fa-location-dot me-1"
                                ></i>

                                <?= inscricaoPubEscapar(
                                    (string) $evento[
                                        "local"
                                    ]
                                ); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div
                        class="col-12 col-md-3 text-md-end"
                    >
                        <small class="text-muted d-block">
                            Valor
                        </small>

                        <strong class="fs-5">
                            <?= $eventoPago
                                ? "R$ "
                                    . number_format(
                                        $valor,
                                        2,
                                        ",",
                                        "."
                                    )
                                : "Gratuito"; ?>
                        </strong>
                    </div>

                </div>
            </div>
        </section>

        <div
            id="alertaInscricao"
            class="alert"
            hidden
        ></div>

        <form
            id="formInscricaoPublica"
            autocomplete="off"
        >
            <input
                type="hidden"
                name="_token"
                value="<?= inscricaoPubEscapar(
                    Session::csrf()
                ); ?>"
            >

            <input
                type="hidden"
                name="idEvento"
                value="<?= $idEvento; ?>"
            >

            <input
                type="hidden"
                name="fluxo"
                id="fluxo"
                value=""
            >

            <section
                class="card border-0 shadow-sm etapa-inscricao"
                data-etapa="email"
            >
                <div class="card-body p-4">

                    <h2 class="h5 fw-bold mb-3">
                        Validação de E-mail
                    </h2>

                    <p class="text-muted">
                        Para iniciar sua inscrição,
                        informe um e-mail válido.
                        Enviaremos um código de verificação.
                    </p>

                    <label
                        class="form-label fw-semibold"
                        for="email"
                    >
                        E-mail
                    </label>

                    <input
                        type="email"
                        class="form-control form-control-lg"
                        id="email"
                        name="email"
                        required
                    >

                    <div
                        id="codigoArea"
                        class="mt-3"
                        hidden
                    >
                        <label
                            class="form-label fw-semibold"
                            for="codigo"
                        >
                            Código de validação
                        </label>

                        <input
                            type="text"
                            class="form-control form-control-lg codigo-validacao"
                            id="codigo"
                            inputmode="numeric"
                            maxlength="6"
                            placeholder="000000"
                        >

                        <small class="text-muted">
                            O código é válido por 10 minutos.
                        </small>
                    </div>

                    <div
                        class="d-flex justify-content-end
                            gap-2 mt-4"
                    >
                        <button
                            type="button"
                            class="btn btn-inscricao px-4"
                            id="btnEnviarCodigo"
                        >
                            Enviar código de validação
                            <i
                                class="fa-solid
                                    fa-chevron-right ms-2"
                            ></i>
                        </button>

                        <button
                            type="button"
                            class="btn btn-inscricao px-4"
                            id="btnValidarCodigo"
                            hidden
                        >
                            Confirmar código
                            <i
                                class="fa-solid
                                    fa-check ms-2"
                            ></i>
                        </button>
                    </div>

                </div>
            </section>

            <section
                class="card border-0 shadow-sm etapa-inscricao"
                data-etapa="pessoal"
                hidden
            >
                <div class="card-body p-4">

                    <h2 class="h5 fw-bold mb-1">
                        Informações Pessoais
                    </h2>

                    <p class="text-muted small mb-4">
                        Digite o CPF completo.
                        Se já houver cadastro vinculado
                        ao e-mail validado, os dados
                        conhecidos serão preenchidos.
                    </p>

                    <div class="row g-3">

                        <div class="col-md-4">
                            <label
                                class="form-label fw-semibold"
                                for="cpf"
                            >
                                CPF
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="cpf"
                                name="cpf"
                                inputmode="numeric"
                                maxlength="14"
                                required
                            >

                            <div
                                class="form-text"
                                id="cpfStatus"
                            ></div>
                        </div>

                        <div class="col-md-8">
                            <label
                                class="form-label fw-semibold"
                                for="nome"
                            >
                                Nome Completo
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="nome"
                                name="nome"
                                maxlength="160"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label
                                class="form-label fw-semibold"
                                for="nacionalidade"
                            >
                                Nacionalidade
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="nacionalidade"
                                name="nacionalidade"
                                value="Brasileira"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label
                                class="form-label fw-semibold"
                                for="data_nascimento"
                            >
                                Data de Nascimento
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                id="data_nascimento"
                                name="data_nascimento"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label
                                class="form-label fw-semibold"
                                for="genero"
                            >
                                Gênero
                            </label>

                            <select
                                class="form-select"
                                id="genero"
                                name="genero"
                                required
                            >
                                <option value="">
                                    Selecione
                                </option>
                                <option value="Masculino">
                                    Masculino
                                </option>
                                <option value="Feminino">
                                    Feminino
                                </option>
                                <option value="Outro">
                                    Outro
                                </option>
                                <option
                                    value="Prefiro não informar"
                                >
                                    Prefiro não informar
                                </option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label
                                class="form-label fw-semibold"
                                for="emailConfirmado"
                            >
                                E-mail
                            </label>

                            <input
                                type="email"
                                class="form-control"
                                id="emailConfirmado"
                                readonly
                            >
                        </div>

                        <div class="col-md-6">
                            <label
                                class="form-label fw-semibold"
                                for="telefone"
                            >
                                Telefone
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                id="telefone"
                                name="telefone"
                                inputmode="tel"
                                required
                            >
                        </div>

                    </div>

                    <?php if ($termos !== []): ?>
                        <hr class="my-4">

                        <div class="termos-evento">
                            <h3 class="h6 fw-bold">
                                Termos e consentimentos
                            </h3>

                            <p class="small text-muted">
                                Leia e marque os termos
                                aplicáveis a este evento.
                            </p>

                            <?php foreach (
                                $termos
                                as $termo
                            ): ?>
                                <div
                                    class="form-check mb-3"
                                >
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        name="termos[]"
                                        value="<?= (int) $termo[
                                            "idTermo"
                                        ]; ?>"
                                        id="termo<?= (int) $termo[
                                            "idTermo"
                                        ]; ?>"
                                        <?= (int) $termo[
                                            "obrigatorio"
                                        ] === 1
                                            ? "required"
                                            : ""; ?>
                                    >

                                    <label
                                        class="form-check-label"
                                        for="termo<?= (int) $termo[
                                            "idTermo"
                                        ]; ?>"
                                    >
                                        <?= inscricaoPubEscapar(
                                            (string) $termo[
                                                "titulo"
                                            ]
                                        ); ?>

                                        <?php if (
                                            (int) $termo[
                                                "obrigatorio"
                                            ] === 1
                                        ): ?>
                                            <strong>*</strong>
                                        <?php endif; ?>
                                    </label>

                                    <?php if (
                                        !empty(
                                            $termo[
                                                "descricao"
                                            ]
                                        )
                                    ): ?>
                                        <div
                                            class="small text-muted mt-1"
                                        >
                                            <?= nl2br(
                                                inscricaoPubEscapar(
                                                    (string) $termo[
                                                        "descricao"
                                                    ]
                                                )
                                            ); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (
                                        !empty(
                                            $termo["url"]
                                        )
                                    ): ?>
                                        <a
                                            href="<?= inscricaoPubEscapar(
                                                (string) $termo[
                                                    "url"
                                                ]
                                            ); ?>"
                                            target="_blank"
                                            rel="noopener"
                                            class="small"
                                        >
                                            Ler documento
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div
                        class="d-flex justify-content-end mt-4"
                    >
                        <button
                            type="button"
                            class="btn btn-inscricao px-4"
                            data-proximo="endereco"
                        >
                            Próximo
                            <i
                                class="fa-solid
                                    fa-chevron-right ms-2"
                            ></i>
                        </button>
                    </div>

                </div>
            </section>

            <section
                class="card border-0 shadow-sm etapa-inscricao"
                data-etapa="endereco"
                hidden
            >
                <div class="card-body p-4">

                    <h2 class="h5 fw-bold mb-4">
                        Endereço e Comunidade
                    </h2>

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                País
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="pais"
                                name="pais"
                                value="Brasil"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                CEP
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="cep"
                                name="cep"
                                inputmode="numeric"
                                maxlength="9"
                                required
                            >
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">
                                Rua
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="logradouro"
                                name="logradouro"
                                required
                            >
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">
                                Número
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="numero"
                                name="numero"
                                required
                            >
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">
                                Complemento
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="complemento"
                                name="complemento"
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">
                                Estado
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="estado"
                                name="estado"
                                maxlength="2"
                                value="RS"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">
                                Cidade
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="cidade"
                                name="cidade"
                                required
                            >
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">
                                Bairro
                            </label>
                            <input
                                type="text"
                                class="form-control"
                                id="bairro"
                                name="bairro"
                                required
                            >
                        </div>

                        <div class="col-md-7">
                            <label
                                class="form-label fw-semibold"
                                for="idComunidade"
                            >
                                Comunidade/Paróquia
                                que faz parte
                            </label>

                            <select
                                class="form-select"
                                id="idComunidade"
                                name="idComunidade"
                                required
                            >
                                <option value="">
                                    Selecione
                                </option>

                                <?php foreach (
                                    $comunidades
                                    as $comunidade
                                ): ?>
                                    <option
                                        value="<?= (int) $comunidade[
                                            "id"
                                        ]; ?>"
                                    >
                                        <?= inscricaoPubEscapar(
                                            (string) $comunidade[
                                                "nome_comunidade"
                                            ]
                                        ); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                    </div>

                    <div
                        class="d-flex justify-content-between mt-4"
                    >
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-voltar="pessoal"
                        >
                            Voltar
                        </button>

                        <button
                            type="button"
                            class="btn btn-inscricao px-4"
                            data-proximo="saude"
                        >
                            Próximo
                            <i
                                class="fa-solid
                                    fa-chevron-right ms-2"
                            ></i>
                        </button>
                    </div>

                </div>
            </section>
            <section
                class="card border-0 shadow-sm etapa-inscricao"
                data-etapa="saude"
                hidden
            >
                <div class="card-body p-4">

                    <h2 class="h5 fw-bold mb-4">
                        Saúde e Acessibilidade
                    </h2>

                    <div class="mb-4">
                        <label
                            class="form-label fw-semibold d-block"
                        >
                            Possui restrição a alguma medicação?
                        </label>

                        <div
                            class="form-check form-check-inline"
                        >
                            <input
                                class="form-check-input"
                                type="radio"
                                name="restricao_medicacao"
                                id="medicacaoSim"
                                value="1"
                                required
                            >
                            <label
                                class="form-check-label"
                                for="medicacaoSim"
                            >
                                Sim
                            </label>
                        </div>

                        <div
                            class="form-check form-check-inline"
                        >
                            <input
                                class="form-check-input"
                                type="radio"
                                name="restricao_medicacao"
                                id="medicacaoNao"
                                value="0"
                                checked
                                required
                            >
                            <label
                                class="form-check-label"
                                for="medicacaoNao"
                            >
                                Não
                            </label>
                        </div>

                        <textarea
                            class="form-control mt-2"
                            name="medicacao_detalhes"
                            id="medicacao_detalhes"
                            rows="2"
                            placeholder="Descreva a restrição ou medicação"
                            hidden
                        ></textarea>
                    </div>

                    <div class="mb-4">
                        <label
                            class="form-label fw-semibold"
                            for="deficiencia"
                        >
                            Possui deficiência?
                        </label>

                        <select
                            class="form-select"
                            id="deficiencia"
                            name="deficiencia"
                            required
                        >
                            <option value="Não">Não</option>
                            <option value="Física">Física</option>
                            <option value="Visual">Visual</option>
                            <option value="Auditiva">Auditiva</option>
                            <option value="Intelectual">Intelectual</option>
                            <option value="Múltipla">Múltipla</option>
                            <option value="Outra">Outra</option>
                        </select>

                        <textarea
                            class="form-control mt-2"
                            name="deficiencia_detalhes"
                            id="deficiencia_detalhes"
                            rows="2"
                            placeholder="Se necessário, detalhe"
                        ></textarea>
                    </div>

                    <div class="mb-4">
                        <label
                            class="form-label fw-semibold d-block"
                        >
                            Você precisa de algum
                            recurso de acessibilidade?
                        </label>

                        <div
                            class="form-check form-check-inline"
                        >
                            <input
                                class="form-check-input"
                                type="radio"
                                name="precisa_acessibilidade"
                                id="acessibilidadeSim"
                                value="1"
                                required
                            >
                            <label
                                class="form-check-label"
                                for="acessibilidadeSim"
                            >
                                Sim
                            </label>
                        </div>

                        <div
                            class="form-check form-check-inline"
                        >
                            <input
                                class="form-check-input"
                                type="radio"
                                name="precisa_acessibilidade"
                                id="acessibilidadeNao"
                                value="0"
                                checked
                                required
                            >
                            <label
                                class="form-check-label"
                                for="acessibilidadeNao"
                            >
                                Não
                            </label>
                        </div>

                        <textarea
                            class="form-control mt-2"
                            name="acessibilidade_detalhes"
                            id="acessibilidade_detalhes"
                            rows="2"
                            placeholder="Informe o recurso necessário"
                            hidden
                        ></textarea>
                    </div>

                    <div class="mb-4">
                        <label
                            class="form-label fw-semibold d-block"
                        >
                            Possui restrição alimentar?
                        </label>

                        <div
                            class="form-check form-check-inline"
                        >
                            <input
                                class="form-check-input"
                                type="radio"
                                name="restricao_alimentar"
                                id="alimentarSim"
                                value="1"
                                required
                            >
                            <label
                                class="form-check-label"
                                for="alimentarSim"
                            >
                                Sim
                            </label>
                        </div>

                        <div
                            class="form-check form-check-inline"
                        >
                            <input
                                class="form-check-input"
                                type="radio"
                                name="restricao_alimentar"
                                id="alimentarNao"
                                value="0"
                                checked
                                required
                            >
                            <label
                                class="form-check-label"
                                for="alimentarNao"
                            >
                                Não
                            </label>
                        </div>

                        <textarea
                            class="form-control mt-2"
                            name="alimentar_detalhes"
                            id="alimentar_detalhes"
                            rows="2"
                            placeholder="Descreva a restrição alimentar"
                            hidden
                        ></textarea>
                    </div>

                    <div
                        class="d-flex justify-content-between mt-4"
                    >
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-voltar="endereco"
                        >
                            Voltar
                        </button>

                        <button
                            type="button"
                            class="btn btn-inscricao px-4"
                            data-proximo="<?= $temCamiseta
                                ? "camiseta"
                                : "pagamento"; ?>"
                        >
                            Próximo
                            <i
                                class="fa-solid
                                    fa-chevron-right ms-2"
                            ></i>
                        </button>
                    </div>

                </div>
            </section>

            <?php if ($temCamiseta): ?>
                <section
                    class="card border-0 shadow-sm etapa-inscricao"
                    data-etapa="camiseta"
                    hidden
                >
                    <div class="card-body p-4">

                        <h2 class="h5 fw-bold mb-4">
                            Camiseta
                        </h2>

                        <p class="fw-semibold">
                            Escolha o tamanho da camiseta:
                        </p>

                        <div class="camisetas-grid">
                            <?php foreach (
                                $camisetas
                                as $tamanho
                            ): ?>
                                <label
                                    class="camiseta-opcao"
                                >
                                    <input
                                        type="radio"
                                        name="camiseta"
                                        value="<?= inscricaoPubEscapar(
                                            $tamanho
                                        ); ?>"
                                        required
                                    >
                                    <span>
                                        <?= inscricaoPubEscapar(
                                            $tamanho
                                        ); ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </div>

                        <div
                            class="d-flex justify-content-between mt-4"
                        >
                            <button
                                type="button"
                                class="btn btn-outline-secondary"
                                data-voltar="saude"
                            >
                                Voltar
                            </button>

                            <button
                                type="button"
                                class="btn btn-inscricao px-4"
                                data-proximo="pagamento"
                            >
                                Próximo
                                <i
                                    class="fa-solid
                                        fa-chevron-right ms-2"
                                ></i>
                            </button>
                        </div>

                    </div>
                </section>
            <?php endif; ?>

            <section
                class="card border-0 shadow-sm etapa-inscricao"
                data-etapa="pagamento"
                hidden
            >
                <div class="card-body p-4">

                    <h2 class="h5 fw-bold mb-4">
                        Dados do comprador
                    </h2>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <small
                                class="text-muted d-block"
                            >
                                Nome
                            </small>
                            <strong id="resumoNome">-</strong>
                        </div>

                        <div class="col-md-6">
                            <small
                                class="text-muted d-block"
                            >
                                CPF
                            </small>
                            <strong id="resumoCpf">-</strong>
                        </div>

                        <div class="col-md-6">
                            <small
                                class="text-muted d-block"
                            >
                                E-mail
                            </small>
                            <strong id="resumoEmail">-</strong>
                        </div>

                        <div class="col-md-6">
                            <small
                                class="text-muted d-block"
                            >
                                Telefone
                            </small>
                            <strong id="resumoTelefone">-</strong>
                        </div>
                    </div>

                    <h3 class="h6 fw-bold">
                        Resumo da compra
                    </h3>

                    <div
                        class="resumo-compra p-3 rounded mb-4"
                    >
                        <div
                            class="d-flex justify-content-between gap-3"
                        >
                            <strong>
                                Inscrição
                                <?= inscricaoPubEscapar(
                                    (string) $evento[
                                        "titulo"
                                    ]
                                ); ?>
                            </strong>

                            <strong>
                                <?= $eventoPago
                                    ? "R$ "
                                        . number_format(
                                            $valor,
                                            2,
                                            ",",
                                            "."
                                        )
                                    : "Gratuito"; ?>
                            </strong>
                        </div>
                    </div>

                    <?php if ($eventoPago): ?>
                        <h3 class="h6 fw-bold mb-3">
                            Escolha a forma de pagamento
                        </h3>

                        <div
                            class="formas-pagamento mb-4"
                        >
                            <label
                                class="forma-pagamento"
                            >
                                <input
                                    type="radio"
                                    name="forma_pagamento"
                                    value="PIX"
                                    checked
                                    required
                                >
                                <span>
                                    <i
                                        class="fa-brands
                                            fa-pix"
                                    ></i>
                                    PIX
                                </span>
                            </label>

                            <label
                                class="forma-pagamento"
                            >
                                <input
                                    type="radio"
                                    name="forma_pagamento"
                                    value="Cartao"
                                    required
                                >
                                <span>
                                    <i
                                        class="fa-regular
                                            fa-credit-card"
                                    ></i>
                                    Cartão de crédito
                                </span>
                            </label>

                            <label
                                class="forma-pagamento"
                            >
                                <input
                                    type="radio"
                                    name="forma_pagamento"
                                    value="Boleto"
                                    required
                                >
                                <span>
                                    <i
                                        class="fa-solid
                                            fa-barcode"
                                    ></i>
                                    Boleto
                                </span>
                            </label>
                        </div>

                        <div
                            id="cartaoCampos"
                            class="border rounded p-3 mb-4"
                            hidden
                        >
                            <div
                                class="alert alert-secondary small"
                            >
                                <i
                                    class="fa-solid
                                        fa-shield-halved me-1"
                                ></i>
                                Número, validade e CVV
                                são enviados somente para
                                processamento e não são
                                armazenados pelo sistema.
                            </div>

                            <div class="row g-3">

                                <div class="col-12">
                                    <label
                                        class="form-label"
                                        for="cardHolderName"
                                    >
                                        Nome impresso no cartão
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        name="cardHolderName"
                                        id="cardHolderName"
                                        autocomplete="cc-name"
                                    >
                                </div>

                                <div class="col-12">
                                    <label
                                        class="form-label"
                                        for="cardNumber"
                                    >
                                        Número do cartão
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        name="cardNumber"
                                        id="cardNumber"
                                        inputmode="numeric"
                                        autocomplete="cc-number"
                                        maxlength="23"
                                    >
                                </div>

                                <div class="col-4">
                                    <label class="form-label">
                                        Mês
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        name="cardExpiryMonth"
                                        inputmode="numeric"
                                        autocomplete="cc-exp-month"
                                        maxlength="2"
                                        placeholder="MM"
                                    >
                                </div>

                                <div class="col-4">
                                    <label class="form-label">
                                        Ano
                                    </label>

                                    <input
                                        type="text"
                                        class="form-control"
                                        name="cardExpiryYear"
                                        inputmode="numeric"
                                        autocomplete="cc-exp-year"
                                        maxlength="4"
                                        placeholder="AAAA"
                                    >
                                </div>

                                <div class="col-4">
                                    <label class="form-label">
                                        CVV
                                    </label>

                                    <input
                                        type="password"
                                        class="form-control"
                                        name="cardCcv"
                                        id="cardCcv"
                                        inputmode="numeric"
                                        autocomplete="cc-csc"
                                        maxlength="4"
                                    >
                                </div>

                            </div>
                        </div>
                    <?php endif; ?>

                    <div
                        class="d-flex justify-content-between mt-4"
                    >
                        <button
                            type="button"
                            class="btn btn-outline-secondary"
                            data-voltar="<?= $temCamiseta
                                ? "camiseta"
                                : "saude"; ?>"
                        >
                            Voltar para o formulário
                        </button>

                        <button
                            type="submit"
                            class="btn btn-inscricao px-4"
                            id="btnConcluirInscricao"
                        >
                            <?= $eventoPago
                                ? "Concluir inscrição"
                                : "Confirmar inscrição"; ?>

                            <i
                                class="fa-solid
                                    fa-chevron-right ms-2"
                            ></i>
                        </button>
                    </div>

                </div>
            </section>

        </form>

        <section
            id="resultadoPagamento"
            class="card border-0 shadow-sm mt-4"
            hidden
        >
            <div class="card-body p-4">

                <h2
                    class="h5 fw-bold"
                    id="resultadoTitulo"
                >
                    Inscrição realizada
                </h2>

                <p
                    class="text-muted"
                    id="resultadoMensagem"
                ></p>

                <div
                    id="resultadoPix"
                    class="text-center"
                    hidden
                >
                    <img
                        id="resultadoPixQr"
                        class="pix-qr"
                        alt="QR Code PIX"
                    >

                    <label
                        class="form-label
                            fw-semibold mt-3 d-block"
                    >
                        PIX Copia e Cola
                    </label>

                    <textarea
                        id="resultadoPixCodigo"
                        class="form-control font-monospace"
                        rows="4"
                        readonly
                    ></textarea>
                </div>

                <div
                    id="resultadoBoleto"
                    hidden
                >
                    <label
                        class="form-label fw-semibold"
                    >
                        Linha digitável
                    </label>

                    <textarea
                        id="resultadoBoletoLinha"
                        class="form-control font-monospace"
                        rows="3"
                        readonly
                    ></textarea>

                    <a
                        id="resultadoBoletoLink"
                        class="btn
                            btn-outline-primary mt-3"
                        target="_blank"
                        rel="noopener"
                    >
                        Abrir boleto
                    </a>
                </div>

                <a
                    href="<?= BASE_URL ?>"
                    class="btn
                        btn-outline-secondary mt-4"
                >
                    Voltar ao início
                </a>

            </div>
        </section>

    </main>

    <script>
    window.INSCRICAO_PUBLICA = <?= json_encode(
        [
            "baseUrl" => BASE_URL,
            "idEvento" => $idEvento,
            "eventoPago" => $eventoPago,
            "temCamiseta" => $temCamiseta
        ],
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    ); ?>;
    </script>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.bundle.min.js"
    ></script>

    <script
        src="<?= THEME_JS ?>inscricao/publica.js?v=<?= VERSION ?>"
    ></script>

</body>
</html>
