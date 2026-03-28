<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$csrf_token = generateCsrfToken();

// Approve or reject a deadline request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid request.'); header('Location: deadline_requests.php'); exit;
    }

    $reqId  = (int)($_POST['request_id'] ?? 0);
    $action = $_POST['action'] ?? '';
    $note   = trim($_POST['admin_note'] ?? '');

    if ($reqId > 0 && in_array($action, ['approve', 'reject'])) {
        try {
            $reqStmt = $pdo->prepare("SELECT * FROM deadline_extension_requests WHERE id = ?");
            $reqStmt->execute([$reqId]);
            $req = $reqStmt->fetch();

            if ($req) {
                $newStatus = $action === 'approve' ? 'approved' : 'rejected';

                $pdo->prepare("UPDATE deadline_extension_requests SET status=?, admin_note=? WHERE id=?")
                    ->execute([$newStatus, $note, $reqId]);

                // If approved, update the project deadline
                if ($action === 'approve' && !empty($req['requested_deadline'])) {
                    $pdo->prepare("UPDATE projects SET deadline=? WHERE id=?")
                        ->execute([$req['requested_deadline'], $req['project_id']]);
                }

                flashMessage('success', 'Request ' . $newStatus . ' successfully.');
            }
        } catch (Exception $e) {
            flashMessage('error', 'Operation failed.');
        }
    }
    header('Location: deadline_requests.php'); exit;
}

$statusF = trim($_GET['status'] ?? 'pending');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$validStatuses = ['pending', 'approved', 'rejected', ''];
if (!in_array($statusF, $validStatuses)) { $statusF = 'pending'; }

$where  = "WHERE 1=1";
$params = [];
if ($statusF !== '') { $where .= " AND dr.status = ?"; $params[] = $statusF; }

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM deadline_extension_requests dr $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT dr.*, p.title AS project_title, u.name AS requester_name, u.email AS requester_email FROM deadline_extension_requests dr LEFT JOIN projects p ON p.id = dr.project_id LEFT JOIN users u ON u.id = dr.developer_id $where ORDER BY dr.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $requests = $stmt->fetchAll();

    // Badge counts
    $pendingCount = (int)$pdo->query("SELECT COUNT(*) FROM deadline_extension_requests WHERE status='pending'")->fetchColumn();
} catch (Exception $e) { $requests = []; $total = 0; $pendingCount = 0; }

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-calendar-x me-2"></i>Deadline Extension Requests</h1>
        <p>Review and manage deadline extension requests from clients and developers</p>
    </div>
    <?php if ($pendingCount > 0): ?>
    <span class="badge bg-warning text-dark fs-6"><i class="bi bi-clock me-1"></i><?php echo $pendingCount; ?> Pending</span>
    <?php endif; ?>
</div>
<div class="container-fluid">
    <!-- Status Tabs -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex gap-2 flex-wrap">
                <?php foreach (['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', '' => 'secondary'] as $s => $color): ?>
                <a href="?status=<?php echo urlencode($s); ?>" class="btn btn-sm btn-<?php echo $statusF === $s ? '' : 'outline-'; ?><?php echo $color; ?>">
                    <?php echo $s === '' ? 'All' : ucfirst($s); ?>
                    <?php if ($s === 'pending' && $pendingCount > 0): ?><span class="badge bg-dark ms-1"><?php echo $pendingCount; ?></span><?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Project</th>
                            <th>Requested By</th>
                            <th>Current Deadline</th>
                            <th>Requested Deadline</th>
                            <th>Reason</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req):
                            $statusBadge = ['pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger'][$req['status']] ?? 'secondary';
                        ?>
                        <tr>
                            <td><?php echo (int)$req['id']; ?></td>
                            <td>
                                <?php if (!empty($req['project_id'])): ?>
                                <a href="project_view.php?id=<?php echo (int)$req['project_id']; ?>"><?php echo h($req['project_title'] ?? 'Project #' . $req['project_id']); ?></a>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="fw-semibold small"><?php echo h($req['requester_name'] ?? '—'); ?></div>
                                <div class="text-muted small"><?php echo h($req['requester_email'] ?? ''); ?></div>
                            </td>
                            <td><?php echo !empty($req['current_deadline']) ? h(date('M j, Y', strtotime($req['current_deadline']))) : '—'; ?></td>
                            <td class="fw-semibold"><?php echo !empty($req['requested_deadline']) ? h(date('M j, Y', strtotime($req['requested_deadline']))) : '—'; ?></td>
                            <td>
                                <?php if (!empty($req['reason'])): ?>
                                <span class="d-inline-block text-truncate" style="max-width:180px;" title="<?php echo h($req['reason']); ?>"><?php echo h($req['reason']); ?></span>
                                <?php else: ?>
                                <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-<?php echo $statusBadge; ?> <?php echo $req['status'] === 'pending' ? 'text-dark' : ''; ?>"><?php echo h(ucfirst($req['status'])); ?></span></td>
                            <td class="small text-muted"><?php echo h(date('M j, Y', strtotime($req['created_at']))); ?></td>
                            <td>
                                <?php if ($req['status'] === 'pending'): ?>
                                <!-- Approve -->
                                <button type="button" class="btn btn-sm btn-success me-1" title="Approve"
                                    data-bs-toggle="modal" data-bs-target="#reviewModal"
                                    data-req-id="<?php echo (int)$req['id']; ?>"
                                    data-action="approve"
                                    data-project="<?php echo h($req['project_title'] ?? ''); ?>"
                                    data-requester="<?php echo h($req['requester_name'] ?? ''); ?>"
                                    data-deadline="<?php echo h($req['requested_deadline'] ?? ''); ?>">
                                    <i class="bi bi-check2"></i> Approve
                                </button>
                                <!-- Reject -->
                                <button type="button" class="btn btn-sm btn-danger" title="Reject"
                                    data-bs-toggle="modal" data-bs-target="#reviewModal"
                                    data-req-id="<?php echo (int)$req['id']; ?>"
                                    data-action="reject"
                                    data-project="<?php echo h($req['project_title'] ?? ''); ?>"
                                    data-requester="<?php echo h($req['requester_name'] ?? ''); ?>"
                                    data-deadline="<?php echo h($req['requested_deadline'] ?? ''); ?>">
                                    <i class="bi bi-x-lg"></i> Reject
                                </button>
                                <?php else: ?>
                                <div class="small text-muted">
                                    <?php if (!empty($req['admin_note'])): ?>
                                    <em><?php echo h($req['admin_note']); ?></em>
                                    <?php else: ?>
                                    <span>Reviewed</span>
                                    <?php endif; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($requests)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No requests found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo urlencode($statusF); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Review Modal -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" id="reviewModalHeader">
                <h5 class="modal-title" id="reviewModalLabel">Review Request</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="request_id" id="modalReqId" value="">
                <input type="hidden" name="action" id="modalAction" value="">
                <div class="modal-body">
                    <div class="mb-3 p-3 bg-light rounded small">
                        <div><strong>Project:</strong> <span id="modalProject"></span></div>
                        <div><strong>Requested By:</strong> <span id="modalRequester"></span></div>
                        <div><strong>Requested Deadline:</strong> <span id="modalDeadline"></span></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Admin Note <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="admin_note" class="form-control" rows="3" placeholder="Leave a note for the requester..."></textarea>
                    </div>
                    <div id="approveWarning" class="alert alert-success d-none">
                        <i class="bi bi-check-circle me-2"></i>Approving this request will update the project deadline to the requested date.
                    </div>
                    <div id="rejectWarning" class="alert alert-danger d-none">
                        <i class="bi bi-x-circle me-2"></i>The deadline will remain unchanged.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn" id="modalSubmitBtn">Confirm</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('reviewModal').addEventListener('show.bs.modal', function(event) {
    var btn        = event.relatedTarget;
    var reqId      = btn.dataset.reqId;
    var action     = btn.dataset.action;
    var project    = btn.dataset.project;
    var requester  = btn.dataset.requester;
    var deadline   = btn.dataset.deadline;

    document.getElementById('modalReqId').value     = reqId;
    document.getElementById('modalAction').value    = action;
    document.getElementById('modalProject').textContent   = project;
    document.getElementById('modalRequester').textContent = requester;
    document.getElementById('modalDeadline').textContent  = deadline || '—';

    var header    = document.getElementById('reviewModalHeader');
    var submitBtn = document.getElementById('modalSubmitBtn');
    var approveW  = document.getElementById('approveWarning');
    var rejectW   = document.getElementById('rejectWarning');

    if (action === 'approve') {
        header.className     = 'modal-header bg-success text-white';
        submitBtn.className  = 'btn btn-success';
        submitBtn.textContent = 'Approve';
        approveW.classList.remove('d-none');
        rejectW.classList.add('d-none');
    } else {
        header.className     = 'modal-header bg-danger text-white';
        submitBtn.className  = 'btn btn-danger';
        submitBtn.textContent = 'Reject';
        approveW.classList.add('d-none');
        rejectW.classList.remove('d-none');
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>
