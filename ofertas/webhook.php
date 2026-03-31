<?php // Sempre responder 200 
http_response_code(200); 
// Captura payload 
$payload = file_get_contents('php://input'); // Log completo (debug) 
file_put_contents( __DIR__ . '/asaas_webhook.log', date('Y-m-d H:i:s') . PHP_EOL . $payload . PHP_EOL . PHP_EOL, FILE_APPEND ); 
$data = json_decode($payload, true); // Segurança mínima 
if (!isset($data['event'])) { exit('Evento inválido'); } 
$event = $data['event']; $payment = $data['payment'] ?? [];
// Só processa quando realmente pago 
if (in_array($event, ['PAYMENT_RECEIVED', 'PAYMENT_CONFIRMED'])) { 
    $id = $payment['id'] ?? ''; 
    $valor = $payment['value'] ?? '';
    $status = $payment['status'] ?? '';
    $descricao = $payment['description'] ?? '';
    $cliente = $payment['customer'] ?? ''; $dataPagto = $payment['confirmedDate'] ?? ''; 
    // Salva em TXT 
    file_put_contents( __DIR__ . '/pagamento.txt', "ID: $id | Valor: $valor | Status: $status | Cliente: $cliente | Desc: $descricao | Data: $dataPagto" . PHP_EOL, FILE_APPEND ); 
// 👉 Aqui você pode atualizar banco, liberar acesso etc. } 
//echo 'OK'; Erro 400 Conteúdo da requisição { "name": "Webhook Pagamentos", "url": "https://ieclbparobe.com.br/ofertas/webhook.php", "enabled": true, "events": [ "PAYMENT_RECEIVED", "PAYMENT_CONFIRMED", "PAYMENT_OVERDUE" ] } Conteúdo da resposta { "errors": [ { "code": "invalid_object", "description": "Já existe uma configuração para os eventos com os mesmos atributos." }, { "code": "invalid_object", "description": "É necessário informar um tipo de envio para essa configuração." } ]
}