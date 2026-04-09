<?php
$lang = $_GET['lang'] ?? 'pt_br';
?>

<div class="card-body">
    <div class='m-2'>
        <div class="col-md-12">
            
        </div>
        <div class="row col-md-12 justify-content-center">
            <div class="col-md-8 ">
                <form action="#" id='usuario_form' autocomplete="off" accept-charset="utf-8" method="post" class="mform">
                    <input type="hidden" name="id_form" id="id_form" value="6">
                    <input type="hidden" name="idioma" id="idioma" value="<?php echo $lang ?>">
                    <!-- <div class="form-group -->
                </form>
            </div>
        </div>
    </div>
    <!-- <div class="m-3">
        <form action="#" method="post" id="formbtnnext">
            <input type="hidden" name="id_form" id="id_form" value="4a">
            <input type="hidden" name="idioma" id="idioma" value="<?php echo $lang ?>">
            <button type="submit" disabled class="btn btn-primary" id='btn_next'>Proximo</button>
        </form>
    </div> -->
</div>