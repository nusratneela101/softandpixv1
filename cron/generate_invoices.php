<?php
/**
 * Cron: Generate recurring invoices
 * Run daily via cron: 0 0 * * * php /path/to/cron/generate_invoices.php
 */
define('CRON_RUN', true);
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/activity_logger.php';
require_once __DIR__ . '/../includes/email_template.php';

$today = date('Y-m-d');
$generated = 0;
$errors    = 0;

try {
    // Fetch all active recurring invoices due today or earlier
    $stmt = $pdo->prepare(
        "SELECT ri.*, u.name as client_name, u.email as client_email
         FROM recurring_invoices ri
         JOIN users u ON u.id = ri.client_id
         WHERE ri.status = 'active' AND ri.next_generate_date <= ?
         AND (ri.end_date IS NULL OR ri.end_date >= ?)"
    );
    $stmt->execute([$today, $today]);
    $due = $stmt->fetchAll();

    foreach ($due as $ri) {
        try {
            // Calculate totals
            $line_items = json_decode($ri['line_items'], true) ?? [];
            $subtotal = 0;
            foreach ($line_items as $item) {
                $subtotal += ($item['qty'] ?? 1) * ($item['price'] ?? 0);
            }
            $tax    = round($subtotal * ($ri['tax_rate'] / 100), 2);
            $total  = $subtotal + $tax;

            // Build line items text for invoice
            $items_text = implode(', ', array_column($line_items, 'title'));

            // Create invoice
            $due_date = date('Y-m-d', strtotime('+14 days'));
            $pdo->prepare(
                "INSERT INTO invoices (client_id, project_id, amount, tax_rate, tax_amount, total_amount, due_date, status, notes, created_by, created_at)
                 VALUES (?,?,?,?,?,?,?,'unpaid',?,?,NOW())"
            )->execute([
                $ri['client_id'],
                $ri['project_id'],
                $subtotal,
                $ri['tax_rate'],
                $tax,
                $total,
                $due_date,
                $ri['notes'],
                $ri['client_id'],
            ]);
            $invoice_id = (int)$pdo->lastInsertId();

            // Record in history
            $pdo->prepare(
                "INSERT INTO recurring_invoice_history (recurring_invoice_id, generated_invoice_id) VALUES (?,?)"
            )->execute([$ri['id'], $invoice_id]);

            // Calculate next generate date
            $next = match($ri['frequency']) {
                'weekly'    => date('Y-m-d', strtotime('+1 week', strtotime($ri['next_generate_date']))),
                'quarterly' => date('Y-m-d', strtotime('+3 months', strtotime($ri['next_generate_date']))),
                'yearly'    => date('Y-m-d', strtotime('+1 year', strtotime($ri['next_generate_date']))),
                default     => date('Y-m-d', strtotime('+1 month', strtotime($ri['next_generate_date']))),
            };

            // Pause if end_date reached
            $new_status = 'active';
            if ($ri['end_date'] && $next > $ri['end_date']) {
                $new_status = 'cancelled';
            }

            $pdo->prepare(
                "UPDATE recurring_invoices SET next_generate_date=?, status=? WHERE id=?"
            )->execute([$next, $new_status, $ri['id']]);

            log_activity($pdo, null, 'invoice_auto_generated', "Auto-generated invoice #{$invoice_id} from recurring #{$ri['id']}", 'invoice', $invoice_id);

            // Send notification email
            try {
                $html = render_email_template('invoice_created', [
                    'user_name'      => $ri['client_name'],
                    'user_email'     => $ri['client_email'],
                    'invoice_number' => 'INV-' . str_pad($invoice_id, 5, '0', STR_PAD_LEFT),
                    'amount'         => number_format($total, 2),
                    'currency'       => '$',
                    'due_date'       => $due_date,
                    'status'         => 'Unpaid',
                    'invoice_url'    => (defined('SITE_URL') ? SITE_URL : '') . '/invoice/view.php?id=' . $invoice_id,
                ]);
                // Send email if send_email function is available
                if (function_exists('send_email')) {
                    send_email($ri['client_email'], 'New Invoice Generated', $html);
                }
            } catch (Exception $emailEx) {
                error_log('Recurring invoice email error: ' . $emailEx->getMessage());
            }

            $generated++;
            echo "Generated invoice #{$invoice_id} for client: {$ri['client_name']}\n";
        } catch (Exception $ex) {
            $errors++;
            error_log("Recurring invoice error for ri #{$ri['id']}: " . $ex->getMessage());
            echo "ERROR for recurring #{$ri['id']}: " . $ex->getMessage() . "\n";
        }
    }
} catch (Exception $e) {
    error_log('generate_invoices cron error: ' . $e->getMessage());
    echo "Fatal error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\nDone. Generated: {$generated}, Errors: {$errors}\n";
