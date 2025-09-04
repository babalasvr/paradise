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

// Handle geolocation requests
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    // Fetch location data from wtfismyip.com
    $location_url = 'https://wtfismyip.com/json';
    $ch_location = curl_init($location_url);
    curl_setopt_array($ch_location, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    
    $response_location = curl_exec($ch_location);
    $http_code_location = curl_getinfo($ch_location, CURLINFO_HTTP_CODE);
    curl_close($ch_location);
    
    if ($http_code_location >= 200 && $http_code_location < 300) {
        echo $response_location;
    } else {
        // Fallback data for São Paulo
        echo json_encode([
            'YourFuckingLocation' => 'São Paulo, SP',
            'YourFuckingIPAddress' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'YourFuckingISP' => 'Local ISP',
            'YourFuckingTorExit' => false,
            'YourFuckingCountryCode' => 'BR'
        ]);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $api_url = 'https://api.paradisepagbr.com/api/public/v1/transactions?api_token=' . $API_TOKEN;
    $raw_input = file_get_contents('php://input');
    
    // Debug: Log raw input
    error_log('Raw input received: ' . $raw_input);
    
    $data = json_decode($raw_input, true);
    
    // Debug: Log decoded data
    error_log('Decoded data: ' . print_r($data, true));
    
    // Check if JSON decoding failed
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON decode error: ' . json_last_error_msg());
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON: ' . json_last_error_msg()]);
        exit;
    }
    
    $customer_data = $data['customer'] ?? [];
    $utms = $data['utms'] ?? [];
    
    // Validate required data structure
    if (!is_array($customer_data)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid customer data format']);
        exit;
    }
    
    // Initialize variables to avoid PHP notices
    $cpf = null;

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
        
        $normalize = fn($str) => preg_replace('/[^\w]/', '', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str) ?? ''));
        
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

    $payload = [
        "amount" => round($BASE_AMOUNT),
        "offer_hash" => $OFFER_HASH,
        "payment_method" => "pix",
        "customer" => $customer_data,
        "cart" => $cart_items,
        "installments" => 1,
        "tracking" => $utms
    ];

    if ($PIX_EXPIRATION_MINUTES > 0) {
        $payload["pix_expires_in"] = $PIX_EXPIRATION_MINUTES * 60;
    }

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    // Initialize variables to avoid PHP notices
    $httpCode = $http_code;
    $error = $curl_error;
    
    if ($curl_error) {
        http_response_code(500);
        echo json_encode(['error' => 'cURL Error: ' . $curl_error]);
        exit;
    }
    
    http_response_code($http_code);
    echo $response;
    exit;
}
?>