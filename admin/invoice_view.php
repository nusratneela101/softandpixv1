<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: invoices.php'); exit; }

$csrf_token = generateCsrfToken();

// Handle status update (Mark as Paid / Cancel / Record Payment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $action = $_POST['action'] ?? '';

    if ($action === 'record_payment') {
        $payAmount = (float)($_POST['pay_amount'] ?? 0);
        $payMethod = trim($_POST['pay_method'] ?? 'manual');
        $payNotes  = trim($_POST['pay_notes'] ?? '');
        $allowedMethods = ['manual', 'bank_transfer', 'paypal', 'stripe', 'cash', 'check', 'other'];
        if (!in_array($payMethod, $allowedMethods)) { $payMethod = 'manual'; }

        if ($payAmount > 0) {
            try {
                $pdo->prepare("INSERT INTO invoice_payments (invoice_id, amount, method, status, notes) VALUES (?,?,?,'completed',?)")
                    ->execute([$id, $payAmount, $payMethod, $payNotes]);

                // Check total paid vs invoice total
                $totalStmt = $pdo->prepare("SELECT i.total, COALESCE(SUM(p.amount),0) as paid FROM invoices i LEFT JOIN invoice_payments p ON p.invoice_id=i.id AND p.status='completed' WHERE i.id=? GROUP BY i.id");
                $totalStmt->execute([$id]);
                $totals = $totalStmt->fetch();
                if ($totals) {
                    $newStatus = ((float)$totals['paid'] >= (float)$totals['total']) ? 'paid' : 'partial';
                    $pdo->prepare("UPDATE invoices SET status=?, updated_at=NOW() WHERE id=? AND status NOT IN ('cancelled')")
                        ->execute([$newStatus, $id]);
                }
                flashMessage('success', 'Payment of ' . number_format($payAmount, 2) . ' recorded successfully.');
            } catch (Exception $e) {
                flashMessage('error', 'Failed to record payment.');
            }
        } else {
            flashMessage('error', 'Payment amount must be greater than zero.');
        }
        header("Location: invoice_view.php?id=$id"); exit;
    }

    if ($action === 'mark_paid') {
        try {
            $pdo->prepare("UPDATE invoices SET status='paid', updated_at=NOW() WHERE id=?")->execute([$id]);
            // Record payment if not already recorded
            $payCheck = $pdo->prepare("SELECT COUNT(*) FROM invoice_payments WHERE invoice_id=? AND notes='Marked as paid by admin'");
            $payCheck->execute([$id]);
            if (!(int)$payCheck->fetchColumn()) {
                $inv = $pdo->prepare("SELECT total FROM invoices WHERE id=?");
                $inv->execute([$id]);
                $invRow = $inv->fetch();
                $pdo->prepare("INSERT INTO invoice_payments (invoice_id, amount, method, status, notes) VALUES (?,?,'manual','completed','Marked as paid by admin')")
                    ->execute([$id, $invRow['total']]);
            }
            flashMessage('success', 'Invoice marked as paid.');
        } catch (Exception $e) { flashMessage('error', 'Update failed.'); }
        header("Location: invoice_view.php?id=$id"); exit;

    } elseif ($action === 'mark_sent') {
        try {
            $pdo->prepare("UPDATE invoices SET status='sent', updated_at=NOW() WHERE id=?")->execute([$id]);
            flashMessage('success', 'Invoice marked as sent.');
        } catch (Exception $e) { flashMessage('error', 'Update failed.'); }
        header("Location: invoice_view.php?id=$id"); exit;

    } elseif ($action === 'cancel') {
        try {
            $pdo->prepare("UPDATE invoices SET status='cancelled', updated_at=NOW() WHERE id=?")->execute([$id]);
            flashMessage('success', 'Invoice cancelled.');
        } catch (Exception $e) { flashMessage('error', 'Update failed.'); }
        header("Location: invoice_view.php?id=$id"); exit;

    } elseif ($action === 'send_email') {
        // Send invoice email to client
        require_once dirname(__DIR__) . '/includes/email.php';
        try {
            $stmt = $pdo->prepare("SELECT i.*, u.name as client_name, u.email as client_email FROM invoices i LEFT JOIN users u ON u.id=i.client_id WHERE i.id=?");
            $stmt->execute([$id]);
            $invForEmail = $stmt->fetch();
            if ($invForEmail && $invForEmail['client_email']) {
                $viewUrl  = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/client/invoice_view.php?id=' . $id;
                $subject  = 'Invoice ' . $invForEmail['invoice_number'] . ' from Softandpix';
                $htmlBody = '<p>Dear ' . h($invForEmail['client_name']) . ',</p>'
                          . '<p>Please find your invoice <strong>' . h($invForEmail['invoice_number']) . '</strong> attached.</p>'
                          . '<p><strong>Amount Due:</strong> ' . h($invForEmail['currency']) . ' ' . number_format((float)$invForEmail['total'], 2) . '</p>'
                          . ($invForEmail['due_date'] ? '<p><strong>Due Date:</strong> ' . date('F j, Y', strtotime($invForEmail['due_date'])) . '</p>' : '')
                          . '<p><a href="' . h($viewUrl) . '">View Invoice Online</a></p>'
                          . '<p>Thank you for your business.</p>';
                if (sendEmail($pdo, $invForEmail['client_email'], $subject, $htmlBody)) {
                    $pdo->prepare("UPDATE invoices SET status=IF(status='draft','sent',status), updated_at=NOW() WHERE id=?")->execute([$id]);
                    flashMessage('success', 'Invoice emailed to ' . $invForEmail['client_email'] . '.');
                } else {
                    flashMessage('error', 'Failed to send email. Check SMTP settings.');
                }
            } else {
                flashMessage('error', 'Client email not found.');
            }
        } catch (Exception $e) { flashMessage('error', 'Email error occurred.'); }
        header("Location: invoice_view.php?id=$id"); exit;

    } elseif ($action === 'send_reminder') {
        // Send payment reminder email to client
        require_once dirname(__DIR__) . '/includes/email.php';
        try {
            $stmt = $pdo->prepare("SELECT i.*, u.name as client_name, u.email as client_email FROM invoices i LEFT JOIN users u ON u.id=i.client_id WHERE i.id=?");
            $stmt->execute([$id]);
            $invForEmail = $stmt->fetch();
            if ($invForEmail && $invForEmail['client_email']) {
                $amountDueCalc = max(0, (float)$invForEmail['total'] - (float)($invForEmail['amount_paid'] ?? 0));
                $viewUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . '/client/invoice_view.php?id=' . $id;
                $subject = 'Payment Reminder: Invoice ' . $invForEmail['invoice_number'];
                $htmlBody = '<p>Dear ' . h($invForEmail['client_name']) . ',</p>'
                          . '<p>This is a friendly reminder that invoice <strong>' . h($invForEmail['invoice_number']) . '</strong> is still outstanding.</p>'
                          . '<p><strong>Amount Due:</strong> ' . h($invForEmail['currency']) . ' ' . number_format($amountDueCalc, 2) . '</p>'
                          . ($invForEmail['due_date'] ? '<p><strong>Due Date:</strong> ' . date('F j, Y', strtotime($invForEmail['due_date'])) . '</p>' : '')
                          . '<p>Please make your payment at your earliest convenience:</p>'
                          . '<p><a href="' . h($viewUrl) . '" style="background:#0d6efd;color:#fff;padding:10px 20px;text-decoration:none;border-radius:5px;">Pay Now</a></p>'
                          . '<p>If you have already made this payment, please disregard this reminder.</p>'
                          . '<p>Thank you,<br>Softandpix Team</p>';

                if (sendEmail($pdo, $invForEmail['client_email'], $subject, $htmlBody)) {

                    flashMessage('success', 'Payment reminder sent to ' . $invForEmail['client_email'] . '.');
                } else {
                    flashMessage('error', 'Failed to send reminder. Check SMTP settings.');
                }
            } else {
                flashMessage('error', 'Client email not found.');
            }
        } catch (Exception $e) { flashMessage('error', 'Reminder error occurred.'); }
        header("Location: invoice_view.php?id=$id"); exit;
    }
}

// Load invoice data
try {
    $stmt = $pdo->prepare("SELECT i.*, 
        u.name as client_name, u.email as client_email, u.phone as client_phone,
        p.title as project_title,
        a.username as admin_name
        FROM invoices i
        LEFT JOIN users u ON u.id=i.client_id
        LEFT JOIN projects p ON p.id=i.project_id
        LEFT JOIN admin_users a ON a.id=i.admin_id
        WHERE i.id=?");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
    if (!$invoice) { header('Location: invoices.php'); exit; }

    $itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order ASC");
    $itemsStmt->execute([$id]);
    $items = $itemsStmt->fetchAll();

    $paymentsStmt = $pdo->prepare("SELECT * FROM invoice_payments WHERE invoice_id=? ORDER BY created_at DESC");
    $paymentsStmt->execute([$id]);
    $payments = $paymentsStmt->fetchAll();

    // Load reminder history (table may not exist yet)
    $reminders = [];
    try {
        $remindersStmt = $pdo->prepare("SELECT * FROM invoice_reminders WHERE invoice_id=? ORDER BY sent_at DESC");
        $remindersStmt->execute([$id]);
        $reminders = $remindersStmt->fetchAll();
    } catch (Exception $e) { /* table may not exist yet */ }

    // Site settings for company info
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ('site_name','site_email','site_phone','site_address','logo_url')");
    $settings = [];
    foreach ($settingsStmt->fetchAll() as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {
    header('Location: invoices.php'); exit;
}

$statusColors = [
    'draft'     => 'secondary',
    'sent'      => 'info',
    'paid'      => 'success',
    'overdue'   => 'danger',
    'partial'   => 'warning',
    'cancelled' => 'dark',
];

$amountPaid = (float)($invoice['amount_paid'] ?? array_sum(array_column($payments, 'amount')));
$amountDue  = max(0, (float)$invoice['total'] - $amountPaid);

require_once 'includes/header.php';
?>
<style>
@media print {
    .sidebar-wrapper, .navbar, .page-header .btn, .no-print, footer { display: none !important; }
    .page-content-wrapper { margin: 0 !important; padding: 0 !important; }
    .invoice-card { box-shadow: none !important; border: none !important; }
    body { background: #fff !important; }
}
</style>

<div class="page-header d-flex justify-content-between align-items-center no-print">
    <div>
        <h1><i class="bi bi-receipt me-2"></i>Invoice</h1>
        <span class="badge bg-<?php echo $statusColors[$invoice['status']] ?? 'secondary'; ?> fs-6"><?php echo ucfirst(h($invoice['status'])); ?></span>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="invoice_edit.php?id=<?php echo $id; ?>" class="btn btn-outline-primary"><i class="bi bi-pencil me-1"></i>Edit</a>


        <?php if ($invoice['status'] === 'draft'): ?>
        <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <input type="hidden" name="action" value="mark_sent">
            <button type="submit" class="btn btn-info text-white"><i class="bi bi-send me-1"></i>Mark as Sent</button>
        </form>
        <?php endif; ?>

        <?php if (in_array($invoice['status'], ['draft','sent','overdue','partial'])): ?>
        <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <input type="hidden" name="action" value="send_email">
            <button type="submit" class="btn btn-outline-info"><i class="bi bi-envelope me-1"></i>Email Client</button>
        </form>

        <form method="POST" class="d-inline">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <input type="hidden" name="action" value="mark_paid">
            <button type="submit" class="btn btn-success"><i class="bi bi-check-circle me-1"></i>Mark as Paid</button>
        </form>
        <button type="button" class="btn btn-outline-warning" data-bs-toggle="modal" data-bs-target="#recordPaymentModal">
            <i class="bi bi-cash-coin me-1"></i>Record Payment
        </button>
        <?php endif; ?>

        <?php if (!in_array($invoice['status'], ['paid','cancelled'])): ?>
        <form method="POST" class="d-inline" onsubmit="return confirm('Cancel this invoice?')">
            <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-x-circle me-1"></i>Cancel</button>
        </form>
        <?php endif; ?>

        <a href="invoices.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>

<div class="container-fluid">
    <div class="row g-4">
        <!-- Main Invoice -->
        <div class="col-lg-8">
            <div class="card invoice-card shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <!-- Header -->
                    <div class="row mb-4">
                        <div class="col-6">
                            <?php if (!empty($settings['logo_url'])): ?>
                            <img src="<?php echo h($settings['logo_url']); ?>" alt="Logo" style="max-height:60px;max-width:180px;" class="mb-2">
                            <?php endif; ?>
                            <h4 class="fw-bold mb-0"><?php echo h($settings['site_name'] ?? 'Softandpix'); ?></h4>
                            <?php if (!empty($settings['site_address'])): ?>
                            <p class="text-muted small mb-0"><?php echo nl2br(h($settings['site_address'])); ?></p>
                            <?php endif; ?>
                            <?php if (!empty($settings['site_email'])): ?>
                            <p class="text-muted small mb-0"><?php echo h($settings['site_email']); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-6 text-end">
                            <h2 class="fw-bold text-primary mb-1">INVOICE</h2>
                            <div class="fw-semibold fs-5"><?php echo h($invoice['invoice_number']); ?></div>
                            <span class="badge bg-<?php echo $statusColors[$invoice['status']] ?? 'secondary'; ?> fs-6 mt-1"><?php echo ucfirst(h($invoice['status'])); ?></span>
                        </div>
                    </div>

                    <hr>

                    <!-- Bill To / Dates -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <h6 class="text-muted text-uppercase small fw-bold mb-2">Bill To</h6>
                            <div class="fw-semibold"><?php echo h($invoice['client_name'] ?? 'Unknown Client'); ?></div>
                            <?php if (!empty($invoice['client_email'])): ?>
                            <div class="text-muted small"><?php echo h($invoice['client_email']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($invoice['client_phone'])): ?>
                            <div class="text-muted small"><?php echo h($invoice['client_phone']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($invoice['project_title'])): ?>
                            <div class="mt-2 small"><strong>Project:</strong> <?php echo h($invoice['project_title']); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6 text-md-end">
                            <table class="ms-auto">
                                <tr>
                                    <td class="text-muted small pe-3">Issue Date:</td>
                                    <td class="fw-semibold small"><?php echo $invoice['issue_date'] ? date('F j, Y', strtotime($invoice['issue_date'])) : '—'; ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted small pe-3">Due Date:</td>
                                    <td class="fw-semibold small <?php echo ($invoice['due_date'] && $invoice['due_date'] < date('Y-m-d') && $invoice['status'] !== 'paid') ? 'text-danger' : ''; ?>">
                                        <?php echo $invoice['due_date'] ? date('F j, Y', strtotime($invoice['due_date'])) : '—'; ?>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="text-muted small pe-3">Currency:</td>
                                    <td class="fw-semibold small"><?php echo h($invoice['currency'] ?? 'USD'); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>

                    <!-- Line Items -->
                    <div class="table-responsive mb-4">
                        <table class="table table-bordered">
                            <thead class="table-dark">
                                <tr>
                                    <th>#</th>
                                    <th>Description</th>
                                    <th class="text-end" style="width:80px;">Qty</th>
                                    <th class="text-end" style="width:120px;">Unit Price</th>
                                    <th class="text-end" style="width:120px;">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                <tr><td colspan="5" class="text-muted text-center">No line items.</td></tr>
                                <?php endif; ?>
                                <?php foreach ($items as $idx => $item): ?>
                                <tr>
                                    <td class="text-muted small"><?php echo $idx + 1; ?></td>
                                    <td><?php echo h($item['description']); ?></td>
                                    <td class="text-end"><?php echo number_format((float)$item['quantity'], 2); ?></td>
                                    <td class="text-end"><?php echo number_format((float)$item['unit_price'], 2); ?></td>
                                    <td class="text-end fw-semibold"><?php echo number_format((float)$item['amount'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totals -->
                    <div class="row">
                        <div class="col-md-6">
                            <?php if (!empty($invoice['notes'])): ?>
                            <h6 class="fw-semibold">Notes</h6>
                            <p class="text-muted small"><?php echo nl2br(h($invoice['notes'])); ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-sm ms-auto" style="max-width:320px;">
                                <tr>
                                    <td class="text-muted">Subtotal</td>
                                    <td class="text-end"><?php echo h($invoice['currency']); ?> <?php echo number_format((float)$invoice['subtotal'], 2); ?></td>
                                </tr>
                                <?php if ((float)$invoice['tax_rate'] > 0): ?>
                                <tr>
                                    <td class="text-muted">Tax (<?php echo h($invoice['tax_rate']); ?>%)</td>
                                    <td class="text-end"><?php echo h($invoice['currency']); ?> <?php echo number_format((float)$invoice['tax_amount'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ((float)$invoice['discount'] > 0): ?>
                                <tr>
                                    <td class="text-muted">Discount</td>
                                    <td class="text-end text-danger">− <?php echo h($invoice['currency']); ?> <?php echo number_format((float)$invoice['discount'], 2); ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr class="table-dark fw-bold fs-5">
                                    <td>Total</td>
                                    <td class="text-end"><?php echo h($invoice['currency']); ?> <?php echo number_format((float)$invoice['total'], 2); ?></td>
                                </tr>
                                <?php if ($amountPaid > 0): ?>
                                <tr class="text-success">
                                    <td>Amount Paid</td>
                                    <td class="text-end">− <?php echo h($invoice['currency']); ?> <?php echo number_format($amountPaid, 2); ?></td>
                                </tr>
                                <tr class="fw-bold">
                                    <td>Amount Due</td>
                                    <td class="text-end <?php echo $amountDue > 0 ? 'text-danger' : 'text-success'; ?>">
                                        <?php echo h($invoice['currency']); ?> <?php echo number_format($amountDue, 2); ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </table>
                        </div>
                    </div>

                    <hr class="mt-4">
                    <p class="text-center text-muted small mb-0">Thank you for your business! — <?php echo h($settings['site_name'] ?? 'Softandpix'); ?></p>
                </div>
            </div>
        </div>

        <!-- Sidebar: Payments & Activity -->
        <div class="col-lg-4 no-print">
            <!-- Quick Info -->
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-info-circle me-2"></i>Quick Info</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Invoice #</span>
                        <strong><?php echo h($invoice['invoice_number']); ?></strong>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Status</span>
                        <span class="badge bg-<?php echo $statusColors[$invoice['status']] ?? 'secondary'; ?>"><?php echo ucfirst(h($invoice['status'])); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Total</span>
                        <strong><?php echo h($invoice['currency']); ?> <?php echo number_format((float)$invoice['total'], 2); ?></strong>
                    </div>
                    <?php if ($amountDue > 0 && $invoice['status'] !== 'cancelled'): ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Amount Due</span>
                        <strong class="text-danger"><?php echo h($invoice['currency']); ?> <?php echo number_format($amountDue, 2); ?></strong>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted small">Created</span>
                        <span class="small"><?php echo $invoice['created_at'] ? date('M j, Y', strtotime($invoice['created_at'])) : '—'; ?></span>
                    </div>
                    <?php if ($invoice['admin_name']): ?>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted small">Created By</span>
                        <span class="small"><?php echo h($invoice['admin_name']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Record Payment Form -->
            <?php if (!in_array($invoice['status'], ['paid', 'cancelled'])): ?>
            <div class="card mb-3">
                <div class="card-header fw-semibold bg-success text-white"><i class="bi bi-plus-circle me-2"></i>Record Payment</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <input type="hidden" name="action" value="record_payment">
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Amount</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text"><?php echo h($invoice['currency']); ?></span>
                                <input type="number" name="pay_amount" class="form-control"
                                       step="0.01" min="0.01"
                                       max="<?php echo number_format($amountDue, 2, '.', ''); ?>"
                                       value="<?php echo number_format($amountDue, 2, '.', ''); ?>"
                                       required>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Payment Method</label>
                            <select name="pay_method" class="form-select form-select-sm">
                                <option value="manual">Manual</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank Transfer</option>
                                <option value="paypal">PayPal</option>
                                <option value="stripe">Stripe</option>
                                <option value="square">Square</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <label class="form-label small fw-bold">Transaction ID <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" name="pay_txn" class="form-control form-control-sm" placeholder="e.g. PAY-XYZ123">
                        </div>
                        <div class="mb-3">
                            <label class="form-label small fw-bold">Notes <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="text" name="pay_notes" class="form-control form-control-sm" placeholder="e.g. First instalment">
                        </div>
                        <button type="submit" class="btn btn-success btn-sm w-100">
                            <i class="bi bi-check-circle me-1"></i>Record Payment
                        </button>
                    </form>
                </div>
            </div>
            <?php endif; ?>

            <!-- Payment History -->
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-credit-card me-2"></i>Payment History</div>
                <?php if (empty($payments)): ?>
                <div class="card-body text-muted small text-center py-3">No payments recorded.</div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($payments as $pay): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <span class="fw-semibold small text-success"><?php echo h($invoice['currency']); ?> <?php echo number_format((float)$pay['amount'], 2); ?></span>
                            <span class="text-muted small"><?php echo $pay['created_at'] ? date('M j, Y', strtotime($pay['created_at'])) : '—'; ?></span>
                        </div>
                        <div class="text-muted" style="font-size:.75rem;">
                            <?php echo h(ucfirst(str_replace('_', ' ', $pay['method'] ?? 'manual'))); ?>
                            <?php if (!empty($pay['transaction_id'])): ?> · <code><?php echo h($pay['transaction_id']); ?></code><?php endif; ?>
                            <?php if (!empty($pay['notes'])): ?> — <?php echo h($pay['notes']); ?><?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Reminder History -->
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-bell me-2"></i>Reminder History</div>
                <?php if (empty($reminders)): ?>
                <div class="card-body text-muted small text-center py-3">No reminders sent yet.</div>
                <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($reminders as $rem): ?>
                    <div class="list-group-item">
                        <div class="d-flex justify-content-between">
                            <span class="small fw-semibold">Reminder Sent</span>
                            <span class="text-muted small"><?php echo !empty($rem['sent_at']) ? date('M j, Y', strtotime($rem['sent_at'])) : '—'; ?></span>
                        </div>
                        <?php if (!empty($rem['recipient_email'])): ?>
                        <div class="text-muted" style="font-size:.75rem;"><?php echo h($rem['recipient_email']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Actions -->
            <div class="card mb-3">
                <div class="card-header fw-semibold"><i class="bi bi-gear me-2"></i>Actions</div>
                <div class="card-body d-grid gap-2">
                    <a href="invoice_print.php?id=<?php echo $id; ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                        <i class="bi bi-printer me-2"></i>Print Invoice
                    </a>
                    <a href="invoice_pdf.php?id=<?php echo $id; ?>" target="_blank" class="btn btn-outline-danger btn-sm">
                        <i class="bi bi-file-earmark-pdf me-2 text-danger"></i>Download PDF
                    </a>
                    <?php if (in_array($invoice['status'], ['sent', 'overdue', 'partial'])): ?>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                        <input type="hidden" name="action" value="send_reminder">
                        <button type="submit" class="btn btn-outline-warning btn-sm w-100" onclick="return confirm('Send payment reminder to client?')">
                            <i class="bi bi-bell me-2"></i>Send Reminder
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Record Partial Payment Modal -->
<?php if (!in_array($invoice['status'], ['paid', 'cancelled'])): ?>
<div class="modal fade" id="recordPaymentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark">
                <h5 class="modal-title"><i class="bi bi-cash-coin me-2"></i>Record Payment</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="record_payment">
                <div class="modal-body">
                    <?php if ($amountDue > 0): ?>
                    <p class="text-muted small mb-3">Amount due: <strong><?php echo h($invoice['currency']); ?> <?php echo number_format($amountDue, 2); ?></strong></p>
                    <?php endif; ?>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text"><?php echo h($invoice['currency'] ?? 'USD'); ?></span>
                            <input type="number" name="pay_amount" class="form-control" step="0.01" min="0.01"
                                   max="<?php echo max(0, $amountDue > 0 ? $amountDue : (float)$invoice['total']); ?>"
                                   value="<?php echo number_format($amountDue > 0 ? $amountDue : (float)$invoice['total'], 2, '.', ''); ?>"
                                   required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Payment Method</label>
                        <select name="pay_method" class="form-select">
                            <option value="manual">Manual / Admin Entry</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="paypal">PayPal</option>
                            <option value="stripe">Stripe</option>
                            <option value="cash">Cash</option>
                            <option value="check">Check</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Notes</label>
                        <input type="text" name="pay_notes" class="form-control" placeholder="Transaction ID, reference, etc." maxlength="255">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning"><i class="bi bi-check-circle me-1"></i>Record Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>
<?php require_once 'includes/footer.php'; ?>
