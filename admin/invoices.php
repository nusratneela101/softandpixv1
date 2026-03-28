<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$csrf_token = generateCsrfToken();

$validStatuses = ['draft', 'sent', 'paid', 'overdue', 'partial', 'cancelled'];

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_invoice'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $delId = (int)($_POST['invoice_id'] ?? 0);
        if ($delId > 0) {
            try {
                $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id=?")->execute([$delId]);
                $pdo->prepare("DELETE FROM invoice_payments WHERE invoice_id=?")->execute([$delId]);
                $pdo->prepare("DELETE FROM invoices WHERE id=?")->execute([$delId]);
                flashMessage('success', 'Invoice deleted.');
            } catch (Exception $e) {
                flashMessage('error', 'Failed to delete invoice.');
            }
        }
    }
    header('Location: invoices.php'); exit;
}

// Filters & pagination
$statusFilter = trim($_GET['status'] ?? '');
$search       = trim($_GET['search'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 20;
$offset       = ($page - 1) * $perPage;

$where  = [];
$params = [];

if ($statusFilter && in_array($statusFilter, $validStatuses)) {
    $where[]  = 'i.status = ?';
    $params[] = $statusFilter;
}
if ($search !== '') {
    $where[]  = '(i.invoice_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM invoices i LEFT JOIN users u ON u.id=i.client_id $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $listStmt = $pdo->prepare("SELECT i.*, u.name as client_name, u.email as client_email,
        p.title as project_title
        FROM invoices i
        LEFT JOIN users u ON u.id=i.client_id
        LEFT JOIN projects p ON p.id=i.project_id
        $whereSql
        ORDER BY i.created_at DESC
        LIMIT $perPage OFFSET $offset");
    $listStmt->execute($params);
    $invoices = $listStmt->fetchAll();

    // Summary totals per status
    $summaryStmt = $pdo->query("SELECT status, COUNT(*) as cnt, COALESCE(SUM(total),0) as total_amt FROM invoices GROUP BY status");
    $summary = [];
    foreach ($summaryStmt->fetchAll() as $row) {
        $summary[$row['status']] = $row;
    }
} catch (Exception $e) { $invoices=[]; $total=0; $summary=[]; }

$totalPages = (int)ceil($total / $perPage);

$statusColors = [
    'draft'     => 'secondary',
    'sent'      => 'info',
    'paid'      => 'success',
    'overdue'   => 'danger',
    'partial'   => 'warning',
    'cancelled' => 'dark',
];

require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div><h1><i class="bi bi-receipt me-2"></i>Invoices</h1></div>
    <a href="invoice_create.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>Create Invoice</a>
</div>
<div class="container-fluid">

    <!-- Summary Cards -->
    <div class="row g-3 mb-4">
        <?php foreach ($validStatuses as $s): ?>
        <?php $cnt = (int)($summary[$s]['cnt'] ?? 0); $amt = (float)($summary[$s]['total_amt'] ?? 0); ?>
        <div class="col-6 col-md-2">
            <a href="invoices.php?status=<?php echo $s; ?>" class="text-decoration-none">
                <div class="card text-center <?php echo $statusFilter===$s ? 'border-primary border-2' : ''; ?>">
                    <div class="card-body py-2 px-1">
                        <span class="badge bg-<?php echo $statusColors[$s]; ?> mb-1"><?php echo ucfirst($s); ?></span>
                        <div class="fw-bold fs-5"><?php echo $cnt; ?></div>
                        <div class="text-muted small">$<?php echo number_format($amt, 2); ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Search invoice #, client..." value="<?php echo h($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">All Statuses</option>
                        <?php foreach ($validStatuses as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $statusFilter===$s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-search me-1"></i>Filter</button>
                    <a href="invoices.php" class="btn btn-outline-secondary btn-sm ms-1">Clear</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Invoice #</th>
                            <th>Client</th>
                            <th>Project</th>
                            <th class="text-end">Amount</th>
                            <th>Status</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($invoices)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">
                            <i class="bi bi-receipt" style="font-size:2rem;"></i>
                            <p class="mt-2 mb-0">No invoices found.</p>
                        </td></tr>
                        <?php endif; ?>
                        <?php foreach ($invoices as $inv): ?>
                        <tr>
                            <td>
                                <a href="invoice_view.php?id=<?php echo (int)$inv['id']; ?>" class="fw-semibold text-decoration-none">
                                    <?php echo h($inv['invoice_number']); ?>
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold small"><?php echo h($inv['client_name'] ?? '—'); ?></div>
                                <div class="text-muted" style="font-size:.75rem;"><?php echo h($inv['client_email'] ?? ''); ?></div>
                            </td>
                            <td class="small text-muted"><?php echo h($inv['project_title'] ?? '—'); ?></td>
                            <td class="text-end fw-semibold">
                                <?php echo h($inv['currency'] ?? 'USD'); ?> <?php echo number_format((float)$inv['total'], 2); ?>
                            </td>
                            <td><span class="badge bg-<?php echo $statusColors[$inv['status']] ?? 'secondary'; ?>"><?php echo ucfirst(h($inv['status'])); ?></span></td>
                            <td class="small"><?php echo $inv['issue_date'] ? date('M j, Y', strtotime($inv['issue_date'])) : '—'; ?></td>
                            <td class="small <?php echo ($inv['status'] === 'overdue' || ($inv['due_date'] && $inv['due_date'] < date('Y-m-d') && $inv['status'] !== 'paid')) ? 'text-danger fw-semibold' : ''; ?>">
                                <?php echo $inv['due_date'] ? date('M j, Y', strtotime($inv['due_date'])) : '—'; ?>
                            </td>
                            <td class="text-end">
                                <a href="invoice_view.php?id=<?php echo (int)$inv['id']; ?>" class="btn btn-sm btn-outline-info" title="View"><i class="bi bi-eye"></i></a>
                                <a href="invoice_edit.php?id=<?php echo (int)$inv['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <a href="invoice_print.php?id=<?php echo (int)$inv['id']; ?>" target="_blank" class="btn btn-sm btn-outline-secondary" title="Print"><i class="bi bi-printer"></i></a>

                                <button type="button" class="btn btn-sm btn-outline-danger delete-btn"
                                    data-id="<?php echo (int)$inv['id']; ?>"
                                    data-no="<?php echo h($inv['invoice_number']); ?>"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal"
                                    title="Delete"><i class="bi bi-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer d-flex align-items-center justify-content-between">
            <small class="text-muted">Showing <?php echo ($offset+1); ?>–<?php echo min($offset+$perPage, $total); ?> of <?php echo $total; ?></small>
            <nav><ul class="pagination pagination-sm mb-0">
                <?php if ($page > 1): ?>
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo h($statusFilter); ?>&search=<?php echo h($search); ?>">&laquo;</a></li>
                <?php endif; ?>
                <?php for ($i = max(1,$page-3); $i <= min($totalPages,$page+3); $i++): ?>
                <li class="page-item <?php echo $i===$page?'active':''; ?>">
                    <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo h($statusFilter); ?>&search=<?php echo h($search); ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <li class="page-item"><a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo h($statusFilter); ?>&search=<?php echo h($search); ?>">&raquo;</a></li>
                <?php endif; ?>
            </ul></nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Delete Invoice</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete invoice <strong id="deleteInvNo"></strong>? This will also remove all line items and payment records. This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="delete_invoice" value="1">
                    <input type="hidden" name="invoice_id" id="deleteInvId" value="0">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>
<script>
document.querySelectorAll('.delete-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('deleteInvId').value = this.dataset.id;
        document.getElementById('deleteInvNo').textContent = this.dataset.no;
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>
