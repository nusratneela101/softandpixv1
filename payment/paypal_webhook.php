<?php
/**
 * PayPal Webhook Handler
 *
 * Receives IPN-style POST from PayPal and processes payment events.
 * Register this URL in your PayPal dashboard:
 *   https://yourdomain.com/payment/paypal_webhook.php
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/includes/payment_helper.php';

$payload = file_get_contents('php://input');
$event   = json_decode($payload, true);

if (!$event || !isset($event['event_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

// Load settings
$settings = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
    foreach ($rows as $r) { $settings[$r['setting_key']] = $r['setting_value']; }
} catch (Exception $e) {}

ensurePaymentTables($pdo);

$eventType = $event['event_type'];
$resource  = $event['resource'] ?? [];

try {
    switch ($eventType) {
        // ── Checkout order approved / completed ───────────────────
        case 'CHECKOUT.ORDER.APPROVED':
        case 'PAYMENT.CAPTURE.COMPLETED':
            $orderId    = $resource['id'] ?? '';
            $amount     = (float)($resource['amount']['value'] ?? $resource['purchase_units'][0]['amount']['value'] ?? 0);
            $currency   = strtoupper($resource['amount']['currency_code'] ?? $resource['purchase_units'][0]['amount']['currency_code'] ?? 'USD');
            $customId   = $resource['purchase_units'][0]['custom_id'] ?? '';   // invoice_id:user_id
            $invoiceId  = 0;
            $userId     = 0;
            if ($customId && strpos($customId, ':') !== false) {
                [$invoiceId, $userId] = array_map('intval', explode(':', $customId, 2));
            }

            // Upsert transaction
            $stmt = $pdo->prepare(
                "SELECT id FROM payment_transactions WHERE transaction_id = ? LIMIT 1"
            );
            $stmt->execute([$orderId]);
            $existing = $stmt->fetchColumn();

            if ($existing) {
                $pdo->prepare(
                    "UPDATE payment_transactions SET status = 'completed',
                     gateway_response = ?, updated_at = NOW() WHERE id = ?"
                )->execute([json_encode($resource), $existing]);
            } else {
                $pdo->prepare(
                    "INSERT INTO payment_transactions
                     (invoice_id, user_id, gateway, transaction_id, amount, currency, status, gateway_response)
                     VALUES (?, ?, 'paypal', ?, ?, ?, 'completed', ?)"
                )->execute([$invoiceId, $userId, $orderId, $amount, $currency, json_encode($resource)]);
            }

            if ($invoiceId > 0) {
                $pdo->prepare("UPDATE invoices SET status = 'paid' WHERE id = ?")
                    ->execute([$invoiceId]);
            }
            break;

        // ── Payment denied / failed ────────────────────────────────
        case 'PAYMENT.CAPTURE.DENIED':
        case 'PAYMENT.CAPTURE.DECLINED':
            $captureId = $resource['id'] ?? '';
            $pdo->prepare(
                "UPDATE payment_transactions SET status = 'failed',
                 gateway_response = ?, updated_at = NOW() WHERE transaction_id = ?"
            )->execute([json_encode($resource), $captureId]);
            break;

        // ── Refund ─────────────────────────────────────────────────
        case 'PAYMENT.CAPTURE.REFUNDED':
            $refundId     = $resource['id'] ?? '';
            $captureId    = $resource['links'][0]['href'] ?? '';   // parent capture link
            $refundAmount = (float)($resource['amount']['value'] ?? 0);

            $stmt = $pdo->prepare(
                "SELECT id FROM payment_transactions WHERE transaction_id = ? LIMIT 1"
            );
            $stmt->execute([$resource['custom_id'] ?? $captureId]);
            $txId = $stmt->fetchColumn();

            if ($txId) {
                $pdo->prepare(
                    "INSERT IGNORE INTO refunds (transaction_id, refund_id, amount, status, gateway_response)
                     VALUES (?, ?, ?, 'processed', ?)"
                )->execute([$txId, $refundId, $refundAmount, json_encode($resource)]);
                $pdo->prepare(
                    "UPDATE payment_transactions SET status = 'refunded', updated_at = NOW() WHERE id = ?"
                )->execute([$txId]);
            }
            break;

        default:
            // Unhandled — acknowledge silently
            break;
    }
} catch (Exception $e) {
    error_log('PayPal webhook error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal error']);
    exit;
}

http_response_code(200);
echo json_encode(['received' => true, 'event_type' => $eventType]);
