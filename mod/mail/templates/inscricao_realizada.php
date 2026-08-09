<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Inscrição realizada</title>
</head>

<body style="margin:0;padding:30px;background:#f5f6fa;font-family:Arial,Helvetica,sans-serif;">
    <table align="center" width="650" cellpadding="0" cellspacing="0"
        style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.08);">
        <tr>
            <td style="background:#0d6efd;color:#fff;text-align:center;padding:30px;">
                <h1 style="margin:0;font-size:28px;">Inscrição realizada!</h1>
            </td>
        </tr>
        <tr>
            <td style="padding:40px;">
                <h2 style="margin-top:0;color:#333;">
                    Olá, <?= htmlspecialchars((string) $nome, ENT_QUOTES, "UTF-8"); ?>!
                </h2>

                <p style="font-size:16px;color:#555;line-height:1.7;">
                    Sua inscrição foi registrada com sucesso.
                </p>

                <table width="100%" cellpadding="10" cellspacing="0"
                    style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;margin:25px 0;">
                    <tr>
                        <td width="170"><strong>Inscrição</strong></td>
                        <td>#<?= (int) $idInscricao; ?></td>
                    </tr>
                    <tr>
                        <td><strong>Evento</strong></td>
                        <td><?= htmlspecialchars((string) $evento, ENT_QUOTES, "UTF-8"); ?></td>
                    </tr>
                    <?php if ($dataEvento !== ""): ?>
                        <tr>
                            <td><strong>Data</strong></td>
                            <td>
                                <?= htmlspecialchars((string) $dataEvento, ENT_QUOTES, "UTF-8"); ?>
                                <?= $horaEvento !== ""
                                    ? " às " . htmlspecialchars((string) $horaEvento, ENT_QUOTES, "UTF-8")
                                    : ""; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($localEvento !== ""): ?>
                        <tr>
                            <td><strong>Local</strong></td>
                            <td><?= htmlspecialchars((string) $localEvento, ENT_QUOTES, "UTF-8"); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>Status</strong></td>
                        <td><?= htmlspecialchars((string) $statusInscricao, ENT_QUOTES, "UTF-8"); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Pagamento</strong></td>
                        <td><?= htmlspecialchars((string) $statusPagamento, ENT_QUOTES, "UTF-8"); ?></td>
                    </tr>
                </table>

                <div style="text-align:center;margin:35px 0;">
                    <a href="<?= BASE_URL ?>my/"
                        style="background:#0d6efd;color:#fff;text-decoration:none;padding:14px 30px;border-radius:6px;display:inline-block;font-size:16px;font-weight:bold;">
                        Acessar Sistema
                    </a>
                </div>

                <hr style="border:none;border-top:1px solid #e5e5e5;">
                <p style="font-size:13px;color:#888;line-height:1.6;margin-top:25px;">
                    Este é um e-mail automático. Não responda esta mensagem.
                </p>
            </td>
        </tr>
        <tr>
            <td style="background:#f8f9fa;text-align:center;padding:20px;font-size:12px;color:#666;">
                © <?= date("Y"); ?>
                <?= htmlspecialchars((string) $nomeSistema, ENT_QUOTES, "UTF-8"); ?><br>
                Todos os direitos reservados.
            </td>
        </tr>
    </table>
</body>

</html>