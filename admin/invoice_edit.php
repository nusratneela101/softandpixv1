<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: invoices.php'); exit; }

$error = '';
$csrf_token = generateCsrfToken();

try {
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id=?");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
    if (!$invoice) { header('Location: invoices.php'); exit; }

    $itemsStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY sort_order ASC");
    $itemsStmt->execute([$id]);
    $existingItems = $itemsStmt->fetchAll();

    $clients  = $pdo->query("SELECT id, name, email FROM users WHERE role='client' AND is_active=1 ORDER BY name")->fetchAll();
    $projects = $pdo->query("SELECT id, title, client_id FROM projects ORDER BY title")->fetchAll();
} catch (Exception $e) {
    header('Location: invoices.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $clientId  = (int)($_POST['client_id'] ?? 0);
        $projectId = (int)($_POST['project_id'] ?? 0) ?: null;
        $status    = $_POST['status'] ?? 'draft';
        $issueDate = $_POST['issue_date'] ?: date('Y-m-d');
        $dueDate   = $_POST['due_date'] ?: null;
        $taxRate   = (float)($_POST['tax_rate'] ?? 0);
        $discount  = (float)($_POST['discount'] ?? 0);
        $currency  = $_POST['currency'] ?? 'USD';
        $notes     = trim($_POST['notes'] ?? '');
        $items     = $_POST['items'] ?? [];

        $validStatuses = ['draft', 'sent', 'paid', 'overdue', 'cancelled'];
        if (!in_array($status, $validStatuses)) $status = 'draft';

        if (!$clientId) {
            $error = 'Please select a client.';
        } elseif (empty($items)) {
            $error = 'Add at least one item.';
        } else {
            try {
                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += (float)($item['quantity'] ?? 1) * (float)($item['unit_price'] ?? 0);
                }
                $taxAmount = $subtotal * ($taxRate / 100);
                $total     = $subtotal + $taxAmount - $discount;

                $pdo->prepare("UPDATE invoices SET client_id=?,project_id=?,status=?,issue_date=?,due_date=?,subtotal=?,tax_rate=?,tax_amount=?,discount=?,total=?,currency=?,notes=?,updated_at=NOW() WHERE id=?")
                    ->execute([$clientId,$projectId,$status,$issueDate,$dueDate,$subtotal,$taxRate,$taxAmount,$discount,$total,$currency,$notes,$id]);

                // Rebuild line items
                $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$id]);
                foreach ($items as $i => $item) {
                    $desc  = trim($item['description'] ?? '');
                    $qty   = (float)($item['quantity'] ?? 1);
                    $price = (float)($item['unit_price'] ?? 0);
                    $amt   = $qty * $price;
                    if (!empty($desc)) {
                        $pdo->prepare("INSERT INTO invoice_items (invoice_id,description,quantity,unit_price,amount,sort_order) VALUES (?,?,?,?,?,?)")
                            ->execute([$id,$desc,$qty,$price,$amt,$i]);
                    }
                }

                flashMessage('success', 'Invoice updated successfully.');
                header("Location: invoice_view.php?id=$id"); exit;
            } catch (PDOException $e) {
                $error = 'Failed to update invoice.';
            }
        }
    }
}

// Use POST data on validation failure, otherwise use DB data
$d = ($_SERVER['REQUEST_METHOD'] === 'POST' && $error) ? array_merge($invoice, $_POST) : $invoice;
$editItems = ($_SERVER['REQUEST_METHOD'] === 'POST' && $error) ? $_POST['items'] ?? $existingItems : $existingItems;

require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-pencil-square me-2"></i>Edit Invoice</h1>
        <span class="text-muted small"><?php echo h($invoice['invoice_number']); ?></span>
    </div>
    <div class="d-flex gap-2">
        <a href="invoice_view.php?id=<?php echo $id; ?>" class="btn btn-outline-info"><i class="bi bi-eye me-1"></i>View</a>
        <a href="invoices.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>
<div class="container-fluid">
    <?php if ($error): ?><div class="alert alert-danger"><?php echo h($error); ?></div><?php endif; ?>
    <form method="POST" id="invoiceForm">
        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
        <div class="row g-4">
            <div class="col-md-8">
                <div class="card mb-3">
                    <div class="card-header fw-semibold">Invoice Details</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Client *</label>
                                <select name="client_id" class="form-select" id="clientSelect" required>
                                    <option value="">— Select Client —</option>
                                    <?php foreach ($clients as $c): ?>
                                    <option value="<?php echo (int)$c['id']; ?>" <?php echo (int)$d['client_id']===(int)$c['id']?'selected':''; ?>>
                                        <?php echo h($c['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Project (optional)</label>
                                <select name="project_id" class="form-select" id="projectSelect">
                                    <option value="">— Select Project —</option>
                                    <?php foreach ($projects as $p): ?>
                                    <option value="<?php echo (int)$p['id']; ?>" data-client="<?php echo (int)$p['client_id']; ?>" <?php echo (int)($d['project_id'] ?? 0)===(int)$p['id']?'selected':''; ?>>
                                        <?php echo h($p['title']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Issue Date</label>
                                <input type="date" name="issue_date" class="form-control" value="<?php echo h($d['issue_date'] ?? date('Y-m-d')); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Due Date</label>
                                <input type="date" name="due_date" class="form-control" value="<?php echo h($d['due_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Currency</label>
                                <select name="currency" class="form-select">
                                    <?php foreach (['USD','EUR','GBP','CAD','AUD'] as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo ($d['currency'] ?? 'USD')===$c?'selected':''; ?>><?php echo $c; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Line Items -->
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between fw-semibold">
                        <span>Line Items</span>
                        <button type="button" class="btn btn-sm btn-primary" onclick="addItem()"><i class="bi bi-plus"></i> Add Item</button>
                    </div>
                    <div class="card-body p-0">
                        <table class="table mb-0" id="itemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Description</th>
                                    <th style="width:100px;">Qty</th>
                                    <th style="width:130px;">Unit Price</th>
                                    <th style="width:120px;">Amount</th>
                                    <th style="width:40px;"></th>
                                </tr>
                            </thead>
                            <tbody id="itemsBody">
                                <?php
                                // Render existing items; for POST errors use array format, for DB use object
                                $rowIdx = 0;
                                foreach ($editItems as $item):
                                    if (is_array($item) && isset($item['description'])) {
                                        $desc  = $item['description'] ?? '';
                                        $qty   = $item['quantity'] ?? 1;
                                        $price = $item['unit_price'] ?? 0;
                                    } else {
                                        $desc  = $item['description'] ?? '';
                                        $qty   = $item['quantity'] ?? 1;
                                        $price = $item['unit_price'] ?? 0;
                                    }
                                    $amt = (float)$qty * (float)$price;
                                ?>
                                <tr>
                                    <td><input type="text" name="items[<?php echo $rowIdx; ?>][description]" class="form-control form-control-sm" value="<?php echo h($desc); ?>" placeholder="Description" required></td>
                                    <td><input type="number" name="items[<?php echo $rowIdx; ?>][quantity]" class="form-control form-control-sm qty-input" value="<?php echo h($qty); ?>" min="0.01" step="0.01"></td>
                                    <td><input type="number" name="items[<?php echo $rowIdx; ?>][unit_price]" class="form-control form-control-sm price-input" value="<?php echo h($price); ?>" min="0" step="0.01"></td>
                                    <td><input type="text" class="form-control form-control-sm amount-display" value="<?php echo number_format($amt, 2); ?>" readonly></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(this)"><i class="bi bi-x"></i></button></td>
                                </tr>
                                <?php $rowIdx++; endforeach; ?>
                                <?php if (empty($editItems)): ?>
                                <tr>
                                    <td><input type="text" name="items[0][description]" class="form-control form-control-sm" placeholder="Description" required></td>
                                    <td><input type="number" name="items[0][quantity]" class="form-control form-control-sm qty-input" value="1" min="0.01" step="0.01"></td>
                                    <td><input type="number" name="items[0][unit_price]" class="form-control form-control-sm price-input" value="0" min="0" step="0.01"></td>
                                    <td><input type="text" class="form-control form-control-sm amount-display" value="0.00" readonly></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(this)"><i class="bi bi-x"></i></button></td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Notes -->
                <div class="card">
                    <div class="card-header fw-semibold">Notes</div>
                    <div class="card-body">
                        <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes..."><?php echo h($d['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header fw-semibold">Status</div>
                    <div class="card-body">
                        <select name="status" class="form-select">
                            <?php foreach (['draft'=>'Draft','sent'=>'Sent','paid'=>'Paid','overdue'=>'Overdue','cancelled'=>'Cancelled'] as $val => $label): ?>
                            <option value="<?php echo $val; ?>" <?php echo ($d['status'] ?? 'draft')===$val?'selected':''; ?>><?php echo $label; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header fw-semibold">Summary</div>
                    <div class="card-body">
                        <table class="table table-sm mb-3">
                            <tr><th>Subtotal</th><td class="text-end" id="subtotalDisplay"><?php echo number_format((float)($d['subtotal'] ?? 0), 2); ?></td></tr>
                            <tr>
                                <th>Tax Rate (%)</th>
                                <td><input type="number" name="tax_rate" id="taxRate" class="form-control form-control-sm text-end" value="<?php echo h($d['tax_rate'] ?? '0'); ?>" min="0" max="100" step="0.1"></td>
                            </tr>
                            <tr><th>Tax Amount</th><td class="text-end" id="taxDisplay"><?php echo number_format((float)($d['tax_amount'] ?? 0), 2); ?></td></tr>
                            <tr>
                                <th>Discount</th>
                                <td><input type="number" name="discount" id="discountInput" class="form-control form-control-sm text-end" value="<?php echo h($d['discount'] ?? '0'); ?>" min="0" step="0.01"></td>
                            </tr>
                            <tr class="table-active fw-bold"><th>Total</th><td class="text-end fs-5" id="totalDisplay"><?php echo number_format((float)($d['total'] ?? 0), 2); ?></td></tr>
                        </table>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-2"></i>Save Changes</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<script>
var itemCount = <?php echo max($rowIdx ?? 1, 1); ?>;
function addItem() {
    var i = itemCount++;
    var row = '<tr><td><input type="text" name="items['+i+'][description]" class="form-control form-control-sm" placeholder="Description" required></td><td><input type="number" name="items['+i+'][quantity]" class="form-control form-control-sm qty-input" value="1" min="0.01" step="0.01"></td><td><input type="number" name="items['+i+'][unit_price]" class="form-control form-control-sm price-input" value="0" min="0" step="0.01"></td><td><input type="text" class="form-control form-control-sm amount-display" value="0.00" readonly></td><td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(this)"><i class="bi bi-x"></i></button></td></tr>';
    document.getElementById('itemsBody').insertAdjacentHTML('beforeend', row);
    bindCalc();
}
function removeItem(btn) {
    var rows = document.getElementById('itemsBody').querySelectorAll('tr');
    if (rows.length > 1) { btn.closest('tr').remove(); calcTotals(); }
}
function bindCalc() {
    document.querySelectorAll('.qty-input, .price-input').forEach(function(el) {
        el.oninput = function() {
            var row = this.closest('tr');
            var qty = parseFloat(row.querySelector('.qty-input').value) || 0;
            var price = parseFloat(row.querySelector('.price-input').value) || 0;
            row.querySelector('.amount-display').value = (qty * price).toFixed(2);
            calcTotals();
        };
    });
    document.getElementById('taxRate').oninput = calcTotals;
    document.getElementById('discountInput').oninput = calcTotals;
}
function calcTotals() {
    var subtotal = 0;
    document.querySelectorAll('#itemsBody tr').forEach(function(row) {
        var qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        var price = parseFloat(row.querySelector('.price-input').value) || 0;
        subtotal += qty * price;
    });
    var taxRate = parseFloat(document.getElementById('taxRate').value) || 0;
    var discount = parseFloat(document.getElementById('discountInput').value) || 0;
    var taxAmt = subtotal * taxRate / 100;
    var total = subtotal + taxAmt - discount;
    document.getElementById('subtotalDisplay').textContent = subtotal.toFixed(2);
    document.getElementById('taxDisplay').textContent = taxAmt.toFixed(2);
    document.getElementById('totalDisplay').textContent = total.toFixed(2);
}
bindCalc();

// Filter projects by client
document.getElementById('clientSelect').addEventListener('change', function() {
    var clientId = this.value;
    document.querySelectorAll('#projectSelect option[data-client]').forEach(function(opt) {
        opt.style.display = (!clientId || opt.dataset.client === clientId) ? '' : 'none';
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>
