<?php
// PARADISE CHECKOUT - POPUP PROXY V4.7 (Robust Email & Redirect Params)
header('Access-Control-Allow-Origin: *'); // Allow requests from any origin for development, restrict in production.
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { exit(0); }

$API_TOKEN         = 'kG038IMOjvaj42sp2XKzrolyKoBswdHTShIkPS6nsY47fJYTeeRZAVvpAbcQ';
$OFFER_HASH        = 'onajpgytmd';
$PRODUCT_HASH      = 'vdgo206faj';
$BASE_AMOUNT       = 2790;
$PRODUCT_TITLE     = 'Finalize sua Compra';
$IS_DROPSHIPPING   = false; // Popups are for digital products
$PIX_EXPIRATION_MINUTES = 15;

// Endpoint for checking payment status
if (isset($_GET['action']) && $_GET['action'] === 'check_status') {
    header('Content-Type: application/json');
    $hash = $_GET['hash'] ?? null;
    if (!$hash) {
        http_response_code(400);
        echo json_encode(['error' => 'Hash não informado']);
        exit;
    }
    $status_url = 'https://api.paradisepagbr.com/api/public/v1/transactions/' . urlencode($hash) . '?api_token=' . $API_TOKEN;
    $ch_status = curl_init($status_url);
    curl_setopt_array($ch_status, [ CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_HTTPHEADER => ['Accept: application/json'] ]);
    $response_status = curl_exec($ch_status);
    $http_code_status = curl_getinfo($ch_status, CURLINFO_HTTP_CODE);
    curl_close($ch_status);

    if ($http_code_status >= 200 && $http_code_status < 300) {
        $data = json_decode($response_status, true);
        if (isset($data['payment_status'])) {
            http_response_code(200);
            echo json_encode(['payment_status' => $data['payment_status']]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Resposta da API inválida']);
        }
    } else {
        http_response_code($http_code_status);
        echo $response_status;
    }
    
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $api_url = 'https://api.paradisepagbr.com/api/public/v1/transactions?api_token=' . $API_TOKEN;
    $data = json_decode(file_get_contents('php://input'), true);
    $customer_data = $data['customer'] ?? [];
    $utms = $data['utms'] ?? [];

    // --- FAKE DATA GENERATION FOR DISABLED FIELDS / DIRECT PIX V3.2 ---
    // This logic ensures user-submitted data is used, and only fills in blanks if fields are disabled or for direct PIX.
    $is_direct_pix = false;

    if ($is_direct_pix) {
        $customer_data = []; // Start fresh for direct PIX
    }

    $cpfs = ['42879052882', '07435993492', '93509642791', '73269352468', '35583648805', '59535423720', '77949412453', '13478710634', '09669560950', '03270618638'];
    $firstNames = ['João', 'Marcos', 'Pedro', 'Lucas', 'Mateus', 'Gabriel', 'Daniel', 'Bruno', 'Maria', 'Ana', 'Juliana', 'Camila', 'Beatriz', 'Larissa', 'Sofia', 'Laura'];
    $lastNames = ['Silva', 'Santos', 'Oliveira', 'Souza', 'Rodrigues', 'Ferreira', 'Alves', 'Pereira', 'Lima', 'Gomes', 'Costa', 'Ribeiro', 'Martins', 'Carvalho'];
    $ddds = ['11', '21', '31', '41', '51', '61', '71', '81', '85', '92', '27', '48'];
    $emailProviders = ['gmail.com', 'hotmail.com', 'outlook.com', 'yahoo.com.br', 'uol.com.br', 'terra.com.br'];
    $generatedName = null;
    
    // Only generate fake data for fields that are not supposed to be in the form OR if it's direct PIX.
    if (empty($customer_data['name']) && ($is_direct_pix || !true)) {
        $randomFirstName = $firstNames[array_rand($firstNames)];
        $randomLastName = $lastNames[array_rand($lastNames)];
        $generatedName = $randomFirstName . ' ' . $randomLastName;
        $customer_data['name'] = $generatedName;
    }
    if (empty($customer_data['email']) && ($is_direct_pix || !true)) {
        $nameForEmail = $generatedName ?? ($customer_data['name'] ?? ($firstNames[array_rand($firstNames)] . ' ' . $lastNames[array_rand($lastNames)]));
        $nameParts = explode(' ', (string)$nameForEmail, 2);
        
        $normalize = fn($str) => preg_replace('/[^w]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str) ?? ''));
        
        $emailUserParts = [];
        if (!empty($nameParts[0])) {
            $part1 = $normalize($nameParts[0]);
            if (strlen($part1) > 0) $emailUserParts[] = $part1;
        }
        if (isset($nameParts[1])) {
            $part2 = $normalize($nameParts[1]);
            if (strlen($part2) > 0) $emailUserParts[] = $part2;
        }
    
        if (empty($emailUserParts)) {
            $emailUserParts[] = 'cliente';
        }
    
        $emailUser = implode('.', $emailUserParts) . mt_rand(100, 999);
        $customer_data['email'] = $emailUser . '@' . $emailProviders[array_rand($emailProviders)];
    }
    if (empty($customer_data['phone_number']) && ($is_direct_pix || !true)) {
        $customer_data['phone_number'] = $ddds[array_rand($ddds)] . '9' . mt_rand(10000000, 99999999);
    }
    if (empty($customer_data['document']) && ($is_direct_pix || !true)) {
        $customer_data['document'] = $cpfs[array_rand($cpfs)];
    }
     // --- END FAKE DATA ---

    if (!$IS_DROPSHIPPING) {
        $customer_data['street_name'] = $customer_data['street_name'] ?? 'Rua do Produto Digital'; $customer_data['number'] = $customer_data['number'] ?? '0'; $customer_data['complement'] = $customer_data['complement'] ?? 'N/A'; $customer_data['neighborhood'] = $customer_data['neighborhood'] ?? 'Internet'; $customer_data['city'] = $customer_data['city'] ?? 'Brasil'; $customer_data['state'] = $customer_data['state'] ?? 'BR';
        if (empty($customer_data['zip_code'])) { $customer_data['zip_code'] = '00000000'; }
    }

    $cart_items = [[ "product_hash" => $PRODUCT_HASH, "title" => $PRODUCT_TITLE, "price" => $BASE_AMOUNT, "quantity" => 1, "operation_type" => 1, "tangible" => $IS_DROPSHIPPING ]];

    // Limpar dados do customer para garantir formato correto
    $clean_customer = [
        "name" => $customer_data['name'] ?? 'Cliente',
        "email" => $customer_data['email'] ?? 'cliente@email.com',
        "phone_number" => $customer_data['phone_number'] ?? '11999999999',
        "document" => $customer_data['document'] ?? '11111111111'
    ];
    
    // Adicionar endereço apenas se não for produto digital
    if ($IS_DROPSHIPPING) {
        $clean_customer['street_name'] = $customer_data['street_name'] ?? 'Rua Principal';
        $clean_customer['number'] = $customer_data['number'] ?? '123';
        $clean_customer['complement'] = $customer_data['complement'] ?? '';
        $clean_customer['neighborhood'] = $customer_data['neighborhood'] ?? 'Centro';
        $clean_customer['city'] = $customer_data['city'] ?? 'São Paulo';
        $clean_customer['state'] = $customer_data['state'] ?? 'SP';
        $clean_customer['zip_code'] = $customer_data['zip_code'] ?? '01234567';
    }

    $payload = [
        "amount" => (int)$BASE_AMOUNT,
        "offer_hash" => $OFFER_HASH,
        "payment_method" => "pix",
        "customer" => $clean_customer,
        "cart" => $cart_items,
        "installments" => 1
    ];
    
    // Adicionar tracking apenas se não estiver vazio
    if (!empty($utms) && is_array($utms)) {
        $payload["tracking"] = $utms;
    }

    if ($PIX_EXPIRATION_MINUTES > 0) {
        $payload["pix_expires_in"] = $PIX_EXPIRATION_MINUTES * 60;
    }

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_VERBOSE, false);
    
    $response = curl_exec($ch); 
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
    $curl_error = curl_error($ch);
    $curl_info = curl_getinfo($ch);
    curl_close($ch);

    // Log de debug - salvar no arquivo de log
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'api_url' => $api_url,
            'payload_sent' => $payload,
            'http_code' => $http_code,
            'api_response' => $response,
            'curl_error' => $curl_error,
            'curl_info' => [
                'url' => $curl_info['url'] ?? null,
                'content_type' => $curl_info['content_type'] ?? null,
                'http_code' => $curl_info['http_code'] ?? null,
                'total_time' => $curl_info['total_time'] ?? null,
                'connect_time' => $curl_info['connect_time'] ?? null,
                'ssl_verify_result' => $curl_info['ssl_verify_result'] ?? null
            ]
        ];
        file_put_contents('debug_payment.log', json_encode($logData, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

    if ($curl_error) { http_response_code(500); echo json_encode(['error' => 'cURL Error: ' . $curl_error]); exit; }
    
    http_response_code($http_code);
    echo $response;
    exit;
}
?>