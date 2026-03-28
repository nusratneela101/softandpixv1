<?php
/**
 * PayPal REST API cURL helper functions (no SDK required)
 */

function getPayPalAccessToken($pdo) {
    $clientId     = getSetting($pdo, 'paypal_client_id');
    $clientSecret = getSetting($pdo, 'paypal_client_secret');
    if (empty($clientId) || empty($clientSecret)) return null;

    $sandbox = getSetting($pdo, 'paypal_sandbox', '1');
    $baseUrl  = $sandbox === '0' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';

    $ch = curl_init($baseUrl . '/v1/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_USERPWD        => $clientId . ':' . $clientSecret,
        CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Accept: application/json', 'Accept-Language: en_US'],
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function paypalRequest($endpoint, $method = 'GET', $payload = null, $pdo = null) {
    $db       = $pdo ?? $GLOBALS['pdo'] ?? null;
    $sandbox  = getSetting($db, 'paypal_sandbox', '1');
    $baseUrl  = $sandbox === '0' ? 'https://api-m.paypal.com' : 'https://api-m.sandbox.paypal.com';
    $token    = getPayPalAccessToken($db);
    if (!$token) return ['error' => 'Could not get PayPal access token'];

    $url = $baseUrl . '/' . ltrim($endpoint, '/');
    $ch  = curl_init();
    $headers = [
        'Authorization: Bearer ' . $token,
        'Content-Type: application/json',
        'Accept: application/json',
    ];

    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => $headers,
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = $payload ? json_encode($payload) : '{}';
    } elseif ($method === 'PATCH') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'PATCH';
        $opts[CURLOPT_POSTFIELDS]    = $payload ? json_encode($payload) : '{}';
    } elseif ($method === 'DELETE') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr) return ['error' => 'cURL error: ' . $curlErr];
    if (empty($response)) return ['http_code' => $httpCode];

    $decoded = json_decode($response, true);
    return $decoded ?? ['error' => 'Invalid PayPal response'];
}

function createPayPalSubscription($plan, $user, $pdo) {
    if (empty($plan['paypal_plan_id'])) return ['error' => 'PayPal plan ID not configured for this plan'];

    $protocol   = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host       = $_SERVER['HTTP_HOST'];
    $successUrl = $protocol . '://' . $host . '/payment/subscription_success.php';
    $cancelUrl  = $protocol . '://' . $host . '/payment/subscription_cancel.php?plan_id=' . $plan['id'];

    $payload = [
        'plan_id'    => $plan['paypal_plan_id'],
        'subscriber' => [
            'name'          => ['given_name' => $user['name']],
            'email_address' => $user['email'],
        ],
        'application_context' => [
            'return_url'          => $successUrl,
            'cancel_url'          => $cancelUrl,
            'brand_name'          => 'Softandpix',
            'locale'              => 'en-US',
            'shipping_preference' => 'NO_SHIPPING',
            'user_action'         => 'SUBSCRIBE_NOW',
        ],
        'custom_id' => 'user_' . $user['id'] . '_plan_' . $plan['id'],
    ];

    return paypalRequest('v1/billing/subscriptions', 'POST', $payload, $pdo);
}

function cancelPayPalSubscription($subscriptionId, $reason, $pdo) {
    $payload = ['reason' => $reason ?: 'Cancelled by user'];
    return paypalRequest('v1/billing/subscriptions/' . $subscriptionId . '/cancel', 'POST', $payload, $pdo);
}

function getPayPalSubscription($subscriptionId, $pdo) {
    return paypalRequest('v1/billing/subscriptions/' . $subscriptionId, 'GET', null, $pdo);
}
