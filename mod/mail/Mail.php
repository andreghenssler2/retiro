<?php

declare(strict_types=1);

require_once __DIR__
    . "/../../lib/vendor/autoload.php";
require_once __DIR__
    . "/../classes/logs.php";

use PHPMailer\PHPMailer\PHPMailer;

class Mail
{
    private PHPMailer $mail;

    public function __construct()
    {
        $config = require __DIR__
            . "/../../config/mail.php";

        if (
            !is_array($config)
            || trim(
                (string) ($config["host"] ?? "")
            ) === ""
            || trim(
                (string) ($config["username"] ?? "")
            ) === ""
        ) {
            throw new RuntimeException(
                "Nenhuma configuração de e-mail ativa foi encontrada."
            );
        }

        $this->mail = new PHPMailer(true);

        /*
         * DEBUG:
         * 0 = desligado
         * 2 = completo
         */
        $this->mail->Debugoutput = "html";
        $this->mail->isSMTP();

        $this->mail->Host = trim(
            (string) $config["host"]
        );

        $this->mail->Port = (int) (
            $config["port"]
            ?? 587
        );

        $encryption = strtolower(
            trim(
                (string) (
                    $config["encryption"]
                    ?? ""
                )
            )
        );

        if ($encryption !== "") {
            $this->mail->SMTPSecure = $encryption;
        }

        $this->mail->Username = trim(
            (string) $config["username"]
        );

        $this->mail->Password = (string) (
            $config["password"]
            ?? ""
        );

        $this->mail->SMTPAuth = true;
        $this->mail->CharSet = "UTF-8";

        $emailServidor = trim(
            (string) (
                $config["from_email"]
                ?? $config["username"]
            )
        );

        $nomeServidor = trim(
            (string) (
                $config["from_name"]
                ?? "Sistema de Eventos"
            )
        );

        if ($nomeServidor === "") {
            $nomeServidor = "Sistema de Eventos";
        }

        $this->mail->setFrom(
            $emailServidor,
            $nomeServidor
        );

        $this->mail->addReplyTo(
            $emailServidor,
            $nomeServidor
        );

        $this->mail->isHTML(true);
    }

    public function send(
        string $email,
        string $nome,
        string $assunto,
        string $html,
        array $anexos = []
    ): bool {
        try {
            $this->mail->clearAddresses();
            $this->mail->clearAttachments();

            $this->mail->addAddress(
                $email,
                $nome
            );

            $this->mail->Subject = $assunto;
            $this->mail->Body = $html;

            foreach ($anexos as $anexo) {
                $caminho = is_array($anexo)
                    ? (string) (
                        $anexo["path"]
                        ?? ""
                    )
                    : (string) $anexo;

                $nomeArquivo = is_array($anexo)
                    ? (string) (
                        $anexo["name"]
                        ?? ""
                    )
                    : "";

                if (
                    $caminho === ""
                    || !is_file($caminho)
                    || !is_readable($caminho)
                ) {
                    throw new RuntimeException(
                        "Anexo de e-mail não encontrado: "
                        . $caminho
                    );
                }

                if ($nomeArquivo !== "") {
                    $this->mail->addAttachment(
                        $caminho,
                        $nomeArquivo
                    );
                } else {
                    $this->mail->addAttachment(
                        $caminho
                    );
                }
            }

            $this->mail->AltBody = strip_tags(
                str_replace(
                    [
                        "<br>",
                        "<br/>",
                        "<br />"
                    ],
                    PHP_EOL,
                    $html
                )
            );

            return $this->mail->send();
        } catch (Throwable $erro) {
            Log::error(
                "Erro ao enviar e-mail",
                [
                    "destinatario" => $email,
                    "erro" => $this->mail->ErrorInfo
                        ?: $erro->getMessage()
                ]
            );

            return false;
        }
    }
}
