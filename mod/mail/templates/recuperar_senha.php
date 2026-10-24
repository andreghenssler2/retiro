<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <title>Recuperação de Senha</title>

</head>

<body style="
    margin:0;
    padding:30px;
    background:#f5f6fa;
    font-family:Arial,Helvetica,sans-serif;
">

    <table align="center" width="650" cellpadding="0" cellspacing="0" style="
            background:#ffffff;
            border-radius:10px;
            overflow:hidden;
            box-shadow:0 2px 10px rgba(0,0,0,.08);
        ">

        <tr>

            <td style="
                background:#dc3545;
                color:#ffffff;
                text-align:center;
                padding:30px;
            ">

                <h1 style="margin:0;font-size:28px;">

                    Recuperação de Senha

                </h1>

            </td>

        </tr>

        <tr>

            <td style="padding:40px;">

                <h2 style="margin-top:0;color:#333;">

                    Olá <?= htmlspecialchars($nome) ?>,

                </h2>

                <p style="font-size:16px;color:#555;line-height:1.7;">

                    Recebemos uma solicitação para redefinir a senha da sua conta.

                </p>

                <p style="font-size:16px;color:#555;line-height:1.7;">

                    Clique no botão abaixo para criar uma nova senha.

                </p>

                <div style="text-align:center;margin:35px 0;">

                    <a href="<?= htmlspecialchars($link) ?>" style="
                            background:#dc3545;
                            color:#ffffff;
                            text-decoration:none;
                            padding:14px 30px;
                            border-radius:6px;
                            display:inline-block;
                            font-size:16px;
                            font-weight:bold;
                        ">

                        Redefinir Senha

                    </a>

                </div>

                <p style="font-size:15px;color:#555;line-height:1.7;">

                    Caso o botão acima não funcione, copie e cole o link abaixo em seu navegador:

                </p>

                <p style="
                    background:#f8f9fa;
                    border:1px solid #dee2e6;
                    border-radius:6px;
                    padding:15px;
                    word-break:break-all;
                    font-size:13px;
                    color:#0d6efd;
                ">

                    <?= htmlspecialchars($link) ?>

                </p>

                <hr style="border:none;border-top:1px solid #e5e5e5;margin:30px 0;">

                <p style="font-size:15px;color:#555;line-height:1.7;">

                    Se você <strong>não solicitou</strong> esta alteração, pode ignorar este e-mail com segurança. Sua
                    senha permanecerá inalterada.

                </p>

                <p style="
                    font-size:13px;
                    color:#888;
                    line-height:1.6;
                    margin-top:25px;
                ">

                    Este link é válido por apenas <strong>1 hora</strong> por motivos de segurança.

                </p>

            </td>

        </tr>

        <tr>

            <td style="
                background:#f8f9fa;
                text-align:center;
                padding:20px;
                font-size:12px;
                color:#666;
            ">

                © <?= date('Y') ?>

                <?= htmlspecialchars(Title::getAtual()->getNome()) ?>

                <br>

                Todos os direitos reservados.

            </td>

        </tr>

    </table>

</body>

</html>