<?php
require_once 'lib/vendor/autoload.php';

use GuzzleHttp\Client;

$accessToken  = '$aact_hmlg_000MzkwODA2MWY2OGM3MWRlMDU2NWM3MzJlNzZmNGZhZGY6OjI3ZGM5ZjgyLWVmYmMtNGVkNi1hY2ZhLTJlYTllMGNiOWQxYTo6JGFhY2hfOGI3MDkzZmYtNmRlOC00NmRiLWEwNWMtMTNiOGYzYTIwYzlk';


$client = new Client([
    'base_uri' => 'https://api-sandbox.asaas.com/v3/',
    'headers' => [
        'accept' => 'application/json',
        'content-type' => 'application/json',
        'access_token' => $accessToken
    ]
]);

$cpfCnpj = '12345678909';
$email   = 'andreghenssler@gmail.com';

/**
 * 1️⃣ VERIFICA SE CLIENTE EXISTE
 */
$response = $client->get('customers', [
    'query' => [
        'cpfCnpj' => $cpfCnpj
    ]
]);

$data = json_decode($response->getBody(), true);

if (!empty($data['data'])) {
    // Cliente já existe
    $customerId = $data['data'][0]['id'];
    echo "Cliente já existe: $customerId<br>";
} else {
    /**
     * 2️⃣ CRIA CLIENTE SE NÃO EXISTIR
     */
    $response = $client->post('customers', [
        'json' => [
            'name' => 'Andre Cliente Teste',
            'email' => $email,
            'cpfCnpj' => $cpfCnpj,
            'mobilePhone' => '51995262803'
        ]
    ]);

    $customer = json_decode($response->getBody(), true);
    $customerId = $customer['id'];

    echo "Cliente criado: $customerId<br>";
}

/**
 * 3️⃣ CRIA PAGAMENTO
 */
$response = $client->post('payments', [
    'json' => [
        'billingType' => 'BOLETO',
        'customer' => $customerId,
        'value' => rand(50, 500),
        'dueDate' => date('Y-m-d', strtotime('+5 days')),
        'description' => 'Pedido 258'
    ]
]);

echo $response->getBody();