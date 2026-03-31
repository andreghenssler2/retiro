<?php
	if(!file_exists("config.php")){
		header("Location: install/index.php?id=1");
		exit;
	}
?>

<?php require_once "config/settings.php"; ?>
<!DOCTYPE html>
<html lang="pt-br">
    <head>
        <?php
		
        $titulo = Title::getAtual();
        if ($titulo) {
            HeaderHTML::metaTags($titulo->getNome(), $titulo->getDescricao(),$titulo->getKeyword(), $titulo->getFavicon());
        }

     
        ?>
        
    </head>
    <body>
        <!-- Conteúdo do site -->
         <nav id="header" class="fixed-top navbar navbar-light bg-faded navbar-static-top navbar-expand moodle-has-zindex">
			<div class="container-fluid navbar-nav">
				<div data-region="drawer-toggle" class="d-inline-block mr-3">
					<!-- <button aria-expanded="false" aria-controls="nav-drawer" type="button" class="btn nav-link float-sm-left mr-1 btn-secondary" data-action="toggle-drawer" data-side="left" data-preference="drawer-open-nav" disabled="">
						<i class="icon fa fa-bars fa-fw " aria-hidden="true"></i><span class="sr-only">Painel lateral</span>
						<span aria-hidden="true"> </span>
						<span aria-hidden="true"> </span>
						<span aria-hidden="true"> </span>
					</button> -->
					<nav class="nav navbar-nav hidden-md-down address-head empty-address"></nav>
				</div>
				<ul class="nav navbar-nav ml-auto">
					<div class="d-none d-lg-block"></div>
					<!-- navbar_plugin_output -->
					<li class="nav-item"> </li>
					<!-- user_menu -->
					<li class="nav-item d-flex align-items-center">
						<!-- <div class="usermenu"><span class="login">Você ainda não se identificou</span></div> -->
					</li>
				</ul>
				<!-- search_box -->
			</div>
		</nav>
        <div class="header-main">
    		<div class="container-fluid">
				<nav class="navbar navbar-light bg-faded">
					<!-- <a href="<?php echo BASE_URL?>" class="navbar-brand has-logo ">
						<span class="logo">
							<img src="<?php echo $titulo->getImagem()?>" alt="<?php echo $titulo->getNome() ?>">
                		</span>
            		</a> -->
					
				</nav>
			</div>
		</div>

		<div class="container-fluid">
			<div class="row">
				<div class="d-flex row">
					<div class="d-flex justify-content-center">
						<?php
							$dezDiasDepois = date('Y-m-d', strtotime('+10 days'));

							$sql = "SELECT * FROM evento  LEFT JOIN local ON local.codEvento = evento.idEvento  LEFT JOIN cidade ON local.codCidade = cidade.idCidade  LEFT JOIN valor_inscricao ON valor_inscricao.codEventov = evento.idEvento  LEFT JOIN importancia ON evento.idEvento = importancia.idImportancia ;";
							$stmt = $db->prepare($sql);
							$stmt->execute();
							$row = $stmt->fetch(PDO::FETCH_ASSOC);
							if($row['codImpot'] == 3){
								echo $row['nomeEvento'];
							}else{
								echo $row['codImpot'];
								$dataInicio = $row['dataInicio'];
$dataFinal  = $row['dataFinal'];

// Evento em andamento OU começando nos próximos 10 dias
if (
    ($dataInicio <= $hoje && $dataFinal >= $hoje) ||  // Em andamento (inclui hoje)
    ($dataInicio > $hoje && $dataInicio <= $dezDiasDepois) // Futuro próximo (até 10 dias)
) {
    echo '<h1 style="color:red;">Evento Importante!</h1>';
}
							}

							
						?>
					</div>
				</div>
			</div>
		</div>
        
		<!-- <div class="footer-main">
			<div class="container-fluid">
				<p class="text-center"><?php echo $titulo->getNome() ?> &copy; <?php echo date("Y")?></p>
			</div>
		</div> -->

    </body>
</html>