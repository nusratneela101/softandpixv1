<?php
/**
 * Admin — Recurring Invoices Management
 */
require_once dirname(__DIR__) . '/config/db.php';
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/language.php';
require_once dirname(__DIR__) . '/includes/activity_logger.php';
requireAdmin();

$page_title = __('recurring_invoices');
$admin_id   = $_SESSION['admin_id'];

// Handle status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['change_status'])) {
    $id     = (int)$_POST['ri_id'];
    $status = $_POST['new_status'];
    if (in_array($status, ['active','paused','cancelled'], true)) {
        try {
            $pdo->prepare("UPDATE recurring_invoices SET status=? WHERE id=?")->execute([$status, $id]);
            log_activity($pdo, $admin_id, 'recurring_invoice_' . $status, "Recurring invoice #$id set to $status", 'recurring_invoice', $id);
        } catch (Exception $e) {}
    }
    header('Location: recurring_invoices.php');
    exit;
}

// Handle create
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['create_recurring'])) {
    $client_id  = (int)$_POST['client_id'];
    $project_id = ($_POST['project_id'] ?? '') ?: null;
    $frequency  = $_POST['frequency'];
    $start_date = $_POST['start_date'];
    $end_date   = ($_POST['end_date'] ?? '') ?: null;
    $notes      = mb_substr($_POST['notes'] ?? '', 0, 1000);
    $tax_rate   = min(100, max(0, (float)$_POST['tax_rate']));
    $line_items = [];

    $titles = $_POST['item_title'] ?? [];
    $qtys   = $_POST['item_qty']   ?? [];
    $prices = $_POST['item_price'] ?? [];

    foreach ($titles as $i => $title) {
        if (empty($title)) continue;
        $line_items[] = [
            'title' => mb_substr($title, 0, 255),
            'qty'   => max(1, (int)($qtys[$i] ?? 1)),
            'price' => max(0, (float)($prices[$i] ?? 0)),
        ];
    }

    if ($client_id && !empty($frequency) && !empty($start_date) && !empty($line_items)) {
        $freqs = ['weekly','monthly','quarterly','yearly'];
        if (!in_array($frequency, $freqs, true)) $frequency = 'monthly';
        try {
            $pdo->prepare(
                "INSERT INTO recurring_invoices (client_id, project_id, frequency, next_generate_date, end_date, status, line_items, tax_rate, notes, created_by)
                 VALUES (?,?,?,?,?,'active',?,?,?,?)"
            )->execute([$client_id, $project_id, $frequency, $start_date, $end_date, json_encode($line_items), $tax_rate, $notes, $admin_id]);
            log_activity($pdo, $admin_id, 'recurring_invoice_created', "Created recurring invoice for client #$client_id", 'recurring_invoice', null);
            flashMessage('success', 'Recurring invoice created.');
        } catch (Exception $e) {
            flashMessage('error', 'Error: ' . $e->getMessage());
        }
    } else {
        flashMessage('error', __('required_fields'));
    }
    header('Location: recurring_invoices.php');
    exit;
}

// Load data
try {
    $recurring = $pdo->query(
        "SELECT ri.*, u.name as client_name, p.name as project_name,
                (SELECT COUNT(*) FROM recurring_invoice_history h WHERE h.recurring_invoice_id=ri.id) as generated_count
         FROM recurring_invoices ri
         JOIN users u ON u.id=ri.client_id
         LEFT JOIN projects p ON p.id=ri.project_id
         ORDER BY ri.created_at DESC"
    )->fetchAll();
} catch (Exception $e) { $recurring = []; }

try {
    $clients  = $pdo->query("SELECT id, name FROM users WHERE role='client' ORDER BY name")->fetchAll();
    $projects = $pdo->query("SELECT id, name FROM projects ORDER BY name")->fetchAll();
} catch (Exception $e) { $clients = []; $projects = []; }

require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="fas fa-redo me-2"></i><?= __('recurring_invoices') ?></h1>
    </div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="fas fa-plus me-1"></i><?= __('create_recurring') ?>
    </button>
</div>
<div class="container-fluid">
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th><?= __('client') ?></th>
                        <th><?= __('project') ?></th>
                        <th><?= __('frequency') ?></th>
                        <th><?= __('next_generate_date') ?></th>
                        <th><?= __('status') ?></th>
                        <th><?= __('generation_history') ?></th>
                        <th><?= __('actions') ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($recurring)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted"><?= __('no_records') ?></td></tr>
                <?php else: ?>
                    <?php foreach ($recurring as $r): ?>
                    <tr>
                        <td><?= h($r['client_name']) ?></td>
                        <td><?= h($r['project_name'] ?? '—') ?></td>
                        <td><span class="badge bg-info text-dark"><?= h(ucfirst($r['frequency'])) ?></span></td>
                        <td><?= h($r['next_generate_date']) ?></td>
                        <td>
                            <?php $sc = ['active'=>'success','paused'=>'warning','cancelled'=>'danger']; ?>
                            <span class="badge bg-<?= $sc[$r['status']] ?? 'secondary' ?>"><?= h(ucfirst($r['status'])) ?></span>
                        </td>
                        <td><?= (int)$r['generated_count'] ?> invoices</td>
                        <td>
                            <?php if ($r['status'] === 'active'): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="ri_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="new_status" value="paused">
                                <button name="change_status" value="1" class="btn btn-sm btn-warning"><?= __('pause') ?></button>
                            </form>
                            <?php elseif ($r['status'] === 'paused'): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="ri_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="new_status" value="active">
                                <button name="change_status" value="1" class="btn btn-sm btn-success"><?= __('resume') ?></button>
                            </form>
                            <?php endif; ?>
                            <?php if ($r['status'] !== 'cancelled'): ?>
                            <form method="post" class="d-inline">
                                <input type="hidden" name="ri_id" value="<?= $r['id'] ?>">
                                <input type="hidden" name="new_status" value="cancelled">
                                <button name="change_status" value="1" class="btn btn-sm btn-danger" onclick="return confirm('Cancel?')"><?= __('cancel_invoice') ?></button>
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Modal -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h5 class="modal-title"><?= __('create_recurring') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= __('client') ?> *</label>
                            <select name="client_id" class="form-select" required>
                                <option value="">—</option>
                                <?php foreach ($clients as $c): ?>
                                <option value="<?= $c['id'] ?>"><?= h($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('project') ?></label>
                            <select name="project_id" class="form-select">
                                <option value="">—</option>
                                <?php foreach ($projects as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= h($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('frequency') ?> *</label>
                            <select name="frequency" class="form-select" required>
                                <option value="weekly"><?= __('weekly') ?></option>
                                <option value="monthly" selected><?= __('monthly') ?></option>
                                <option value="quarterly"><?= __('quarterly') ?></option>
                                <option value="yearly"><?= __('yearly') ?></option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('start_date') ?> *</label>
                            <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('end_date') ?> (<?= __('status_active') ?>)</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>
                        <!-- Line Items -->
                        <div class="col-12">
                            <label class="form-label fw-bold"><?= __('invoices') ?></label>
                            <div id="lineItems">
                                <div class="row g-2 mb-2 line-item">
                                    <div class="col-6"><input type="text" name="item_title[]" class="form-control" placeholder="Description" required></div>
                                    <div class="col-2"><input type="number" name="item_qty[]" class="form-control" placeholder="Qty" min="1" value="1"></div>
                                    <div class="col-3"><input type="number" name="item_price[]" class="form-control" placeholder="Price" step="0.01" min="0"></div>
                                    <div class="col-1"><button type="button" class="btn btn-outline-danger" onclick="this.closest('.line-item').remove()">✕</button></div>
                                </div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary mt-2" id="addLineItem">
                                <i class="fas fa-plus me-1"></i><?= __('add') ?>
                            </button>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label"><?= __('tax') ?> (%)</label>
                            <input type="number" name="tax_rate" class="form-control" value="0" min="0" max="100" step="0.01">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= __('notes') ?></label>
                            <textarea name="notes" class="form-control" rows="2"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('cancel') ?></button>
                    <button type="submit" name="create_recurring" value="1" class="btn btn-primary"><?= __('create') ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('addLineItem').addEventListener('click', function() {
    var row = document.createElement('div');
    row.className = 'row g-2 mb-2 line-item';
    row.innerHTML = '<div class="col-6"><input type="text" name="item_title[]" class="form-control" placeholder="Description" required></div>'
        + '<div class="col-2"><input type="number" name="item_qty[]" class="form-control" value="1" min="1"></div>'
        + '<div class="col-3"><input type="number" name="item_price[]" class="form-control" step="0.01" min="0"></div>'
        + '<div class="col-1"><button type="button" class="btn btn-outline-danger" onclick="this.closest(\'.line-item\').remove()">✕</button></div>';
    document.getElementById('lineItems').appendChild(row);
});
</script>

<?php require_once 'includes/footer.php'; ?>
