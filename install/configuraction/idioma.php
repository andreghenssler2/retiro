<?php
    $lang = $_GET['lang'] ?? 'pt_br';
?>
<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card-body">
            <div class='m-2 text-center'>
                <h3><?php echo $ecolhaidioma?></h3>
            </div>
            <div class="alert alert-info" role="alert">
                
                <p>
                    <?php echo $idioma_selecionado ?>
                </p>
                
                <form action="#" method="post" id="form_idioma">
                    <input type="hidden" name="id" value="2">

                    <div class="form-group">
                        <select name="idioma" id='idioma_install'  class="custom-select" >
                            <option value="pt_br" <?= $lang == 'pt_br' ? 'selected' : '' ?>>Português (Brasil)</option>
                            <option value="en_us" <?= $lang == 'en_us' ? 'selected' : '' ?>>Inglês (Estados Unidos)</option>
                            <option value="es_es" <?= $lang == 'es_es' ? 'selected' : '' ?>>Espanhol (Espanha)</option>
                        </select>
                    </div>
                    <div class="form-group form-check">
                        <input type="checkbox" class="form-check-input" id="confirm_lang" name="confirm_lang">
                        <label class="form-check-label" for="confirm_lang"><?php echo $checked_lang ?></label>
                    </div>
                    <div class="mt-3 text-center">
                        <button type="submit" class="btn btn-primary" id='btn_next_idioma' disabled><?php echo $proximo ?></button>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-5 mb-2 mt-2">
            <?php
                echo progress_install(1);
            ?>
        </div>
    </div>
    
</div>