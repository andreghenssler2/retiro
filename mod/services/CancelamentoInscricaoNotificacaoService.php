<?php

declare(strict_types=1);

/**
 * Notifica os Administradores quando um usuário solicita
 * o cancelamento de uma inscrição.
 *
 * Canais:
 * - notificação interna do sistema;
 * - e-mail para todos os Administradores ativos.
 */
class CancelamentoInscricaoNotificacaoService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();

        $this->db->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $this->db->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );
    }

    public function notificarSolicitacao(
        int $idSolicitacao
    ): array {
        if ($idSolicitacao <= 0) {
            throw new InvalidArgumentException(
                "Solicitação inválida."
            );
        }

        $dados = $this->buscarDados(
            $idSolicitacao
        );

        if (!$dados) {
            throw new RuntimeException(
                "Solicitação de cancelamento "
                . "não encontrada."
            );
        }

        $notificacaoSistema = false;
        $emailsEnviados = 0;
        $emailsFalharam = 0;

        try {
            $notificacaoSistema =
                $this->notificarSistema(
                    $dados
                );
        } catch (Throwable $erro) {
            Log::warning(
                "Falha ao criar notificação interna "
                . "de cancelamento",
                [
                    "idSolicitacao" =>
                        $idSolicitacao,
                    "erro" =>
                        $erro->getMessage()
                ]
            );
        }

        foreach (
            $this->listarAdministradores()
            as $administrador
        ) {
            $email = trim(
                (string) (
                    $administrador["email"]
                    ?? ""
                )
            );

            $nome = trim(
                (string) (
                    $administrador["nome"]
                    ?? "Administrador"
                )
            );

            if (
                !filter_var(
                    $email,
                    FILTER_VALIDATE_EMAIL
                )
            ) {
                $emailsFalharam++;

                Log::warning(
                    "Administrador sem e-mail "
                    . "válido para notificação "
                    . "de cancelamento",
                    [
                        "idAdministrador" =>
                            (int) (
                                $administrador["id"]
                                ?? 0
                            ),
                        "email" => $email
                    ]
                );

                continue;
            }

            try {
                if (
                    $this->enviarEmail(
                        $email,
                        $nome,
                        $dados
                    )
                ) {
                    $emailsEnviados++;
                } else {
                    $emailsFalharam++;
                }
            } catch (Throwable $erro) {
                $emailsFalharam++;

                Log::warning(
                    "Falha ao enviar e-mail de "
                    . "solicitação de cancelamento",
                    [
                        "idSolicitacao" =>
                            $idSolicitacao,
                        "email" => $email,
                        "erro" =>
                            $erro->getMessage()
                    ]
                );
            }
        }

        return [
            "notificacaoSistema" =>
                $notificacaoSistema,
            "emailsEnviados" =>
                $emailsEnviados,
            "emailsFalharam" =>
                $emailsFalharam
        ];
    }

    private function buscarDados(
        int $idSolicitacao
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                s.idSolicitacao,
                s.idInscricao,
                s.idUsuario,
                s.motivo,
                s.criado_em,
                i.status AS statusInscricao,
                i.pagamento AS statusPagamento,
                e.idEvento,
                e.titulo AS evento,
                e.data_inicio AS dataEvento,
                e.hora_inicio AS horaEvento,
                COALESCE(
                    u.nome,
                    i.nome
                ) AS participante,
                COALESCE(
                    u.email,
                    i.email
                ) AS emailParticipante,
                COALESCE(
                    u.telefone,
                    i.telefone
                ) AS telefoneParticipante
            FROM
                solicitacoes_cancelamento_inscricao s
            INNER JOIN inscricoes i
                ON i.idInscricao =
                    s.idInscricao
            INNER JOIN eventos e
                ON e.idEvento =
                    i.idEvento
            LEFT JOIN usuarios u
                ON u.id = s.idUsuario
            WHERE s.idSolicitacao =
                :idSolicitacao
            LIMIT 1
        ");

        $stmt->execute([
            ":idSolicitacao" =>
                $idSolicitacao
        ]);

        $registro = $stmt->fetch();

        return $registro ?: null;
    }

    private function listarAdministradores(): array
    {
        $stmt = $this->db->query("
            SELECT
                id,
                nome,
                email
            FROM usuarios
            WHERE tipo = 1
              AND ativo = 1
              AND email IS NOT NULL
              AND TRIM(email) <> ''
            ORDER BY id ASC
        ");

        return $stmt->fetchAll();
    }

    private function notificarSistema(
        array $dados
    ): bool {
        /*
         * idUsuarioRelacionado = NULL:
         *
         * Administrador vê a notificação porque
         * o perfil administrativo visualiza todas.
         *
         * Usuários comuns não veem porque o filtro
         * exige idUsuarioRelacionado = Auth::id().
         */
        $stmt = $this->db->prepare("
            INSERT INTO notificacoes (
                tipo,
                idReferencia,
                idUsuarioRelacionado,
                titulo,
                mensagem,
                url,
                tituloUsuario,
                mensagemUsuario,
                urlUsuario,
                criadoEm
            ) VALUES (
                'cancelamento',
                :idReferencia,
                NULL,
                :titulo,
                :mensagem,
                :url,
                '',
                '',
                '',
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                titulo = VALUES(titulo),
                mensagem = VALUES(mensagem),
                url = VALUES(url),
                criadoEm = VALUES(criadoEm)
        ");

        $participante = trim(
            (string) (
                $dados["participante"]
                ?? "Participante"
            )
        );

        $evento = trim(
            (string) (
                $dados["evento"]
                ?? "evento"
            )
        );

        $idInscricao = (int) (
            $dados["idInscricao"]
            ?? 0
        );

        return $stmt->execute([
            ":idReferencia" =>
                (int) $dados["idSolicitacao"],
            ":titulo" =>
                "Solicitação de cancelamento",
            ":mensagem" =>
                $participante
                . " solicitou o cancelamento "
                . "da inscrição #"
                . $idInscricao
                . " em "
                . $evento
                . ".",
            ":url" =>
                "admin/inscricao/"
                . "cancelamentos.php"
                . "?status=Pendente"
        ]);
    }

    private function enviarEmail(
        string $email,
        string $nomeAdministrador,
        array $dados
    ): bool {
        $participante = $this->escapar(
            (string) (
                $dados["participante"]
                ?? "Participante"
            )
        );

        $evento = $this->escapar(
            (string) (
                $dados["evento"]
                ?? "Evento"
            )
        );

        $motivo = nl2br(
            $this->escapar(
                (string) (
                    $dados["motivo"]
                    ?? ""
                )
            )
        );

        $idInscricao = (int) (
            $dados["idInscricao"]
            ?? 0
        );

        $idSolicitacao = (int) (
            $dados["idSolicitacao"]
            ?? 0
        );

        $criadoEm = $this->formatarDataHora(
            $dados["criado_em"]
                ?? null
        );

        $dataEvento = $this->formatarData(
            $dados["dataEvento"]
                ?? null
        );

        $horaEvento = $this->formatarHora(
            $dados["horaEvento"]
                ?? null
        );

        $urlAnalise =
            BASE_URL
            . "admin/inscricao/"
            . "cancelamentos.php"
            . "?status=Pendente";

        $urlEscapada =
            $this->escapar(
                $urlAnalise
            );

        $nomeAdminEscapado =
            $this->escapar(
                $nomeAdministrador
            );

        $emailParticipante =
            $this->escapar(
                (string) (
                    $dados[
                        "emailParticipante"
                    ]
                    ?? ""
                )
            );

        $telefone =
            $this->escapar(
                (string) (
                    $dados[
                        "telefoneParticipante"
                    ]
                    ?? ""
                )
            );

        $html = <<<HTML
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
</head>
<body style="
    margin:0;
    padding:0;
    background:#f4f6f9;
    font-family:Arial,Helvetica,sans-serif;
    color:#1f2937;
">
    <table
        role="presentation"
        width="100%"
        cellspacing="0"
        cellpadding="0"
        style="background:#f4f6f9;padding:24px 12px;"
    >
        <tr>
            <td align="center">
                <table
                    role="presentation"
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    style="
                        max-width:640px;
                        background:#ffffff;
                        border-radius:12px;
                        overflow:hidden;
                        border:1px solid #e5e7eb;
                    "
                >
                    <tr>
                        <td style="
                            padding:24px;
                            background:#fff3cd;
                            border-bottom:1px solid #ffe69c;
                        ">
                            <h1 style="
                                margin:0;
                                font-size:22px;
                                color:#664d03;
                            ">
                                Nova solicitação de cancelamento
                            </h1>
                        </td>
                    </tr>

                    <tr>
                        <td style="padding:28px;">
                            <p style="margin-top:0;">
                                Olá, {$nomeAdminEscapado}.
                            </p>

                            <p>
                                Uma nova solicitação de
                                cancelamento de inscrição
                                precisa ser analisada.
                            </p>

                            <table
                                role="presentation"
                                width="100%"
                                cellspacing="0"
                                cellpadding="8"
                                style="
                                    margin:20px 0;
                                    border-collapse:collapse;
                                    background:#f8fafc;
                                "
                            >
                                <tr>
                                    <td>
                                        <strong>Solicitação</strong>
                                    </td>
                                    <td>#{$idSolicitacao}</td>
                                </tr>

                                <tr>
                                    <td>
                                        <strong>Inscrição</strong>
                                    </td>
                                    <td>#{$idInscricao}</td>
                                </tr>

                                <tr>
                                    <td>
                                        <strong>Participante</strong>
                                    </td>
                                    <td>{$participante}</td>
                                </tr>

                                <tr>
                                    <td>
                                        <strong>E-mail</strong>
                                    </td>
                                    <td>{$emailParticipante}</td>
                                </tr>

                                <tr>
                                    <td>
                                        <strong>Telefone</strong>
                                    </td>
                                    <td>{$telefone}</td>
                                </tr>

                                <tr>
                                    <td>
                                        <strong>Evento</strong>
                                    </td>
                                    <td>{$evento}</td>
                                </tr>

                                <tr>
                                    <td>
                                        <strong>Data do evento</strong>
                                    </td>
                                    <td>{$dataEvento} {$horaEvento}</td>
                                </tr>

                                <tr>
                                    <td>
                                        <strong>Solicitado em</strong>
                                    </td>
                                    <td>{$criadoEm}</td>
                                </tr>
                            </table>

                            <div style="
                                padding:16px;
                                margin:20px 0;
                                border-left:4px solid #dc3545;
                                background:#fff5f5;
                            ">
                                <strong>
                                    Motivo informado:
                                </strong>

                                <div style="margin-top:8px;">
                                    {$motivo}
                                </div>
                            </div>

                            <p style="
                                margin:28px 0;
                                text-align:center;
                            ">
                                <a
                                    href="{$urlEscapada}"
                                    style="
                                        display:inline-block;
                                        padding:12px 22px;
                                        background:#0d6efd;
                                        color:#ffffff;
                                        text-decoration:none;
                                        border-radius:7px;
                                        font-weight:bold;
                                    "
                                >
                                    Analisar solicitação
                                </a>
                            </p>

                            <p style="
                                margin-bottom:0;
                                color:#6b7280;
                                font-size:13px;
                            ">
                                O cancelamento ainda não foi
                                efetivado. A inscrição permanece
                                ativa até a decisão do Administrador.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        $assunto =
            "Cancelamento solicitado - "
            . trim(
                (string) (
                    $dados["evento"]
                    ?? "Evento"
                )
            );

        $mail = new Mail();

        $enviado = $mail->send(
            $email,
            $nomeAdministrador,
            $assunto,
            $html
        );

        if ($enviado) {
            Log::info(
                "Administrador notificado por e-mail "
                . "sobre cancelamento",
                [
                    "idSolicitacao" =>
                        $idSolicitacao,
                    "email" => $email
                ]
            );
        }

        return $enviado;
    }

    private function escapar(
        string $valor
    ): string {
        return htmlspecialchars(
            $valor,
            ENT_QUOTES | ENT_SUBSTITUTE,
            "UTF-8"
        );
    }

    private function formatarData(
        mixed $valor
    ): string {
        $texto = trim(
            (string) ($valor ?? "")
        );

        if ($texto === "") {
            return "-";
        }

        $timestamp = strtotime($texto);

        return $timestamp !== false
            ? date(
                "d/m/Y",
                $timestamp
            )
            : $texto;
    }

    private function formatarHora(
        mixed $valor
    ): string {
        $texto = trim(
            (string) ($valor ?? "")
        );

        if ($texto === "") {
            return "";
        }

        return substr(
            $texto,
            0,
            5
        );
    }

    private function formatarDataHora(
        mixed $valor
    ): string {
        $texto = trim(
            (string) ($valor ?? "")
        );

        if ($texto === "") {
            return "-";
        }

        $timestamp = strtotime($texto);

        return $timestamp !== false
            ? date(
                "d/m/Y H:i",
                $timestamp
            )
            : $texto;
    }
}
