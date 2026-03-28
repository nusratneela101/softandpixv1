<?php
/**
 * Invoice PDF / Print View
 * Usage: /api/invoice_pdf.php?id=<invoice_id>
 * Generates a print-friendly HTML page that users can save as PDF via browser print dialog.
 */

session_start();
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    http_response_code(400);
    die('Invoice ID required.');
}

// Auth: admin or owning client
$isAdmin  = !empty($_SESSION['admin_id']);
$clientId = (int)($_SESSION['user_id'] ?? 0);

if (!$isAdmin && !$clientId) {
    http_response_code(403);
    die('Access denied.');
}

try {
    $stmt = $pdo->prepare("
        SELECT i.*,
               u.name  AS client_name,
               u.email AS client_email,
               u.phone AS client_phone,
               p.title AS project_title
        FROM invoices i
        LEFT JOIN users u ON u.id = i.client_id
        LEFT JOIN projects p ON p.id = i.project_id
        WHERE i.id = ?
    ");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
} catch (Exception $e) {
    $invoice = null;
}

if (!$invoice) {
    http_response_code(404);
    die('Invoice not found.');
}

// Access control: client can only view their own invoice
if (!$isAdmin && $invoice['client_id'] != $clientId) {
    http_response_code(403);
    die('Access denied.');
}

// Load line items
try {
    $ist = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order ASC, id ASC");
    $ist->execute([$id]);
    $items = $ist->fetchAll();
} catch (Exception $e) { $items = []; }

// Load payments
try {
    $ps = $pdo->prepare("SELECT * FROM invoice_payments WHERE invoice_id=? ORDER BY created_at ASC");
    $ps->execute([$id]);
    $payments = $ps->fetchAll();
} catch (Exception $e) { $payments = []; }

// Company settings
$siteName    = getSetting($pdo, 'site_name', 'Softandpix');
$siteEmail   = getSetting($pdo, 'site_email', 'support@softandpix.com');
$sitePhone   = getSetting($pdo, 'site_phone', '');
$siteAddress = getSetting($pdo, 'site_address', '');
$logoUrl     = getSetting($pdo, 'logo_url', '/assets/img/SoftandPix -LOGO.png');

$amountPaid = (float)($invoice['amount_paid'] ?? array_sum(array_column($payments, 'amount')));
$amountDue  = max(0, (float)$invoice['total'] - $amountPaid);
$currency   = h($invoice['currency'] ?? 'USD');

$statusColors = [
    'draft'     => '#6c757d',
    'sent'      => '#0dcaf0',
    'paid'      => '#198754',
    'overdue'   => '#dc3545',
    'partial'   => '#fd7e14',
    'cancelled' => '#343a40',
];
$statusColor = $statusColors[$invoice['status']] ?? '#6c757d';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Invoice <?php echo h($invoice['invoice_number']); ?> — <?php echo h($siteName); ?></title>
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: 'Segoe UI', Arial, sans-serif;
    font-size: 14px;
    color: #333;
    background: #f0f2f5;
    padding: 20px;
}
.invoice-wrapper {
    max-width: 800px;
    margin: 0 auto;
    background: #fff;
    padding: 48px 56px;
    box-shadow: 0 4px 24px rgba(0,0,0,.12);
    border-radius: 8px;
}
.print-btn {
    display: block;
    width: 200px;
    margin: 0 auto 24px;
    padding: 12px 24px;
    background: #0d6efd;
    color: #fff;
    border: none;
    border-radius: 6px;
    font-size: 15px;
    cursor: pointer;
    text-align: center;
}
.print-btn:hover { background: #0b5ed7; }
.invoice-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 36px; }
.company-name { font-size: 22px; font-weight: 700; color: #1e3a5f; }
.company-info { font-size: 12px; color: #666; line-height: 1.6; margin-top: 6px; }
.invoice-title { text-align: right; }
.invoice-title h1 { font-size: 32px; font-weight: 800; color: #0d6efd; letter-spacing: 2px; }
.invoice-number { font-size: 15px; font-weight: 600; color: #555; }
.status-badge {
    display: inline-block;
    padding: 3px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    color: #fff;
    background: <?php echo $statusColor; ?>;
    text-transform: uppercase;
    margin-top: 6px;
}
.meta-section { display: flex; justify-content: space-between; margin-bottom: 32px; gap: 24px; }
.bill-to h3 { font-size: 11px; text-transform: uppercase; color: #999; letter-spacing: 1px; margin-bottom: 8px; }
.bill-to .name { font-size: 16px; font-weight: 700; }
.bill-to .info { font-size: 13px; color: #666; line-height: 1.6; }
.dates-table { min-width: 240px; }
.dates-table td { padding: 3px 0 3px 12px; font-size: 13px; }
.dates-table td:first-child { color: #999; padding-left: 0; }
.dates-table td:last-child { font-weight: 600; text-align: right; }
table.items {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 24px;
}
table.items thead th {
    background: #1e3a5f;
    color: #fff;
    padding: 10px 14px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: .5px;
}
table.items tbody td {
    border-bottom: 1px solid #eee;
    padding: 10px 14px;
    font-size: 13px;
    vertical-align: top;
}
table.items tbody tr:last-child td { border-bottom: none; }
table.items .text-right { text-align: right; }
table.items .text-center { text-align: center; }
.totals-section { display: flex; justify-content: flex-end; margin-bottom: 32px; }
table.totals { min-width: 280px; border-collapse: collapse; }
table.totals td { padding: 6px 12px; font-size: 13px; }
table.totals td:first-child { color: #666; }
table.totals td:last-child { text-align: right; font-weight: 600; }
table.totals .total-row td { border-top: 2px solid #1e3a5f; font-size: 16px; font-weight: 800; color: #1e3a5f; padding-top: 10px; }
table.totals .paid-row td { color: #198754; }
table.totals .due-row td { color: #dc3545; font-size: 15px; font-weight: 800; }
.notes-section { border-top: 1px solid #eee; padding-top: 20px; margin-bottom: 24px; }
.notes-section h3 { font-size: 12px; text-transform: uppercase; color: #999; letter-spacing: 1px; margin-bottom: 8px; }
.notes-section p { font-size: 13px; color: #555; line-height: 1.6; }
.payment-history { border-top: 1px solid #eee; padding-top: 20px; margin-bottom: 24px; }
.payment-history h3 { font-size: 12px; text-transform: uppercase; color: #999; letter-spacing: 1px; margin-bottom: 8px; }
table.pay-hist { width: 100%; border-collapse: collapse; }
table.pay-hist th { background: #f8f9fa; padding: 7px 10px; font-size: 11px; text-align: left; border-bottom: 1px solid #dee2e6; }
table.pay-hist td { padding: 7px 10px; font-size: 12px; border-bottom: 1px solid #f0f0f0; }
.footer-note { text-align: center; font-size: 12px; color: #aaa; border-top: 1px solid #eee; padding-top: 16px; }

@media print {
    body { background: #fff; padding: 0; }
    .print-btn { display: none !important; }
    .invoice-wrapper { box-shadow: none; border-radius: 0; padding: 24px; margin: 0; max-width: 100%; }
    table.items thead th {
        background: #1e3a5f !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .status-badge {
        background: <?php echo $statusColor; ?> !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
}
</style>
</head>
<body>
<button class="print-btn" onclick="window.print()">🖨️ Print / Save as PDF</button>

<div class="invoice-wrapper">
    <!-- Header -->
    <div class="invoice-header">
        <div>
            <?php if ($logoUrl): ?>
            <img src="<?php echo h($logoUrl); ?>" alt="<?php echo h($siteName); ?>" style="max-height:50px;max-width:180px;margin-bottom:10px;display:block;">
            <?php endif; ?>
            <div class="company-name"><?php echo h($siteName); ?></div>
            <div class="company-info">
                <?php if ($siteAddress): echo nl2br(h($siteAddress)) . '<br>'; endif; ?>
                <?php if ($siteEmail): echo h($siteEmail) . '<br>'; endif; ?>
                <?php if ($sitePhone): echo h($sitePhone); endif; ?>
            </div>
        </div>
        <div class="invoice-title">
            <h1>INVOICE</h1>
            <div class="invoice-number"><?php echo h($invoice['invoice_number']); ?></div>
            <div class="status-badge"><?php echo ucfirst(h($invoice['status'])); ?></div>
        </div>
    </div>

    <!-- Bill To + Dates -->
    <div class="meta-section">
        <div class="bill-to">
            <h3>Bill To</h3>
            <div class="name"><?php echo h($invoice['client_name'] ?? '—'); ?></div>
            <div class="info">
                <?php if ($invoice['client_email']): echo h($invoice['client_email']) . '<br>'; endif; ?>
                <?php if ($invoice['client_phone']): echo h($invoice['client_phone']) . '<br>'; endif; ?>
                <?php if ($invoice['project_title']): ?>
                <span style="color:#999;">Project:</span> <?php echo h($invoice['project_title']); ?>
                <?php endif; ?>
            </div>
        </div>
        <table class="dates-table">
            <tr>
                <td>Invoice #</td>
                <td><?php echo h($invoice['invoice_number']); ?></td>
            </tr>
            <tr>
                <td>Issue Date</td>
                <td><?php echo $invoice['issue_date'] ? date('F j, Y', strtotime($invoice['issue_date'])) : '—'; ?></td>
            </tr>
            <tr>
                <td>Due Date</td>
                <td style="color:<?php echo ($invoice['status'] === 'overdue') ? '#dc3545' : 'inherit'; ?>">
                    <?php echo $invoice['due_date'] ? date('F j, Y', strtotime($invoice['due_date'])) : '—'; ?>
                </td>
            </tr>
            <tr>
                <td>Currency</td>
                <td><?php echo $currency; ?></td>
            </tr>
        </table>
    </div>

    <!-- Line Items -->
    <table class="items">
        <thead>
            <tr>
                <th style="width:40px;">#</th>
                <th>Description</th>
                <th class="text-center" style="width:70px;">Qty</th>
                <th class="text-right" style="width:110px;">Unit Price</th>
                <th class="text-right" style="width:110px;">Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php if (empty($items)): ?>
            <tr><td colspan="5" style="text-align:center;color:#999;padding:20px;">Services rendered</td></tr>
        <?php else: ?>
            <?php foreach ($items as $i => $item): ?>
            <tr>
                <td style="color:#999;"><?php echo $i + 1; ?></td>
                <td><?php echo h($item['description']); ?></td>
                <td class="text-center"><?php echo number_format((float)$item['quantity'], 2); ?></td>
                <td class="text-right"><?php echo $currency . ' ' . number_format((float)$item['unit_price'], 2); ?></td>
                <td class="text-right" style="font-weight:600;"><?php echo $currency . ' ' . number_format((float)$item['amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div class="totals-section">
        <table class="totals">
            <?php if ((float)$invoice['subtotal'] > 0): ?>
            <tr>
                <td>Subtotal</td>
                <td><?php echo $currency . ' ' . number_format((float)$invoice['subtotal'], 2); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ((float)($invoice['tax_rate'] ?? 0) > 0): ?>
            <tr>
                <td>Tax (<?php echo h($invoice['tax_rate']); ?>%)</td>
                <td><?php echo $currency . ' ' . number_format((float)$invoice['tax_amount'], 2); ?></td>
            </tr>
            <?php endif; ?>
            <?php if ((float)($invoice['discount'] ?? 0) > 0): ?>
            <tr>
                <td>Discount</td>
                <td style="color:#dc3545;">−<?php echo $currency . ' ' . number_format((float)$invoice['discount'], 2); ?></td>
            </tr>
            <?php endif; ?>
            <tr class="total-row">
                <td>Total</td>
                <td><?php echo $currency . ' ' . number_format((float)$invoice['total'], 2); ?></td>
            </tr>
            <?php if ($amountPaid > 0): ?>
            <tr class="paid-row">
                <td>Amount Paid</td>
                <td>−<?php echo $currency . ' ' . number_format($amountPaid, 2); ?></td>
            </tr>
            <tr class="due-row">
                <td>Balance Due</td>
                <td><?php echo $currency . ' ' . number_format($amountDue, 2); ?></td>
            </tr>
            <?php endif; ?>
        </table>
    </div>

    <?php if (!empty($invoice['notes'])): ?>
    <!-- Notes -->
    <div class="notes-section">
        <h3>Notes</h3>
        <p><?php echo nl2br(h($invoice['notes'])); ?></p>
    </div>
    <?php endif; ?>

    <?php if (!empty($payments)): ?>
    <!-- Payment History -->
    <div class="payment-history">
        <h3>Payment History</h3>
        <table class="pay-hist">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Transaction ID</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($payments as $pay): ?>
            <tr>
                <td><?php echo $pay['created_at'] ? date('M j, Y', strtotime($pay['created_at'])) : '—'; ?></td>
                <td style="font-weight:600;color:#198754;"><?php echo $currency . ' ' . number_format((float)$pay['amount'], 2); ?></td>
                <td><?php echo h(ucfirst($pay['method'] ?? 'manual')); ?></td>
                <td><?php echo h($pay['transaction_id'] ?? '—'); ?></td>
                <td><?php echo h($pay['notes'] ?? ''); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- Footer -->
    <div class="footer-note">
        Thank you for your business! — <?php echo h($siteName); ?><?php if ($siteEmail): ?> · <?php echo h($siteEmail); ?><?php endif; ?>
    </div>
</div>
</body>
</html>
