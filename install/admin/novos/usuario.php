<?php
$lang = $_GET['lang'] ?? 'pt_br';
?>

<div class="card-body">
    <div class='m-2'>
        <div class="col-md-12">

        </div>
        <div class="row col-md-12 justify-content-center">
            <div class="col-md-8 ">
                <form action="#" id='usuario_form' autocomplete="off" accept-charset="utf-8" method="post"
                    class="mform">
                    <input type="hidden" name="id_form" id="id_form" value="6">
                    <input type="hidden" name="idioma" id="idioma" value="<?php echo $lang ?>">
                    <div class="clearfix">
                        <div class="col-md-12">
                            <div class="card-title">
                                <h5>Nova conta</h5>
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <div class="col-md-4">
                                <sapn class="float-sm-right text-nowrap">
                                    <abbr class="initialism text-danger" title="Necessários">
                                        <i class="icon fa fa-exclamation-circle text-danger fa-fw " title="Necessários"
                                            aria-label="Necessários"></i>
                                    </abbr>
                                </sapn>
                                <label class="col-form-label d-inline " for="id_username">
                                    Login (nome de usuário)
                                </label>
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control " name="username" id="id_username" value=""
                                    autocomplete="off">
                                <div class="form-control-feedback invalid-feedback" id="id_error_username"> </div>
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <div class="col-md-1"> </div>
                            <div class="col-md-11"> A senha deve ter ao menos 8 caracteres, ao menos 1 número(s), ao
                                menos 1 letra(s) minúscula(s), ao menos 1 letra(s) maiúscula(s), no mínimo 1
                                caractere(s) não alfa-numéricos, como *, -, ou #.
                            </div>
                        </div>

                        <div class="form-group row mb-2">
                            <div class="col-md-4">
                                <sapn class="float-sm-right text-nowrap">
                                    <abbr class="initialism text-danger" title="Necessários">
                                        <i class="icon fa fa-exclamation-circle text-danger fa-fw " title="Necessários"
                                            aria-label="Necessários"></i>
                                    </abbr>
                                </sapn>
                                <label class="col-form-label d-inline " for="id_username">
                                    Senha
                                </label>
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control " name="username" id="id_username" value=""
                                    autocomplete="off">
                                <div class="form-control-feedback invalid-feedback" id="id_error_username"> </div>
                            </div>
                        </div>
                    </div>

                    <div class="crearfix">
                        <div class="col-md-12">
                            <div class="card-title">
                                <h5>Informações de Contato</h5>
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <div class="col-md-4">
                                <sapn class="float-sm-right text-nowrap">
                                    <abbr class="initialism text-danger" title="Necessários">
                                        <i class="icon fa fa-exclamation-circle text-danger fa-fw " title="Necessários"
                                            aria-label="Necessários"></i>
                                    </abbr>
                                </sapn>
                                <label class="col-form-label d-inline " for="id_email">
                                    E-mail
                                </label>
                            </div>
                            <div class="col-md-8">
                                <input type="email" class="form-control " name="email" id="id_email" value=""
                                    autocomplete="off">
                                <div class="form-control-feedback invalid-feedback" id="id_error_email"> </div>
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <div class="col-md-4">
                                <sapn class="float-sm-right text-nowrap">
                                    <abbr class="initialism text-danger" title="Necessários">
                                        <i class="icon fa fa-exclamation-circle text-danger fa-fw " title="Necessários"
                                            aria-label="Necessários"></i>
                                    </abbr>
                                </sapn>
                                <label class="col-form-label d-inline " for="id_email_confirmar">
                                    E-mail Confirmar
                                </label>
                            </div>
                            <div class="col-md-8">
                                <input type="email" class="form-control " name="email" id="id_email_confirmar" value=""
                                    autocomplete="off">
                                <div class="form-control-feedback invalid-feedback" id="id_error_email_confirmar">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="crearfix">
                        <div class="col-md-12">
                            <div class="card-title">
                                <h5>Informações Pessoais</h5>
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <div class="col-md-4">
                                <sapn class="float-sm-right text-nowrap">
                                    <abbr class="initialism text-danger" title="Necessários">
                                        <i class="icon fa fa-exclamation-circle text-danger fa-fw " title="Necessários"
                                            aria-label="Necessários"></i>
                                    </abbr>
                                </sapn>
                                <label class="col-form-label d-inline " for="id_nome">
                                    Nome
                                </label>
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control " name="nome" id="id_nome" value=""
                                    autocomplete="off">
                                <div class="form-control-feedback invalid-feedback" id="id_error_nome"> </div>
                            </div>
                        </div>
                        <div class="form-group row mb-2">
                            <div class="col-md-4">
                                <sapn class="float-sm-right text-nowrap">
                                    <abbr class="initialism text-danger" title="Necessários">
                                        <i class="icon fa fa-exclamation-circle text-danger fa-fw " title="Necessários"
                                            aria-label="Necessários"></i>
                                    </abbr>
                                </sapn>
                                <label class="col-form-label d-inline " for="id_cpf">
                                    CPF
                                </label>
                            </div>
                            <div class="col-md-8">
                                <input type="text" class="form-control " name="cpf" id="id_cpf" value=""
                                    autocomplete="off" data-digitar="cpf">
                                <div class="form-control-feedback invalid-feedback" id="id_error_cpf"> </div>
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>