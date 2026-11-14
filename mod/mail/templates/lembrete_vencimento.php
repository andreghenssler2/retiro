<!doctype html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><title>Lembrete de vencimento</title></head>
<body style="margin:0;padding:24px;background:#f5f6fa;font-family:Arial,Helvetica,sans-serif;color:#263238">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
<table role="presentation" width="620" cellpadding="0" cellspacing="0" style="max-width:620px;background:#ffffff;border:1px solid #e1e7ee;border-radius:10px">
<tr><td style="background:#15528a;padding:24px;text-align:center;color:#ffffff;border-radius:10px 10px 0 0"><h2 style="margin:0">Lembrete de pagamento</h2></td></tr>
<tr><td style="padding:28px">
<p>Olá, <strong><?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?></strong>!</p>
<p>Identificamos que seu pagamento por <strong><?= htmlspecialchars($forma, ENT_QUOTES, 'UTF-8') ?></strong> ainda consta como pendente e vence <strong><?= $dias === 1 ? 'amanhã' : 'em 3 dias' ?></strong>.</p>
<table role="presentation" width="100%" cellpadding="9" cellspacing="0" style="background:#f6f9fc;border:1px solid #e3e8f0;border-radius:6px">
<tr><td><strong>Evento</strong></td><td><?= htmlspecialchars($evento, ENT_QUOTES, 'UTF-8') ?></td></tr>
<tr><td><strong>Forma</strong></td><td><?= htmlspecialchars($forma, ENT_QUOTES, 'UTF-8') ?></td></tr>
<tr><td><strong>Valor</strong></td><td><?= htmlspecialchars($valor, ENT_QUOTES, 'UTF-8') ?></td></tr>
<tr><td><strong>Vencimento</strong></td><td><?= htmlspecialchars($data, ENT_QUOTES, 'UTF-8') ?></td></tr>
</table>
<p style="text-align:center;margin:30px 0"><a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>" style="display:inline-block;background:#0d6efd;color:#fff;padding:14px 24px;border-radius:6px;text-decoration:none;font-weight:bold">Consultar pagamento</a></p>
<?php if ($forma === 'PIX' && $pixCopiaCola !== ''): ?>
<p><strong>PIX Copia e Cola:</strong></p><p style="padding:12px;background:#f6f9fc;word-break:break-all;font-size:12px"><?= htmlspecialchars($pixCopiaCola, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($forma === 'Boleto' && $boletoLinhaDigitavel !== ''): ?>
<p><strong>Linha digitável do boleto:</strong></p><p style="padding:12px;background:#f6f9fc;word-break:break-all"><?= htmlspecialchars($boletoLinhaDigitavel, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<p style="font-size:14px;color:#546577">Se você já efetuou o pagamento, desconsidere este aviso. A confirmação pode levar algum tempo. Este lembrete não altera sua inscrição nem cancela o pagamento.</p>
<hr style="border:none;border-top:1px solid #e5e9ee"><p style="font-size:12px;color:#87929f">Mensagem automática do Sistema Retiro. Não responda este e-mail.</p>
</td></tr></table></td></tr></table>
</body></html>
