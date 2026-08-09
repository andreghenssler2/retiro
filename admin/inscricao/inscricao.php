<?php

declare(strict_types=1);

require_once "../../config/settings.php";

Middleware::auth();

$eventoModel = new Evento();
$inscricaoModel = new Inscricao();

$id = (int) ($_GET["id"] ?? 0);
$editando = $id > 0;

$dados = [
    "idInscricao" => 0,
    "idEvento" => 0,
    "idUsuario" => 0,
    "nome" => "",
    "cpf" => "",
    "email" => "",
    "telefone" => "",
    "igreja" => "",
    "camiseta" => "",
    "observacoes" => ""
];

if ($editando) {
    $registro = $inscricaoModel->buscar($id);

    if (!$registro) {
        $_SESSION["danger"] = "Inscrição não encontrada.";
        header("Location: inscricoes.php");
        exit;
    }

    $dados = array_merge($dados, $registro);
}

$eventos = $eventoModel->listarAtivos();

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme/dist/select2-bootstrap4.min.css">

<div class="content" id="content">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="fw-bold mb-1">
                <i class="fa fa-user-check text-success"></i>
                <?= $editando ? "Editar inscrição" : "Nova inscrição" ?>
            </h2>
            <p class="text-muted mb-0">
                Relacione um usuário a um evento. O pagamento será gerado automaticamente quando for obrigatório.
            </p>
        </div>

        <a href="inscricoes.php" class="btn btn-outline-secondary">
            <i class="fa fa-arrow-left me-1"></i>
            Voltar
        </a>
    </div>

    <form id="formInscricao" autocomplete="off">
        <input type="hidden" name="_token" id="_token" value="<?= htmlspecialchars(Session::csrf(), ENT_QUOTES, "UTF-8") ?>">
        <input type="hidden" name="idInscricao" value="<?= (int) $dados["idInscricao"] ?>">
        <input type="hidden" name="idUsuario" id="idUsuario" value="<?= (int) $dados["idUsuario"] ?>">

        <div class="row g-4">

            <div class="col-lg-7">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fa fa-link text-primary me-1"></i>
                            Usuário e evento
                        </h5>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="idEvento" class="form-label">Evento</label>
                                <select class="form-select" name="idEvento" id="idEvento" required>
                                    <option value="">Selecione o evento</option>

                                    <?php foreach ($eventos as $evento): ?>
                                        <option value="<?= (int) $evento["idEvento"] ?>"
                                            <?= (int) $dados["idEvento"] === (int) $evento["idEvento"] ? "selected" : "" ?>>
                                            <?= htmlspecialchars((string) $evento["titulo"], ENT_QUOTES, "UTF-8") ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="buscarUsuario" class="form-label">Participante</label>
                                <select id="buscarUsuario" class="form-select" required>
                                    <?php if ((int) $dados["idUsuario"] > 0): ?>
                                        <option value="<?= (int) $dados["idUsuario"] ?>" selected>
                                            <?= htmlspecialchars((string) $dados["nome"], ENT_QUOTES, "UTF-8") ?>
                                        </option>
                                    <?php endif; ?>
                                </select>
                                <div class="form-text">Pesquise por nome, CPF ou e-mail.</div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome</label>
                                <input type="text" id="participanteNome" class="form-control bg-light" readonly
                                    value="<?= htmlspecialchars((string) $dados["nome"], ENT_QUOTES, "UTF-8") ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">CPF</label>
                                <input type="text" id="participanteCpf" class="form-control bg-light" readonly
                                    value="<?= htmlspecialchars((string) $dados["cpf"], ENT_QUOTES, "UTF-8") ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Telefone</label>
                                <input type="text" id="participanteTelefone" class="form-control bg-light" readonly
                                    value="<?= htmlspecialchars((string) $dados["telefone"], ENT_QUOTES, "UTF-8") ?>">
                            </div>

                            <div class="col-md-7">
                                <label class="form-label">E-mail</label>
                                <input type="text" id="participanteEmail" class="form-control bg-light" readonly
                                    value="<?= htmlspecialchars((string) $dados["email"], ENT_QUOTES, "UTF-8") ?>">
                            </div>

                            <div class="col-md-5">
                                <label class="form-label">Comunidade</label>
                                <input type="text" id="participanteIgreja" class="form-control bg-light" readonly
                                    value="<?= htmlspecialchars((string) ($dados["igreja"] ?? ""), ENT_QUOTES, "UTF-8") ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-5">
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fa fa-calendar-check text-success me-1"></i>
                            Configuração do evento
                        </h5>
                    </div>

                    <div class="card-body">
                        <div id="eventoSemSelecao" class="text-center text-muted py-4">
                            Selecione um evento para ver as condições da inscrição.
                        </div>

                        <div id="eventoResumo" class="d-none">
                            <dl class="row mb-0">
                                <dt class="col-6">Valor</dt>
                                <dd class="col-6 text-end fw-semibold" id="eventoValor">R$ 0,00</dd>

                                <dt class="col-6">Vagas disponíveis</dt>
                                <dd class="col-6 text-end" id="eventoDisponiveis">-</dd>

                                <dt class="col-6">Pagamento</dt>
                                <dd class="col-6 text-end" id="eventoPagamento">-</dd>

                                <dt class="col-6">Camiseta</dt>
                                <dd class="col-6 text-end" id="eventoCamiseta">-</dd>
                            </dl>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mb-4 d-none" id="cardCamiseta">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fa fa-shirt text-primary me-1"></i>
                            Camiseta
                        </h5>
                    </div>

                    <div class="card-body">
                        <label for="camiseta" class="form-label">Tamanho da camiseta</label>
                        <select class="form-select" name="camiseta" id="camiseta">
                            <option value="">Selecione</option>
                            <?php foreach (["Não","PP", "P", "M", "G", "GG", "XGG"] as $tamanho): ?>
                                <option value="<?= $tamanho ?>" <?= $dados["camiseta"] === $tamanho ? "selected" : "" ?>>
                                    <?= $tamanho ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">
                            <i class="fa fa-comment text-secondary me-1"></i>
                            Observações
                        </h5>
                    </div>

                    <div class="card-body">
                        <textarea class="form-control" name="observacoes" rows="5" maxlength="1000"
                            placeholder="Informações específicas desta inscrição."><?= htmlspecialchars((string) $dados["observacoes"], ENT_QUOTES, "UTF-8") ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2 mt-4 mb-5">
            <a href="inscricoes.php" class="btn btn-secondary">Cancelar</a>
            <button type="submit" class="btn btn-success" id="btnSalvar">
                <i class="fa fa-save me-1"></i>
                Salvar inscrição
            </button>
        </div>
    </form>
</div>

<?php
$pageScripts = [
    "https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js",
    THEME_JS . "admin/inscricao/inscricao-form.js?v=" . VERSION
];
require_once "../includes/footer.php";
?>
