<!DOCTYPE html>
<html lang="pt-BR">

<head>
       <meta charset="UTF-8">
       <title>Pagamento vencido</title>
</head>

<body style="margin:0;padding:30px;background:#f5f6fa;font-family:Arial,Helvetica,sans-serif;">
       <table align="center" width="650" cellpadding="0" cellspacing="0"
              style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.08);">
              <tr>
                     <td style="background:#dc3545;color:#fff;text-align:center;padding:30px;">
                            <h1 style="margin:0;font-size:28px;">Pagamento vencido</h1>
                     </td>
              </tr>
              <tr>
                     <td style="padding:40px;">
                            <h2 style="margin-top:0;color:#333;">Olá,
                                   <?= htmlspecialchars((string) $nome, ENT_QUOTES, "UTF-8"); ?>!</h2>
                            <p style="font-size:16px;color:#555;line-height:1.7;">O vencimento do seu pagamento foi
                                   identificado.</p>
                            <table width="100%" cellpadding="10" cellspacing="0"
                                   style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:6px;margin:25px 0;">
                                   <tr>
                                          <td width="170"><strong>Evento</strong></td>
                                          <td><?= htmlspecialchars((string) $evento, ENT_QUOTES, "UTF-8"); ?></td>
                                   </tr>
                                   <tr>
                                          <td><strong>Código</strong></td>
                                          <td><?= htmlspecialchars((string) $codigoPagamento, ENT_QUOTES, "UTF-8"); ?>
                                          </td>
                                   </tr>
                                   <tr>
                                          <td><strong>Valor</strong></td>
                                          <td><?= htmlspecialchars((string) $valor, ENT_QUOTES, "UTF-8"); ?></td>
                                   </tr>
                                   <tr>
                                          <td><strong>Forma</strong></td>
                                          <td><?= htmlspecialchars((string) $formaPagamento, ENT_QUOTES, "UTF-8"); ?>
                                          </td>
                                   </tr>
                                   <?php if ($dataVencimento !== ""): ?>
                                          <tr>
                                                 <td><strong>Vencimento</strong></td>
                                                 <td><?= htmlspecialchars((string) $dataVencimento, ENT_QUOTES, "UTF-8"); ?>
                                                 </td>
                                          </tr>
                                   <?php endif; ?>
                            </table>

                            <p style="font-size:15px;color:#555;line-height:1.7;">
                                   Regularize o pagamento assim que possível. Caso exista período de tolerância,
                                   a inscrição seguirá as regras definidas para o evento.
                            </p>
                            <?php $urlPagamento = $bankSlipUrl !== "" ? $bankSlipUrl : $invoiceUrl; ?>
                            <?php if ($urlPagamento !== ""): ?>
                                   <div style="text-align:center;margin:30px 0;">
                                          <a href="<?= htmlspecialchars((string) $urlPagamento, ENT_QUOTES, "UTF-8"); ?>"
                                                 style="background:#dc3545;color:#fff;text-decoration:none;padding:14px 30px;border-radius:6px;display:inline-block;font-size:16px;font-weight:bold;">
                                                 Ver pagamento
                                          </a>
                                   </div>
                            <?php endif; ?>

                            <hr style="border:none;border-top:1px solid #e5e5e5;">
                            <p style="font-size:13px;color:#888;line-height:1.6;">Este é um e-mail automático. Não
                                   responda esta mensagem.</p>
                     </td>
              </tr>
              <tr>
                     <td style="background:#f8f9fa;text-align:center;padding:20px;font-size:12px;color:#666;">
                            © <?= date("Y"); ?>
                            <?= htmlspecialchars((string) $nomeSistema, ENT_QUOTES, "UTF-8"); ?><br>Todos os direitos
                            reservados.
                     </td>
              </tr>
       </table>
</body>

</html>