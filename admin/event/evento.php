<?php

require_once "../../config/settings.php";

Middleware::auth();

$evento = new Evento();

$id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

$editando = false;

/*
|--------------------------------------------------------------------------
| Dados padrão
|--------------------------------------------------------------------------
*/

$dados = [

    "idEvento" => 0,

    "titulo" => "",

    "slug" => "",

    "descricao_curta" => "",

    "descricao" => "",

    "tipo" => "Retiro",

    "data_inicio" => "",

    "hora_inicio" => "",

    "data_fim" => "",

    "hora_fim" => "",

    "local" => "",

    "endereco" => "",

    "cidade" => "",

    "estado" => "RS",

    "valor" => "0,00",

    "valor_inscricao" => "0,00",

    "vagas" => "",

    "idade_minima" => "",

    "idade_maxima" => "",

    "imagem" => "",

    "inscricao_inicio" => "",

    "inscricao_fim" => "",

    "pagamento_fim" => "",

    "certificado" => 0,

    "certificado_ativo" => 0,

    "inscricao_aberta" => 1,

    "camiseta_ativa" => 0,

    "pagamento_obrigatorio" => 1,

    "repassar_taxa_asaas" => 0,

    "ativo" => 1

];

/*
|--------------------------------------------------------------------------
| Editando
|--------------------------------------------------------------------------
*/

if ($id) {

    $registro = $evento->buscar($id);

    if (!$registro) {

        $_SESSION["danger"] = "Evento não encontrado.";

        header("Location: eventos.php");

        exit;

    }

    $dados = array_merge($dados, $registro);

    $editando = true;

}

require_once "../includes/header.php";
require_once "../includes/navbar.php";
require_once "../includes/sidebar.php";

?>

<div class="content" id="content">

    <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-4">

            <div>

                <h2 class="fw-bold">

                    <i class="fa fa-calendar-days"></i>

                    <?= $editando ? "Editar Evento" : "Novo Evento"; ?>

                </h2>

                <p class="text-muted">

                    Cadastro de Eventos

                </p>

            </div>

            <a href="eventos.php" class="btn btn-outline-secondary">

                <i class="fa fa-arrow-left"></i>

                Voltar

            </a>

        </div>

        <form id="formEvento" enctype="multipart/form-data" autocomplete="off">

            <input type="hidden" name="_token" value="<?= Session::csrf(); ?>">

            <input type="hidden" name="id" value="<?= (int) $dados["idEvento"]; ?>">

            <div class="row">

                <div class="col-lg-4">

                    <div class="card shadow-sm border-0 mb-4">

                        <div class="card-header">

                            <h5>

                                <i class="fa fa-image"></i>

                                Imagem do Evento

                            </h5>

                        </div>

                        <div class="card-body text-center">

                            <?php

                            $imagem = !empty($dados["imagem"])

                                ? BASE_URL . "uploads/eventos/" . $dados["imagem"]

                                : THEME_IMG . "sem-imagem.png";

                            ?>

                            <img id="previewImagem" src="<?= $imagem; ?>" class="img-fluid rounded border shadow" style="

max-height:250px;

cursor:pointer;

object-fit:cover;

">

                            <input type="file" name="imagem" id="imagem" accept=".jpg,.jpeg,.png,.webp" class="d-none">

                            <div class="d-grid mt-3">

                                <button type="button" id="btnImagem" class="btn btn-primary">

                                    <i class="fa fa-upload"></i>

                                    Selecionar Imagem

                                </button>

                            </div>

                        </div>

                    </div>

                </div>

                <div class="col-lg-8">

                    <div class="card shadow-sm border-0">

                        <div class="card-header">

                            <h5>

                                <i class="fa fa-calendar"></i>

                                Informações do Evento

                            </h5>

                        </div>

                        <div class="card-body">

                            <div class="row">

                                <div class="col-md-8 mb-3">

                                    <label class="form-label">

                                        Título

                                    </label>

                                    <input type="text" name="titulo" id="titulo" class="form-control" maxlength="200"
                                        required value="<?= htmlspecialchars($dados["titulo"]); ?>">

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label class="form-label">

                                        Tipo

                                    </label>

                                    <select name="tipo" id="tipo" class="form-select select2">
                                        <option value="Retiro" <?= $dados["tipo"] == "Retiro" ? "selected" : ""; ?>>Retiro
                                        </option>

                                        <option value="Congresso" <?= $dados["tipo"] == "Congresso" ? "selected" : ""; ?>
                                            >Congresso</option>

                                        <option value="Acampamento" <?= $dados["tipo"] == "Acampamento" ? "selected" : ""; ?>
                                            >Acampamento</option>

                                        <option value="Curso" <?= $dados["tipo"] == "Curso" ? "selected" : ""; ?>>Curso
                                        </option>

                                        <option value="Encontro" <?= $dados["tipo"] == "Encontro" ? "selected" : ""; ?>
                                            >Encontro</option>

                                        <option value="Culto" <?= $dados["tipo"] == "Culto" ? "selected" : ""; ?>>Culto
                                        </option>

                                        <option value="Outro" <?= $dados["tipo"] == "Outro" ? "selected" : ""; ?>>Outro
                                        </option>

                                    </select>

                                </div>

                                <div class="col-md-12 mb-3">

                                    <label class="form-label">

                                        Slug

                                    </label>

                                    <input type="text" name="slug" id="slug" class="form-control" readonly
                                        value="<?= htmlspecialchars($dados["slug"]); ?>">

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">

                                        Data Início

                                    </label>

                                    <input type="date" name="data_inicio" class="form-control" required
                                        value="<?= $dados["data_inicio"]; ?>">

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">

                                        Hora Início

                                    </label>

                                    <input type="time" name="hora_inicio" class="form-control" value="<?= $dados["hora_inicio"]; ?>">

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">

                                        Data Final

                                    </label>

                                    <input type="date" name="data_fim" class="form-control" value="<?= $dados["data_fim"]; ?>">

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">

                                        Hora Final

                                    </label>

                                    <input type="time" name="hora_fim" class="form-control" value="<?= $dados["hora_fim"]; ?>">

                                </div>

                                <div class="col-md-6 mb-3">

                                    <label class="form-label">

                                        Local

                                    </label>

                                    <input type="text" name="local" class="form-control" maxlength="200"
                                        value="<?= htmlspecialchars($dados["local"]); ?>">

                                </div>

                                <div class="col-md-6 mb-3">

                                    <label class="form-label">

                                        Endereço

                                    </label>

                                    <input type="text" name="endereco" class="form-control" maxlength="255"
                                        value="<?= htmlspecialchars($dados["endereco"]); ?>">

                                </div>

                                <div class="col-md-4 mb-3">

                                    <label class="form-label">

                                        Cidade

                                    </label>

                                    <input type="text" name="cidade" class="form-control" maxlength="120"
                                        value="<?= htmlspecialchars($dados["cidade"]); ?>">

                                </div>

                                <div class="col-md-2 mb-3">

                                    <label class="form-label">

                                        UF

                                    </label>

                                    <input type="text" name="estado" maxlength="2" class="form-control"
                                        value="<?= htmlspecialchars($dados["estado"]); ?>">

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">

                                        Valor

                                    </label>

                                    <input type="text" name="valor" class="form-control money"
                                        value="<?= number_format((float) $dados["valor"], 2, ",", "."); ?>">

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">

                                        Valor da inscrição

                                    </label>

                                    <input type="text" name="valor_inscricao" class="form-control money"
                                        value="<?= number_format((float) ($dados["valor_inscricao"] ?? $dados["valor"]), 2, ",", "."); ?>">

                                    <div class="form-text">
                                        Valor utilizado para gerar o pagamento da inscrição.
                                    </div>

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">

                                        Vagas

                                    </label>

                                    <input type="number" name="vagas" class="form-control" value="<?= $dados["vagas"]; ?>">

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">

                                        Idade mínima

                                    </label>

                                    <input type="number" name="idade_minima" class="form-control" value="<?= $dados["idade_minima"]; ?>">

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">

                                        Idade máxima

                                    </label>

                                    <input type="number" name="idade_maxima" class="form-control" value="<?= $dados["idade_maxima"]; ?>">

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">

                                        Início das inscrições

                                    </label>

                                    <input type="datetime-local" name="inscricao_inicio" class="form-control"
                                        value="<?= !empty($dados["inscricao_inicio"]) ? date("Y-m-d\\TH:i", strtotime($dados["inscricao_inicio"])) : ""; ?>">

                                </div>

                                <div class="col-md-3 mb-3">

                                    <label class="form-label">

                                        Fim das inscrições

                                    </label>

                                    <input type="datetime-local" name="inscricao_fim" class="form-control"
                                        value="<?= !empty($dados["inscricao_fim"]) ? date("Y-m-d\\TH:i", strtotime($dados["inscricao_fim"])) : ""; ?>">
                                </div>

                                <div class="col-md-6 mb-3">

                                    <label class="form-label" for="pagamento_fim">

                                        Limite para pagamentos

                                    </label>

                                    <input type="datetime-local" name="pagamento_fim" id="pagamento_fim" class="form-control"
                                        value="<?= !empty($dados["pagamento_fim"]) ? date("Y-m-d\\TH:i", strtotime($dados["pagamento_fim"])) : ""; ?>">

                                    <div class="form-text">
                                        Para eventos pagos, informe até quando o pagamento poderá ser realizado.
                                        O limite máximo é o dia anterior ao início do evento, às 23h59.
                                    </div>
                                </div>
                                <div class="col-12 mb-3">
                                    <label class="form-label">
                                        Descrição Curta
                                    </label>

<textarea

name="descricao_curta"

class="form-control"

rows="3"

maxlength="255"><?= htmlspecialchars($dados["descricao_curta"]); ?></textarea>

</div>

<div class="col-12 mb-3">

<label class="form-label">

Descrição Completa

</label>

<textarea

name="descricao"

id="descricao"

class="form-control"

rows="10"><?= htmlspecialchars($dados["descricao"]); ?></textarea>

</div>

<div class="col-md-3 mb-3">
    <div class="form-check form-switch mt-4">
        <input class="form-check-input" type="checkbox" name="ativo" id="ativo"
            <?= $dados["ativo"] ? "checked" : ""; ?>>
        <label class="form-check-label" for="ativo">Evento ativo</label>
    </div>
</div>

<div class="col-md-3 mb-3">
    <div class="form-check form-switch mt-4">
        <input class="form-check-input" type="checkbox" name="inscricao_aberta" id="inscricao_aberta"
            <?= $dados["inscricao_aberta"] ? "checked" : ""; ?>>
        <label class="form-check-label" for="inscricao_aberta">Inscrições abertas</label>
    </div>
</div>

<div class="col-md-3 mb-3">
    <div class="form-check form-switch mt-4">
        <input class="form-check-input" type="checkbox" name="camiseta_ativa" id="camiseta_ativa"
            <?= $dados["camiseta_ativa"] ? "checked" : ""; ?>>
        <label class="form-check-label" for="camiseta_ativa">Solicitar camiseta</label>
    </div>
</div>

<div class="col-md-3 mb-3">
    <div class="form-check form-switch mt-4">
        <input class="form-check-input" type="checkbox" name="pagamento_obrigatorio" id="pagamento_obrigatorio"
            <?= $dados["pagamento_obrigatorio"] ? "checked" : ""; ?>>
        <label class="form-check-label" for="pagamento_obrigatorio">Pagamento obrigatório</label>
    </div>
</div>

<div class="col-md-6 mb-3">
    <div class="form-check form-switch mt-4">
        <input class="form-check-input" type="checkbox" name="repassar_taxa_asaas" id="repassar_taxa_asaas"
            <?= !empty($dados["repassar_taxa_asaas"]) ? "checked" : ""; ?>>
        <label class="form-check-label" for="repassar_taxa_asaas">
            Repassar taxas do Asaas ao participante
        </label>
    </div>
    <div class="form-text">
        Quando marcado, a cobrança por PIX, boleto ou cartão será acrescida da tarifa atual da conta Asaas.
        Pagamentos manuais não recebem acréscimo.
    </div>
</div>

<div class="col-md-3 mb-3">
    <div class="form-check form-switch mt-4">
        <input class="form-check-input" type="checkbox" name="certificado" id="certificado"
            <?= $dados["certificado"] ? "checked" : ""; ?>>
        <label class="form-check-label" for="certificado">Emitir certificado</label>
    </div>
</div>

</div>

</div>

</div>

<div class="text-end mt-4 mb-5">

<a

href="eventos.php"

class="btn btn-secondary">

<i class="fa fa-arrow-left"></i>

Cancelar

</a>

<button

type="submit"

id="btnSalvar"

class="btn btn-success">

<i class="fa fa-save"></i>

<?= $editando ? "Atualizar Evento" : "Salvar Evento"; ?>

</button>

</div>

</div>

</div>

</form>

</div>

</div>

<?php

require_once "../includes/footer.php";

?>

<script>

const BASE_URL = "<?= BASE_URL ?>";

const ID_EVENTO = <?= (int)$dados["idEvento"]; ?>;

const EDITANDO = <?= $editando ? "true" : "false"; ?>;

</script>

<script src="<?= THEME_JS ?>script.js"></script>

<script src="<?= THEME_JS ?>admin/event/admin_event.js"></script>

<script>

$(function(){

$("#btnImagem,#previewImagem").click(function(){

$("#imagem").trigger("click");

});

$("#imagem").change(function(){

let file=this.files[0];

if(!file){

return;

}

let reader=new FileReader();

reader.onload=function(e){

$("#previewImagem").attr("src",e.target.result);

};

reader.readAsDataURL(file);

});

/*
|--------------------------------------------------------------------------
| Summernote
|--------------------------------------------------------------------------
*/

$("#descricao").summernote({

height:300,

lang:"pt-BR"

});

/*
|--------------------------------------------------------------------------
| Geração automática do slug
|--------------------------------------------------------------------------
*/

// $("#titulo").keyup(function(){

// let texto=$(this).val()

// .toLowerCase()

// .normalize("NFD")

// .replace(/[\u0300-\u036f]/g,"")

// .replace(/[^a-z0-9\s-]/g,"")

// .replace(/\s+/g,"-")

// .replace(/-+/g,"-");

// $("#slug").val(texto);

// });

});

</script>