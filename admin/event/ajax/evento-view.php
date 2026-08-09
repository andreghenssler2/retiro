<?php

require_once "../../../config/settings.php";

Middleware::auth();

$id = (int) ($_GET["id"] ?? 0);

if ($id <= 0) {

    echo '
        <div class="alert alert-danger m-3">
            Evento inválido.
        </div>
    ';

    exit;

}

$evento = new Evento();

$e = $evento->buscar($id);

if (!$e) {

    echo '
        <div class="alert alert-warning m-3">
            Evento não encontrado.
        </div>
    ';

    exit;

}

$imagem = BASE_URL . "uploads/eventos/sem-imagem.png";

if (!empty($e["imagem"])) {

    $arquivo = ROOT_PATH . "/uploads/eventos/" . $e["imagem"];

    if (file_exists($arquivo)) {

        $imagem = BASE_URL . "uploads/eventos/" . $e["imagem"];

    }

}

?>

<div class="container-fluid">

    <div class="row">

        <div class="col-md-5">

            <img src="<?= $imagem ?>" class="img-fluid rounded shadow-sm w-100">

        </div>

        <div class="col-md-7">

            <h3>

                <?= htmlspecialchars($e["titulo"]) ?>

            </h3>

            <?php if (!empty($e["descricao_curta"])): ?>

                <p class="text-muted">

                    <?= nl2br(htmlspecialchars($e["descricao_curta"])) ?>

                </p>

            <?php endif; ?>

            <table class="table table-sm table-bordered">

                <tr>

                    <th width="180">

                        Tipo

                    </th>

                    <td>

                        <?= $e["tipo"] ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Data Inicial

                    </th>

                    <td>

                        <?= date("d/m/Y", strtotime($e["data_inicio"])) ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Hora Inicial

                    </th>

                    <td>

                        <?= substr($e["hora_inicio"], 0, 5) ?>

                    </td>

                </tr>

                <?php if (!empty($e["data_fim"])): ?>

                    <tr>

                        <th>

                            Data Final

                        </th>

                        <td>

                            <?= date("d/m/Y", strtotime($e["data_fim"])) ?>

                        </td>

                    </tr>

                <?php endif; ?>

                <?php if (!empty($e["hora_fim"])): ?>

                    <tr>

                        <th>

                            Hora Final

                        </th>

                        <td>

                            <?= substr($e["hora_fim"], 0, 5) ?>

                        </td>

                    </tr>

                <?php endif; ?>

                <tr>

                    <th>

                        Local

                    </th>

                    <td>

                        <?= htmlspecialchars($e["local"]) ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Endereço

                    </th>

                    <td>

                        <?= htmlspecialchars($e["endereco"]) ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Cidade

                    </th>

                    <td>

                        <?= htmlspecialchars($e["cidade"]) ?>

                        /

                        <?= htmlspecialchars($e["estado"]) ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Valor

                    </th>

                    <td>

                        R$
                        <?= number_format($e["valor"], 2, ",", ".") ?>

                    </td>

                </tr>

                <?php if (!empty($e["pagamento_fim"])): ?>

                    <tr>

                        <th>

                            Limite para pagamentos

                        </th>

                        <td>

                            <?= date("d/m/Y H:i", strtotime($e["pagamento_fim"])) ?>

                        </td>

                    </tr>

                <?php endif; ?>

                <tr>

                    <th>

                        Vagas

                    </th>

                    <td>

                        <?= $e["vagas"] ?: "-" ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Certificado

                    </th>

                    <td>

                        <?= $e["certificado"] ? "Sim" : "Não" ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Status

                    </th>

                    <td>

                        <?php if ($e["ativo"]): ?>

                            <span class="badge bg-success">

                                Ativo

                            </span>

                        <?php else: ?>

                            <span class="badge bg-danger">

                                Inativo

                            </span>

                        <?php endif; ?>

                    </td>

                </tr>

            </table>

        </div>

    </div>

    <?php if (!empty($e["descricao"])): ?>

        <hr>

        <h5>

            Descrição

        </h5>

        <div class="border rounded p-3 bg-light">

            <?= nl2br(htmlspecialchars($e["descricao"])) ?>

        </div>

    <?php endif; ?>

</div>