<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <title>Cadastro Realizado</title>

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
                background:#0d6efd;
                color:#fff;
                text-align:center;
                padding:30px;
            ">

                <h1 style="margin:0;font-size:28px;">

                    Bem-vindo!

                </h1>

            </td>

        </tr>

        <tr>

            <td style="padding:40px;">

                <h2 style="margin-top:0;color:#333;">

                    Olá
                    <?= htmlspecialchars($nome) ?>,

                </h2>

                <p style="font-size:16px;color:#555;line-height:1.7;">

                    Seu cadastro foi realizado com sucesso em nosso sistema.

                </p>

                <table width="100%" cellpadding="10" cellspacing="0" style="
                        background:#f8f9fa;
                        border:1px solid #dee2e6;
                        border-radius:6px;
                        margin:25px 0;
                    ">

                    <tr>

                        <td width="130">

                            <strong>Nome</strong>

                        </td>

                        <td>

                            <?= htmlspecialchars($nome) ?>

                        </td>

                    </tr>

                    <tr>

                        <td>

                            <strong>E-mail</strong>

                        </td>

                        <td>

                            <?= htmlspecialchars($email) ?>

                        </td>

                    </tr>

                </table>

                <p style="font-size:16px;color:#555;line-height:1.7;">

                    Agora você já pode acessar o sistema utilizando seu e-mail e sua senha cadastrada.

                </p>

                <div style="text-align:center;margin:35px 0;">

                    <a href="<?= BASE_URL ?>login/" style="
                            background:#0d6efd;
                            color:#fff;
                            text-decoration:none;
                            padding:14px 30px;
                            border-radius:6px;
                            display:inline-block;
                            font-size:16px;
                            font-weight:bold;
                        ">

                        Acessar Sistema

                    </a>

                </div>

                <hr style="border:none;border-top:1px solid #e5e5e5;">

                <p style="
                    font-size:13px;
                    color:#888;
                    line-height:1.6;
                    margin-top:25px;
                ">

                    Este é um e-mail automático. Não responda esta mensagem.

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

                ©
                <?= date('Y') ?>

                <?= htmlspecialchars(Title::getAtual()->getNome()) ?>

                <br>

                Todos os direitos reservados.

            </td>

        </tr>

    </table>

</body>

</html>