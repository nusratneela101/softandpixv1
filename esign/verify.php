<?php
/**
 * E-Signature — Public Document Verification
 * Anyone can verify a signed document using its unique hash.
 * URL: /esign/verify.php?hash=DOCUMENT_HASH
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/esign_helper.php';
ensureEsignTables($pdo);

$hash = trim($_GET['hash'] ?? '');
$document = null;
$signatures = [];
$auditLog = [];

if ($hash) {
    try {
        $stmt = $pdo->prepare("SELECT d.*, u.name AS creator_name FROM esign_documents d LEFT JOIN users u ON u.id = d.created_by WHERE d.unique_hash = ?");
        $stmt->execute([$hash]);
        $document = $stmt->fetch();

        if ($document) {
            $stmt = $pdo->prepare("SELECT * FROM esign_signatures WHERE document_id = ? ORDER BY created_at");
            $stmt->execute([(int)$document['id']]);
            $signatures = $stmt->fetchAll();

            $stmt = $pdo->prepare("SELECT * FROM esign_audit_log WHERE document_id = ? ORDER BY created_at DESC");
            $stmt->execute([(int)$document['id']]);
            $auditLog = $stmt->fetchAll();
        }
    } catch (Exception $e) {
        $document = null;
    }
}

$siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Verify Document — SoftandPix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>body { background: #f5f5f5; } .verify-container { max-width: 800px; margin: 30px auto; }
.typed-sig { font-family: 'Brush Script MT', cursive; font-size: 28px; color: #1a237e; }
</style></head><body>
<div class="verify-container">
<div class="text-center mb-4"><h4><i class="fas fa-shield-alt me-2"></i>SoftandPix — Document Verification</h4></div>

<?php if (!$hash): ?>
<!-- Verification form -->
<div class="card"><div class="card-body text-center py-5">
    <h5 class="mb-3">Enter Document Hash or Verification Code</h5>
    <form method="GET" class="row g-2 justify-content-center">
        <div class="col-md-6"><input type="text" name="hash" class="form-control" placeholder="Paste document hash..." required></div>
        <div class="col-auto"><button type="submit" class="btn btn-primary"><i class="fas fa-search me-1"></i>Verify</button></div>
    </form>
</div></div>

<?php elseif (!$document): ?>
<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>Document not found. The hash may be invalid or the document has been removed.</div>

<?php else: ?>
<!-- Verification Result -->
<div class="alert alert-<?= $document['status'] === 'signed' ? 'success' : ($document['status'] === 'revoked' ? 'danger' : 'info') ?>">
    <h5><i class="fas fa-<?= $document['status'] === 'signed' ? 'check-circle' : ($document['status'] === 'revoked' ? 'ban' : 'info-circle') ?> me-2"></i>
    Document Status: <?= ucfirst(h($document['status'])) ?></h5>
    <p class="mb-0">Verification Code: <strong><?= h(getShortVerificationCode($document['unique_hash'])) ?></strong></p>
</div>

<!-- Document Details -->
<div class="card mb-3"><div class="card-header"><h5 class="mb-0"><?= h($document['title']) ?></h5></div>
<div class="card-body">
    <div class="row">
        <div class="col-md-6"><strong>Created by:</strong> <?= h($document['creator_name'] ?? 'Unknown') ?></div>
        <div class="col-md-6"><strong>Created:</strong> <?= date('M j, Y g:i A', strtotime($document['created_at'])) ?></div>
        <div class="col-md-6"><strong>Status:</strong> <?= getEsignStatusBadge($document['status']) ?></div>
        <div class="col-md-6"><strong>Expires:</strong> <?= $document['expires_at'] ? date('M j, Y', strtotime($document['expires_at'])) : 'No expiry' ?></div>
    </div>
    <?php if ($document['description']): ?>
    <hr><p class="text-muted"><?= h($document['description']) ?></p>
    <?php endif; ?>
</div></div>

<!-- Signatures -->
<div class="card mb-3"><div class="card-header">Signatures (<?= count($signatures) ?>)</div>
<div class="card-body">
<?php if (empty($signatures)): ?>
    <p class="text-muted">No signatures on this document.</p>
<?php else: ?>
    <?php foreach ($signatures as $sig): ?>
    <div class="border rounded p-3 mb-2">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <strong><?= h($sig['signer_name'] ?: $sig['signer_email']) ?></strong><br>
                <small class="text-muted"><?= h($sig['signer_email']) ?></small>
            </div>
            <div><?= getSignatureStatusBadge($sig['status']) ?></div>
        </div>
        <?php if ($sig['status'] === 'signed' && $sig['signature_data']): ?>
        <div class="mt-2">
            <?php if (str_starts_with($sig['signature_data'], 'typed:')): ?>
            <div class="typed-sig"><?= h(substr($sig['signature_data'], 6)) ?></div>
            <?php elseif (str_starts_with($sig['signature_data'], 'data:image')): ?>
            <img src="<?= h($sig['signature_data']) ?>" alt="Signature" style="max-height:80px;border:1px solid #eee;border-radius:4px;">
            <?php endif; ?>
            <small class="text-muted d-block mt-1">Signed: <?= date('M j, Y g:i A', strtotime($sig['signed_at'])) ?> | IP: <?= h($sig['signature_ip'] ?? 'N/A') ?></small>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div></div>

<!-- Audit Trail -->
<div class="card mb-3"><div class="card-header">Audit Trail</div>
<div class="card-body p-0"><div class="table-responsive">
<table class="table table-sm mb-0"><thead class="table-light"><tr><th>Action</th><th>Details</th><th>Date</th></tr></thead><tbody>
<?php foreach ($auditLog as $log): ?>
<tr><td><span class="badge bg-secondary"><?= h($log['action']) ?></span></td>
    <td><small><?= h($log['details'] ?? '') ?></small></td>
    <td><small><?= date('M j, Y g:i A', strtotime($log['created_at'])) ?></small></td></tr>
<?php endforeach; ?>
<?php if (empty($auditLog)): ?><tr><td colspan="3" class="text-center text-muted py-3">No audit entries.</td></tr><?php endif; ?>
</tbody></table></div></div></div>
<?php endif; ?>

<div class="text-center mt-4"><a href="<?= h($siteUrl) ?>" class="text-muted"><i class="fas fa-home me-1"></i>SoftandPix</a></div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
