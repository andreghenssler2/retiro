<?php

declare(strict_types=1);

require_once __DIR__ . "/../config/settings.php";

Session::start();
Auth::requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Método não permitido.");
}

if (!Session::validateCsrf($_POST["_token"] ?? "")) {
    http_response_code(419);
    exit("Token de segurança inválido.");
}

$idEvento = filter_input(
    INPUT_POST,
    "idEvento",
    FILTER_VALIDATE_INT
) ?: 0;

$eventoModel = new Evento();
$inscricaoModel = new Inscricao($db);
$pagamentoModel = new Pagamento($db);
$usuarioModel = new Usuario();

$evento = $eventoModel->buscar($idEvento);

if (!$evento || (int) ($evento["ativo"] ?? 0) !== 1) {
    Session::flash(
        "error",
        "O evento não está disponível para inscrição."
    );

    header("Location: " . BASE_URL . "eventos/");
    exit;
}

$slug = trim((string) ($evento["slug"] ?? ""));
$urlEvento = BASE_URL
    . "eventos/detalhe.php"
    . (
        $slug !== ""
            ? "?slug=" . rawurlencode($slug)
            : "?id=" . $idEvento
    );

$idUsuario = (int) (Auth::id() ?? 0);

if ($idUsuario <= 0) {
    header("Location: " . BASE_URL . "login/");
    exit;
}

$usuario = $usuarioModel->buscar($idUsuario);

if (!$usuario) {
    Session::flash(
        "error",
        "Não foi possível localizar seus dados."
    );

    header("Location: " . BASE_URL . "user/index.php");
    exit;
}

if (
    $inscricaoModel->existeNoEvento(
        $idEvento,
        $idUsuario
    )
) {
    $existente = $inscricaoModel->buscarDoUsuarioNoEvento(
        $idEvento,
        $idUsuario
    );

    if ($existente) {
        $pagamento = $pagamentoModel->buscarPorInscricao(
            (int) $existente["idInscricao"]
        );

        if (
            $pagamento
            && (int) ($pagamento["idPagamento"] ?? 0) > 0
            && (string) ($pagamento["status"] ?? "") !== "Pago"
        ) {
            header(
                "Location: "
                . BASE_URL
                . "eventos/pagamento.php?id="
                . (int) $pagamento["idPagamento"]
            );
            exit;
        }
    }

    Session::flash(
        "error",
        "Você já possui uma inscrição ativa neste evento."
    );

    header("Location: " . $urlEvento);
    exit;
}

$agora = new DateTimeImmutable(
    "now",
    new DateTimeZone("America/Sao_Paulo")
);

if ((int) ($evento["inscricao_aberta"] ?? 0) !== 1) {
    Session::flash(
        "error",
        "As inscrições para este evento estão fechadas."
    );

    header("Location: " . $urlEvento);
    exit;
}

foreach (
    [
        "inscricao_inicio" => "início",
        "inscricao_fim" => "fim"
    ]
    as $campo => $descricao
) {
    $texto = trim((string) ($evento[$campo] ?? ""));

    if ($texto === "") {
        continue;
    }

    try {
        $data = new DateTimeImmutable(
            $texto,
            new DateTimeZone("America/Sao_Paulo")
        );

        if (
            $campo === "inscricao_inicio"
            && $agora < $data
        ) {
            throw new RuntimeException(
                "As inscrições ainda não começaram."
            );
        }

        if (
            $campo === "inscricao_fim"
            && $agora > $data
        ) {
            throw new RuntimeException(
                "O período de inscrições foi encerrado."
            );
        }
    } catch (RuntimeException $erro) {
        Session::flash(
            "error",
            $erro->getMessage()
        );

        header("Location: " . $urlEvento);
        exit;
    } catch (Throwable) {
        Session::flash(
            "error",
            "O período de inscrição está indisponível."
        );

        header("Location: " . $urlEvento);
        exit;
    }
}

if ($inscricaoModel->vagasDisponiveis($idEvento) === 0) {
    Session::flash(
        "error",
        "As vagas para este evento estão esgotadas."
    );

    header("Location: " . $urlEvento);
    exit;
}

$nome = trim((string) ($usuario["nome"] ?? ""));
$cpf = Usuario::normalizarCpf(
    (string) ($usuario["cpf"] ?? "")
);
$email = trim((string) ($usuario["email"] ?? ""));
$telefone = trim((string) ($usuario["telefone"] ?? ""));

$valor = (float) (
    $evento["valor_inscricao"]
    ?? $evento["valor"]
    ?? 0
);

$pagamentoObrigatorio =
    (int) ($evento["pagamento_obrigatorio"] ?? 1) === 1
    && $valor > 0;

/*
 * O Asaas exige dados mínimos para gerar a cobrança.
 * Em evento pago, garantimos isso antes de criar a inscrição.
 */
if ($pagamentoObrigatorio) {
    if (
        $nome === ""
        || !Usuario::cpfValido($cpf)
        || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || preg_replace("/\D+/", "", $telefone) === ""
    ) {
        Session::flash(
            "error",
            "Para participar de um evento pago, complete "
            . "seu nome, CPF, e-mail e telefone no perfil."
        );

        header(
            "Location: "
            . BASE_URL
            . "user/index.php"
        );
        exit;
    }
}

$iniciouTransacao = !$db->inTransaction();

if ($iniciouTransacao) {
    $db->beginTransaction();
}

try {
    /*
     * Revalidação dentro da transação para reduzir
     * risco de inscrição duplicada.
     */
    if (
        $inscricaoModel->existeNoEvento(
            $idEvento,
            $idUsuario
        )
    ) {
        throw new RuntimeException(
            "Você já possui uma inscrição ativa neste evento."
        );
    }

    if ($inscricaoModel->vagasDisponiveis($idEvento) === 0) {
        throw new RuntimeException(
            "As vagas para este evento estão esgotadas."
        );
    }

    $salvou = $inscricaoModel->salvar([
        "idEvento" => $idEvento,
        "idUsuario" => $idUsuario,
        "nome" => $nome,
        "cpf" => $cpf !== "" ? $cpf : null,
        "rg" => null,
        "email" => $email !== "" ? $email : null,
        "telefone" => $telefone !== "" ? $telefone : null,
        "sexo" => "Masculino",
        "data_nascimento" => null,
        "cidade" => $usuario["cidade"] ?? null,
        "estado" => $usuario["estado"] ?? null,
        "camiseta" => null,
        "observacoes" => null,
        "contato_emergencia" => null,
        "telefone_emergencia" => null,
        "status" => "Pendente",
        "pagamento" => $pagamentoObrigatorio
            ? "Pendente"
            : "Pago",
        "presenca" => 0,
        "certificado" => 0,
        "valor" => $valor,
        "valor_pago" => 0,
        "forma_pagamento" => null,
        "codigo_pagamento" => null
    ]);

    if (!$salvou) {
        throw new RuntimeException(
            "Não foi possível realizar a inscrição."
        );
    }

    $idInscricao = $inscricaoModel->ultimoId();

    if ($idInscricao <= 0) {
        throw new RuntimeException(
            "Não foi possível identificar a inscrição criada."
        );
    }

    $idPagamento = $pagamentoModel->criarParaInscricao(
        $idInscricao
    );

    if ($iniciouTransacao) {
        $db->commit();
    }

    if ($idPagamento > 0) {
        header(
            "Location: "
            . BASE_URL
            . "eventos/pagamento.php?id="
            . $idPagamento
        );
        exit;
    }

    Session::flash(
        "success",
        "Inscrição realizada e confirmada com sucesso."
    );

    header("Location: " . $urlEvento);
    exit;
} catch (Throwable $erro) {
    if (
        $iniciouTransacao
        && $db->inTransaction()
    ) {
        $db->rollBack();
    }

    error_log(
        "Falha na inscrição do evento"
        . " | evento=" . $idEvento
        . " | usuario=" . $idUsuario
        . " | erro=" . $erro->getMessage()
    );

    Session::flash(
        "error",
        $erro instanceof RuntimeException
            ? $erro->getMessage()
            : "Não foi possível concluir a inscrição."
    );

    header("Location: " . $urlEvento);
    exit;
}
