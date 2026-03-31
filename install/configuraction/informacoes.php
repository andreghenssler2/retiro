<?php
    $lang = $_GET['lang'] ?? 'pt_br';
?>

<div class="card-body">
    <div class='m-2'>
        <div class='p-2'>
            <h3><?php echo $informacoes_sistema;  ?></h3>
            <?php echo $informacoes_sistema_descrica ?>
            
        </div>
        <div class="p-2">
            <h3><?php echo  $objetivo?> </h3>
            <?php echo  $objetivo_descicao?> 
            
        </div>
        <div class="p-2">
            <h3><?php echo  $recursos?> </h3>
            <?php echo  $recursos_descricao?> 

            
        </div>
        <div class="p-2">
            <h3>Suporte e Atualizações</h3>
            <p>
                O sistema conta com suporte contínuo e atualizações regulares para garantir a segurança e a funcionalidade do software.
                Os usuários podem contar com uma equipe dedicada para auxiliar em dúvidas e problemas técnicos.
            </p>

            <p>
                Além disso, a comunidade de usuários pode contribuir com sugestões e melhorias, tornando o sistema cada vez mais robusto e eficiente.
            </p>
        </div>
        
        <div class="col-md-5">
            <?php
                echo progress_install($_GET['id']);
            ?>
        </div>
    </div>

    <div class="m-3">
        <form action="#" method="post" id="formbtnnext">
            <input type="hidden" name="id_form" id="id_form" value="3">
            <input type="hidden" name="idioma" id="idioma" value="<?php echo  $lang?>">
            <button type="submit" class="btn btn-primary" id='btn_next'><?php echo $proximo ?></button>
        </form>
        <!-- <a href="?id=2" class="btn btn-primary">Próximo Passo: Configuração <i class="fa-solid fa-chevron-right"></i></a> -->
    </div>
</div>