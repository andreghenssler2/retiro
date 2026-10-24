<?php

require_once "../../config/settings.php";
require_once "../../mod/auth/Geral.php";

Middleware::auth();

$usuario = new Usuario();
$geral = new Geral();

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);
$editando = false;

$dados = [
    "id" => 0,
    "nome" => "",
    "email" => "",
    "telefone" => "",
    "cpf" => "",
    "foto" => "",
    "tipo" => 3,
    "ativo" => 1,
    "idComunidade" => "",
    "logradouro" => "",
    "numero" => "",
    "bairro" => "",
    "cidade" => "",
    "estado" => "RS"
];

if ($id) {
    $registro = $usuario->buscar((int) $id);

    if (!$registro) {
        $_SESSION["danger"] = "Usuário não encontrado.";
        header("Location: usuarios.php");
        exit;
    }

    $dados = array_merge($dados, $registro);
    $editando = true;
}

try {
    $comunidades = $geral->listarComunidadesAtivas();

    if (!is_array($comunidades)) {
        $comunidades = [];
    }

    $idComunidadeAtual = (int) ($dados["idComunidade"] ?? 0);
    $comunidadeAtualPresente = false;

    foreach ($comunidades as $comunidade) {
        if ((int) ($comunidade["id"] ?? 0) === $idComunidadeAtual) {
            $comunidadeAtualPresente = true;
            break;
        }
    }

    if (
        $editando
        && $idComunidadeAtual > 0
        && !$comunidadeAtualPresente
    ) {
        $comunidadeAtual = $geral->buscarComunidadePorId(
            $idComunidadeAtual
        );

        if ($comunidadeAtual) {
            $comunidadeAtual["inativa"] = 1;
            $comunidades[] = $comunidadeAtual;
        }
    }
} catch (Throwable $erro) {
    error_log(
        "Erro ao carregar comunidades no cadastro administrativo: "
        . $erro->getMessage()
    );

    $comunidades = [];
}

$ufs = [
    "AC", "AL", "AP", "AM", "BA", "CE", "DF",
    "ES", "GO", "MA", "MT", "MS", "MG", "PA",
    "PB", "PR", "PE", "PI", "RJ", "RN", "RS",
    "RO", "RR", "SC", "SP", "SE", "TO"
];

$esc = static function (mixed $valor): string {
    return htmlspecialchars(
        (string) $valor,
        ENT_QUOTES,
        "UTF-8"
    );
};

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

?>

<div class="content user-cadastro" id="content">

    <div class="container-fluid">

        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

            <div>
                <h2 class="fw-bold mb-1">
                    <i class="fa fa-user-pen"></i>
                    <?= $editando ? "Editar Usuário" : "Novo Usuário"; ?>
                </h2>

                <p class="text-muted mb-0">
                    Preencha os dados pessoais, endereço e acesso ao sistema.
                </p>
            </div>

            <a href="usuarios.php" class="btn btn-outline-secondary">
                <i class="fa fa-arrow-left"></i>
                Voltar
            </a>

        </div>

        <form
            id="formUsuario"
            enctype="multipart/form-data"
            autocomplete="on"
            novalidate
            data-salvar-url="<?= BASE_URL; ?>admin/user/ajax/usuario-new.php"
            data-verificar-cpf-url="<?= BASE_URL; ?>mod/ajax/verificar-cpf.php"
            data-verificar-email-url="<?= BASE_URL; ?>mod/ajax/verificar-email.php"
        >

            <input
                type="hidden"
                name="_token"
                value="<?= Session::csrf(); ?>"
            >

            <input
                type="hidden"
                name="id"
                value="<?= (int) $dados["id"]; ?>"
            >

            <div class="row g-4">

                <div class="col-xl-3 col-lg-4">

                    <div class="card shadow-sm border-0 h-100">

                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fa fa-camera me-1"></i>
                                Foto do usuário
                            </h5>
                        </div>

                        <div class="card-body text-center d-flex flex-column justify-content-center">

                            <?php
                            $foto = !empty($dados["foto"])
                                ? BASE_URL . "uploads/usuarios/" . rawurlencode((string) $dados["foto"])
                                : THEME_IMG . "user.png";
                            ?>

                            <img
                                src="<?= $esc($foto); ?>"
                                id="previewFoto"
                                class="rounded-circle border shadow-sm mx-auto"
                                alt="Foto do usuário"
                                style="width:180px;height:180px;object-fit:cover;cursor:pointer;"
                            >

                            <input
                                type="file"
                                name="foto"
                                id="foto"
                                class="d-none"
                                accept="image/jpeg,image/png,image/webp"
                            >

                            <div class="d-grid mt-3">
                                <button
                                    type="button"
                                    id="btnSelecionarFoto"
                                    class="btn btn-outline-primary"
                                >
                                    <i class="fa fa-upload"></i>
                                    Selecionar foto
                                </button>
                            </div>

                            <small class="text-muted mt-2">
                                JPG, PNG ou WEBP, com até 5 MB.
                            </small>

                        </div>

                    </div>

                </div>

                <div class="col-xl-9 col-lg-8">

                    <div class="card shadow-sm border-0 mb-4">

                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fa fa-address-card me-1"></i>
                                Dados pessoais
                            </h5>
                        </div>

                        <div class="card-body">

                            <div class="row">

                                <div class="col-md-7 mb-3">
                                    <label class="form-label" for="nome">
                                        Nome completo
                                    </label>

                                    <input
                                        type="text"
                                        name="nome"
                                        id="nome"
                                        class="form-control"
                                        maxlength="150"
                                        autocomplete="name"
                                        required
                                        value="<?= $esc($dados["nome"]); ?>"
                                    >

                                    <div class="invalid-feedback">
                                        Informe o nome completo.
                                    </div>
                                </div>

                                <div class="col-md-5 mb-3">
                                    <label class="form-label" for="cpf">
                                        CPF
                                    </label>

                                    <input
                                        type="text"
                                        name="cpf"
                                        id="cpf"
                                        class="form-control"
                                        maxlength="14"
                                        inputmode="numeric"
                                        autocomplete="off"
                                        required
                                        value="<?= $esc($dados["cpf"]); ?>"
                                    >

                                    <div id="cpfFeedback" class="form-text"></div>
                                </div>

                                <div class="col-md-7 mb-3">
                                    <label class="form-label" for="email">
                                        E-mail
                                    </label>

                                    <input
                                        type="email"
                                        name="email"
                                        id="email"
                                        class="form-control"
                                        maxlength="150"
                                        autocomplete="email"
                                        required
                                        value="<?= $esc($dados["email"]); ?>"
                                    >

                                    <div id="emailFeedback" class="form-text"></div>
                                </div>

                                <div class="col-md-5 mb-3">
                                    <label class="form-label" for="telefone">
                                        Telefone
                                    </label>

                                    <input
                                        type="text"
                                        name="telefone"
                                        id="telefone"
                                        class="form-control"
                                        maxlength="16"
                                        inputmode="tel"
                                        autocomplete="tel"
                                        required
                                        value="<?= $esc($dados["telefone"]); ?>"
                                    >

                                    <div class="invalid-feedback">
                                        Informe um telefone com DDD.
                                    </div>
                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="card shadow-sm border-0 mb-4">

                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fa fa-location-dot me-1"></i>
                                Comunidade e endereço
                            </h5>
                        </div>

                        <div class="card-body">

                            <div class="row">

                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="comunidade">
                                        Comunidade
                                    </label>

                                    <select
                                        name="comunidade"
                                        id="comunidade"
                                        class="form-select"
                                        required
                                    >
                                        <option value="">Selecione...</option>

                                        <?php foreach ($comunidades as $comunidade): ?>
                                            <?php
                                            $idComunidade = (int) ($comunidade["id"] ?? 0);
                                            $nomeComunidade = trim((string) ($comunidade["nome_comunidade"] ?? ""));

                                            if ($idComunidade <= 0 || $nomeComunidade === "") {
                                                continue;
                                            }

                                            $selecionada = $idComunidade === (int) ($dados["idComunidade"] ?? 0);
                                            $inativa = !empty($comunidade["inativa"]);
                                            ?>

                                            <option
                                                value="<?= $idComunidade; ?>"
                                                <?= $selecionada ? "selected" : ""; ?>
                                            >
                                                <?= $esc($nomeComunidade); ?><?= $inativa ? " (inativa)" : ""; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>

                                    <div class="invalid-feedback">
                                        Selecione uma comunidade.
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="logradouro">
                                        Logradouro
                                    </label>

                                    <input
                                        type="text"
                                        name="logradouro"
                                        id="logradouro"
                                        class="form-control"
                                        maxlength="180"
                                        autocomplete="address-line1"
                                        required
                                        value="<?= $esc($dados["logradouro"]); ?>"
                                    >
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label" for="numero">
                                        Número
                                    </label>

                                    <input
                                        type="text"
                                        name="numero"
                                        id="numero"
                                        class="form-control"
                                        maxlength="20"
                                        autocomplete="address-line2"
                                        required
                                        value="<?= $esc($dados["numero"]); ?>"
                                    >
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label" for="bairro">
                                        Bairro
                                    </label>

                                    <input
                                        type="text"
                                        name="bairro"
                                        id="bairro"
                                        class="form-control"
                                        maxlength="120"
                                        required
                                        value="<?= $esc($dados["bairro"]); ?>"
                                    >
                                </div>

                                <div class="col-md-3 mb-3">
                                    <label class="form-label" for="cidade">
                                        Cidade
                                    </label>

                                    <input
                                        type="text"
                                        name="cidade"
                                        id="cidade"
                                        class="form-control"
                                        maxlength="120"
                                        autocomplete="address-level2"
                                        required
                                        value="<?= $esc($dados["cidade"]); ?>"
                                    >
                                </div>

                                <div class="col-md-2 mb-3">
                                    <label class="form-label" for="estado">
                                        Estado
                                    </label>

                                    <select
                                        name="estado"
                                        id="estado"
                                        class="form-select"
                                        autocomplete="address-level1"
                                        required
                                    >
                                        <?php foreach ($ufs as $uf): ?>
                                            <option
                                                value="<?= $uf; ?>"
                                                <?= strtoupper((string) ($dados["estado"] ?? "RS")) === $uf ? "selected" : ""; ?>
                                            >
                                                <?= $uf; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="card shadow-sm border-0 mb-4">

                        <div class="card-header bg-white">
                            <h5 class="mb-0">
                                <i class="fa fa-shield-halved me-1"></i>
                                Acesso ao sistema
                            </h5>
                        </div>

                        <div class="card-body">

                            <div class="row">

                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="tipo">
                                        Perfil
                                    </label>

                                    <select
                                        name="tipo"
                                        id="tipo"
                                        class="form-select"
                                        required
                                    >
                                        <option value="1" <?= (int) $dados["tipo"] === 1 ? "selected" : ""; ?>>Administrador</option>
                                        <option value="2" <?= (int) $dados["tipo"] === 2 ? "selected" : ""; ?>>Moderador</option>
                                        <option value="3" <?= (int) $dados["tipo"] === 3 ? "selected" : ""; ?>>Usuário normal</option>
                                    </select>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label d-block">
                                        Situação
                                    </label>

                                    <div class="form-check form-switch mt-2">
                                        <input
                                            type="checkbox"
                                            class="form-check-input"
                                            id="ativo"
                                            name="ativo"
                                            <?= !empty($dados["ativo"]) ? "checked" : ""; ?>
                                        >

                                        <label class="form-check-label" for="ativo">
                                            Usuário ativo
                                        </label>
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="senha">
                                        <?= $editando ? "Nova senha" : "Senha"; ?>
                                    </label>

                                    <div class="input-group">
                                        <input
                                            type="password"
                                            name="senha"
                                            id="senha"
                                            class="form-control senha_input"
                                            minlength="6"
                                            autocomplete="new-password"
                                            <?= $editando ? "" : "required"; ?>
                                        >

                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary toggleSenha"
                                            aria-label="Mostrar ou ocultar senha"
                                        >
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </div>

                                    <?php if ($editando): ?>
                                        <div class="form-text">
                                            Deixe em branco para manter a senha atual.
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label class="form-label" for="confirmar_senha">
                                        Confirmar senha
                                    </label>

                                    <div class="input-group">
                                        <input
                                            type="password"
                                            name="confirmar_senha"
                                            id="confirmar_senha"
                                            class="form-control senha_input"
                                            minlength="6"
                                            autocomplete="new-password"
                                            <?= $editando ? "" : "required"; ?>
                                        >

                                        <button
                                            type="button"
                                            class="btn btn-outline-secondary toggleSenha"
                                            aria-label="Mostrar ou ocultar senha"
                                        >
                                            <i class="fa fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div id="requisitosSenha" class="small row g-1">
                                        <div id="reqMaiuscula" class="col-md-4 text-danger"><i class="fa fa-times me-1"></i>1 letra maiúscula</div>
                                        <div id="reqMinuscula" class="col-md-4 text-danger"><i class="fa fa-times me-1"></i>1 letra minúscula</div>
                                        <div id="reqNumero" class="col-md-4 text-danger"><i class="fa fa-times me-1"></i>1 número</div>
                                        <div id="reqEspecial" class="col-md-4 text-danger"><i class="fa fa-times me-1"></i>1 caractere especial (! @ # $ &amp; -)</div>
                                        <div id="reqTamanho" class="col-md-4 text-danger"><i class="fa fa-times me-1"></i>Mínimo de 6 caracteres</div>
                                        <div id="reqIgual" class="col-md-4 text-danger"><i class="fa fa-times me-1"></i>Senhas iguais</div>
                                    </div>
                                </div>

                            </div>

                        </div>

                    </div>

                    <div class="d-flex justify-content-end gap-2 mb-5">
                        <a href="usuarios.php" class="btn btn-secondary">
                            <i class="fa fa-arrow-left"></i>
                            Cancelar
                        </a>

                        <button
                            type="submit"
                            id="btnSalvar"
                            class="btn btn-primary"
                            data-texto-normal="<?= $editando ? "Atualizar usuário" : "Cadastrar usuário"; ?>"
                        >
                            <i class="fa fa-save"></i>
                            <?= $editando ? "Atualizar usuário" : "Cadastrar usuário"; ?>
                        </button>
                    </div>

                </div>

            </div>

        </form>

    </div>

</div>

<?php require_once "../includes/footer.php"; ?>

<script>
    const BASE_URL = "<?= BASE_URL; ?>";
    const ID_USUARIO = <?= (int) $dados["id"]; ?>;
    const EDITANDO = <?= $editando ? "true" : "false"; ?>;
</script>

<script src="<?= THEME_JS; ?>script.js"></script>
<script src="<?= THEME_JS; ?>admin/user/admin_user.js"></script>
