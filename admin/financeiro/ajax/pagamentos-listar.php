<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json; charset=utf-8");

function responderListaPagamento(array $dados, int $http = 200): never
{
    http_response_code($http);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderListaPagamento([
        "sucesso" => false,
        "mensagem" => "Método de requisição não permitido."
    ], 405);
}

if (!Session::validateCsrf((string) ($_POST["_token"] ?? ""))) {
    responderListaPagamento([
        "sucesso" => false,
        "mensagem" => "Token de segurança inválido. Atualize a página."
    ], 419);
}

$pagina = max(1, (int) ($_POST["pagina"] ?? 1));
$limite = 10;
$offset = ($pagina - 1) * $limite;

$status = trim((string) ($_POST["status"] ?? ""));
$forma = trim((string) ($_POST["forma"] ?? ""));

$statusPermitidos = ["", "Pendente", "Vencido", "Pago", "Cancelado", "Estornado"];
$formasPermitidas = ["", "NaoDefinido", "PIX", "Cartao", "Boleto", "Dinheiro", "Transferencia"];

if (!in_array($status, $statusPermitidos, true)) {
    $status = "";
}

if (!in_array($forma, $formasPermitidas, true)) {
    $forma = "";
}

$filtros = [
    "pesquisa" => trim((string) ($_POST["pesquisa"] ?? "")),
    "evento" => max(0, (int) ($_POST["evento"] ?? 0)),
    "status" => $status,
    "forma" => $forma,
    "limite" => $limite,
    "offset" => $offset
];

try {
    $pagamento = new Pagamento($db);
    $lista = $pagamento->pesquisar($filtros);
    $totalRegistros = $pagamento->totalPesquisar($filtros);
    $totalPaginas = max(1, (int) ceil($totalRegistros / $limite));

    if ($pagina > $totalPaginas) {
        $pagina = $totalPaginas;
        $offset = ($pagina - 1) * $limite;
        $filtros["offset"] = $offset;
        $lista = $pagamento->pesquisar($filtros);
    }

    ob_start();
    ?>
    <table class="table table-hover align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>Código</th>
                <th>Participante</th>
                <th>Evento</th>
                <th>Forma</th>
                <th class="text-end">Valor</th>
                <th class="text-center">Status</th>
                <th>Recebimento</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$lista): ?>
                <tr>
                    <td colspan="8" class="text-center py-5 text-muted">
                        <i class="fa fa-receipt fa-2x d-block mb-3"></i>
                        Nenhum pagamento de inscrição encontrado.
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($lista as $item): ?>
                    <?php
                    $idPagamento = (int) ($item["idPagamento"] ?? 0);
                    $statusPagamento = (string) ($item["status"] ?? "Pendente");
                    $formaPagamento = (string) ($item["formaPagamento"] ?? "NaoDefinido");

                    $classeStatus = match ($statusPagamento) {
                        "Pago" => "text-bg-success",
                        "Pendente" => "text-bg-warning",
                        "Vencido" => "text-bg-danger",
                        "Cancelado" => "text-bg-dark",
                        "Estornado" => "text-bg-secondary",
                        default => "text-bg-light"
                    };

                    $nomeForma = match ($formaPagamento) {
                        "NaoDefinido" => "A definir",
                        "Cartao" => "Cartão de crédito",
                        "Transferencia" => "Transferência",
                        default => $formaPagamento
                    };

                    $integracao = (string) ($item["integracao"] ?? "Manual");
                    $dataPagamento = trim((string) ($item["dataPagamento"] ?? ""));

                    /*
                     * HORA_RECEBIMENTO_LISTA_V1_1
                     */
                    $recebidoEm =
                        trim(
                            (string) (
                                $item["recebidoEm"]
                                ?? ""
                            )
                        );

                    $dataRecebimento =
                        $recebidoEm !== ""
                            ? $recebidoEm
                            : $dataPagamento;
                    ?>
                    <tr>
                        <td>
                            <span class="fw-semibold">
                                <?= htmlspecialchars((string) ($item["codigo"] ?? "#{$idPagamento}"), ENT_QUOTES, "UTF-8") ?>
                            </span>
                            <small class="text-muted d-block">
                                Inscrição #<?= (int) ($item["idInscricao"] ?? 0) ?>
                            </small>
                        </td>

                        <td>
                            <div class="fw-semibold">
                                <?= htmlspecialchars((string) ($item["participante"] ?? "Não informado"), ENT_QUOTES, "UTF-8") ?>
                            </div>
                            <?php if (!empty($item["email"])): ?>
                                <small class="text-muted">
                                    <?= htmlspecialchars((string) $item["email"], ENT_QUOTES, "UTF-8") ?>
                                </small>
                            <?php endif; ?>
                        </td>

                        <td>
                            <?= htmlspecialchars((string) ($item["tituloEvento"] ?? "Não informado"), ENT_QUOTES, "UTF-8") ?>
                        </td>

                        <td>
                            <span class="<?= $formaPagamento === "NaoDefinido" ? "text-muted" : "" ?>">
                                <?= htmlspecialchars($nomeForma, ENT_QUOTES, "UTF-8") ?>
                            </span>
                            <?php if ($integracao === "Asaas"): ?>
                                <span class="badge text-bg-primary d-block mt-1" style="width:max-content">Asaas</span>
                            <?php elseif ($formaPagamento === "NaoDefinido"): ?>
                                <span class="badge text-bg-light border d-block mt-1" style="width:max-content">Aguardando escolha</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-end fw-semibold">
                            R$ <?= number_format((float) ($item["valor"] ?? 0), 2, ",", ".") ?>
                        </td>

                        <td class="text-center">
                            <span class="badge <?= $classeStatus ?>">
                                <?= htmlspecialchars($statusPagamento, ENT_QUOTES, "UTF-8") ?>
                            </span>
                        </td>

                        <td>
                            <?php if (
                                $dataRecebimento !== ""
                                && $dataRecebimento
                                    !== "0000-00-00 00:00:00"
                            ): ?>
                                <?php
                                $dataRecebimentoObj =
                                    new DateTime(
                                        $dataRecebimento
                                    );

                                $temHoraRecebimento =
                                    $recebidoEm !== ""
                                    || $dataRecebimentoObj
                                        ->format("H:i:s")
                                        !== "00:00:00";
                                ?>

                                <span class="fw-semibold">
                                    <?= htmlspecialchars(
                                        $dataRecebimentoObj
                                            ->format(
                                                $temHoraRecebimento
                                                    ? "d/m/Y H:i"
                                                    : "d/m/Y"
                                            ),
                                        ENT_QUOTES,
                                        "UTF-8"
                                    ); ?>
                                </span>

                                <?php if (
                                    !$temHoraRecebimento
                                    && $statusPagamento
                                        === "Pago"
                                ): ?>
                                    <small
                                        class="text-muted d-block"
                                    >
                                        Horário não registrado
                                    </small>
                                <?php endif; ?>

                            <?php elseif ($statusPagamento === "Vencido" && !empty($item["dataVencimento"])): ?>
                                <span class="text-danger">
                                    Vencido em
                                    <?= htmlspecialchars((new DateTime((string) $item["dataVencimento"]))->format("d/m/Y"), ENT_QUOTES, "UTF-8") ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Ainda não recebido</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary btn-visualizar"
                                    data-id="<?= $idPagamento ?>" title="Visualizar pagamento">
                                    <i class="fa fa-eye"></i>
                                </button>
                                <?php if ($statusPagamento === "Pendente"): ?>
                                    <button type="button" class="btn btn-outline-success btn-editar"
                                        data-id="<?= $idPagamento ?>" title="Atualizar recebimento">
                                        <i class="fa fa-hand-holding-dollar"></i>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    $html = ob_get_clean();

    responderListaPagamento([
        "sucesso" => true,
        "html" => $html,
        "pagina" => $pagina,
        "totalPaginas" => $totalPaginas,
        "totalRegistros" => $totalRegistros,
        "inicio" => $totalRegistros > 0 ? $offset + 1 : 0,
        "fim" => min($offset + count($lista), $totalRegistros)
    ]);
} catch (Throwable $erro) {
    error_log(
        "Erro em pagamentos-listar.php: "
        . $erro->getMessage()
        . " | Linha: "
        . $erro->getLine()
    );

    responderListaPagamento([
        "sucesso" => false,
        "mensagem" => "Não foi possível carregar os pagamentos."
    ], 500);
}
