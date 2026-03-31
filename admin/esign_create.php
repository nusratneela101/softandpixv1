<?php
/**
 * Admin — Create E-Signature Document
 * Create from scratch or use a template, add signers, send for signing.
 */
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();
require_once dirname(__DIR__) . '/includes/esign_helper.php';
ensureEsignTables($pdo);

$csrf_token = generateCsrfToken();
$siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid security token.');
        header('Location: esign_create.php'); exit;
    }

    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $content     = $_POST['content'] ?? '';
    $expiresAt   = trim($_POST['expires_at'] ?? '');
    $signers     = $_POST['signer_email'] ?? [];
    $signerNames = $_POST['signer_name'] ?? [];
    $sendNow     = isset($_POST['send_now']);

    if (!$title) {
        flashMessage('error', 'Document title is required.');
        header('Location: esign_create.php'); exit;
    }

    try {
        $hash = generateDocumentHash();
        $status = $sendNow ? 'pending' : 'draft';

        $stmt = $pdo->prepare("INSERT INTO esign_documents (title, description, content, created_by, status, unique_hash, expires_at, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->execute([
            $title, $description, $content,
            (int)$_SESSION['user_id'], $status, $hash,
            $expiresAt ?: null
        ]);
        $docId = (int)$pdo->lastInsertId();

        logEsignAudit($pdo, $docId, 'created', (int)$_SESSION['user_id'], "Document created: $title");

        // Add signers
        foreach ($signers as $i => $email) {
            $email = trim($email);
            $name  = trim($signerNames[$i] ?? '');
            if (!$email) continue;

            // Check if the signer is a registered user
            $uStmt = $pdo->prepare("SELECT id FROM users WHERE email=?");
            $uStmt->execute([$email]);
            $signerId = $uStmt->fetchColumn() ?: null;

            $pdo->prepare("INSERT INTO esign_signatures (document_id, signer_id, signer_name, signer_email, status, created_at)
                VALUES (?, ?, ?, ?, 'pending', NOW())")
                ->execute([$docId, $signerId, $name, $email]);

            if ($sendNow) {
                $sigRow = $pdo->query("SELECT * FROM esign_signatures WHERE document_id=$docId AND signer_email=" . $pdo->quote($email))->fetch();
                $docRow = $pdo->query("SELECT * FROM esign_documents WHERE id=$docId")->fetch();
                if ($sigRow && $docRow) {
                    sendSigningRequestEmail($pdo, $docRow, $sigRow, $siteUrl);
                }
                logEsignAudit($pdo, $docId, 'signing_requested', (int)$_SESSION['user_id'], "Signing request sent to: $email");
            }
        }

        flashMessage('success', $sendNow ? 'Document created and signing requests sent.' : 'Document saved as draft.');
        header('Location: esign.php'); exit;
    } catch (Exception $e) {
        flashMessage('error', 'Error creating document: ' . $e->getMessage());
        header('Location: esign_create.php'); exit;
    }
}

// Load templates for dropdown
$templates = getDefaultEsignTemplates();
try {
    $dbTemplates = $pdo->query("SELECT * FROM esign_templates ORDER BY name")->fetchAll();
    foreach ($dbTemplates as $t) { $templates[] = $t; }
} catch (Exception $e) {}

// Load users for signer suggestions
try {
    $users = $pdo->query("SELECT id, name, email, role FROM users ORDER BY name")->fetchAll();
} catch (Exception $e) { $users = []; }
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-pen me-2"></i>Create E-Sign Document</h1>
    </div>
    <div>
        <a href="esign.php" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>
<div class="container-fluid">
<form method="POST" id="esignForm">
<input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">
<div class="row">
<div class="col-lg-8">
    <!-- Document Info -->
    <div class="card mb-3"><div class="card-header">Document Details</div><div class="card-body">
        <div class="mb-3"><label class="form-label">Title <span class="text-danger">*</span></label>
            <input type="text" name="title" class="form-control" required placeholder="e.g. Non-Disclosure Agreement"></div>
        <div class="mb-3"><label class="form-label">Description</label>
            <textarea name="description" class="form-control" rows="2" placeholder="Brief description..."></textarea></div>
        <div class="mb-3"><label class="form-label">Load Template</label>
            <select id="templateSelect" class="form-select"><option value="">— Select Template —</option>
            <?php foreach ($templates as $t): ?>
            <option value="<?= h($t['content'] ?? '') ?>"><?= h($t['name']) ?></option>
            <?php endforeach; ?></select></div>
        <div class="mb-3"><label class="form-label">Document Content</label>
            <textarea name="content" id="docContent" class="form-control" rows="15" placeholder="Enter or paste document content (HTML supported)..."></textarea></div>
    </div></div>

    <!-- Signers -->
    <div class="card mb-3"><div class="card-header d-flex justify-content-between align-items-center">
        Signers <button type="button" class="btn btn-sm btn-outline-primary" id="addSignerBtn"><i class="fas fa-plus me-1"></i>Add Signer</button>
    </div><div class="card-body" id="signersContainer">
        <div class="row g-2 mb-2 signer-row">
            <div class="col-md-5"><input type="text" name="signer_name[]" class="form-control" placeholder="Signer name"></div>
            <div class="col-md-5"><input type="email" name="signer_email[]" class="form-control" placeholder="Signer email" required></div>
            <div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm remove-signer" title="Remove"><i class="fas fa-times"></i></button></div>
        </div>
    </div></div>
</div>

<div class="col-lg-4">
    <!-- Options -->
    <div class="card mb-3"><div class="card-header">Options</div><div class="card-body">
        <div class="mb-3"><label class="form-label">Expiry Date</label>
            <input type="date" name="expires_at" class="form-control" min="<?= date('Y-m-d', strtotime('+1 day')) ?>"></div>
        <div class="form-check mb-3"><input type="checkbox" name="send_now" class="form-check-input" id="sendNow" checked>
            <label class="form-check-label" for="sendNow">Send signing requests immediately</label></div>
        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-paper-plane me-1"></i>Create Document</button>
        <button type="submit" name="draft" class="btn btn-outline-secondary w-100 mt-2" onclick="document.getElementById('sendNow').checked=false"><i class="fas fa-save me-1"></i>Save as Draft</button>
    </div></div>

    <!-- Quick Add Users -->
    <div class="card mb-3"><div class="card-header">Quick Add User</div><div class="card-body">
        <select id="quickAddUser" class="form-select form-select-sm">
            <option value="">— Select user —</option>
            <?php foreach ($users as $u): ?>
            <option data-name="<?= h($u['name']) ?>" data-email="<?= h($u['email']) ?>"><?= h($u['name']) ?> (<?= h($u['email']) ?>)</option>
            <?php endforeach; ?>
        </select>
    </div></div>
</div>
</form>
</div>
<script>
// Template loading
document.getElementById('templateSelect').addEventListener('change', function() {
    if (this.value) document.getElementById('docContent').value = this.value;
});
// Add signer
document.getElementById('addSignerBtn').addEventListener('click', function() {
    var row = document.createElement('div');
    row.className = 'row g-2 mb-2 signer-row';
    row.innerHTML = '<div class="col-md-5"><input type="text" name="signer_name[]" class="form-control" placeholder="Signer name"></div>'
        + '<div class="col-md-5"><input type="email" name="signer_email[]" class="form-control" placeholder="Signer email" required></div>'
        + '<div class="col-md-2"><button type="button" class="btn btn-outline-danger btn-sm remove-signer"><i class="bi bi-x"></i></button></div>';
    document.getElementById('signersContainer').appendChild(row);
});
// Remove signer
document.getElementById('signersContainer').addEventListener('click', function(e) {
    var btn = e.target.closest('.remove-signer');
    if (btn) {
        var rows = document.querySelectorAll('.signer-row');
        if (rows.length > 1) btn.closest('.signer-row').remove();
    }
});
// Quick add user
document.getElementById('quickAddUser').addEventListener('change', function() {
    var opt = this.options[this.selectedIndex];
    if (!opt.dataset.email) return;
    var rows = document.querySelectorAll('.signer-row');
    var lastRow = rows[rows.length - 1];
    var nameInput = lastRow.querySelector('input[name="signer_name[]"]');
    var emailInput = lastRow.querySelector('input[name="signer_email[]"]');
    if (emailInput.value) {
        document.getElementById('addSignerBtn').click();
        rows = document.querySelectorAll('.signer-row');
        lastRow = rows[rows.length - 1];
        nameInput = lastRow.querySelector('input[name="signer_name[]"]');
        emailInput = lastRow.querySelector('input[name="signer_email[]"]');
    }
    nameInput.value = opt.dataset.name;
    emailInput.value = opt.dataset.email;
    this.selectedIndex = 0;
});
</script>
<?php require_once 'includes/footer.php'; ?>
