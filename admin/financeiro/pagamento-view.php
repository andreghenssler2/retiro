<?php

require_once "../../../config/settings.php";

Middleware::auth();

$id = (int) ($_GET["id"] ?? 0);

$pagamento = new Pagamento();

$item = $pagamento->buscar($id);

if (!$item) {

    echo '<div class="alert alert-danger">
            Pagamento não encontrado.
          </div>';

    exit;

}

?>

<div class="container-fluid">

    <div class="row">

        <div class="col-md-12 mb-3">

            <h4>

                <?= htmlspecialchars($item["nome"]) ?>

            </h4>

            <small class="text-muted">

                <?= htmlspecialchars($item["titulo"]) ?>

            </small>

        </div>

        <div class="col-md-6">

            <table class="table table-bordered">

                <tr>

                    <th width="180">

                        Participante

                    </th>

                    <td>

                        <?= htmlspecialchars($item["nome"]) ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Evento

                    </th>

                    <td>

                        <?= htmlspecialchars($item["titulo"]) ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Email

                    </th>

                    <td>

                        <?= htmlspecialchars($item["email"]) ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Telefone

                    </th>

                    <td>

                        <?= htmlspecialchars($item["telefone"]) ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Gateway

                    </th>

                    <td>

                        <?= htmlspecialchars($item["gateway"]) ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Código Gateway

                    </th>

                    <td>

                        <?= htmlspecialchars($item["codigo_gateway"]) ?>

                    </td>

                </tr>

            </table>

        </div>

        <div class="col-md-6">

            <table class="table table-bordered">

                <tr>

                    <th width="180">

                        Valor

                    </th>

                    <td class="text-success">

                        <strong>

                            R$
                            <?= number_format($item["valor"], 2, ",", ".") ?>

                        </strong>

                    </td>

                </tr>

                <tr>

                    <th>

                        Desconto

                    </th>

                    <td>

                        R$
                        <?= number_format($item["desconto"], 2, ",", ".") ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Juros

                    </th>

                    <td>

                        R$
                        <?= number_format($item["juros"], 2, ",", ".") ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Multa

                    </th>

                    <td>

                        R$
                        <?= number_format($item["multa"], 2, ",", ".") ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Valor Final

                    </th>

                    <td class="text-primary">

                        <strong>

                            R$
                            <?= number_format($item["valor_final"], 2, ",", ".") ?>

                        </strong>

                    </td>

                </tr>

                <tr>

                    <th>

                        Forma Pagamento

                    </th>

                    <td>

                        <?= htmlspecialchars($item["forma_pagamento"]) ?>

                    </td>

                </tr>

            </table>

        </div>

        <div class="col-md-12">

            <table class="table table-bordered">

                <tr>

                    <th width="180">

                        Status

                    </th>

                    <td>

                        <?php

                        $cor = "secondary";

                        switch ($item["status"]) {

                            case "Pago":
                                $cor = "success";
                                break;

                            case "Pendente":
                                $cor = "warning";
                                break;

                            case "Cancelado":
                                $cor = "danger";
                                break;

                            case "Estornado":
                                $cor = "dark";
                                break;

                        }

                        ?>

                        <span class="badge bg-<?= $cor ?>">

                            <?= $item["status"] ?>

                        </span>

                    </td>

                </tr>

                <tr>

                    <th>

                        TXID

                    </th>

                    <td>

                        <?= htmlspecialchars($item["txid"]) ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Linha Digitável

                    </th>

                    <td>

                        <?= htmlspecialchars($item["linha_digitavel"]) ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Vencimento

                    </th>

                    <td>

                        <?= !empty($item["vencimento"])
                            ? date("d/m/Y H:i", strtotime($item["vencimento"]))
                            : "-" ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Pago em

                    </th>

                    <td>

                        <?= !empty($item["data_pagamento"])
                            ? date("d/m/Y H:i", strtotime($item["data_pagamento"]))
                            : "-" ?>

                    </td>

                </tr>

                <tr>

                    <th>

                        Criado em

                    </th>

                    <td>

                        <?= date("d/m/Y H:i", strtotime($item["criado_em"])) ?>

                    </td>

                </tr>

            </table>

        </div>

        <?php if (!empty($item["qr_code"])): ?>

            <div class="col-md-12">

                <div class="alert alert-info">

                    <strong>QR Code PIX</strong>

                    <hr>

                    <textarea class="form-control" rows="5" readonly><?= $item["qr_code"] ?></textarea>

                </div>

            </div>

        <?php endif; ?>

        <?php if (!empty($item["observacoes"])): ?>

            <div class="col-md-12">

                <div class="alert alert-light">

                    <strong>

                        Observações

                    </strong>

                    <hr>

                    <?= nl2br(htmlspecialchars($item["observacoes"])) ?>

                </div>

            </div>

        <?php endif; ?>

        <?php if (!empty($item["comprovante"])): ?>

            <div class="col-md-12 text-center">

                <h5 class="mb-3">

                    Comprovante

                </h5>

                <img src="<?= BASE_URL ?>/uploads/comprovantes/<?= $item["comprovante"] ?>" class="img-fluid rounded shadow"
                    style="max-height:600px;">

            </div>

        <?php endif; ?>

    </div>

</div>