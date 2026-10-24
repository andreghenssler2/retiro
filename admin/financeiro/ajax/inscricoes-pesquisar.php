<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json; charset=utf-8");

/**
 * Envia uma resposta JSON e encerra a execução.
 */
function responderJson(
    bool $sucesso,
    string $mensagem = "",
    array $dados = [],
    int $statusHttp = 200
): never {

    http_response_code($statusHttp);

    echo json_encode(
        array_merge(
            [
                "sucesso" => $sucesso,
                "mensagem" => $mensagem
            ],
            $dados
        ),
        JSON_UNESCAPED_UNICODE
        | JSON_UNESCAPED_SLASHES
    );

    exit;
}

/**
 * Escapa conteúdo antes de exibir no HTML.
 */
function escaparInscricao(
    mixed $valor
): string {

    return htmlspecialchars(
        (string) ($valor ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

/**
 * Formata o status do pagamento.
 */
function badgeStatusPagamento(
    string $status
): string {

    $status = trim($status);

    return match ($status) {

        "" => '
            <span class="badge bg-secondary">
                Sem pagamento
            </span>
        ',

        "Pendente" => '
            <span class="badge bg-warning text-dark">
                Pendente
            </span>
        ',
        "Vencido" => '
            <span class="badge bg-danger">
                Vencido
            </span>
        ',

        "Pago" => '
            <span class="badge bg-success">
                Pago
            </span>
        ',

        "Cancelado" => '
            <span class="badge bg-danger">
                Cancelado
            </span>
        ',

        "Estornado" => '
            <span class="badge bg-dark">
                Estornado
            </span>
        ',

        default => '
            <span class="badge bg-info text-dark">'
                . escaparInscricao($status)
                . '
            </span>
        '
    };
}

/**
 * Validação do método HTTP.
 */
if (
    !isset($_SERVER["REQUEST_METHOD"])
    || $_SERVER["REQUEST_METHOD"] !== "POST"
) {

    responderJson(
        false,
        "Método de requisição não permitido.",
        [],
        405
    );
}

/**
 * Validação do token CSRF.
 */
$token = $_POST["_token"] ?? "";

if (
    !is_string($token)
    || !Session::validateCsrf($token)
) {

    responderJson(
        false,
        "Token de segurança inválido. Atualize a página e tente novamente.",
        [],
        419
    );
}

/**
 * Parâmetros recebidos.
 */
$pesquisa = trim(
    (string) ($_POST["pesquisa"] ?? "")
);

/*
 * O JavaScript pode enviar tanto idEvento
 * quanto evento. Aceitamos os dois.
 */
$idEventoRecebido =
    $_POST["idEvento"]
    ?? $_POST["evento"]
    ?? 0;

$idEvento = filter_var(
    $idEventoRecebido,
    FILTER_VALIDATE_INT
);

$idEvento = (
    $idEvento !== false
    && $idEvento !== null
)
    ? max(0, (int) $idEvento)
    : 0;

$pagina = filter_var(
    $_POST["pagina"] ?? 1,
    FILTER_VALIDATE_INT
);

$pagina = (
    $pagina !== false
    && $pagina !== null
)
    ? max(1, (int) $pagina)
    : 1;

$limite = filter_var(
    $_POST["limite"] ?? 10,
    FILTER_VALIDATE_INT
);

$limite = (
    $limite !== false
    && $limite !== null
)
    ? (int) $limite
    : 10;

/*
 * Impede valores muito altos na paginação.
 */
$limite = max(
    5,
    min($limite, 50)
);

$offset = ($pagina - 1) * $limite;

try {

    if (
        !isset($db)
        || !$db instanceof PDO
    ) {

        throw new RuntimeException(
            "A conexão PDO não está disponível."
        );
    }

    $condicoes = [
        "1 = 1"
    ];

    $parametros = [];

    /*
     * Cada ocorrência da pesquisa usa um placeholder
     * diferente. Isso evita o erro:
     *
     * SQLSTATE[HY093]: Invalid parameter number
     */
    if ($pesquisa !== "") {

        $valorPesquisa =
            "%" . $pesquisa . "%";

        $condicoes[] = "
            (
                i.nome LIKE :pesquisaNome
                OR i.email LIKE :pesquisaEmail
                OR i.cpf LIKE :pesquisaCpf
                OR CAST(i.idInscricao AS CHAR)
                    LIKE :pesquisaInscricao
                OR e.titulo LIKE :pesquisaEvento
            )
        ";

        $parametros[":pesquisaNome"] =
            $valorPesquisa;

        $parametros[":pesquisaEmail"] =
            $valorPesquisa;

        $parametros[":pesquisaCpf"] =
            $valorPesquisa;

        $parametros[":pesquisaInscricao"] =
            $valorPesquisa;

        $parametros[":pesquisaEvento"] =
            $valorPesquisa;
    }

    if ($idEvento > 0) {

        $condicoes[] = "
            i.idEvento = :idEvento
        ";

        $parametros[":idEvento"] =
            $idEvento;
    }

    /*
     * Não exibe inscrições que já possuem
     * algum pagamento confirmado como Pago.
     */
    $condicoes[] = "
        NOT EXISTS (
            SELECT 1
            FROM pagamentos pagamentoConfirmado
            WHERE
                pagamentoConfirmado.idInscricao =
                    i.idInscricao
                AND pagamentoConfirmado.status = 'Pago'
        )
    ";

    $whereSql = implode(
        " AND ",
        $condicoes
    );

    /*
     * Utiliza o pagamento mais recente da inscrição,
     * caso exista algum pagamento ainda não confirmado.
     */
    $sqlBase = "
        FROM inscricoes i

        INNER JOIN eventos e
            ON e.idEvento = i.idEvento

        LEFT JOIN pagamentos p
            ON p.idPagamento = (
                SELECT MAX(pagamentoRecente.idPagamento)
                FROM pagamentos pagamentoRecente
                WHERE
                    pagamentoRecente.idInscricao =
                        i.idInscricao
            )
    ";

    /*
     * Consulta de contagem.
     */
    $sqlTotal = "
        SELECT
            COUNT(DISTINCT i.idInscricao)

        {$sqlBase}

        WHERE {$whereSql}
    ";

    $stmtTotal = $db->prepare(
        $sqlTotal
    );

    foreach (
        $parametros as $campo => $valor
    ) {

        $tipo = (
            $campo === ":idEvento"
        )
            ? PDO::PARAM_INT
            : PDO::PARAM_STR;

        $stmtTotal->bindValue(
            $campo,
            $valor,
            $tipo
        );
    }

    $stmtTotal->execute();

    $totalRegistros = (int)
        $stmtTotal->fetchColumn();

    $totalPaginas = max(
        1,
        (int) ceil(
            $totalRegistros / $limite
        )
    );

    /*
     * Corrige a página caso ela seja maior
     * do que a quantidade disponível.
     */
    if ($pagina > $totalPaginas) {

        $pagina = $totalPaginas;

        $offset =
            ($pagina - 1)
            * $limite;
    }

    /*
     * Consulta principal.
     */
    $sql = "
        SELECT
            i.idInscricao,
            i.idEvento,
            i.nome,
            i.email,
            i.cpf,

            e.titulo AS tituloEvento,

            p.idPagamento,
            p.status AS statusPagamento,
            p.formaPagamento,
            p.valor AS valorPagamento,

            COALESCE(
                p.valor,
                i.valor,
                0
            ) AS valorInscricao

        {$sqlBase}

        WHERE {$whereSql}

        ORDER BY
            i.idInscricao DESC

        LIMIT :limite
        OFFSET :offset
    ";

    $stmt = $db->prepare(
        $sql
    );

    foreach (
        $parametros as $campo => $valor
    ) {

        $tipo = (
            $campo === ":idEvento"
        )
            ? PDO::PARAM_INT
            : PDO::PARAM_STR;

        $stmt->bindValue(
            $campo,
            $valor,
            $tipo
        );
    }

    $stmt->bindValue(
        ":limite",
        $limite,
        PDO::PARAM_INT
    );

    $stmt->bindValue(
        ":offset",
        $offset,
        PDO::PARAM_INT
    );

    $stmt->execute();

    $inscricoes = $stmt->fetchAll(
        PDO::FETCH_ASSOC
    );

    ob_start();

    if (!$inscricoes) {

        ?>

        <div class="text-center py-5">

            <div class="mb-3">

                <i
                    class="fa-solid fa-user-slash text-muted"
                    style="font-size: 3rem;">
                </i>

            </div>

            <h5 class="text-muted">

                Nenhuma inscrição encontrada

            </h5>

            <p class="text-muted mb-0">

                Tente pesquisar por outro nome,
                CPF, e-mail, número da inscrição
                ou evento.

            </p>

        </div>

        <?php

    } else {

        ?>

        <div class="table-responsive">

            <table
                class="table table-hover align-middle mb-0">

                <thead class="table-light">

                    <tr>

                        <th>
                            Inscrição
                        </th>

                        <th>
                            Participante
                        </th>

                        <th>
                            Evento
                        </th>

                        <th>
                            Contato
                        </th>

                        <th class="text-end">
                            Valor
                        </th>

                        <th class="text-center">
                            Pagamento
                        </th>

                        <th class="text-end">
                            Ação
                        </th>

                    </tr>

                </thead>

                <tbody>

                    <?php foreach (
                        $inscricoes as $inscricao
                    ): ?>

                        <?php

                        $idInscricao = (int) (
                            $inscricao["idInscricao"]
                            ?? 0
                        );

                        $idEventoInscricao = (int) (
                            $inscricao["idEvento"]
                            ?? 0
                        );

                        $nomeParticipante = trim(
                            (string) (
                                $inscricao["nome"]
                                ?? ""
                            )
                        );

                        $emailParticipante = trim(
                            (string) (
                                $inscricao["email"]
                                ?? ""
                            )
                        );

                        $cpfParticipante = trim(
                            (string) (
                                $inscricao["cpf"]
                                ?? ""
                            )
                        );

                        $tituloEvento = trim(
                            (string) (
                                $inscricao["tituloEvento"]
                                ?? ""
                            )
                        );

                        $valorInscricao = (float) (
                            $inscricao["valorInscricao"]
                            ?? 0
                        );

                        $statusPagamento = trim(
                            (string) (
                                $inscricao["statusPagamento"]
                                ?? ""
                            )
                        );

                        ?>

                        <tr
                            data-id-inscricao="<?= $idInscricao ?>"
                            data-id-evento="<?= $idEventoInscricao ?>"
                            data-participante="<?= escaparInscricao(
                                $nomeParticipante
                            ) ?>"
                            data-email="<?= escaparInscricao(
                                $emailParticipante
                            ) ?>"
                            data-evento="<?= escaparInscricao(
                                $tituloEvento
                            ) ?>"
                            data-valor="<?= escaparInscricao(
                                number_format(
                                    $valorInscricao,
                                    2,
                                    ",",
                                    "."
                                )
                            ) ?>">

                            <td>

                                <span class="fw-semibold">

                                    #<?= $idInscricao ?>

                                </span>

                            </td>

                            <td>

                                <div class="fw-semibold">

                                    <?= escaparInscricao(
                                        $nomeParticipante
                                    ) ?>

                                </div>

                                <?php if (
                                    $cpfParticipante !== ""
                                ): ?>

                                    <small class="text-muted">

                                        CPF:
                                        <?= escaparInscricao(
                                            $cpfParticipante
                                        ) ?>

                                    </small>

                                <?php endif; ?>

                            </td>

                            <td>

                                <?= escaparInscricao(
                                    $tituloEvento
                                ) ?>

                            </td>

                            <td>

                                <?php if (
                                    $emailParticipante !== ""
                                ): ?>

                                    <small>

                                        <i
                                            class="fa fa-envelope me-1 text-muted">
                                        </i>

                                        <?= escaparInscricao(
                                            $emailParticipante
                                        ) ?>

                                    </small>

                                <?php else: ?>

                                    <span class="text-muted">

                                        Não informado

                                    </span>

                                <?php endif; ?>

                            </td>

                            <td class="text-end fw-semibold">

                                R$
                                <?= number_format(
                                    $valorInscricao,
                                    2,
                                    ",",
                                    "."
                                ) ?>

                            </td>

                            <td class="text-center">

                                <?= badgeStatusPagamento(
                                    $statusPagamento
                                ) ?>

                            </td>

                            <td class="text-end">

                                <button
                                    type="button"
                                    class="btn btn-sm btn-success btnSelecionarInscricao"
                                    data-id-inscricao="<?= $idInscricao ?>"
                                    data-id-evento="<?= $idEventoInscricao ?>"
                                    data-participante="<?= escaparInscricao(
                                        $nomeParticipante
                                    ) ?>"
                                    data-email="<?= escaparInscricao(
                                        $emailParticipante
                                    ) ?>"
                                    data-evento="<?= escaparInscricao(
                                        $tituloEvento
                                    ) ?>"
                                    data-valor="<?= escaparInscricao(
                                        number_format(
                                            $valorInscricao,
                                            2,
                                            ",",
                                            "."
                                        )
                                    ) ?>">

                                    <i class="fa fa-check me-1"></i>

                                    Selecionar

                                </button>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        </div>

        <?php
    }

    $html = (string) ob_get_clean();

    $inicio = (
        $totalRegistros > 0
    )
        ? $offset + 1
        : 0;

    $fim = min(
        $offset + $limite,
        $totalRegistros
    );

    /*
     * O JavaScript espera os dados de paginação
     * dentro da propriedade "paginacao".
     */
    responderJson(
        true,
        "",
        [
            "html" => $html,

            "paginacao" => [
                "paginaAtual" =>
                    $pagina,

                "pagina" =>
                    $pagina,

                "totalPaginas" =>
                    $totalPaginas,

                "totalRegistros" =>
                    $totalRegistros,

                "total" =>
                    $totalRegistros,

                "inicio" =>
                    $inicio,

                "fim" =>
                    $fim,

                "limite" =>
                    $limite
            ]
        ]
    );

} catch (Throwable $erro) {

    error_log(
        "Erro ao pesquisar inscrições para pagamento: "
        . $erro->getMessage()
        . " | Arquivo: "
        . $erro->getFile()
        . " | Linha: "
        . $erro->getLine()
    );

    /*
     * Durante os testes, os campos erro, arquivo e linha
     * ajudam a identificar problemas. Em produção, podem
     * ser removidos.
     */
    responderJson(
        false,
        "Não foi possível pesquisar as inscrições.",
        [
            "erro" =>
                $erro->getMessage(),

            "arquivo" =>
                basename(
                    $erro->getFile()
                ),

            "linha" =>
                $erro->getLine()
        ],
        500
    );
}