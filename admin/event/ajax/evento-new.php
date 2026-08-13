<?php

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json");

$ret = [
    "status" => false,
    "msg" => ""
];

/*
|--------------------------------------------------------------------------
| CSRF
|--------------------------------------------------------------------------
*/

if (!Session::validateCsrf($_POST["_token"] ?? "")) {

    $ret["msg"] = "Token inválido.";

    echo json_encode($ret);

    exit;

}

$evento = new Evento();

$id = (int)($_POST["id"] ?? 0);

/*
|--------------------------------------------------------------------------
| Dados
|--------------------------------------------------------------------------
*/

$dados = [];

$dados["idEvento"] = $id;

$dados["titulo"] = trim($_POST["titulo"] ?? "");

$dados["slug"] = trim($_POST["slug"] ?? "");

$dados["descricao_curta"] = trim($_POST["descricao_curta"] ?? "");

$dados["descricao"] = trim($_POST["descricao"] ?? "");

$dados["tipo"] = trim($_POST["tipo"] ?? "Outro");

$dados["data_inicio"] = $_POST["data_inicio"] ?? "";

$dados["data_fim"] = $_POST["data_fim"] ?? "";

$dados["hora_inicio"] = $_POST["hora_inicio"] ?? "";

$dados["hora_fim"] = $_POST["hora_fim"] ?? "";

$dados["local"] = trim($_POST["local"] ?? "");

$dados["endereco"] = trim($_POST["endereco"] ?? "");

$dados["cidade"] = trim($_POST["cidade"] ?? "");

$dados["estado"] = trim($_POST["estado"] ?? "");

$dados["valor"] = str_replace(
    ",",
    ".",
    str_replace(".", "", $_POST["valor"] ?? "0")
);

$dados["valor_inscricao"] = str_replace(
    ",",
    ".",
    str_replace(
        ".",
        "",
        $_POST["valor_inscricao"] ?? $_POST["valor"] ?? "0"
    )
);

$dados["vagas"] = (int)($_POST["vagas"] ?? 0);

$dados["idade_minima"] = (int)($_POST["idade_minima"] ?? 0);

$dados["idade_maxima"] = (int)($_POST["idade_maxima"] ?? 0);

$dados["inscricao_inicio"] = !empty($_POST["inscricao_inicio"])
    ? str_replace("T", " ", $_POST["inscricao_inicio"]) . ":00"
    : null;

$dados["inscricao_fim"] = !empty($_POST["inscricao_fim"])
    ? str_replace("T", " ", $_POST["inscricao_fim"]) . ":00"
    : null;

$dados["pagamento_fim"] = !empty($_POST["pagamento_fim"])
    ? str_replace("T", " ", $_POST["pagamento_fim"]) . ":00"
    : null;

$dados["certificado"] = isset($_POST["certificado"]) ? 1 : 0;
$dados["certificado_ativo"] = $dados["certificado"];
$dados["inscricao_aberta"] = isset($_POST["inscricao_aberta"]) ? 1 : 0;
$dados["camiseta_ativa"] = isset($_POST["camiseta_ativa"]) ? 1 : 0;
$dados["pagamento_obrigatorio"] = isset($_POST["pagamento_obrigatorio"]) ? 1 : 0;
$dados["repassar_taxa_asaas"] = isset($_POST["repassar_taxa_asaas"]) ? 1 : 0;

if ($dados["pagamento_obrigatorio"] !== 1) {
    $dados["repassar_taxa_asaas"] = 0;
}

$dados["ativo"] = isset($_POST["ativo"]) ? 1 : 0;

/*
|--------------------------------------------------------------------------
| Validação
|--------------------------------------------------------------------------
*/

if ($dados["titulo"] == "") {

    $ret["msg"] = "Informe o título do evento.";

    echo json_encode($ret);

    exit;

}

if ($dados["data_inicio"] == "") {

    $ret["msg"] = "Informe a data inicial.";

    echo json_encode($ret);

    exit;

}
$eventoPago = (int) $dados["pagamento_obrigatorio"] === 1
    && (float) $dados["valor_inscricao"] > 0;

if ($eventoPago && empty($dados["pagamento_fim"])) {

    $ret["msg"] = "Informe o limite para realização dos pagamentos.";

    echo json_encode($ret);

    exit;

}

if (!empty($dados["pagamento_fim"])) {

    $fuso = new DateTimeZone("America/Sao_Paulo");
    $horaInicio = trim((string) $dados["hora_inicio"]);

    if ($horaInicio === "") {
        $horaInicio = "00:00";
    }

    if (strlen($horaInicio) === 5) {
        $horaInicio .= ":00";
    }

    $inicioEvento = DateTimeImmutable::createFromFormat(
        "!Y-m-d H:i:s",
        $dados["data_inicio"] . " " . $horaInicio,
        $fuso
    );

    $limitePagamento = DateTimeImmutable::createFromFormat(
        "!Y-m-d H:i:s",
        (string) $dados["pagamento_fim"],
        $fuso
    );

    if (!$inicioEvento instanceof DateTimeImmutable) {
        $ret["msg"] = "A data ou a hora de início do evento é inválida.";
        echo json_encode($ret);
        exit;
    }

    if (!$limitePagamento instanceof DateTimeImmutable) {
        $ret["msg"] = "O limite para pagamentos é inválido.";
        echo json_encode($ret);
        exit;
    }

    $limiteMaximo = $inicioEvento
        ->modify("-1 day")
        ->setTime(23, 59, 59);

    if ($limitePagamento > $limiteMaximo) {
        $ret["msg"] = "O limite para pagamentos deve ser, no máximo, até "
            . $limiteMaximo->format("d/m/Y H:i")
            . ", um dia antes do evento.";

        echo json_encode($ret);
        exit;
    }
}

/*
|--------------------------------------------------------------------------
| Upload da Imagem
|--------------------------------------------------------------------------
*/

if (
    isset($_FILES["imagem"]) &&
    $_FILES["imagem"]["error"] != UPLOAD_ERR_NO_FILE
) {

    if ($_FILES["imagem"]["error"] != UPLOAD_ERR_OK) {

        $ret["msg"] = "Erro ao enviar a imagem.";

        echo json_encode($ret);

        exit;

    }

    $ext = strtolower(
        pathinfo(
            $_FILES["imagem"]["name"],
            PATHINFO_EXTENSION
        )
    );

    $permitidos = [
        "jpg",
        "jpeg",
        "png",
        "webp"
    ];

    if (!in_array($ext, $permitidos)) {

        $ret["msg"] = "Formato de imagem inválido.";

        echo json_encode($ret);

        exit;

    }

    $pasta = ROOT_PATH . "/uploads/eventos/";

    if (!is_dir($pasta)) {

        mkdir($pasta, 0777, true);

    }

    $novoNome = uniqid("evento_") . "." . $ext;

    if (
        move_uploaded_file(
            $_FILES["imagem"]["tmp_name"],
            $pasta . $novoNome
        )
    ) {

        /*
        |--------------------------------------------------------------------------
        | Remove imagem antiga
        |--------------------------------------------------------------------------
        */

        if ($id > 0) {

            $eventoAtual = $evento->buscar($id);

            if (
                $eventoAtual &&
                !empty($eventoAtual["imagem"])
            ) {

                $arquivo = $pasta . $eventoAtual["imagem"];

                if (file_exists($arquivo)) {

                    unlink($arquivo);

                }

            }

        }

        $dados["imagem"] = $novoNome;

    } else {

        $ret["msg"] = "Não foi possível salvar a imagem.";

        echo json_encode($ret);

        exit;

    }

}

/*
|--------------------------------------------------------------------------
| Mantém a imagem antiga
|--------------------------------------------------------------------------
*/

if (
    $id > 0 &&
    empty($dados["imagem"])
) {

    $eventoAtual = $evento->buscar($id);

    if ($eventoAtual) {

        $dados["imagem"] = $eventoAtual["imagem"];

    }

}
/*
|--------------------------------------------------------------------------
| Salvar
|--------------------------------------------------------------------------
*/

try {

    if ($id > 0) {

        $ok = $evento->editar($dados);

        if ($ok) {

            $ret["status"] = true;
            $ret["msg"] = "Evento atualizado com sucesso.";
            $ret["id"] = $id;

        } else {

            $ret["msg"] = "Erro ao atualizar o evento.";

        }

    } else {

        $novoId = $evento->salvar($dados);

        if ($novoId) {

            $ret["status"] = true;
            $ret["msg"] = "Evento cadastrado com sucesso.";
            $ret["id"] = $novoId;

        } else {

            $ret["msg"] = "Erro ao cadastrar o evento.";

        }

    }

} catch (Throwable $e) {

    $ret["status"] = false;
    $ret["msg"] = $e->getMessage();

}

/*
|--------------------------------------------------------------------------
| Retorno
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Configuração do formulário público de inscrição
|--------------------------------------------------------------------------
*/
if (
    !empty($ret["status"])
    && !empty($ret["id"])
) {
    try {
        $configPublica =
            new EventoInscricaoPublicaConfig(
                $db
            );

        $configPublica->salvarTermos(
            (int) $ret["id"],
            is_array(
                $_POST["termos"]
                ?? null
            )
                ? $_POST["termos"]
                : []
        );

        $configPublica->salvarCamisetas(
            (int) $ret["id"],
            is_array(
                $_POST["camisetas"]
                ?? null
            )
                ? $_POST["camisetas"]
                : []
        );
    } catch (Throwable $configErro) {
        $ret["status"] = false;

        $ret["msg"] =
            "O evento foi salvo, mas houve "
            . "erro ao salvar os termos "
            . "ou tamanhos de camiseta: "
            . $configErro->getMessage();
    }
}
echo json_encode($ret);

exit;