<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

$requisicaoAjax = strtolower((string) ($_SERVER["HTTP_X_REQUESTED_WITH"] ?? "")) === "xmlhttprequest";
$requisicaoFragmento = (int) ($_GET["fragmento"] ?? 0) === 1;

/*
 * Este arquivo entrega somente o fragmento HTML usado pelo JavaScript.
 * Quando aberto diretamente no navegador, redireciona para a página completa,
 * onde Bootstrap e o CSS responsivo estão carregados.
 */
if (!$requisicaoAjax && !$requisicaoFragmento) {
    $parametros = [
        "pesquisa" => trim((string) ($_GET["pesquisa"] ?? "")),
        "evento" => max(0, (int) ($_GET["evento"] ?? 0)),
        "status" => trim((string) ($_GET["status"] ?? "")),
        "pagamento" => trim((string) ($_GET["pagamento"] ?? "")),
        "pagina" => max(1, (int) ($_GET["pagina"] ?? 1))
    ];

    header(
        "Location: ../inscricoes.php?" . http_build_query($parametros),
        true,
        302
    );
    exit;
}

$inscricao = new Inscricao($db);

$pesquisa = trim((string) ($_GET["pesquisa"] ?? ""));
$idEvento = max(0, (int) ($_GET["evento"] ?? 0));
$status = trim((string) ($_GET["status"] ?? ""));
$pagamento = trim((string) ($_GET["pagamento"] ?? ""));
$paginaAtual = max(1, (int) ($_GET["pagina"] ?? 1));
$limite = 15;

$statusPermitidos = ["", "Pendente", "Confirmada", "Cancelada"];
$pagamentosPermitidos = ["", "Pendente", "Vencido", "Pago", "Cancelado", "Estornado"];

if (!in_array($status, $statusPermitidos, true)) {
    $status = "";
}

if (!in_array($pagamento, $pagamentosPermitidos, true)) {
    $pagamento = "";
}

try {
    $lista = $inscricao->listarPaginado(
        $pesquisa,
        $idEvento,
        $status,
        $pagamento,
        "criado_em",
        "DESC",
        $paginaAtual,
        $limite
    );

    $dados = $lista["dados"];
    $total = (int) $lista["total"];
    $totalPaginas = max(1, (int) ceil($total / $limite));

    if ($paginaAtual > $totalPaginas) {
        $paginaAtual = $totalPaginas;
        $lista = $inscricao->listarPaginado(
            $pesquisa,
            $idEvento,
            $status,
            $pagamento,
            "criado_em",
            "DESC",
            $paginaAtual,
            $limite
        );
        $dados = $lista["dados"];
    }
} catch (Throwable $erro) {
    error_log(
        "Erro em inscricoes-lista.php: "
        . $erro->getMessage()
        . " | Linha: "
        . $erro->getLine()
    );
    ?>
    <div class="alert alert-danger m-3">
        Não foi possível carregar as inscrições.
    </div>
    <?php exit; ?>
<?php }

function classeStatusInscricao(string $status): string
{
    return match ($status) {
        "Confirmada" => "text-bg-success",
        "Pendente" => "text-bg-warning",
        "Cancelada" => "text-bg-danger",
        default => "text-bg-secondary"
    };
}

function classePagamentoInscricao(string $status): string
{
    return match ($status) {
        "Pago" => "text-bg-success",
        "Pendente" => "text-bg-warning",
        "Vencido" => "text-bg-danger",
        "Cancelado" => "text-bg-dark",
        "Estornado" => "text-bg-secondary",
        default => "text-bg-light"
    };
}
?>

<div class="table-responsive inscricoes-table-wrapper">
    <table class="table table-hover align-middle mb-0 inscricoes-table">
        <thead class="table-light">
            <tr>
                <th>#</th>
                <th>Participante</th>
                <th>Evento</th>
                <th>Contato</th>
                <th>Camiseta</th>
                <th class="text-center">Inscrição</th>
                <th class="text-center">Pagamento</th>
                <th class="text-center">Presença</th>
                <th class="text-end">Ações</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$dados): ?>
                <tr class="inscricoes-vazio">
                    <td colspan="9" class="text-center p-5 text-muted">
                        <i class="fa fa-users fa-3x mb-3"></i>
                        <div>Nenhuma inscrição encontrada.</div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($dados as $item): ?>
                    <?php
                    $idInscricao = (int) ($item["idInscricao"] ?? 0);
                    $statusInscricao = (string) ($item["status"] ?? "Pendente");
                    $statusPagamento = (string) ($item["pagamento"] ?? "Pendente");
                    $camisetaAtiva = (int) ($item["camiseta_ativa"] ?? 0) === 1;
                    $presencaConfirmada = (int) ($item["presenca"] ?? 0) === 1;
                    $eventoEmiteCertificado =
                        (int) ($item["evento_certificado"] ?? 0) === 1
                        || (int) ($item["evento_certificado_ativo"] ?? 0) === 1;
                    $certificadoEmitido = (int) ($item["certificado"] ?? 0) === 1;
                    $pagamentoObrigatorio =
                        (int) ($item["pagamento_obrigatorio"] ?? 0) === 1;
                    $inscricaoCancelada = $statusInscricao === "Cancelada";
                    $pagamentoConfirmado = $statusPagamento === "Pago";

                    /*
                     * Eventos gratuitos permitem confirmação normal da presença.
                     * Em eventos pagos, a presença somente pode ser alterada depois
                     * que o pagamento estiver confirmado como Pago.
                     */
                    $presencaPermitida =
                        !$inscricaoCancelada
                        && (!$pagamentoObrigatorio || $pagamentoConfirmado);

                    $motivoPresencaBloqueada = $inscricaoCancelada
                        ? "A inscrição está cancelada"
                        : "Disponível após a confirmação do pagamento";
                    ?>
                    <tr class="inscricao-row">
                        <td class="fw-semibold id-cell" data-label="Inscrição">#<?= $idInscricao ?></td>

                        <td class="participante-cell" data-label="Participante">
                            <div class="fw-semibold">
                                <?= htmlspecialchars((string) ($item["nome"] ?? ""), ENT_QUOTES, "UTF-8") ?>
                            </div>
                            <?php if (!empty($item["cpf"])): ?>
                                <small class="text-muted">
                                    CPF: <?= htmlspecialchars((string) $item["cpf"], ENT_QUOTES, "UTF-8") ?>
                                </small>
                            <?php endif; ?>
                        </td>

                        <td class="evento-cell" data-label="Evento">
                            <?= htmlspecialchars((string) ($item["titulo"] ?? ""), ENT_QUOTES, "UTF-8") ?>
                        </td>

                        <td class="contato-cell" data-label="Contato">
                            <div><?= htmlspecialchars((string) ($item["telefone"] ?? ""), ENT_QUOTES, "UTF-8") ?></div>
                            <small class="text-muted">
                                <?= htmlspecialchars((string) ($item["email"] ?? ""), ENT_QUOTES, "UTF-8") ?>
                            </small>
                        </td>

                        <td class="camiseta-cell" data-label="Camiseta">
                            <?php if ($camisetaAtiva): ?>
                                <span class="badge text-bg-info">
                                    <?= htmlspecialchars((string) ($item["camiseta"] ?? "Não informado"), ENT_QUOTES, "UTF-8") ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Não solicitada</span>
                            <?php endif; ?>
                        </td>

                        <td class="text-center status-cell" data-label="Inscrição">
                            <span class="badge <?= classeStatusInscricao($statusInscricao) ?>">
                                <?= htmlspecialchars($statusInscricao, ENT_QUOTES, "UTF-8") ?>
                            </span>
                        </td>

                        <td class="text-center pagamento-cell" data-label="Pagamento">
                            <span class="badge <?= classePagamentoInscricao($statusPagamento) ?>">
                                <?= htmlspecialchars($statusPagamento, ENT_QUOTES, "UTF-8") ?>
                            </span>
                        </td>

                        <td class="text-center presenca-cell" data-label="Presença">
                            <span class="badge <?= (int) ($item["presenca"] ?? 0) === 1 ? "text-bg-success" : "text-bg-secondary" ?>">
                                <?= (int) ($item["presenca"] ?? 0) === 1 ? "Sim" : "Não" ?>
                            </span>
                        </td>

                        <td class="text-end acao-cell" data-label="Ações">
                            <div class="acoes-inscricao">
                                <button type="button" class="btn btn-outline-info btn-view"
                                    data-id="<?= $idInscricao ?>" title="Visualizar" aria-label="Visualizar inscrição">
                                    <i class="fa fa-eye"></i>
                                    <span class="acao-texto">Visualizar</span>
                                </button>

                                <a href="inscricao.php?id=<?= $idInscricao ?>"
                                    class="btn btn-outline-primary" title="Editar vínculo e camiseta" aria-label="Editar inscrição">
                                    <i class="fa fa-pencil"></i>
                                    <span class="acao-texto">Editar</span>
                                </a>

                                <?php if ($presencaPermitida): ?>
                                    <button type="button" class="btn btn-outline-secondary btn-presenca"
                                        data-id="<?= $idInscricao ?>"
                                        title="<?= $presencaConfirmada ? 'Remover presença' : 'Confirmar presença' ?>"
                                        aria-label="<?= $presencaConfirmada ? 'Remover presença' : 'Confirmar presença' ?>">
                                        <i class="fa fa-user-check"></i>
                                        <span class="acao-texto"><?= $presencaConfirmada ? 'Remover presença' : 'Confirmar presença' ?></span>
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-outline-secondary" disabled
                                        title="<?= htmlspecialchars($motivoPresencaBloqueada, ENT_QUOTES, 'UTF-8') ?>"
                                        aria-label="<?= htmlspecialchars($motivoPresencaBloqueada, ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="fa fa-lock"></i>
                                        <span class="acao-texto"><?= $inscricaoCancelada ? 'Inscrição cancelada' : 'Aguardando pagamento' ?></span>
                                    </button>
                                <?php endif; ?>

                                <?php if ($eventoEmiteCertificado): ?>
                                    <?php if ($presencaConfirmada): ?>
                                        <button type="button"
                                            class="btn <?= $certificadoEmitido ? 'btn-outline-success' : 'btn-outline-dark' ?> btn-emitir-certificado"
                                            data-id="<?= $idInscricao ?>"
                                            data-nome="<?= htmlspecialchars((string) ($item["nome"] ?? ""), ENT_QUOTES, "UTF-8") ?>"
                                            data-emitido="<?= $certificadoEmitido ? 1 : 0 ?>"
                                            title="<?= $certificadoEmitido ? 'Reenviar certificado' : 'Emitir certificado' ?>"
                                            aria-label="<?= $certificadoEmitido ? 'Reenviar certificado' : 'Emitir certificado' ?>">
                                            <i class="fa <?= $certificadoEmitido ? 'fa-envelope-circle-check' : 'fa-award' ?>"></i>
                                            <span class="acao-texto"><?= $certificadoEmitido ? 'Reenviar certificado' : 'Emitir certificado' ?></span>
                                        </button>
                                    <?php else: ?>
                                        <button type="button" class="btn btn-outline-secondary" disabled
                                            title="Disponível após confirmar a presença"
                                            aria-label="Certificado disponível após confirmar a presença">
                                            <i class="fa fa-lock"></i>
                                            <span class="acao-texto">Confirme a presença</span>
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <button type="button" class="btn btn-outline-danger btn-delete"
                                    data-id="<?= $idInscricao ?>"
                                    data-nome="<?= htmlspecialchars((string) ($item["nome"] ?? ""), ENT_QUOTES, "UTF-8") ?>"
                                    title="Excluir inscrição e pagamento vinculado" aria-label="Excluir inscrição">
                                    <i class="fa fa-trash"></i>
                                    <span class="acao-texto">Excluir</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPaginas > 1): ?>
    <nav class="pt-3" aria-label="Paginação das inscrições">
        <ul class="pagination pagination-sm justify-content-center mb-0">
            <?php
            $inicio = max(1, $paginaAtual - 2);
            $fim = min($totalPaginas, $paginaAtual + 2);
            ?>

            <li class="page-item <?= $paginaAtual <= 1 ? "disabled" : "" ?>">
                <button type="button" class="page-link pagina-inscricao"
                    data-pagina="<?= max(1, $paginaAtual - 1) ?>">
                    <i class="fa fa-chevron-left"></i>
                </button>
            </li>

            <?php for ($p = $inicio; $p <= $fim; $p++): ?>
                <li class="page-item <?= $paginaAtual === $p ? "active" : "" ?>">
                    <button type="button" class="page-link pagina-inscricao" data-pagina="<?= $p ?>">
                        <?= $p ?>
                    </button>
                </li>
            <?php endfor; ?>

            <li class="page-item <?= $paginaAtual >= $totalPaginas ? "disabled" : "" ?>">
                <button type="button" class="page-link pagina-inscricao"
                    data-pagina="<?= min($totalPaginas, $paginaAtual + 1) ?>">
                    <i class="fa fa-chevron-right"></i>
                </button>
            </li>
        </ul>
    </nav>
<?php endif; ?>
