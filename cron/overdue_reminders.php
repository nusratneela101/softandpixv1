<?php
/**
 * Cron job: Send payment reminders for overdue invoices.
 *
 * Recommended crontab entry (run daily at 8 AM):
 *   0 8 * * * php /path/to/softandpix/cron/overdue_reminders.php >> /var/log/softandpix_reminders.log 2>&1
 */

define('RUNNING_FROM_CRON', true);

require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/functions.php';
require_once dirname(__DIR__) . '/includes/email.php';

$today = date('Y-m-d');
echo "[" . date('Y-m-d H:i:s') . "] Overdue reminder cron started.\n";

// Step 1: Mark sent invoices with past due dates as overdue
try {
    $markOverdue = $pdo->prepare("
        UPDATE invoices
        SET status = 'overdue', updated_at = NOW()
        WHERE status = 'sent'
          AND due_date IS NOT NULL
          AND due_date < ?
    ");
    $markOverdue->execute([$today]);
    $markedCount = $markOverdue->rowCount();
    echo "[" . date('Y-m-d H:i:s') . "] Marked $markedCount invoice(s) as overdue.\n";
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR marking overdue invoices: " . $e->getMessage() . "\n";
}

// Step 2: Fetch overdue invoices that have a client email
try {
    $stmt = $pdo->prepare("
        SELECT i.id, i.invoice_number, i.total, i.amount_paid, i.currency, i.due_date,
               u.name AS client_name, u.email AS client_email
        FROM invoices i
        LEFT JOIN users u ON u.id = i.client_id
        WHERE i.status IN ('overdue', 'partial')
          AND i.due_date IS NOT NULL
          AND i.due_date < ?
          AND u.email IS NOT NULL
          AND u.email <> ''
        ORDER BY i.due_date ASC
    ");
    $stmt->execute([$today]);
    $overdueInvoices = $stmt->fetchAll();
} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] ERROR fetching overdue invoices: " . $e->getMessage() . "\n";
    exit(1);
}

echo "[" . date('Y-m-d H:i:s') . "] Found " . count($overdueInvoices) . " overdue invoice(s) to process.\n";

$sent = 0;
$skipped = 0;
$failed = 0;

foreach ($overdueInvoices as $invoice) {
    $invoiceId = (int)$invoice['id'];

    // Check if a reminder was already sent today for this invoice
    try {
        $checkStmt = $pdo->prepare("
            SELECT COUNT(*) FROM invoice_reminders
            WHERE invoice_id = ? AND DATE(sent_at) = ?
        ");
        $checkStmt->execute([$invoiceId, $today]);
        if ((int)$checkStmt->fetchColumn() > 0) {
            echo "[" . date('Y-m-d H:i:s') . "] Skipping invoice #{$invoice['invoice_number']} - reminder already sent today.\n";
            $skipped++;
            continue;
        }
    } catch (Exception $e) {
        // invoice_reminders table may not exist yet; proceed anyway
    }

    $amountDue = max(0, (float)$invoice['total'] - (float)($invoice['amount_paid'] ?? 0));
    $currency  = htmlspecialchars($invoice['currency'] ?? 'USD', ENT_QUOTES, 'UTF-8');
    $dueDate   = $invoice['due_date'] ? date('F j, Y', strtotime($invoice['due_date'])) : '-';

    // Build the invoice URL from the stored site_url setting
    try {
        $urlRow = $pdo->query("SELECT setting_value FROM site_settings WHERE setting_key='site_url' LIMIT 1")->fetch();
        $siteUrl = rtrim($urlRow['setting_value'] ?? '', '/');
    } catch (Exception $e) {
        $siteUrl = '';
    }
    if (!$siteUrl) {
        $siteUrl = defined('APP_URL') ? rtrim(APP_URL, '/') : 'https://softandpix.com';
    }
    $viewUrl = $siteUrl . '/client/invoice_view.php?id=' . $invoiceId;

    $subject  = 'Payment Reminder: Invoice ' . $invoice['invoice_number'] . ' is Overdue';
    $htmlBody = '<p>Dear ' . htmlspecialchars($invoice['client_name'] ?? 'Client', ENT_QUOTES, 'UTF-8') . ',</p>'
              . '<p>This is a friendly reminder that the following invoice is still outstanding:</p>'
              . '<table style="border-collapse:collapse;margin:12px 0;">'
              . '<tr><td style="padding:4px 12px 4px 0;color:#666;">Invoice #</td><td style="font-weight:600;">' . htmlspecialchars($invoice['invoice_number'], ENT_QUOTES, 'UTF-8') . '</td></tr>'
              . '<tr><td style="padding:4px 12px 4px 0;color:#666;">Due Date</td><td style="font-weight:600;color:#dc3545;">' . $dueDate . '</td></tr>'
              . '<tr><td style="padding:4px 12px 4px 0;color:#666;">Amount Due</td><td style="font-weight:600;color:#dc3545;">' . $currency . ' ' . number_format($amountDue, 2) . '</td></tr>'
              . '</table>'
              . '<p>Please make your payment at your earliest convenience:</p>'
              . '<p><a href="' . htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') . '" style="background:#0d6efd;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;display:inline-block;">View &amp; Pay Invoice</a></p>'
              . '<p>If you have already made this payment, please disregard this reminder.</p>'
              . '<p>Thank you,<br>Softandpix Team</p>';

    if (sendEmail($pdo, $invoice['client_email'], $subject, $htmlBody)) {
        echo "[" . date('Y-m-d H:i:s') . "] Reminder sent for invoice #{$invoice['invoice_number']} to {$invoice['client_email']}.\n";

        // Log the reminder
        try {
            $logStmt = $pdo->prepare("
                INSERT INTO invoice_reminders (invoice_id, recipient_email, sent_at)
                VALUES (?, ?, NOW())
            ");
            $logStmt->execute([$invoiceId, $invoice['client_email']]);
        } catch (Exception $e) {
            // invoice_reminders table may not exist yet; non-fatal
            echo "[" . date('Y-m-d H:i:s') . "] WARNING: Could not log reminder: " . $e->getMessage() . "\n";
        }

        $sent++;
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] FAILED to send reminder for invoice #{$invoice['invoice_number']} to {$invoice['client_email']}.\n";
        $failed++;
    }
}

echo "[" . date('Y-m-d H:i:s') . "] Done. Sent: $sent, Skipped: $skipped, Failed: $failed.\n";
