<?php
/**
 * Payment Gateway Helper Functions
 * 
 * Functions for Stripe and PayPal payment processing.
 */

/**
 * Ensure payment database tables exist
 */
function ensurePaymentTables($pdo) {
    try {
        // Payment transactions
        $pdo->exec("CREATE TABLE IF NOT EXISTS payment_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            invoice_id INT NOT NULL,
            user_id INT NOT NULL,
            gateway ENUM('stripe','paypal','manual') NOT NULL,
            transaction_id VARCHAR(255),
            amount DECIMAL(10,2) NOT NULL,
            currency VARCHAR(10) DEFAULT 'USD',
            status ENUM('pending','completed','failed','refunded','partially_refunded') DEFAULT 'pending',
            gateway_response JSON,
            payment_method VARCHAR(100),
            receipt_url VARCHAR(500),
            metadata JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_invoice (invoice_id),
            INDEX idx_user (user_id),
            INDEX idx_gateway (gateway),
            INDEX idx_status (status),
            INDEX idx_transaction (transaction_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Payment settings
        $pdo->exec("CREATE TABLE IF NOT EXISTS payment_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            gateway VARCHAR(50) NOT NULL,
            setting_key VARCHAR(100) NOT NULL,
            setting_value TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_gateway_key (gateway, setting_key),
            INDEX idx_gateway (gateway)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Refunds
        $pdo->exec("CREATE TABLE IF NOT EXISTS refunds (
            id INT AUTO_INCREMENT PRIMARY KEY,
            transaction_id INT NOT NULL,
            refund_id VARCHAR(255),
            amount DECIMAL(10,2) NOT NULL,
            reason TEXT,
            status ENUM('pending','processed','failed') DEFAULT 'pending',
            processed_by INT DEFAULT NULL,
            gateway_response JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            processed_at DATETIME DEFAULT NULL,
            INDEX idx_transaction (transaction_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        return true;
    } catch (Exception $e) {
        error_log('ensurePaymentTables error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get payment configuration
 */
function getPaymentConfig($pdo = null) {
    $basePath = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__);
    $configFile = $basePath . '/config/payment.php';
    
    if (file_exists($configFile)) {
        return require $configFile;
    }
    
    // Default configuration
    return [
        'stripe' => [
            'enabled' => false,
            'mode' => 'test',
            'test_publishable_key' => '',
            'test_secret_key' => '',
            'live_publishable_key' => '',
            'live_secret_key' => '',
            'webhook_secret' => '',
            'currency' => 'USD',
        ],
        'paypal' => [
            'enabled' => false,
            'mode' => 'sandbox',
            'sandbox_client_id' => '',
            'sandbox_secret' => '',
            'live_client_id' => '',
            'live_secret' => '',
            'currency' => 'USD',
        ],
    ];
}

/**
 * Get active Stripe keys
 */
function getStripeKeys($config) {
    if (empty($config['stripe']['enabled'])) {
        return null;
    }
    
    $mode = $config['stripe']['mode'] ?? 'test';
    
    return [
        'publishable' => $mode === 'live' 
            ? ($config['stripe']['live_publishable_key'] ?? '')
            : ($config['stripe']['test_publishable_key'] ?? ''),
        'secret' => $mode === 'live'
            ? ($config['stripe']['live_secret_key'] ?? '')
            : ($config['stripe']['test_secret_key'] ?? ''),
        'webhook_secret' => $config['stripe']['webhook_secret'] ?? '',
        'currency' => $config['stripe']['currency'] ?? 'USD',
        'mode' => $mode,
    ];
}

/**
 * Get active PayPal credentials
 */
function getPayPalCredentials($config) {
    if (empty($config['paypal']['enabled'])) {
        return null;
    }
    
    $mode = $config['paypal']['mode'] ?? 'sandbox';
    
    return [
        'client_id' => $mode === 'live'
            ? ($config['paypal']['live_client_id'] ?? '')
            : ($config['paypal']['sandbox_client_id'] ?? ''),
        'secret' => $mode === 'live'
            ? ($config['paypal']['live_secret'] ?? '')
            : ($config['paypal']['sandbox_secret'] ?? ''),
        'currency' => $config['paypal']['currency'] ?? 'USD',
        'mode' => $mode,
        'api_url' => $mode === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com',
    ];
}

/**
 * Create Stripe Checkout Session
 */
function createStripeCheckoutSession($secretKey, $invoiceId, $amount, $currency, $successUrl, $cancelUrl, $metadata = []) {
    $url = 'https://api.stripe.com/v1/checkout/sessions';
    
    $data = [
        'payment_method_types' => ['card'],
        'line_items' => [[
            'price_data' => [
                'currency' => strtolower($currency),
                'product_data' => [
                    'name' => 'Invoice Payment #' . $invoiceId,
                ],
                'unit_amount' => (int)($amount * 100), // Convert to cents
            ],
            'quantity' => 1,
        ]],
        'mode' => 'payment',
        'success_url' => $successUrl,
        'cancel_url' => $cancelUrl,
        'metadata' => array_merge(['invoice_id' => $invoiceId], $metadata),
    ];
    
    return stripeApiRequest($url, $secretKey, $data);
}

/**
 * Retrieve Stripe Checkout Session
 */
function retrieveStripeSession($secretKey, $sessionId) {
    $url = 'https://api.stripe.com/v1/checkout/sessions/' . urlencode($sessionId);
    return stripeApiRequest($url, $secretKey, null, 'GET');
}

/**
 * Create Stripe Refund
 */
function createStripeRefund($secretKey, $paymentIntentId, $amount = null, $reason = '') {
    $url = 'https://api.stripe.com/v1/refunds';
    
    $data = [
        'payment_intent' => $paymentIntentId,
    ];
    
    if ($amount !== null) {
        $data['amount'] = (int)($amount * 100);
    }
    
    if ($reason) {
        $data['reason'] = 'requested_by_customer';
        $data['metadata'] = ['reason' => $reason];
    }
    
    return stripeApiRequest($url, $secretKey, $data);
}

/**
 * Make Stripe API request
 */
function stripeApiRequest($url, $secretKey, $data = null, $method = 'POST') {
    $ch = curl_init();
    
    $headers = [
        'Authorization: Bearer ' . $secretKey,
        'Content-Type: application/x-www-form-urlencoded',
    ];
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST' && $data !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $result];
    }
    
    return [
        'success' => false,
        'error' => $result['error']['message'] ?? 'Unknown error',
        'data' => $result
    ];
}

/**
 * Verify Stripe webhook signature
 */
function verifyStripeWebhookSignature($payload, $sigHeader, $webhookSecret) {
    if (empty($webhookSecret)) {
        return ['valid' => false, 'error' => 'Webhook secret not configured'];
    }
    
    $elements = explode(',', $sigHeader);
    $timestamp = null;
    $signatures = [];
    
    foreach ($elements as $element) {
        $parts = explode('=', $element, 2);
        if (count($parts) === 2) {
            if ($parts[0] === 't') {
                $timestamp = $parts[1];
            } elseif ($parts[0] === 'v1') {
                $signatures[] = $parts[1];
            }
        }
    }
    
    if (!$timestamp || empty($signatures)) {
        return ['valid' => false, 'error' => 'Invalid signature header'];
    }
    
    // Check timestamp tolerance (5 minutes)
    if (abs(time() - intval($timestamp)) > 300) {
        return ['valid' => false, 'error' => 'Timestamp too old'];
    }
    
    $signedPayload = $timestamp . '.' . $payload;
    $expectedSignature = hash_hmac('sha256', $signedPayload, $webhookSecret);
    
    foreach ($signatures as $sig) {
        if (hash_equals($expectedSignature, $sig)) {
            return ['valid' => true];
        }
    }
    
    return ['valid' => false, 'error' => 'Signature mismatch'];
}

/**
 * Get PayPal Access Token
 */
function getPayPalAccessToken($clientId, $secret, $apiUrl) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $apiUrl . '/v1/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
    curl_setopt($ch, CURLOPT_USERPWD, $clientId . ':' . $secret);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return ['success' => false, 'error' => $error];
    }
    
    $result = json_decode($response, true);
    
    if (!empty($result['access_token'])) {
        return ['success' => true, 'access_token' => $result['access_token']];
    }
    
    return ['success' => false, 'error' => $result['error_description'] ?? 'Failed to get access token'];
}

/**
 * Create PayPal Order
 */
function createPayPalOrder($credentials, $invoiceId, $amount, $currency, $returnUrl, $cancelUrl) {
    $tokenResult = getPayPalAccessToken($credentials['client_id'], $credentials['secret'], $credentials['api_url']);
    
    if (!$tokenResult['success']) {
        return $tokenResult;
    }
    
    $ch = curl_init();
    
    $data = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => 'invoice_' . $invoiceId,
            'amount' => [
                'currency_code' => strtoupper($currency),
                'value' => number_format($amount, 2, '.', ''),
            ],
            'description' => 'Invoice Payment #' . $invoiceId,
        ]],
        'application_context' => [
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'brand_name' => 'SoftandPix',
            'user_action' => 'PAY_NOW',
        ],
    ];
    
    curl_setopt($ch, CURLOPT_URL, $credentials['api_url'] . '/v2/checkout/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $tokenResult['access_token'],
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 201 && !empty($result['id'])) {
        // Find approval URL
        $approvalUrl = '';
        foreach ($result['links'] ?? [] as $link) {
            if ($link['rel'] === 'approve') {
                $approvalUrl = $link['href'];
                break;
            }
        }
        
        return [
            'success' => true,
            'order_id' => $result['id'],
            'approval_url' => $approvalUrl,
            'data' => $result,
        ];
    }
    
    return ['success' => false, 'error' => $result['message'] ?? 'Failed to create order', 'data' => $result];
}

/**
 * Capture PayPal Order
 */
function capturePayPalOrder($credentials, $orderId) {
    $tokenResult = getPayPalAccessToken($credentials['client_id'], $credentials['secret'], $credentials['api_url']);
    
    if (!$tokenResult['success']) {
        return $tokenResult;
    }
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $credentials['api_url'] . '/v2/checkout/orders/' . $orderId . '/capture');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, '');
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $tokenResult['access_token'],
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 201 && $result['status'] === 'COMPLETED') {
        $captureId = '';
        if (!empty($result['purchase_units'][0]['payments']['captures'][0]['id'])) {
            $captureId = $result['purchase_units'][0]['payments']['captures'][0]['id'];
        }
        
        return [
            'success' => true,
            'capture_id' => $captureId,
            'data' => $result,
        ];
    }
    
    return ['success' => false, 'error' => $result['message'] ?? 'Failed to capture order', 'data' => $result];
}

/**
 * Create PayPal Refund
 */
function createPayPalRefund($credentials, $captureId, $amount = null, $currency = 'USD') {
    $tokenResult = getPayPalAccessToken($credentials['client_id'], $credentials['secret'], $credentials['api_url']);
    
    if (!$tokenResult['success']) {
        return $tokenResult;
    }
    
    $ch = curl_init();
    
    $data = [];
    if ($amount !== null) {
        $data['amount'] = [
            'value' => number_format($amount, 2, '.', ''),
            'currency_code' => strtoupper($currency),
        ];
    }
    
    curl_setopt($ch, CURLOPT_URL, $credentials['api_url'] . '/v2/payments/captures/' . $captureId . '/refund');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $tokenResult['access_token'],
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    
    if ($httpCode === 201 && $result['status'] === 'COMPLETED') {
        return [
            'success' => true,
            'refund_id' => $result['id'],
            'data' => $result,
        ];
    }
    
    return ['success' => false, 'error' => $result['message'] ?? 'Failed to process refund', 'data' => $result];
}

/**
 * Record a payment transaction
 */
function recordPaymentTransaction($pdo, $invoiceId, $userId, $gateway, $transactionId, $amount, $currency, $status, $gatewayResponse = []) {
    try {
        $stmt = $pdo->prepare("INSERT INTO payment_transactions 
            (invoice_id, user_id, gateway, transaction_id, amount, currency, status, gateway_response) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $invoiceId,
            $userId,
            $gateway,
            $transactionId,
            $amount,
            $currency,
            $status,
            json_encode($gatewayResponse)
        ]);
        return (int)$pdo->lastInsertId();
    } catch (Exception $e) {
        error_log('recordPaymentTransaction error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Update invoice payment status
 */
function updateInvoicePaymentStatus($pdo, $invoiceId, $status = 'paid', $paidAmount = null) {
    try {
        $updates = ["status = ?", "paid_at = NOW()"];
        $params = [$status];
        
        if ($paidAmount !== null) {
            $updates[] = "amount_paid = ?";
            $params[] = $paidAmount;
        }
        
        $params[] = $invoiceId;
        $sql = "UPDATE invoices SET " . implode(', ', $updates) . " WHERE id = ?";
        $pdo->prepare($sql)->execute($params);
        return true;
    } catch (Exception $e) {
        error_log('updateInvoicePaymentStatus error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Get payment transaction by ID
 */
function getPaymentTransaction($pdo, $transactionId) {
    $stmt = $pdo->prepare("SELECT pt.*, i.invoice_number, u.name as user_name, u.email as user_email 
        FROM payment_transactions pt
        LEFT JOIN invoices i ON i.id = pt.invoice_id
        LEFT JOIN users u ON u.id = pt.user_id
        WHERE pt.id = ?");
    $stmt->execute([$transactionId]);
    return $stmt->fetch();
}

/**
 * Get payment history
 */
function getPaymentHistory($pdo, $userId = null, $isAdmin = false, $limit = 50, $offset = 0) {
    $sql = "SELECT pt.*, i.invoice_number, u.name as user_name, u.email as user_email 
            FROM payment_transactions pt
            LEFT JOIN invoices i ON i.id = pt.invoice_id
            LEFT JOIN users u ON u.id = pt.user_id";
    $params = [];
    
    if (!$isAdmin && $userId) {
        $sql .= " WHERE pt.user_id = ?";
        $params[] = $userId;
    }
    
    $sql .= " ORDER BY pt.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Get payment status badge class
 */
function getPaymentTransactionStatusBadge($status) {
    $badges = [
        'pending'            => 'warning',
        'completed'          => 'success',
        'failed'             => 'danger',
        'refunded'           => 'info',
        'partially_refunded' => 'secondary',
    ];
    return $badges[$status] ?? 'secondary';
}

/**
 * Format currency amount
 */
function formatPaymentAmount($amount, $currency = 'USD') {
    $symbols = [
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'CAD' => 'C$',
        'AUD' => 'A$',
    ];
    $symbol = $symbols[strtoupper($currency)] ?? $currency . ' ';
    return $symbol . number_format((float)$amount, 2);
}

/**
 * Send payment receipt email
 */
function sendPaymentReceiptEmail($pdo, $transaction, $invoice, $siteUrl) {
    $stmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
    $stmt->execute([$transaction['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) return false;
    
    $subject = "Payment Receipt - Invoice #{$invoice['invoice_number']}";
    $amount = formatPaymentAmount($transaction['amount'], $transaction['currency']);
    
    $body = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #198754; color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 8px 8px; }
            .amount { font-size: 28px; font-weight: bold; color: #198754; }
            .details { background: white; padding: 15px; border-radius: 8px; margin: 15px 0; }
            .footer { text-align: center; color: #666; font-size: 12px; margin-top: 20px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>✓ Payment Successful</h2>
            </div>
            <div class='content'>
                <p>Hello {$user['name']},</p>
                <p>Thank you for your payment! Here are the details:</p>
                
                <div class='details'>
                    <p><strong>Invoice:</strong> #{$invoice['invoice_number']}</p>
                    <p class='amount'>{$amount}</p>
                    <p><strong>Payment Method:</strong> " . ucfirst($transaction['gateway']) . "</p>
                    <p><strong>Transaction ID:</strong> {$transaction['transaction_id']}</p>
                    <p><strong>Date:</strong> " . date('F j, Y \a\t g:i A', strtotime($transaction['created_at'])) . "</p>
                </div>
                
                <p>You can view your invoice and payment history in your dashboard.</p>
            </div>
            <div class='footer'>
                <p>This is an automated receipt from SoftandPix.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return send_email($user['email'], $user['name'], $subject, $body);
}
