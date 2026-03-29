<?php
/**
 * Payment Processing - Redirects to appropriate gateway
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { redirect(BASE_URL); }
verify_csrf_token($_POST['csrf_token'] ?? '');

$invoice_id = (int)$_POST['invoice_id'];
$gateway = $_POST['gateway'] ?? '';

$invoice = $pdo->prepare("SELECT * FROM invoices WHERE id=? AND client_id=? AND status='pending'");
$invoice->execute([$invoice_id, $_SESSION['user_id']]);
$invoice = $invoice->fetch();

if (!$invoice) { set_flash('error', 'Invoice not found or already paid.'); redirect(BASE_URL . '/client/invoices.php'); }

$gw = $pdo->prepare("SELECT * FROM payment_gateways WHERE gateway_name=? AND is_active=1");
$gw->execute([$gateway]); $gw = $gw->fetch();

if (!$gw) { set_flash('error', 'Payment gateway not available.'); redirect(BASE_URL . '/invoice/view.php?id=' . $invoice_id); }

// Create pending payment record
$pdo->prepare("INSERT INTO payments (invoice_id, client_id, amount, gateway, status) VALUES (?,?,?,?,'pending')")
    ->execute([$invoice_id, $_SESSION['user_id'], $invoice['total'], $gateway]);
$payment_id = $pdo->lastInsertId();

// Redirect to gateway processor
redirect(BASE_URL . '/payment/gateways/' . $gateway . '.php?payment_id=' . $payment_id);
