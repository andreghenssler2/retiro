<?php
$lang = $_GET['lang'] ?? 'pt_br';
?>

<div class="card-body">
    <div class='m-2'>
        <div class="col-md-12">
            <h3><?php echo $caminho_install; ?></h3>
            <div class="alert alert-info" role="alert">
                <?php echo $caminho_install_info ?>
            </div>
        </div>
        <div class="row col-md-12">
            <div class="col-md-8">
                <form action="#" id='configura_caminho' autocomplete="off" accept-charset="utf-8" method="post"
                    class="mform">
                    <div id="fitem_caminho_sistema" class="form-group row">
                        <div class="col-md-4">
                            <span class="float-sm-right text-nowrap">
                                <span class="initialism text-danger" title="Necessários">
                                    <i class="icon fa fa-exclamation-circle text-danger fa-fw " title="Necessários"
                                        aria-label="Necessários"></i>
                                </span>
                            </span>
                            <label class="col-form-label d-inline " for="caminho">Caminho do Sistema</label>
                        </div>
                        <div class="col-md-8 form-inline felement" data-fieldtype="caminho"
                            id="yui_3_17_2_1_17687428090826_95">
                            <input type="text" class="form-control " name="caminho" id="caminho"
                                value="<?php echo $_SERVER['DOCUMENT_ROOT']; ?>" autocomplete="off"
                                placeholder="Exemplo: /var/www/html/seudominio">
                            <div class="form-control-feedback invalid-feedback" id="id_error_caminho"> </div>
                        </div>
                        <div>
                            <div id="fitem_caminho_pasta" class="form-group row mt-3">
                                <div class="col-md-4">
                                    <span class="float-sm-right text-nowrap">
                                        <span class="initialism text-danger" title="Necessários">
                                            <i class="icon fa fa-exclamation-circle text-danger fa-fw "
                                                title="Necessários" aria-label="Necessários"></i>
                                        </span>
                                    </span>
                                    <label class="col-form-label d-inline " for="caminho">Nome da Pasta</label>
                                </div>
                                <div class="col-md-8 form-inline felement" data-fieldtype="caminho_pasta"
                                    id="yui_3_17_2_1_1768742809086_95">
                                    <input type="text" class="form-control " name="caminho_pasta" id="caminho_pasta"
                                        value="" autocomplete="off" placeholder="Exemplo: arquivos">
                                    <div class="form-control-feedback invalid-feedback" id="id_error_caminho_pasta">
                                    </div>
                                </div>
                                <div>
                                    <div id="fitem_botao_criar" class="form-group row mt-3">
                                        <div class="col-md-3">
                                            <input type="submit" value="Criar" id='criar_caminho_install'
                                                class="btn btn-primary">
                                        </div>
                                        <div class="col-md-4"> </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="m-3">
        <form action="#" method="post" id="formbtnnext">
            <input type="hidden" name="id_form" id="id_form" value="5">
            <input type="hidden" name="idioma" id="idioma" value="<?php echo $lang ?>">
            <button type="submit" disabled class="btn btn-primary" id='btn_next'><?php echo $proximo ?></button>
        </form>
    </div>
</div>