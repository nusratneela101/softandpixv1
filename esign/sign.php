<?php
/**
 * E-Signature — Public Signing Page
 * Signers visit this page to view and sign a document using canvas signature pad.
 * URL: /esign/sign.php?hash=DOCUMENT_HASH&email=SIGNER_EMAIL
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/esign_helper.php';
ensureEsignTables($pdo);

$hash  = trim($_GET['hash'] ?? '');
$email = trim($_GET['email'] ?? '');
$error = '';
$success = false;

if (!$hash || !$email) {
    $error = 'Invalid signing link. Please check the URL.';
}

// Load document
$document = null;
$signature = null;
if (!$error) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM esign_documents WHERE unique_hash = ?");
        $stmt->execute([$hash]);
        $document = $stmt->fetch();
        if (!$document) {
            $error = 'Document not found.';
        } elseif ($document['status'] === 'revoked') {
            $error = 'This document has been revoked.';
        } elseif (isDocumentExpired($document)) {
            $error = 'This document has expired.';
        } elseif ($document['status'] === 'signed') {
            $error = 'This document has already been fully signed.';
        }
    } catch (Exception $e) {
        $error = 'An error occurred loading the document.';
    }
}

if (!$error && $document) {
    $stmt = $pdo->prepare("SELECT * FROM esign_signatures WHERE document_id = ? AND signer_email = ?");
    $stmt->execute([(int)$document['id'], $email]);
    $signature = $stmt->fetch();
    if (!$signature) {
        $error = 'You are not listed as a signer for this document.';
    } elseif ($signature['status'] === 'signed') {
        $error = 'You have already signed this document.';
    } elseif ($signature['status'] === 'declined') {
        $error = 'You have declined to sign this document.';
    }
}

// Handle signing POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error && $document && $signature) {
    $action        = $_POST['action'] ?? 'sign';
    $signatureData = $_POST['signature_data'] ?? '';
    $signatureType = $_POST['signature_type'] ?? 'draw';

    if ($action === 'decline') {
        $pdo->prepare("UPDATE esign_signatures SET status='declined', signed_at=NOW() WHERE id=?")
            ->execute([(int)$signature['id']]);
        logEsignAudit($pdo, (int)$document['id'], 'declined', $signature['signer_id'] ?? 0,
            "Signer declined: {$signature['signer_email']}");
        $success = true;
        $error = 'You have declined to sign this document.';
    } elseif ($action === 'sign') {
        if (!$signatureData) {
            $error = 'Please provide your signature.';
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $pdo->prepare("UPDATE esign_signatures SET signature_data=?, signature_type=?, signature_ip=?, status='signed', signed_at=NOW() WHERE id=?")
                ->execute([$signatureData, $signatureType, $ip, (int)$signature['id']]);

            logEsignAudit($pdo, (int)$document['id'], 'signed', $signature['signer_id'] ?? 0,
                "Document signed by: {$signature['signer_email']} (IP: $ip, type: $signatureType)");

            updateDocumentStatusFromSignatures($pdo, (int)$document['id']);
            $success = true;
        }
    }
}

// Log view event
if (!$error && $document && $signature && !$success) {
    logEsignAudit($pdo, (int)$document['id'], 'viewed', $signature['signer_id'] ?? 0,
        "Document viewed by: {$signature['signer_email']}");
}

$siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Sign Document — SoftandPix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
body { background: #f5f5f5; }
.sign-container { max-width: 900px; margin: 30px auto; }
.document-content { background: #fff; padding: 40px; border: 1px solid #ddd; min-height: 400px; margin-bottom: 20px; font-family: Georgia, serif; line-height: 1.8; }
.signature-pad-container { border: 2px dashed #ccc; border-radius: 8px; background: #fff; position: relative; }
.signature-pad-container canvas { width: 100%; cursor: crosshair; display: block; }
.typed-signature { font-family: 'Brush Script MT', 'Dancing Script', cursive; font-size: 36px; color: #1a237e; text-align: center; padding: 20px; border: 2px dashed #ccc; border-radius: 8px; background: #fff; }
.tab-content { min-height: 200px; }
</style></head><body>
<div class="sign-container">
<div class="text-center mb-4"><h4><i class="fas fa-file-signature me-2"></i>SoftandPix E-Signature</h4></div>

<?php if ($error): ?>
<div class="alert alert-<?= $success ? 'success' : 'danger' ?>"><i class="fas fa-<?= $success ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i><?= h($error) ?></div>
<?php if ($success): ?><div class="text-center"><a href="<?= h($siteUrl) ?>" class="btn btn-primary">Go to SoftandPix</a></div><?php endif; ?>

<?php elseif ($success): ?>
<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>Thank you! Your signature has been recorded successfully.</div>
<div class="text-center">
    <p>Verification code: <strong><?= h(getShortVerificationCode($document['unique_hash'])) ?></strong></p>
    <a href="<?= h($siteUrl) ?>/esign/verify.php?hash=<?= h($document['unique_hash']) ?>" class="btn btn-outline-primary">Verify Document</a>
</div>

<?php else: ?>
<!-- Document Content -->
<div class="card mb-4"><div class="card-header"><h5 class="mb-0"><?= h($document['title']) ?></h5>
    <?php if ($document['description']): ?><small class="text-muted"><?= h($document['description']) ?></small><?php endif; ?>
</div><div class="card-body document-content"><?= $document['content'] ?></div></div>

<!-- Signature Area -->
<div class="card"><div class="card-header"><h5 class="mb-0">Your Signature</h5>
    <small class="text-muted">Signing as: <?= h($signature['signer_name'] ?: $signature['signer_email']) ?></small>
</div><div class="card-body">
<form method="POST" id="signForm">
    <input type="hidden" name="action" value="sign" id="formAction">
    <input type="hidden" name="signature_data" id="signatureData">
    <input type="hidden" name="signature_type" id="signatureType" value="draw">

    <ul class="nav nav-tabs mb-3" role="tablist">
        <li class="nav-item"><a class="nav-link active" data-bs-toggle="tab" href="#tabDraw" onclick="document.getElementById('signatureType').value='draw'">✏️ Draw</a></li>
        <li class="nav-item"><a class="nav-link" data-bs-toggle="tab" href="#tabType" onclick="document.getElementById('signatureType').value='type'">⌨️ Type</a></li>
    </ul>
    <div class="tab-content">
        <!-- Draw Tab -->
        <div class="tab-pane fade show active" id="tabDraw">
            <div class="signature-pad-container mb-2">
                <canvas id="signaturePad" width="760" height="200"></canvas>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="clearPad"><i class="fas fa-eraser me-1"></i>Clear</button>
        </div>
        <!-- Type Tab -->
        <div class="tab-pane fade" id="tabType">
            <input type="text" id="typedSig" class="form-control mb-2" placeholder="Type your full name">
            <div class="typed-signature" id="typedPreview">&nbsp;</div>
        </div>
    </div>
    <hr>
    <div class="d-flex justify-content-between">
        <button type="button" class="btn btn-outline-danger" id="declineBtn"><i class="fas fa-times me-1"></i>Decline</button>
        <button type="submit" class="btn btn-success" id="signBtn"><i class="fas fa-check me-1"></i>Sign Document</button>
    </div>
</form>
</div></div>
<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?php if (!$error && !$success): ?>
<script>
(function() {
    // Signature Pad
    var canvas = document.getElementById('signaturePad');
    var ctx = canvas.getContext('2d');
    var drawing = false;
    var lastX, lastY;

    function getPos(e) {
        var rect = canvas.getBoundingClientRect();
        var x, y;
        if (e.touches) { x = e.touches[0].clientX - rect.left; y = e.touches[0].clientY - rect.top; }
        else { x = e.clientX - rect.left; y = e.clientY - rect.top; }
        var scaleX = canvas.width / rect.width;
        var scaleY = canvas.height / rect.height;
        return { x: x * scaleX, y: y * scaleY };
    }

    ctx.lineWidth = 2.5;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';
    ctx.strokeStyle = '#1a237e';

    canvas.addEventListener('mousedown', function(e) { drawing = true; var p = getPos(e); lastX = p.x; lastY = p.y; });
    canvas.addEventListener('mousemove', function(e) { if (!drawing) return; var p = getPos(e); ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(p.x, p.y); ctx.stroke(); lastX = p.x; lastY = p.y; });
    canvas.addEventListener('mouseup', function() { drawing = false; });
    canvas.addEventListener('mouseleave', function() { drawing = false; });

    // Touch support
    canvas.addEventListener('touchstart', function(e) { e.preventDefault(); drawing = true; var p = getPos(e); lastX = p.x; lastY = p.y; });
    canvas.addEventListener('touchmove', function(e) { e.preventDefault(); if (!drawing) return; var p = getPos(e); ctx.beginPath(); ctx.moveTo(lastX, lastY); ctx.lineTo(p.x, p.y); ctx.stroke(); lastX = p.x; lastY = p.y; });
    canvas.addEventListener('touchend', function() { drawing = false; });

    document.getElementById('clearPad').addEventListener('click', function() {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
    });

    // Typed signature preview
    var typedInput = document.getElementById('typedSig');
    var typedPreview = document.getElementById('typedPreview');
    typedInput.addEventListener('input', function() {
        typedPreview.textContent = this.value || '\u00A0';
    });

    // Form submit
    document.getElementById('signForm').addEventListener('submit', function(e) {
        var sigType = document.getElementById('signatureType').value;
        var data = '';
        if (sigType === 'draw') {
            data = canvas.toDataURL('image/png');
            // Check if canvas is blank
            var blank = document.createElement('canvas');
            blank.width = canvas.width; blank.height = canvas.height;
            if (canvas.toDataURL() === blank.toDataURL()) {
                e.preventDefault();
                alert('Please draw your signature on the pad.');
                return;
            }
        } else if (sigType === 'type') {
            var txt = typedInput.value.trim();
            if (!txt) { e.preventDefault(); alert('Please type your name.'); return; }
            data = 'typed:' + txt;
        }
        document.getElementById('signatureData').value = data;
    });

    // Decline button
    document.getElementById('declineBtn').addEventListener('click', function() {
        if (confirm('Are you sure you want to decline signing this document?')) {
            document.getElementById('formAction').value = 'decline';
            document.getElementById('signForm').submit();
        }
    });
})();
</script>
<?php endif; ?>
</body></html>
