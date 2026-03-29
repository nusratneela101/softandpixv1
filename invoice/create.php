<?php
/**
 * Create Invoice
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_role('admin');
header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) $input = $_POST;

$client_id = (int)($input['client_id'] ?? 0);
$project_id = (int)($input['project_id'] ?? 0) ?: null;
$items = $input['items'] ?? [];
$tax_percent = (float)($input['tax_percent'] ?? 0);
$discount = (float)($input['discount'] ?? 0);
$due_date = $input['due_date'] ?? null;
$notes = trim($input['notes'] ?? '');

if (!$client_id || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Client and items required']);
    exit;
}

$subtotal = 0;
foreach ($items as $item) {
    $subtotal += ((float)$item['quantity']) * ((float)$item['rate']);
}

$tax_amount = $subtotal * ($tax_percent / 100);
$total = $subtotal + $tax_amount - $discount;

$invoice_number = 'INV-' . date('Y') . '-' . str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);

$stmt = $pdo->prepare("INSERT INTO invoices (invoice_number, client_id, project_id, subtotal, tax_percent, tax_amount, discount, total, due_date, notes, sent_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())");
$stmt->execute([$invoice_number, $client_id, $project_id, $subtotal, $tax_percent, $tax_amount, $discount, $total, $due_date, $notes]);
$invoice_id = $pdo->lastInsertId();

foreach ($items as $item) {
    $amount = ((float)$item['quantity']) * ((float)$item['rate']);
    $pdo->prepare("INSERT INTO invoice_items (invoice_id, description, quantity, rate, amount) VALUES (?,?,?,?,?)")
        ->execute([$invoice_id, $item['description'] ?? '', (int)$item['quantity'], (float)$item['rate'], $amount]);
}

// Send invoice email via info@ SMTP
$client = get_user($pdo, $client_id);
if ($client) {
    $email_body = '<h2>Invoice ' . htmlspecialchars($invoice_number) . '</h2>';
    $email_body .= '<p>Total: <strong>$' . number_format($total, 2) . '</strong></p>';
    $email_body .= '<p>Due Date: ' . ($due_date ? date('M j, Y', strtotime($due_date)) : 'Upon receipt') . '</p>';
    $email_body .= '<p><a href="' . BASE_URL . '/invoice/view.php?id=' . $invoice_id . '">View Invoice</a></p>';
    send_email($client['email'], $client['name'], 'Invoice ' . $invoice_number . ' from SoftandPix', $email_body, 'info');
    
    // Create notification
    create_notification($pdo, $client_id, 'invoice', 'New Invoice', 'Invoice ' . $invoice_number . ' - $' . number_format($total, 2), '/invoice/view.php?id=' . $invoice_id);
    
    // Save to email system
    $pdo->prepare("INSERT INTO emails (from_user_id, from_email, to_user_id, to_email, subject, body, smtp_account, folder, sent_via_smtp) VALUES (?,?,?,?,?,?,'info','sent',1)")
        ->execute([$_SESSION['user_id'], 'info@softandpix.com', $client_id, $client['email'], 'Invoice ' . $invoice_number, $email_body]);
    $pdo->prepare("INSERT INTO emails (from_user_id, from_email, to_user_id, to_email, subject, body, smtp_account, folder) VALUES (?,?,?,?,?,?,'info','inbox')")
        ->execute([$_SESSION['user_id'], 'info@softandpix.com', $client_id, $client['email'], 'Invoice ' . $invoice_number, $email_body]);
}

echo json_encode(['success' => true, 'invoice_id' => $invoice_id, 'invoice_number' => $invoice_number]);
