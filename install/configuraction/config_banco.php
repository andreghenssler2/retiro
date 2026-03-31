<?php
    $lang = $_GET['lang'] ?? 'pt_br';
?>

<div class="card-body">
    <div class='m-2 row'>
        <div class="col-md-12">
            <h3><?php echo $confi_install; ?></h3>
            <div class="alert alert-info" role="alert">
                <?php echo $confi_install_info?>
            </div>
        </div>
        <div class="row col-md-12">
            <div class="col-md-7">
                <form action="#" id='configura_banco' autocomplete="off" accept-charset="utf-8" method="post" class="mform">
                    <div id="fitem_host" class="form-group row">
                        <div class="col-md-3">
                            <span class="float-sm-right text-nowrap">
                                <span class="initialism text-danger" title="Necessários">
                                    <i class="icon fa fa-exclamation-circle text-danger fa-fw " title="Necessários" aria-label="Necessários"></i>
                                </span>
                            </span>
                            <label class="col-form-label d-inline " for="host">HOST</label>
                        </div>
                        <div class="col-md-9 form-inline felement" data-fieldtype="host" id="yui_3_17_2_1_1768742809086_95">
                            <input type="text" class="form-control " name="host" id="host" value="" autocomplete="off">
                            <div class="form-control-feedback invalid-feedback" id="id_error_host">  </div>
                        </div>
                    </div>
                    <div id="fitem_user" class="form-group row">
                        <div class="col-md-3">
                            <span class="float-sm-right text-nowrap">
                                <span class="initialism text-danger" title="Necessários">
                                    <i class="icon fa fa-exclamation-circle text-danger fa-fw " title="Necessários" aria-label="Necessários"></i>
                                </span>
                            </span>
                            <label class="col-form-label d-inline " for="user">Usuário</label>
                        </div>
                        <div class="col-md-9 form-inline felement" data-fieldtype="user" id="yui_3_17_2_1_1768742809086_95">
                            <input type="text" class="form-control " name="user" id="user" value="" autocomplete="off">
                            <div class="form-control-feedback invalid-feedback" id="id_error_user">  </div>
                        </div>
                    </div>
                    <div id="fitem_senha" class="form-group row">
                        <div class="col-md-3">
                            <span class="float-sm-right text-nowrap">
                                <span class="initialism text-danger" title="Necessários">
                                    <i class="icon fa fa-exclamation-circle text-danger fa-fw " title="Necessários" aria-label="Necessários"></i>
                                </span>
                            </span>
                            <label class="col-form-label d-inline " for="senha">Senha</label>
                        </div>
                        <div class="col-md-9 form-inline felement" data-fieldtype="senha" id="yui_3_17_2_1_1768742809086_95">
                            <input type="password" class="form-control " name="senha" id="senha" value="" autocomplete="off">
                            <div class="form-control-feedback invalid-feedback" id="id_error_senha">  </div>
                        </div>
                    </div>
                    <div id="fitem_nomebanco" class="form-group row">
                        <div class="col-md-3">
                            <span class="float-sm-right text-nowrap">
                                <span class="initialism text-danger" title="Necessários">
                                    <i class="icon fa fa-exclamation-circle text-danger fa-fw " title="Necessários" aria-label="Necessários"></i>
                                </span>
                            </span>
                            <label class="col-form-label d-inline " for="nomebanco">Nome do Banco</label>
                        </div>
                        <div class="col-md-9 form-inline felement" data-fieldtype="nomebanco" id="yui_3_17_2_1_1768742809086_95">
                            <input type="text" class="form-control " name="nomebanco" id="nomebanco" value="" autocomplete="off">
                            <div class="form-control-feedback invalid-feedback" id="id_error_nomebanco">  </div>
                        </div>
                    </div>
                    <div id="fitem_porta" class="form-group row">
                        <div class="col-md-3">
                            <span class="float-sm-right text-nowrap">
                                <span class="initialism text-danger" title="Necessários">
                                    <i class="icon fa fa-exclamation-circle text-danger fa-fw " title="Necessários" aria-label="Necessários"></i>
                                </span>
                            </span>
                            <label class="col-form-label d-inline " for="porta">Porta</label>
                        </div>
                        <div class="col-md-9 form-inline felement" data-fieldtype="porta" id="yui_3_17_2_1_1768742809086_95">
                            <input type="text" class="form-control " name="porta" id="porta" value="3306" autocomplete="off">
                            <div class="form-control-feedback invalid-feedback" id="id_error_porta">  </div>
                        </div>
                    </div>
                    <div class="form-group row ">
                        <div class="col-md-3">
                            <input type="submit" value="Testar Conexao" id='testar_banco' class="btn btn-warning">
                        </div>
                        <div class="col-md-4">

                        </div>
                        
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-12">
            
            <div class="col-md-5">
                <?php
                    echo progress_install($_GET['id']);
                ?>
            </div>
        </div>
    </div>
    <div class="m-3">
        <form action="#" method="post" id="formbtnnext">
            <input type="hidden" name="id_form" id="id_form" value="4">
            <input type="hidden" name="idioma" id="idioma" value="<?php echo  $lang?>">
            <button type="submit" disabled class="btn btn-primary" id='btn_next'><?php echo $proximo ?></button>
        </form>
    </div>
</div>