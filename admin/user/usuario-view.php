<?php

require_once "../../config/settings.php";

Middleware::auth();

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

$db = Config::getDB();
$usuario = new Usuario();

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$id) {
    $_SESSION["danger"] = "Usuário não encontrado.";
    header("Location: usuarios.php");
    exit;
}

$dados = $usuario->buscar((int) $id);

if (!$dados) {
    $_SESSION["danger"] = "Usuário não encontrado.";
    header("Location: usuarios.php");
    exit;
}

$foto = !empty($dados["foto"])
    ? BASE_URL . "uploads/usuarios/" . $dados["foto"]
    : THEME_IMG . "user.png";

$escapar = static function ($valor): string {
    return htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8");
};

$exibir = static function ($valor) use ($escapar): string {
    $texto = trim((string) ($valor ?? ""));
    return $texto !== "" ? $escapar($texto) : "-";
};

$formatarCpf = static function ($cpf) use ($exibir): string {
    $numeros = preg_replace('/\D+/', '', (string) ($cpf ?? ""));

    if (strlen($numeros) !== 11) {
        return $exibir($cpf);
    }

    return substr($numeros, 0, 3) . "."
        . substr($numeros, 3, 3) . "."
        . substr($numeros, 6, 3) . "-"
        . substr($numeros, 9, 2);
};

$formatarTelefone = static function ($telefone) use ($exibir): string {
    $numeros = preg_replace('/\D+/', '', (string) ($telefone ?? ""));

    if (strlen($numeros) === 11) {
        return "(" . substr($numeros, 0, 2) . ") "
            . substr($numeros, 2, 5) . "-"
            . substr($numeros, 7, 4);
    }

    if (strlen($numeros) === 10) {
        return "(" . substr($numeros, 0, 2) . ") "
            . substr($numeros, 2, 4) . "-"
            . substr($numeros, 6, 4);
    }

    return $exibir($telefone);
};

$perfis = [
    1 => "Administrador",
    2 => "Moderador",
    3 => "Usuário normal"
];

$perfil = $perfis[(int) ($dados["tipo"] ?? 0)] ?? "Não definido";

$comunidade = "-";
$idComunidade = (int) ($dados["idComunidade"] ?? 0);

if ($idComunidade > 0) {
    try {
        $stmtComunidade = $db->prepare(
            "SELECT nome_comunidade
             FROM minha_comunidade
             WHERE id = :id
             LIMIT 1"
        );
        $stmtComunidade->execute([":id" => $idComunidade]);
        $nomeComunidade = $stmtComunidade->fetchColumn();

        if ($nomeComunidade !== false && trim((string) $nomeComunidade) !== "") {
            $comunidade = (string) $nomeComunidade;
        }
    } catch (Throwable $e) {
        $comunidade = "-";
    }
}

$partesEndereco = [];

if (!empty($dados["logradouro"])) {
    $logradouroCompleto = trim((string) $dados["logradouro"]);

    if (!empty($dados["numero"])) {
        $logradouroCompleto .= ", " . trim((string) $dados["numero"]);
    }

    $partesEndereco[] = $logradouroCompleto;
}

if (!empty($dados["bairro"])) {
    $partesEndereco[] = trim((string) $dados["bairro"]);
}

$cidadeUf = [];

if (!empty($dados["cidade"])) {
    $cidadeUf[] = trim((string) $dados["cidade"]);
}

if (!empty($dados["estado"])) {
    $cidadeUf[] = strtoupper(trim((string) $dados["estado"]));
}

if ($cidadeUf) {
    $partesEndereco[] = implode("/", $cidadeUf);
}

$enderecoCompleto = $partesEndereco
    ? implode(" - ", $partesEndereco)
    : "-";

?>
<div class="content user-cadastro" id="content">
    <div class="container-fluid">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">
                    <i class="fa fa-user"></i>
                    Perfil do Usuário
                </h2>
                <p class="text-muted mb-0">
                    Visualização completa do cadastro.
                </p>
            </div>

            <div class="d-flex gap-2">
                <a href="usuarios.php" class="btn btn-outline-secondary">
                    <i class="fa fa-arrow-left"></i>
                    Voltar
                </a>

                <a href="usuario.php?id=<?= (int) $dados["id"] ?>" class="btn btn-primary">
                    <i class="fa fa-pencil"></i>
                    Editar
                </a>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body text-center p-4">
                        <img src="<?= $escapar($foto) ?>" alt="Foto de <?= $escapar($dados["nome"] ?? "usuário") ?>"
                            class="rounded-circle shadow border" style="width:180px;height:180px;object-fit:cover;">

                        <h3 class="mt-3 mb-1">
                            <?= $exibir($dados["nome"] ?? "") ?>
                        </h3>

                        <p class="text-muted mb-3">
                            <?= $exibir($dados["email"] ?? "") ?>
                        </p>

                        <?php if ((int) ($dados["ativo"] ?? 0) === 1): ?>
                            <span class="badge bg-success">
                                <i class="fa fa-circle-check me-1"></i>
                                Ativo
                            </span>
                        <?php else: ?>
                            <span class="badge bg-danger">
                                <i class="fa fa-circle-xmark me-1"></i>
                                Inativo
                            </span>
                        <?php endif; ?>

                        <hr>

                        <div class="row text-center g-3">
                            <div class="col-6">
                                <h6 class="mb-1">
                                    <?= $escapar($perfil) ?>
                                </h6>
                                <small class="text-muted">Perfil</small>
                            </div>

                            <div class="col-6">
                                <h6 class="mb-1">
                                    <?= !empty($dados["ultimo_login"])
                                        ? date("d/m/Y", strtotime($dados["ultimo_login"]))
                                        : "-" ?>
                                </h6>
                                <small class="text-muted">Último login</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fa fa-address-card text-primary me-1"></i>
                            Dados pessoais
                        </h5>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <label class="fw-bold text-muted small">Nome</label>
                                <div><?= $exibir($dados["nome"] ?? "") ?></div>
                            </div>

                            <div class="col-md-6 mb-4">
                                <label class="fw-bold text-muted small">CPF</label>
                                <div><?= $escapar($formatarCpf($dados["cpf"] ?? "")) ?></div>
                            </div>

                            <div class="col-md-6 mb-4">
                                <label class="fw-bold text-muted small">E-mail</label>
                                <div><?= $exibir($dados["email"] ?? "") ?></div>
                            </div>

                            <div class="col-md-6 mb-4">
                                <label class="fw-bold text-muted small">Telefone</label>
                                <div><?= $escapar($formatarTelefone($dados["telefone"] ?? "")) ?></div>
                            </div>

                            <div class="col-md-6 mb-4">
                                <label class="fw-bold text-muted small">Comunidade</label>
                                <div><?= $escapar($comunidade) ?></div>
                            </div>

                            <div class="col-md-3 mb-4">
                                <label class="fw-bold text-muted small">Perfil</label>
                                <div><?= $escapar($perfil) ?></div>
                            </div>

                            <div class="col-md-3 mb-4">
                                <label class="fw-bold text-muted small">Status</label>
                                <div><?= (int) ($dados["ativo"] ?? 0) === 1 ? "Ativo" : "Inativo" ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fa fa-location-dot text-primary me-1"></i>
                            Endereço
                        </h5>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8 mb-4">
                                <label class="fw-bold text-muted small">Logradouro</label>
                                <div><?= $exibir($dados["logradouro"] ?? "") ?></div>
                            </div>

                            <div class="col-md-4 mb-4">
                                <label class="fw-bold text-muted small">Número</label>
                                <div><?= $exibir($dados["numero"] ?? "") ?></div>
                            </div>

                            <div class="col-md-5 mb-4">
                                <label class="fw-bold text-muted small">Bairro</label>
                                <div><?= $exibir($dados["bairro"] ?? "") ?></div>
                            </div>

                            <div class="col-md-5 mb-4">
                                <label class="fw-bold text-muted small">Cidade</label>
                                <div><?= $exibir($dados["cidade"] ?? "") ?></div>
                            </div>

                            <div class="col-md-2 mb-4">
                                <label class="fw-bold text-muted small">Estado</label>
                                <div><?= $exibir(strtoupper((string) ($dados["estado"] ?? ""))) ?></div>
                            </div>

                            <div class="col-12">
                                <label class="fw-bold text-muted small">Endereço completo</label>
                                <div><?= $escapar($enderecoCompleto) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0">
                            <i class="fa fa-clock-rotate-left text-primary me-1"></i>
                            Informações do sistema
                        </h5>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3 mb-md-0">
                                <label class="fw-bold text-muted small">Último login</label>
                                <div>
                                    <?= !empty($dados["ultimo_login"])
                                        ? date("d/m/Y H:i", strtotime($dados["ultimo_login"]))
                                        : "Nunca acessou" ?>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <label class="fw-bold text-muted small">Data do cadastro</label>
                                <div>
                                    <?= !empty($dados["created_at"])
                                        ? date("d/m/Y H:i", strtotime($dados["created_at"]))
                                        : "-" ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0 mt-4 mb-4">
                    <div class="card-body d-flex flex-column flex-sm-row justify-content-end gap-2">
                        <a href="usuario.php?id=<?= (int) $dados["id"] ?>" class="btn btn-primary">
                            <i class="fa fa-pencil"></i>
                            Editar
                        </a>

                        <button type="button" class="btn btn-danger" id="btnExcluir">
                            <i class="fa fa-trash"></i>
                            Excluir
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    $("#btnExcluir").on("click", function () {
        Swal.fire({
            title: "Excluir usuário?",
            text: "Esta ação não poderá ser desfeita.",
            icon: "warning",
            showCancelButton: true,
            confirmButtonText: "Excluir",
            cancelButtonText: "Cancelar"
        }).then(function (resultado) {
            if (resultado.isConfirmed) {
                window.location.href = "usuario-delete.php?id=<?= (int) $dados["id"] ?>";
            }
        });
    });
</script>

<?php require_once "../includes/footer.php"; ?>
<script src="<?= THEME_JS ?>admin/user/admin_user.js"></script>