<?php
$pdo = $db;

$lang = $_GET['lang'] ?? 'pt_br';
?>
<div class="card-body">
    <div class='m-2'>
        <div class="row col-md-12">
            <?php

            $sqlFile = __DIR__ . '/../../mod/db/me.sql';

            if (!file_exists($sqlFile)) {

                echo '<div class="alert alert-danger">';
                echo "Arquivo SQL não encontrado: $sqlFile";
                echo '</div>';
                exit;
            }

            echo '<div class="alert alert-success">';
            echo "Conectado ao banco com sucesso.";
            echo '</div>';

            $sql = file_get_contents($sqlFile);

            $queries = preg_split('/;[\r\n]+/', $sql);

            $total = count($queries);
            $executadas = 0;
            $erros = 0;

            ?>
            <div class="row">

            </div>
            <div class="progress mb-4">
                <div id="barra" class="progress-bar progress-bar-striped progress-bar-animated" style="width:0%">0%
                </div>
            </div>

            <div>

                <?php

                foreach ($queries as $query) {

                    $query = trim($query);

                    if ($query == '') {
                        continue;
                    }

                    $executadas++;

                    $nomeTabela = '';
                    $tipo = '';

                    if (preg_match('/CREATE TABLE\s+`?(\w+)`?/i', $query, $match)) {
                        $nomeTabela = $match[1];
                        $tipo = 'create';
                    }

                    if (preg_match('/INSERT INTO\s+`?(\w+)`?/i', $query, $match)) {
                        $nomeTabela = $match[1];
                        $tipo = 'insert';
                    }

                    try {

                        $pdo->exec($query);

                        if ($tipo == 'create') {
                            echo "<div class='alert alert-success'>📦 Tabela <b>$nomeTabela</b> criada</div>";
                        }

                        if ($tipo == 'insert') {
                            echo "<div class='alert alert-success'>🟢 Dados inseridos em <b>$nomeTabela</b></div>";
                        }

                    } catch (PDOException $e) {

                        $erro = $e->getMessage();

                        if (strpos($erro, 'already exists') !== false) {

                            echo "<div class='alert alert-warning'>⚠️ Tabela <b>$nomeTabela</b> já criada</div>";
                            continue;

                        }

                        if (strpos($erro, 'Duplicate entry') !== false) {

                            echo "<div class='alert alert-info'>⚠️ Dados já existentes em <b>$nomeTabela</b></div>";
                            continue;

                        }

                        $erros++;

                        echo "<div class='alert alert-danger'>";
                        echo "❌ Erro ao executar operação em <b>$nomeTabela</b><br>";
                        echo "<small>" . htmlspecialchars($erro) . "</small>";
                        echo "</div>";

                    }

                    $progress = intval(($executadas / $total) * 100);

                    echo "<script>
document.getElementById('barra').style.width='{$progress}%';
document.getElementById('barra').innerHTML='{$progress}%';
</script>";

                    ob_flush();
                    flush();

                }

                echo "</div>";

                if ($erros == 0) {

                    echo '<div class="alert alert-success mt-3">';
                    echo "<b>Instalação concluída!</b><br>";
                    echo "Banco configurado automaticamente pelo instalador PHP.";
                    echo "</div>";


                } else {

                    echo '<div class="alert alert-warning mt-3">';
                    echo "<b>Instalação finalizada com $erros erro(s).</b>";
                    echo "</div>";

                }

                ?>
        </div>
        <div class="col-md-12">
            
            <div class="col-md-5">
                <?php
                    echo progress_install($_GET['id']);
                ?>
            </div>
        </div>
    </div>
</div>
