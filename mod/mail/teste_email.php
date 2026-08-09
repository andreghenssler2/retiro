<?php

require_once 'Mail.php';

$mail = new Mail();

$ok = $mail->send(
    'andreghenssler@gmail.com',
    'André Gustavo Henssler',
    'Teste de envio',
    '<h1>Teste</h1><p>Se você recebeu este e-mail, o SMTP está funcionando.</p>'
);

var_dump($ok);