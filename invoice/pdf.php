<?php
/**
 * Invoice PDF Export — generates a downloadable PDF using pure PHP (no external libs).
 * Creates an HTML invoice that is printed as PDF via browser print dialog,
 * or served as a self-contained HTML file that opens in print mode.
 *
 * For a truly server-side PDF, include the FPDF library in libs/fpdf/fpdf.php.
 * This file auto-detects FPDF and falls back to print-ready HTML if not available.
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { redirect(BASE_URL); }

$stmt = $pdo->prepare("SELECT i.*, u.name AS client_name, u.email AS client_email, u.address AS client_address
    FROM invoices i JOIN users u ON i.client_id=u.id WHERE i.id=?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) { redirect(BASE_URL); }

// Access check
if ($_SESSION['user_role'] === 'client' && $invoice['client_id'] !== $_SESSION['user_id']) { redirect(BASE_URL); }
if ($_SESSION['user_role'] === 'developer') { redirect(BASE_URL); }

$items = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id=?");
$items->execute([$id]);
$items = $items->fetchAll();

// Get site settings for branding
try {
    $settingsStmt = $pdo->query("SELECT setting_key, setting_value FROM site_settings");
    $site = array_column($settingsStmt->fetchAll(), 'setting_value', 'setting_key');
} catch (Exception $e) { $site = []; }
$siteName    = $site['site_name'] ?? 'SoftandPix';
$siteAddress = $site['site_address'] ?? '';
$sitePhone   = $site['site_phone'] ?? '';
$siteEmail   = $site['site_email'] ?? 'info@softandpix.com';
$currency    = $site['currency_symbol'] ?? '$';

// Try FPDF if available
$fpdfPath = BASE_PATH . '/libs/fpdf/fpdf.php';
if (file_exists($fpdfPath)) {
    require_once $fpdfPath;

    class InvoicePDF extends FPDF {
        public $siteName = 'SoftandPix';
        public $currency = '$';

        function Header() {
            $this->SetFillColor(102, 126, 234);
            $this->Rect(0, 0, 210, 22, 'F');
            $this->SetFont('Helvetica', 'B', 16);
            $this->SetTextColor(255, 255, 255);
            $this->SetXY(10, 5);
            $this->Cell(0, 12, $this->siteName, 0, 1, 'L');
            $this->SetTextColor(0, 0, 0);
            $this->Ln(4);
        }

        function Footer() {
            $this->SetY(-15);
            $this->SetFont('Helvetica', 'I', 8);
            $this->SetTextColor(150, 150, 150);
            $this->Cell(0, 10, 'Generated on ' . date('Y-m-d H:i') . ' | Page ' . $this->PageNo(), 0, 0, 'C');
        }

        function MoneyFormat($amount) {
            return $this->currency . number_format((float)$amount, 2);
        }
    }

    $pdf = new InvoicePDF();
    $pdf->siteName = $siteName;
    $pdf->currency = $currency;
    $pdf->AddPage();
    $pdf->SetAutoPageBreak(true, 20);

    // Invoice title row
    $pdf->SetFont('Helvetica', 'B', 20);
    $pdf->SetTextColor(102, 126, 234);
    $pdf->Cell(100, 10, 'INVOICE', 0, 0, 'L');
    $pdf->SetFont('Helvetica', '', 11);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Cell(90, 10, '#' . $invoice['invoice_number'], 0, 1, 'R');
    $pdf->Ln(2);

    // Bill To & Invoice Info
    $pdf->SetFont('Helvetica', 'B', 11);
    $pdf->Cell(100, 7, 'Bill To:', 0, 0, 'L');
    $pdf->Cell(90, 7, 'Invoice Details:', 0, 1, 'R');

    $pdf->SetFont('Helvetica', '', 10);
    $leftY = $pdf->GetY();
    $pdf->Cell(100, 6, $invoice['client_name'], 0, 0, 'L');
    $pdf->Cell(90, 6, 'Date: ' . date('M j, Y', strtotime($invoice['created_at'])), 0, 1, 'R');
    $pdf->Cell(100, 6, $invoice['client_email'], 0, 0, 'L');
    $pdf->Cell(90, 6, 'Due: ' . ($invoice['due_date'] ? date('M j, Y', strtotime($invoice['due_date'])) : 'N/A'), 0, 1, 'R');
    if ($invoice['client_address']) {
        $pdf->Cell(100, 6, $invoice['client_address'], 0, 0, 'L');
    } else {
        $pdf->Cell(100, 6, '', 0, 0, 'L');
    }
    $statuses = ['paid' => 'PAID', 'pending' => 'PENDING', 'overdue' => 'OVERDUE'];
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->Cell(90, 6, 'Status: ' . ($statuses[$invoice['status']] ?? strtoupper($invoice['status'])), 0, 1, 'R');
    $pdf->Ln(6);

    // Items table header
    $pdf->SetFillColor(240, 240, 240);
    $pdf->SetFont('Helvetica', 'B', 10);
    $pdf->SetFillColor(42, 58, 90);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(90, 8, 'Description', 1, 0, 'L', true);
    $pdf->Cell(25, 8, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(35, 8, 'Rate', 1, 0, 'R', true);
    $pdf->Cell(40, 8, 'Amount', 1, 1, 'R', true);
    $pdf->SetTextColor(0, 0, 0);

    $fill = false;
    $pdf->SetFont('Helvetica', '', 10);
    foreach ($items as $item) {
        $pdf->SetFillColor($fill ? 248 : 255, $fill ? 249 : 255, $fill ? 250 : 255);
        $amount = (float)$item['quantity'] * (float)$item['unit_price'];
        $pdf->Cell(90, 7, $item['description'], 1, 0, 'L', $fill);
        $pdf->Cell(25, 7, number_format((float)$item['quantity'], 2), 1, 0, 'C', $fill);
        $pdf->Cell(35, 7, $pdf->MoneyFormat($item['unit_price']), 1, 0, 'R', $fill);
        $pdf->Cell(40, 7, $pdf->MoneyFormat($amount), 1, 1, 'R', $fill);
        $fill = !$fill;
    }

    // Totals
    $pdf->Ln(4);
    $pdf->SetFont('Helvetica', '', 10);
    $pdf->Cell(150, 7, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(40, 7, $pdf->MoneyFormat($invoice['subtotal'] ?? $invoice['amount'] ?? 0), 0, 1, 'R');

    if (!empty($invoice['tax_amount']) && (float)$invoice['tax_amount'] > 0) {
        $pdf->Cell(150, 7, 'Tax (' . ($invoice['tax_rate'] ?? '') . '%):', 0, 0, 'R');
        $pdf->Cell(40, 7, $pdf->MoneyFormat($invoice['tax_amount']), 0, 1, 'R');
    }

    $pdf->SetFont('Helvetica', 'B', 12);
    $pdf->SetFillColor(102, 126, 234);
    $pdf->SetTextColor(255, 255, 255);
    $pdf->Cell(150, 9, 'TOTAL:', 0, 0, 'R');
    $pdf->Cell(40, 9, $pdf->MoneyFormat($invoice['total_amount'] ?? $invoice['amount'] ?? 0), 'T', 1, 'R');
    $pdf->SetTextColor(0, 0, 0);

    if ($invoice['notes'] ?? '') {
        $pdf->Ln(6);
        $pdf->SetFont('Helvetica', 'B', 10);
        $pdf->Cell(0, 7, 'Notes:', 0, 1);
        $pdf->SetFont('Helvetica', '', 10);
        $pdf->MultiCell(0, 6, $invoice['notes']);
    }

    $filename = 'Invoice-' . $invoice['invoice_number'] . '.pdf';
    $pdf->Output('D', $filename);
    exit;
}

// -----------------------------------------------------------------------
// Fallback: print-ready HTML (auto-triggers print dialog)
// -----------------------------------------------------------------------
$filename = 'Invoice-' . $invoice['invoice_number'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($filename) ?> — <?= h($siteName) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { margin: 0; }
            .invoice-box { box-shadow: none !important; margin: 0 !important; max-width: 100% !important; }
        }
        body { background: #f0f2f5; }
        .invoice-box {
            background: #fff;
            max-width: 850px;
            margin: 30px auto;
            padding: 48px;
            border-radius: 10px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }
        .brand-bar {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: #fff;
            padding: 24px 32px;
            border-radius: 8px;
            margin-bottom: 32px;
        }
        .section-label { font-size: 11px; text-transform: uppercase; letter-spacing: 1px; color: #888; margin-bottom: 4px; }
        .items-table thead { background: #2a3a5a; color: #fff; }
        .totals-row td { border: none; }
        .total-final { font-size: 1.2rem; font-weight: 700; color: #667eea; }
        .status-badge { font-size: 0.9rem; padding: 6px 16px; border-radius: 20px; }
    </style>
</head>
<body>

<div class="no-print text-center py-3" style="background:#fff;border-bottom:1px solid #dee2e6;position:sticky;top:0;z-index:100;">
    <button onclick="window.print()" class="btn btn-primary me-2"><i class="bi bi-printer me-1"></i>Print / Save as PDF</button>
    <a href="<?= e(BASE_URL) ?>/invoice/view.php?id=<?= $id ?>" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    <small class="text-muted ms-3"><i class="bi bi-info-circle me-1"></i>Use Ctrl+P (or Cmd+P) and select "Save as PDF" to download</small>
</div>

<div class="invoice-box">
    <!-- Header -->
    <div class="brand-bar d-flex justify-content-between align-items-start">
        <div>
            <h2 class="mb-1 fw-800"><?= h($siteName) ?></h2>
            <?php if ($siteAddress): ?><div class="small opacity-75"><?= h($siteAddress) ?></div><?php endif; ?>
            <?php if ($sitePhone): ?><div class="small opacity-75">Tel: <?= h($sitePhone) ?></div><?php endif; ?>
            <div class="small opacity-75"><?= h($siteEmail) ?></div>
        </div>
        <div class="text-end">
            <h1 class="fw-800 mb-1" style="font-size:2rem;letter-spacing:2px;">INVOICE</h1>
            <div class="fw-bold fs-5">#<?= h($invoice['invoice_number']) ?></div>
            <div class="mt-2">
                <?php
                $statClass = match($invoice['status']) { 'paid'=>'bg-success', 'overdue'=>'bg-danger', default=>'bg-warning text-dark' };
                ?>
                <span class="badge <?= $statClass ?> status-badge"><?= ucfirst($invoice['status']) ?></span>
            </div>
        </div>
    </div>

    <!-- Bill To + Details -->
    <div class="row mb-4">
        <div class="col-6">
            <div class="section-label">Bill To</div>
            <div class="fw-bold fs-5"><?= h($invoice['client_name']) ?></div>
            <div class="text-muted"><?= h($invoice['client_email']) ?></div>
            <?php if ($invoice['client_address']): ?><div class="text-muted small"><?= h($invoice['client_address']) ?></div><?php endif; ?>
        </div>
        <div class="col-6 text-end">
            <div class="section-label">Invoice Date</div>
            <div class="fw-semibold"><?= date('F j, Y', strtotime($invoice['created_at'])) ?></div>
            <?php if ($invoice['due_date']): ?>
            <div class="section-label mt-2">Due Date</div>
            <div class="fw-semibold"><?= date('F j, Y', strtotime($invoice['due_date'])) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Items -->
    <table class="table items-table mb-4">
        <thead>
            <tr>
                <th>Description</th>
                <th class="text-center" style="width:100px">Qty</th>
                <th class="text-end" style="width:120px">Rate</th>
                <th class="text-end" style="width:130px">Amount</th>
            </tr>
        </thead>
        <tbody>
        <?php $subtotal = 0; foreach ($items as $item):
            $line = (float)$item['quantity'] * (float)$item['unit_price'];
            $subtotal += $line;
        ?>
        <tr>
            <td><?= h($item['description']) ?></td>
            <td class="text-center"><?= number_format((float)$item['quantity'], 2) ?></td>
            <td class="text-end"><?= $currency ?><?= number_format((float)$item['unit_price'], 2) ?></td>
            <td class="text-end"><?= $currency ?><?= number_format($line, 2) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Totals -->
    <div class="row justify-content-end">
        <div class="col-md-5">
            <table class="table table-sm">
                <tr class="totals-row">
                    <td class="text-muted">Subtotal:</td>
                    <td class="text-end"><?= $currency ?><?= number_format($subtotal, 2) ?></td>
                </tr>
                <?php if (!empty($invoice['tax_amount']) && (float)$invoice['tax_amount'] > 0): ?>
                <tr class="totals-row">
                    <td class="text-muted">Tax (<?= h($invoice['tax_rate'] ?? '') ?>%):</td>
                    <td class="text-end"><?= $currency ?><?= number_format((float)$invoice['tax_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <?php if (!empty($invoice['discount_amount']) && (float)$invoice['discount_amount'] > 0): ?>
                <tr class="totals-row">
                    <td class="text-muted">Discount:</td>
                    <td class="text-end text-danger">-<?= $currency ?><?= number_format((float)$invoice['discount_amount'], 2) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="border-top">
                    <td class="total-final">Total:</td>
                    <td class="text-end total-final"><?= $currency ?><?= number_format((float)($invoice['total_amount'] ?? $invoice['amount'] ?? $subtotal), 2) ?></td>
                </tr>
            </table>
        </div>
    </div>

    <?php if (!empty($invoice['notes'])): ?>
    <div class="mt-4 p-3" style="background:#f8f9fa;border-radius:6px;">
        <div class="section-label">Notes</div>
        <div class="small"><?= nl2br(h($invoice['notes'])) ?></div>
    </div>
    <?php endif; ?>

    <div class="text-center text-muted small mt-5 pt-4 border-top">
        Thank you for your business! — <?= h($siteName) ?>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Auto trigger print dialog when opened via download button
if (window.location.search.includes('print=1')) {
    window.addEventListener('load', function() { setTimeout(window.print, 500); });
}
</script>
</body>
</html>
