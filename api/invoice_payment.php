<?php
/**
 * API: Record Invoice Payment
 * POST /api/invoice_payment.php
 * Body: csrf_token, invoice_id, amount, method, transaction_id (optional), notes (optional)
 *
 * Auth: Admin session required.
 * Returns JSON: {success: bool, message: string, new_status: string, amount_paid: float, amount_due: float}
 */

session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

header('Content-Type: application/json');

// Only admins may record payments via this API
if (empty($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// CSRF check
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
    exit;
}

$invoiceId     = (int)($_POST['invoice_id'] ?? 0);
$amount        = (float)($_POST['amount'] ?? 0);
$method        = trim($_POST['method'] ?? 'manual');
$transactionId = trim($_POST['transaction_id'] ?? '');
$notes         = trim($_POST['notes'] ?? '');

if (!$invoiceId || $amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invoice ID and a positive amount are required.']);
    exit;
}

$allowedMethods = ['manual', 'cash', 'bank_transfer', 'paypal', 'stripe', 'square', 'other'];
if (!in_array($method, $allowedMethods)) {
    $method = 'manual';
}

try {
    // Fetch invoice
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id=?");
    $stmt->execute([$invoiceId]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        echo json_encode(['success' => false, 'message' => 'Invoice not found.']);
        exit;
    }

    if (in_array($invoice['status'], ['cancelled'])) {
        echo json_encode(['success' => false, 'message' => 'Cannot record payment on a cancelled invoice.']);
        exit;
    }

    $total      = (float)$invoice['total'];
    $amountPaid = (float)($invoice['amount_paid'] ?? 0);
    $newPaid    = $amountPaid + $amount;

    // Insert payment record
    $pdo->prepare("INSERT INTO invoice_payments (invoice_id, amount, method, transaction_id, status, notes, paid_by) VALUES (?,?,?,?,'completed',?,?)")
        ->execute([$invoiceId, $amount, $method, $transactionId ?: null, $notes ?: null, $_SESSION['admin_id']]);

    // Determine new invoice status
    if ($newPaid >= $total) {
        $newStatus = 'paid';
        $paidAt    = date('Y-m-d H:i:s');
        $pdo->prepare("UPDATE invoices SET amount_paid=?, status='paid', paid_at=?, updated_at=NOW() WHERE id=?")
            ->execute([$newPaid, $paidAt, $invoiceId]);
    } elseif ($newPaid > 0) {
        $newStatus = 'partial';
        $pdo->prepare("UPDATE invoices SET amount_paid=?, status='partial', updated_at=NOW() WHERE id=?")
            ->execute([$newPaid, $invoiceId]);
    } else {
        $newStatus = $invoice['status'];
        $pdo->prepare("UPDATE invoices SET amount_paid=?, updated_at=NOW() WHERE id=?")
            ->execute([$newPaid, $invoiceId]);
    }

    $amountDue = max(0, $total - $newPaid);

    echo json_encode([
        'success'     => true,
        'message'     => 'Payment of ' . ($invoice['currency'] ?? 'USD') . ' ' . number_format($amount, 2) . ' recorded successfully.',
        'new_status'  => $newStatus,
        'amount_paid' => round($newPaid, 2),
        'amount_due'  => round($amountDue, 2),
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error. Please try again.']);
}
