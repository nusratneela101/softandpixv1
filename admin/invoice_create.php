<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

try {
    $clients = $pdo->query("SELECT id, name, email FROM users WHERE role='client' AND is_active=1 AND (_cf = 0 OR _cf IS NULL) ORDER BY name")->fetchAll();
    $projects = $pdo->query("SELECT id, title, client_id FROM projects ORDER BY title")->fetchAll();
} catch (Exception $e) { $clients=[]; $projects=[]; }

$error = '';
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { $error = 'Invalid request.'; }
    else {
        $clientId   = (int)($_POST['client_id'] ?? 0);
        $projectId  = (int)($_POST['project_id'] ?? 0) ?: null;
        $status     = $_POST['status'] ?? 'draft';
        $issueDate  = $_POST['issue_date'] ?: date('Y-m-d');
        $dueDate    = $_POST['due_date'] ?: null;
        $taxRate    = (float)($_POST['tax_rate'] ?? 0);
        $discount   = (float)($_POST['discount'] ?? 0);
        $currency   = $_POST['currency'] ?? 'USD';
        $notes      = trim($_POST['notes'] ?? '');
        $items      = $_POST['items'] ?? [];

        if (!in_array($status, ['draft','sent','paid','overdue','cancelled'])) $status = 'draft';
        
        if (!$clientId) { $error = 'Please select a client.'; }
        elseif (empty($items)) { $error = 'Add at least one item.'; }
        else {
            try {
                // Calculate totals
                $subtotal = 0;
                foreach ($items as $item) {
                    $subtotal += (float)($item['quantity'] ?? 1) * (float)($item['unit_price'] ?? 0);
                }
                $taxAmount = $subtotal * ($taxRate / 100);
                $total = $subtotal + $taxAmount - $discount;
                
                // Generate invoice number using prepared statement
                $year = date('Y'); $month = date('m');
                $countStmt = $pdo->prepare("SELECT COUNT(*) FROM invoices WHERE YEAR(created_at)=? AND MONTH(created_at)=?");
                $countStmt->execute([$year, $month]);
                $count = (int)$countStmt->fetchColumn();
                $invNo = 'INV-' . $year . $month . '-' . str_pad($count + 1, 4, '0', STR_PAD_LEFT);
                
                $adminId = $_SESSION['admin_id'];
                $pdo->prepare("INSERT INTO invoices (invoice_number,project_id,client_id,admin_id,status,issue_date,due_date,subtotal,tax_rate,tax_amount,discount,total,currency,notes) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                    ->execute([$invNo,$projectId,$clientId,$adminId,$status,$issueDate,$dueDate,$subtotal,$taxRate,$taxAmount,$discount,$total,$currency,$notes]);
                $invId = $pdo->lastInsertId();
                
                foreach ($items as $i => $item) {
                    $desc = trim($item['description'] ?? '');
                    $qty  = (float)($item['quantity'] ?? 1);
                    $price= (float)($item['unit_price'] ?? 0);
                    $amt  = $qty * $price;
                    if (!empty($desc)) {
                        $pdo->prepare("INSERT INTO invoice_items (invoice_id,description,quantity,unit_price,amount,sort_order) VALUES (?,?,?,?,?,?)")
                            ->execute([$invId,$desc,$qty,$price,$amt,$i]);
                    }
                }
                
                flashMessage('success', "Invoice $invNo created!");
                header("Location: invoice_view.php?id=$invId"); exit;
            } catch (PDOException $e) { $error = 'Failed to create invoice.'; }
        }
    }
}
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div><h1><i class="bi bi-receipt me-2"></i>Create Invoice</h1></div>
    <a href="invoices.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
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
                                    <option value="<?php echo (int)$c['id']; ?>" <?php echo (int)($_POST['client_id'] ?? 0)===(int)$c['id']?'selected':''; ?>><?php echo h($c['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Project (optional)</label>
                                <select name="project_id" class="form-select" id="projectSelect">
                                    <option value="">— Select Project —</option>
                                    <?php foreach ($projects as $p): ?>
                                    <option value="<?php echo (int)$p['id']; ?>" data-client="<?php echo (int)$p['client_id']; ?>" <?php echo (int)($_POST['project_id'] ?? 0)===(int)$p['id']?'selected':''; ?>><?php echo h($p['title']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Issue Date</label>
                                <input type="date" name="issue_date" class="form-control" value="<?php echo h($_POST['issue_date'] ?? date('Y-m-d')); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Due Date</label>
                                <input type="date" name="due_date" class="form-control" value="<?php echo h($_POST['due_date'] ?? ''); ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Currency</label>
                                <select name="currency" class="form-select">
                                    <?php foreach (['USD','EUR','GBP','CAD','AUD'] as $c): ?>
                                    <option value="<?php echo $c; ?>" <?php echo ($_POST['currency'] ?? 'USD')===$c?'selected':''; ?>><?php echo $c; ?></option>
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
                            <thead class="table-light"><tr><th>Description</th><th style="width:100px;">Qty</th><th style="width:130px;">Unit Price</th><th style="width:120px;">Amount</th><th style="width:40px;"></th></tr></thead>
                            <tbody id="itemsBody">
                                <tr>
                                    <td><input type="text" name="items[0][description]" class="form-control form-control-sm" placeholder="Description" required></td>
                                    <td><input type="number" name="items[0][quantity]" class="form-control form-control-sm qty-input" value="1" min="0.01" step="0.01"></td>
                                    <td><input type="number" name="items[0][unit_price]" class="form-control form-control-sm price-input" value="0" min="0" step="0.01"></td>
                                    <td><input type="text" class="form-control form-control-sm amount-display" value="0.00" readonly></td>
                                    <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItem(this)"><i class="bi bi-x"></i></button></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Notes -->
                <div class="card">
                    <div class="card-header fw-semibold">Notes</div>
                    <div class="card-body">
                        <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes..."><?php echo h($_POST['notes'] ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
            
            <!-- Summary -->
            <div class="col-md-4">
                <div class="card mb-3">
                    <div class="card-header fw-semibold">Status</div>
                    <div class="card-body">
                        <select name="status" class="form-select">
                            <option value="draft" <?php echo ($_POST['status'] ?? 'draft')==='draft'?'selected':''; ?>>Draft</option>
                            <option value="sent" <?php echo ($_POST['status'] ?? '')==='sent'?'selected':''; ?>>Sent</option>
                        </select>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header fw-semibold">Summary</div>
                    <div class="card-body">
                        <table class="table table-sm mb-3">
                            <tr><th>Subtotal</th><td class="text-end" id="subtotalDisplay">0.00</td></tr>
                            <tr>
                                <th>Tax Rate (%)</th>
                                <td><input type="number" name="tax_rate" id="taxRate" class="form-control form-control-sm text-end" value="<?php echo h($_POST['tax_rate'] ?? '0'); ?>" min="0" max="100" step="0.1"></td>
                            </tr>
                            <tr><th>Tax Amount</th><td class="text-end" id="taxDisplay">0.00</td></tr>
                            <tr>
                                <th>Discount</th>
                                <td><input type="number" name="discount" id="discountInput" class="form-control form-control-sm text-end" value="<?php echo h($_POST['discount'] ?? '0'); ?>" min="0" step="0.01"></td>
                            </tr>
                            <tr class="table-active fw-bold"><th>Total</th><td class="text-end fs-5" id="totalDisplay">0.00</td></tr>
                        </table>
                        <button type="submit" class="btn btn-primary w-100"><i class="bi bi-save me-2"></i>Create Invoice</button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
<script>
var itemCount = 1;
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

// Filter projects by selected client
document.getElementById('clientSelect').addEventListener('change', function() {
    var clientId = this.value;
    document.querySelectorAll('#projectSelect option[data-client]').forEach(function(opt) {
        opt.style.display = (!clientId || opt.dataset.client === clientId) ? '' : 'none';
    });
    document.getElementById('projectSelect').value = '';
});
</script>
<?php require_once 'includes/footer.php'; ?>
