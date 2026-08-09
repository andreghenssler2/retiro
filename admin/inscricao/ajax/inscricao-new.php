<?php

declare(strict_types=1);

require_once "../../../config/settings.php";

Middleware::auth();

header("Content-Type: application/json; charset=utf-8");

function responderInscricao(
    bool $status,
    string $mensagem,
    array $dados = [],
    int $http = 200
): never {
    http_response_code($http);

    echo json_encode(
        array_merge(
            [
                "status" => $status,
                "msg" => $mensagem
            ],
            $dados
        ),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );

    exit;
}

function valorDecimalInscricao(mixed $valor): float
{
    $texto = str_replace(
        ["R$", " "],
        "",
        trim((string) ($valor ?? "0"))
    );

    if (str_contains($texto, ",")) {
        $texto = str_replace(".", "", $texto);
        $texto = str_replace(",", ".", $texto);
    }

    return is_numeric($texto)
        ? round((float) $texto, 2)
        : 0.0;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    responderInscricao(false, "Método de requisição inválido.", [], 405);
}

$token = (string) ($_POST["_token"] ?? "");

if ($token === "" || !Session::validateCsrf($token)) {
    responderInscricao(
        false,
        "Token de segurança inválido. Atualize a página e tente novamente.",
        [],
        419
    );
}

$idInscricao = (int) ($_POST["idInscricao"] ?? 0);
$idEvento = (int) ($_POST["idEvento"] ?? 0);
$idUsuario = (int) ($_POST["idUsuario"] ?? 0);
$camiseta = trim((string) ($_POST["camiseta"] ?? ""));
$observacoes = trim((string) ($_POST["observacoes"] ?? ""));

if ($idEvento <= 0) {
    responderInscricao(false, "Selecione o evento.", [], 422);
}

if ($idUsuario <= 0) {
    responderInscricao(false, "Selecione o participante.", [], 422);
}

try {
    if (!isset($db) || !$db instanceof PDO) {
        throw new RuntimeException("A conexão com o banco não está disponível.");
    }

    $inscricaoModel = new Inscricao($db);
    $pagamentoModel = new Pagamento($db);
    $eventoModel = new Evento();
    $usuarioModel = new Usuario();

    $inscricaoAtual = $idInscricao > 0
        ? $inscricaoModel->buscar($idInscricao)
        : false;

    if ($idInscricao > 0 && !$inscricaoAtual) {
        responderInscricao(false, "Inscrição não encontrada.", [], 404);
    }

    $evento = $eventoModel->buscarConfiguracao($idEvento);

    if (!$evento) {
        responderInscricao(false, "Evento não encontrado.", [], 404);
    }

    if ((int) ($evento["ativo"] ?? 0) !== 1) {
        responderInscricao(false, "O evento está inativo.", [], 422);
    }

    if ($idInscricao === 0) {
        if ((int) ($evento["inscricao_aberta"] ?? 1) !== 1) {
            responderInscricao(false, "As inscrições deste evento estão fechadas.", [], 422);
        }

        $agora = new DateTimeImmutable("now");
        $inicioInscricao = trim((string) ($evento["inscricao_inicio"] ?? ""));
        $fimInscricao = trim((string) ($evento["inscricao_fim"] ?? ""));

        if ($inicioInscricao !== "" && $agora < new DateTimeImmutable($inicioInscricao)) {
            responderInscricao(false, "O período de inscrições ainda não iniciou.", [], 422);
        }

        if ($fimInscricao !== "" && $agora > new DateTimeImmutable($fimInscricao)) {
            responderInscricao(false, "O período de inscrições foi encerrado.", [], 422);
        }
    }

    $usuario = $usuarioModel->buscarInscricao($idUsuario);

    if (!$usuario) {
        responderInscricao(false, "Participante não encontrado ou inativo.", [], 404);
    }

    if (
        $inscricaoModel->existeNoEvento(
            $idEvento,
            $idUsuario,
            $idInscricao
        )
    ) {
        responderInscricao(
            false,
            "Este usuário já possui uma inscrição ativa neste evento.",
            [],
            409
        );
    }

    $mudouEvento = $inscricaoAtual
        && (int) $inscricaoAtual["idEvento"] !== $idEvento;

    if ($idInscricao === 0 || $mudouEvento) {
        $vagas = (int) ($evento["vagas"] ?? 0);

        if (
            $vagas > 0
            && $inscricaoModel->totalEvento($idEvento) >= $vagas
        ) {
            responderInscricao(false, "Não existem mais vagas disponíveis.", [], 422);
        }
    }

    $camisetaAtiva = (int) ($evento["camiseta_ativa"] ?? 0) === 1;
    $tamanhosPermitidos = ["PP", "P", "M", "G", "GG", "XGG"];

    if ($camisetaAtiva) {
        if (!in_array($camiseta, $tamanhosPermitidos, true)) {
            responderInscricao(false, "Selecione o tamanho da camiseta.", [], 422);
        }
    } else {
        $camiseta = "";
    }

    $valor = valorDecimalInscricao($evento["valor_inscricao"] ?? 0);

    if ($valor <= 0) {
        $valor = valorDecimalInscricao($evento["valor"] ?? 0);
    }

    $pagamentoObrigatorio = (int) (
        $evento["pagamento_obrigatorio"] ?? 1
    ) === 1 && $valor > 0;

    $dados = [
        "idInscricao" => $idInscricao,
        "idEvento" => $idEvento,
        "idUsuario" => $idUsuario,
        "nome" => (string) ($usuario["nome"] ?? ""),
        "cpf" => $usuario["cpf"] ?? null,
        "rg" => $inscricaoAtual["rg"] ?? null,
        "email" => $usuario["email"] ?? null,
        "telefone" => $usuario["telefone"] ?? null,
        "sexo" => $inscricaoAtual["sexo"] ?? "Masculino",
        "data_nascimento" => $inscricaoAtual["data_nascimento"] ?? null,
        "cidade" => $inscricaoAtual["cidade"] ?? null,
        "estado" => $inscricaoAtual["estado"] ?? null,
        "camiseta" => $camiseta,
        "observacoes" => $observacoes,
        "contato_emergencia" => $inscricaoAtual["contato_emergencia"] ?? null,
        "telefone_emergencia" => $inscricaoAtual["telefone_emergencia"] ?? null,
        "status" => $pagamentoObrigatorio ? "Pendente" : "Confirmada",
        "pagamento" => $pagamentoObrigatorio ? "Pendente" : "Pago",
        "presenca" => (int) ($inscricaoAtual["presenca"] ?? 0),
        "certificado" => (int) (
            $evento["certificado_ativo"]
            ?? $evento["certificado"]
            ?? 0
        ),
        "valor" => $valor,
        "valor_pago" => $pagamentoObrigatorio ? 0 : $valor,
        "forma_pagamento" => $inscricaoAtual["forma_pagamento"] ?? null,
        "codigo_pagamento" => $inscricaoAtual["codigo_pagamento"] ?? null
    ];

    $db->beginTransaction();

    try {
        if ($idInscricao > 0) {
            $salvou = $inscricaoModel->editar($dados);
        } else {
            $salvou = $inscricaoModel->salvar($dados);
            $idInscricao = $inscricaoModel->ultimoId();
        }

        if (!$salvou || $idInscricao <= 0) {
            throw new RuntimeException("Não foi possível salvar a inscrição.");
        }

        $idPagamento = $pagamentoModel->criarParaInscricao($idInscricao);

        $db->commit();
    } catch (Throwable $erroTransacao) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $erroTransacao;
    }

    $mensagem = $idInscricao > 0 && $inscricaoAtual
        ? "Inscrição atualizada com sucesso."
        : "Inscrição cadastrada com sucesso.";

    if ($pagamentoObrigatorio) {
        $mensagem .= " O pagamento foi gerado e aguarda confirmação no financeiro.";
    } else {
        $mensagem .= " Como não há pagamento obrigatório, ela foi confirmada automaticamente.";
    }

    responderInscricao(
        true,
        $mensagem,
        [
            "idInscricao" => $idInscricao,
            "idPagamento" => $idPagamento ?? 0,
            "pagamentoObrigatorio" => $pagamentoObrigatorio
        ]
    );
} catch (InvalidArgumentException $erro) {
    responderInscricao(false, $erro->getMessage(), [], 422);
} catch (PDOException $erro) {
    error_log(
        "Erro PDO em inscricao-new.php: "
        . $erro->getMessage()
        . " | Linha: "
        . $erro->getLine()
    );

    if ($erro->getCode() === "23000") {
        $mensagemBanco = $erro->getMessage();

        if (str_contains($mensagemBanco, "uk_inscricoes_evento_usuario")) {
            responderInscricao(
                false,
                "O banco ainda possui a regra antiga que bloqueia a reinscrição. Execute a migração 20260802_liberar_reinscricao_cancelada_estornada.sql.",
                [],
                409
            );
        }

        responderInscricao(
            false,
            "Não foi possível concluir a inscrição por uma restrição do banco de dados.",
            [],
            409
        );
    }

    responderInscricao(false, "Não foi possível salvar a inscrição.", [], 500);
} catch (Throwable $erro) {
    error_log(
        "Erro em inscricao-new.php: "
        . $erro->getMessage()
        . " | Arquivo: "
        . $erro->getFile()
        . " | Linha: "
        . $erro->getLine()
    );

    responderInscricao(false, "Não foi possível salvar a inscrição.", [], 500);
}
