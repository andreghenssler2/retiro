<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json; charset=utf-8");

$retorno = [
    "status" => false,
    "msg" => "",
    "dados" => []
];

$idEvento = (int) ($_GET["id"] ?? 0);

if ($idEvento <= 0) {
    http_response_code(422);
    $retorno["msg"] = "Evento inválido.";
    echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $eventoModel = new Evento();
    $inscricaoModel = new Inscricao();

    $evento = $eventoModel->buscarConfiguracao($idEvento);

    if (!$evento) {
        http_response_code(404);
        $retorno["msg"] = "Evento não encontrado.";
        echo json_encode($retorno, JSON_UNESCAPED_UNICODE);
        exit;
    }

    $inscritos = $inscricaoModel->totalEvento($idEvento);
    $vagas = (int) ($evento["vagas"] ?? 0);
    $disponiveis = $vagas > 0
        ? max(0, $vagas - $inscritos)
        : 0;

    $valor = (float) ($evento["valor_inscricao"] ?? 0);

    if ($valor <= 0) {
        $valor = (float) ($evento["valor"] ?? 0);
    }

    $retorno["status"] = true;
    $retorno["dados"] = [
        "idEvento" => (int) $evento["idEvento"],
        "titulo" => (string) $evento["titulo"],
        "valor" => $valor,
        "valor_formatado" => "R$ " . number_format($valor, 2, ",", "."),
        "vagas" => $vagas,
        "inscritos" => $inscritos,
        "disponiveis" => $disponiveis,
        "inscricao_aberta" => (int) ($evento["inscricao_aberta"] ?? 1),
        "camiseta_ativa" => (int) ($evento["camiseta_ativa"] ?? 0),
        "pagamento_obrigatorio" => (
            (int) ($evento["pagamento_obrigatorio"] ?? 1) === 1
            && $valor > 0
        ) ? 1 : 0,
        "certificado_ativo" => (int) (
            $evento["certificado_ativo"]
            ?? $evento["certificado"]
            ?? 0
        )
    ];
} catch (Throwable $erro) {
    error_log(
        "Erro em evento-get.php: "
        . $erro->getMessage()
        . " | Linha: "
        . $erro->getLine()
    );

    http_response_code(500);
    $retorno["msg"] = "Não foi possível carregar o evento.";
}

echo json_encode(
    $retorno,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);
