<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Certificado emitido</title>
</head>
<body style="margin:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#1f2937;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f4f6;padding:24px 10px;">
    <tr>
        <td align="center">
            <table role="presentation" width="620" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border-radius:14px;overflow:hidden;box-shadow:0 8px 28px rgba(15,23,42,.08);">
                <tr>
                    <td style="background:#0d6efd;color:#ffffff;padding:24px 28px;">
                        <h1 style="margin:0;font-size:24px;">Seu certificado está disponível</h1>
                    </td>
                </tr>
                <tr>
                    <td style="padding:28px;line-height:1.65;">
                        <p style="margin-top:0;">Olá, <strong><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></strong>.</p>
                        <p>
                            Seu certificado de participação no evento
                            <strong><?= htmlspecialchars($evento, ENT_QUOTES, 'UTF-8') ?></strong>
                            foi emitido e está anexado a este e-mail.
                        </p>
                        <?php if ($dataEvento !== ''): ?>
                            <p><strong>Data do evento:</strong> <?= htmlspecialchars($dataEvento, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                        <p><strong>Código de validação:</strong> <?= htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') ?></p>
                        <p style="margin:24px 0;">
                            <a href="<?= htmlspecialchars($urlValidacao, ENT_QUOTES, 'UTF-8') ?>"
                               style="display:inline-block;background:#0d6efd;color:#ffffff;text-decoration:none;padding:12px 20px;border-radius:8px;font-weight:bold;">
                                Validar certificado
                            </a>
                        </p>
                        <p style="font-size:13px;color:#6b7280;margin-bottom:0;">
                            A autenticidade também pode ser consultada informando o código acima na página de validação do sistema.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
