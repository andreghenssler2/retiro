<?php

declare(strict_types=1);

require_once __DIR__
    . "/../config/settings.php";

Session::start();
Auth::requireLogin();

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    exit("Método não permitido.");
}

if (
    !Session::validateCsrf(
        $_POST["_token"] ?? ""
    )
) {
    http_response_code(419);
    exit("Token de segurança inválido.");
}

$idInscricao = filter_input(
    INPUT_POST,
    "idInscricao",
    FILTER_VALIDATE_INT
) ?: 0;

$motivo = trim(
    (string) ($_POST["motivo"] ?? "")
);

$idUsuario = (int) (Auth::id() ?? 0);

$inscricaoModel = new Inscricao($db);
$inscricao = $inscricaoModel->buscar(
    $idInscricao
);

$urlRetorno = BASE_URL
    . "user/eventos.php";

if ($inscricao) {
    $eventoModel = new Evento();

    $evento = $eventoModel->buscar(
        (int) (
            $inscricao["idEvento"]
            ?? 0
        )
    );

    if ($evento) {
        $slug = trim(
            (string) (
                $evento["slug"]
                ?? ""
            )
        );

        $urlRetorno =
            BASE_URL
            . "eventos/detalhe.php"
            . (
                $slug !== ""
                    ? "?slug="
                        . rawurlencode(
                            $slug
                        )
                    : "?id="
                        . (int) $evento[
                            "idEvento"
                        ]
            );
    }
}

try {
    if (
        !$inscricao
        || (int) (
            $inscricao["idUsuario"]
            ?? 0
        ) !== $idUsuario
    ) {
        throw new RuntimeException(
            "Inscrição não encontrada."
        );
    }

    $service =
        new SolicitacaoCancelamentoInscricao(
            $db
        );

    $idSolicitacao =
        $service->solicitar(
            $idInscricao,
            $idUsuario,
            $motivo
        );

    /*
     * A solicitação já foi gravada com sucesso.
     *
     * Falha na notificação NÃO desfaz o pedido.
     */
    try {
        $notificador =
            new CancelamentoInscricaoNotificacaoService(
                $db
            );

        $resultadoNotificacao =
            $notificador->notificarSolicitacao(
                $idSolicitacao
            );

        Log::info(
            "Notificações administrativas do "
            . "cancelamento processadas",
            [
                "idSolicitacao" =>
                    $idSolicitacao,
                "notificacaoSistema" =>
                    (bool) (
                        $resultadoNotificacao[
                            "notificacaoSistema"
                        ]
                        ?? false
                    ),
                "emailsEnviados" =>
                    (int) (
                        $resultadoNotificacao[
                            "emailsEnviados"
                        ]
                        ?? 0
                    ),
                "emailsFalharam" =>
                    (int) (
                        $resultadoNotificacao[
                            "emailsFalharam"
                        ]
                        ?? 0
                    )
            ]
        );
    } catch (Throwable $erroNotificacao) {
        Log::warning(
            "Solicitação registrada, mas houve "
            . "falha ao notificar Administradores",
            [
                "idSolicitacao" =>
                    $idSolicitacao,
                "erro" =>
                    $erroNotificacao
                        ->getMessage()
            ]
        );
    }

    Session::flash(
        "success",
        "Solicitação de cancelamento "
        . "enviada. O Administrador foi "
        . "notificado e analisará o pedido."
    );
} catch (Throwable $erro) {
    Session::flash(
        "error",
        $erro->getMessage()
    );
}

header("Location: " . $urlRetorno);
exit;
