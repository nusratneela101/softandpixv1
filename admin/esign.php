<?php
/**
 * Admin E-Signature Dashboard
 * Lists all documents, allows management (revoke, delete, filter).
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_role('admin');
update_online_status($pdo, $_SESSION['user_id']);
require_once BASE_PATH . '/includes/esign_helper.php';
ensureEsignTables($pdo);

$csrf_token = generateCsrfToken();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: esign.php'); exit;
    }
    $docId = (int)($_POST['document_id'] ?? 0);
    if ($docId > 0) {
        if (isset($_POST['revoke_document'])) {
            $pdo->prepare("UPDATE esign_documents SET status='revoked', updated_at=NOW() WHERE id=?")->execute([$docId]);
            logEsignAudit($pdo, $docId, 'revoked', (int)$_SESSION['user_id'], 'Document revoked by admin');
            flashMessage('success', 'Document revoked.');
        } elseif (isset($_POST['delete_document'])) {
            $pdo->prepare("DELETE FROM esign_signatures WHERE document_id=?")->execute([$docId]);
            $pdo->prepare("DELETE FROM esign_audit_log WHERE document_id=?")->execute([$docId]);
            $pdo->prepare("DELETE FROM esign_documents WHERE id=?")->execute([$docId]);
            flashMessage('success', 'Document deleted.');
        }
    }
    header('Location: esign.php'); exit;
}

// Filters
$search  = trim($_GET['search'] ?? '');
$statusF = trim($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (d.title LIKE ? OR d.description LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($statusF) { $where .= " AND d.status = ?"; $params[] = $statusF; }

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM esign_documents d $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT d.*, u.name AS creator_name FROM esign_documents d LEFT JOIN users u ON u.id = d.created_by $where ORDER BY d.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $documents = $stmt->fetchAll();
} catch (Exception $e) { $documents = []; $total = 0; }

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

// Count stats
try {
    $stats = [
        'total'   => $pdo->query("SELECT COUNT(*) FROM esign_documents")->fetchColumn(),
        'pending' => $pdo->query("SELECT COUNT(*) FROM esign_documents WHERE status='pending'")->fetchColumn(),
        'signed'  => $pdo->query("SELECT COUNT(*) FROM esign_documents WHERE status='signed'")->fetchColumn(),
        'expired' => $pdo->query("SELECT COUNT(*) FROM esign_documents WHERE status='expired'")->fetchColumn(),
    ];
} catch (Exception $e) { $stats = ['total' => 0, 'pending' => 0, 'signed' => 0, 'expired' => 0]; }
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>E-Signatures — SoftandPix Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="<?= e(BASE_URL) ?>/public/assets/css/style.css" rel="stylesheet"></head><body>
<?php include BASE_PATH . '/includes/sidebar_admin.php'; ?>
<div class="topbar"><div class="topbar-left"><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button><h5 class="mb-0">E-Signatures</h5></div>
<div class="topbar-right"><a href="esign_create.php" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>New Document</a></div></div>
<div class="main-content">

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3"><div class="card text-center p-3"><h3><?= (int)$stats['total'] ?></h3><small class="text-muted">Total Documents</small></div></div>
    <div class="col-md-3"><div class="card text-center p-3"><h3 class="text-warning"><?= (int)$stats['pending'] ?></h3><small class="text-muted">Pending</small></div></div>
    <div class="col-md-3"><div class="card text-center p-3"><h3 class="text-success"><?= (int)$stats['signed'] ?></h3><small class="text-muted">Signed</small></div></div>
    <div class="col-md-3"><div class="card text-center p-3"><h3 class="text-danger"><?= (int)$stats['expired'] ?></h3><small class="text-muted">Expired</small></div></div>
</div>

<!-- Filters -->
<div class="card mb-3"><div class="card-body">
<form method="GET" class="row g-2">
    <div class="col-md-5"><input type="text" name="search" class="form-control" placeholder="Search documents..." value="<?= h($search) ?>"></div>
    <div class="col-md-3"><select name="status" class="form-select"><option value="">All Statuses</option>
        <?php foreach (['draft','pending','signed','expired','revoked'] as $s): ?>
        <option value="<?= $s ?>" <?= $statusF === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
        <?php endforeach; ?></select></div>
    <div class="col-auto"><button type="submit" class="btn btn-outline-primary">Filter</button></div>
    <div class="col-auto"><a href="esign.php" class="btn btn-outline-secondary">Clear</a></div>
</form></div></div>

<!-- Documents Table -->
<div class="table-card"><div class="card-header">Documents (<?= $total ?>)</div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr>
    <th>#</th><th>Title</th><th>Created By</th><th>Status</th><th>Signers</th><th>Created</th><th>Expires</th><th>Actions</th>
</tr></thead><tbody>
<?php if (empty($documents)): ?>
<tr><td colspan="8" class="text-center text-muted py-4">No documents found.</td></tr>
<?php else: foreach ($documents as $doc):
    $sigCount = $pdo->prepare("SELECT COUNT(*) FROM esign_signatures WHERE document_id=?");
    $sigCount->execute([(int)$doc['id']]);
    $signerTotal = (int)$sigCount->fetchColumn();
    $sigSigned = 0;
    $ss = $pdo->prepare("SELECT COUNT(*) FROM esign_signatures WHERE document_id=? AND status='signed'");
    $ss->execute([(int)$doc['id']]);
    $sigSigned = (int)$ss->fetchColumn();
?>
<tr>
    <td><?= (int)$doc['id'] ?></td>
    <td><strong><?= h($doc['title']) ?></strong><br><small class="text-muted"><?= h(mb_strimwidth($doc['description'] ?? '', 0, 60, '...')) ?></small></td>
    <td><?= h($doc['creator_name'] ?? '—') ?></td>
    <td><?= getEsignStatusBadge($doc['status']) ?></td>
    <td><span class="badge bg-secondary"><?= $sigSigned ?>/<?= $signerTotal ?></span></td>
    <td><?= time_ago($doc['created_at']) ?></td>
    <td><?= $doc['expires_at'] ? date('M j, Y', strtotime($doc['expires_at'])) : '—' ?></td>
    <td>
        <a href="<?= e(BASE_URL) ?>/esign/verify.php?hash=<?= h($doc['unique_hash'] ?? '') ?>" class="btn btn-sm btn-outline-info" title="View" target="_blank"><i class="fas fa-eye"></i></a>
        <?php if ($doc['status'] === 'pending'): ?>
        <form method="POST" class="d-inline" onsubmit="return confirm('Revoke this document?')">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
            <input type="hidden" name="revoke_document" value="1">
            <button type="submit" class="btn btn-sm btn-outline-warning" title="Revoke"><i class="fas fa-ban"></i></button>
        </form>
        <?php endif; ?>
        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this document permanently?')">
            <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
            <input type="hidden" name="document_id" value="<?= (int)$doc['id'] ?>">
            <input type="hidden" name="delete_document" value="1">
            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="fas fa-trash"></i></button>
        </form>
    </td>
</tr>
<?php endforeach; endif; ?></tbody></table></div>
<?php if ($totalPages > 1): ?>
<div class="card-footer"><nav><ul class="pagination pagination-sm mb-0">
<?php for ($i = 1; $i <= $totalPages; $i++): ?>
<li class="page-item <?= $i === $page ? 'active' : '' ?>"><a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= urlencode($statusF) ?>"><?= $i ?></a></li>
<?php endfor; ?></ul></nav></div>
<?php endif; ?>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
