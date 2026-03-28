<?php
/**
 * Stripe cURL helper functions (no SDK required)
 */

function stripeRequest($endpoint, $method = 'GET', $data = [], $pdo = null) {
    $db = $pdo ?? $GLOBALS['pdo'] ?? null;
    $secretKey = $db ? getSetting($db, 'stripe_secret_key') : '';
    if (empty($secretKey)) return ['error' => ['message' => 'Stripe secret key not configured']];

    $url = 'https://api.stripe.com/v1/' . ltrim($endpoint, '/');
    $ch  = curl_init();
    $opts = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD        => $secretKey . ':',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ];

    if ($method === 'POST') {
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($data);
    } elseif ($method === 'DELETE') {
        $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
    } elseif ($method === 'GET' && !empty($data)) {
        $opts[CURLOPT_URL] = $url . '?' . http_build_query($data);
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) return ['error' => ['message' => 'cURL error: ' . $curlError]];

    $decoded = json_decode($response, true);
    return $decoded ?? ['error' => ['message' => 'Invalid response from Stripe']];
}

function createStripeCheckoutSession($plan, $user, $pdo) {
    $successUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST'] . '/payment/subscription_success.php?session_id={CHECKOUT_SESSION_ID}';
    $cancelUrl  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
        . '://' . $_SERVER['HTTP_HOST'] . '/payment/subscription_cancel.php?plan_id=' . $plan['id'];

    $data = [
        'mode'                                 => 'subscription',
        'success_url'                          => $successUrl,
        'cancel_url'                           => $cancelUrl,
        'customer_email'                       => $user['email'],
        'metadata[user_id]'                    => $user['id'],
        'metadata[plan_id]'                    => $plan['id'],
        'line_items[0][price]'                 => $plan['stripe_price_id'],
        'line_items[0][quantity]'              => 1,
    ];

    return stripeRequest('checkout/sessions', 'POST', $data, $pdo);
}

function cancelStripeSubscription($subscriptionId, $pdo) {
    return stripeRequest('subscriptions/' . $subscriptionId, 'DELETE', [], $pdo);
}

function getStripeSubscription($subscriptionId, $pdo) {
    return stripeRequest('subscriptions/' . $subscriptionId, 'GET', [], $pdo);
}

function createStripeProduct($name, $description, $pdo) {
    return stripeRequest('products', 'POST', [
        'name'        => $name,
        'description' => $description,
    ], $pdo);
}

function createStripePrice($productId, $amount, $currency, $billingCycle, $pdo) {
    $interval = 'month';
    $intervalCount = 1;
    if ($billingCycle === 'yearly') {
        $interval = 'year';
    } elseif ($billingCycle === 'quarterly') {
        $interval      = 'month';
        $intervalCount = 3;
    }
    return stripeRequest('prices', 'POST', [
        'product'            => $productId,
        'unit_amount'        => (int)round($amount * 100),
        'currency'           => strtolower($currency),
        'recurring[interval]'       => $interval,
        'recurring[interval_count]' => $intervalCount,
    ], $pdo);
}
