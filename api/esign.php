<?php
/**
 * API — E-Signature Endpoints
 *
 * POST /api/esign.php?action=save_signature  — signer submits signature
 * POST /api/esign.php?action=decline         — signer declines document
 * GET  /api/esign.php?action=status&id=X     — get document status (admin)
 * POST /api/esign.php?action=send_reminder   — resend signing email (admin)
 */
session_start();
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/esign_helper.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ──────────────────────────────────────────────────────
// Helper: JSON response
// ──────────────────────────────────────────────────────
function jsonResponse(bool $ok, string $message, array $data = []): void
{
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $data));
    exit;
}

// ──────────────────────────────────────────────────────
// Ensure tables
// ──────────────────────────────────────────────────────
ensureEsignTables($pdo);

// ──────────────────────────────────────────────────────
// Action: save_signature  (public — token-based auth)
// ──────────────────────────────────────────────────────
if ($action === 'save_signature' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $signatureId   = (int)($_POST['signature_id'] ?? 0);
    $token         = trim($_POST['token'] ?? '');
    $signatureData = trim($_POST['signature_data'] ?? '');
    $signatureType = in_array($_POST['signature_type'] ?? '', ['draw', 'type', 'upload'])
        ? $_POST['signature_type'] : 'draw';

    if (!$signatureId || !$token || !$signatureData) {
        jsonResponse(false, 'Missing required fields.');
    }

    // Validate token against signature row
    try {
        $stmt = $pdo->prepare(
            "SELECT s.*, d.status AS doc_status, d.expires_at, d.id AS doc_id
             FROM esign_signatures s
             JOIN esign_documents d ON d.id = s.document_id
             WHERE s.id = ? AND s.status = 'pending' LIMIT 1"
        );
        $stmt->execute([$signatureId]);
        $sig = $stmt->fetch();
    } catch (Exception $e) {
        jsonResponse(false, 'Database error.');
    }

    if (!$sig) {
        jsonResponse(false, 'Signature request not found or already processed.');
    }

    // Verify token (hash of signature id + signer email)
    $expectedToken = hash('sha256', $sig['id'] . $sig['signer_email'] . $sig['document_id']);
    if (!hash_equals($expectedToken, $token)) {
        jsonResponse(false, 'Invalid token.');
    }

    if ($sig['doc_status'] === 'revoked' || $sig['doc_status'] === 'expired') {
        jsonResponse(false, 'Document is no longer available for signing.');
    }
    if ($sig['expires_at'] && strtotime($sig['expires_at']) < time()) {
        jsonResponse(false, 'Document signing period has expired.');
    }

    // Validate signature data length (~2MB max)
    if (strlen($signatureData) > 2 * 1024 * 1024) {
        jsonResponse(false, 'Signature data is too large.');
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    try {
        $pdo->prepare(
            "UPDATE esign_signatures
             SET signature_data = ?, signature_type = ?, signature_ip = ?,
                 signed_at = NOW(), status = 'signed'
             WHERE id = ?"
        )->execute([$signatureData, $signatureType, $ip, $signatureId]);

        logEsignAudit($pdo, $sig['doc_id'], 'signed', $sig['signer_id'] ?? 0,
            "Signed by {$sig['signer_name']} ({$sig['signer_email']}) via {$signatureType}");

        updateDocumentStatusFromSignatures($pdo, $sig['doc_id']);
    } catch (Exception $e) {
        jsonResponse(false, 'Failed to save signature.');
    }

    jsonResponse(true, 'Document signed successfully.');
}

// ──────────────────────────────────────────────────────
// Action: decline  (public — token-based auth)
// ──────────────────────────────────────────────────────
if ($action === 'decline' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $signatureId = (int)($_POST['signature_id'] ?? 0);
    $token       = trim($_POST['token'] ?? '');
    $reason      = trim($_POST['reason'] ?? '');

    if (!$signatureId || !$token) {
        jsonResponse(false, 'Missing required fields.');
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT s.*, d.id AS doc_id FROM esign_signatures s
             JOIN esign_documents d ON d.id = s.document_id
             WHERE s.id = ? AND s.status = 'pending' LIMIT 1"
        );
        $stmt->execute([$signatureId]);
        $sig = $stmt->fetch();
    } catch (Exception $e) {
        jsonResponse(false, 'Database error.');
    }

    if (!$sig) {
        jsonResponse(false, 'Signature request not found.');
    }

    $expectedToken = hash('sha256', $sig['id'] . $sig['signer_email'] . $sig['document_id']);
    if (!hash_equals($expectedToken, $token)) {
        jsonResponse(false, 'Invalid token.');
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    try {
        $pdo->prepare(
            "UPDATE esign_signatures SET status = 'declined', decline_reason = ?,
             signature_ip = ?, signed_at = NOW() WHERE id = ?"
        )->execute([substr($reason, 0, 500), $ip, $signatureId]);

        logEsignAudit($pdo, $sig['doc_id'], 'declined', $sig['signer_id'] ?? 0,
            "Declined by {$sig['signer_name']} ({$sig['signer_email']}): $reason");
    } catch (Exception $e) {
        jsonResponse(false, 'Failed to record decline.');
    }

    jsonResponse(true, 'You have declined to sign this document.');
}

// ──────────────────────────────────────────────────────
// Admin-only actions below — require session
// ──────────────────────────────────────────────────────
$isAdmin = (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin')
    || isset($_SESSION['admin_id']);

if (!$isAdmin) {
    http_response_code(401);
    jsonResponse(false, 'Unauthorized.');
}

// ──────────────────────────────────────────────────────
// Action: status  (admin)
// ──────────────────────────────────────────────────────
if ($action === 'status' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $docId = (int)($_GET['id'] ?? 0);
    if (!$docId) {
        jsonResponse(false, 'Missing document ID.');
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM esign_documents WHERE id = ? LIMIT 1");
        $stmt->execute([$docId]);
        $doc = $stmt->fetch();

        if (!$doc) {
            jsonResponse(false, 'Document not found.');
        }

        $sigStmt = $pdo->prepare(
            "SELECT id, signer_name, signer_email, status, signed_at FROM esign_signatures WHERE document_id = ?"
        );
        $sigStmt->execute([$docId]);
        $signatures = $sigStmt->fetchAll();

        jsonResponse(true, 'OK', [
            'document'   => [
                'id'         => (int)$doc['id'],
                'title'      => h($doc['title']),
                'status'     => $doc['status'],
                'expires_at' => $doc['expires_at'],
                'created_at' => $doc['created_at'],
            ],
            'signatures' => array_map(fn($s) => [
                'id'          => (int)$s['id'],
                'signer_name' => h($s['signer_name']),
                'signer_email'=> h($s['signer_email']),
                'status'      => $s['status'],
                'signed_at'   => $s['signed_at'],
            ], $signatures),
        ]);
    } catch (Exception $e) {
        jsonResponse(false, 'Database error: ' . $e->getMessage());
    }
}

// ──────────────────────────────────────────────────────
// Action: send_reminder  (admin, POST)
// ──────────────────────────────────────────────────────
if ($action === 'send_reminder' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, 'Invalid CSRF token.');
    }

    $signatureId = (int)($_POST['signature_id'] ?? 0);
    if (!$signatureId) {
        jsonResponse(false, 'Missing signature ID.');
    }

    try {
        $stmt = $pdo->prepare(
            "SELECT s.*, d.title AS doc_title, d.unique_hash
             FROM esign_signatures s
             JOIN esign_documents d ON d.id = s.document_id
             WHERE s.id = ? AND s.status = 'pending' LIMIT 1"
        );
        $stmt->execute([$signatureId]);
        $sig = $stmt->fetch();
    } catch (Exception $e) {
        jsonResponse(false, 'Database error.');
    }

    if (!$sig) {
        jsonResponse(false, 'Pending signature not found.');
    }

    $sent = sendSigningRequest($pdo, (int)$sig['document_id'], $sig['id'], $sig['signer_name'], $sig['signer_email']);

    if ($sent) {
        logEsignAudit($pdo, (int)$sig['document_id'], 'reminder_sent',
            (int)($_SESSION['user_id'] ?? 0),
            "Reminder sent to {$sig['signer_name']} ({$sig['signer_email']})");
        jsonResponse(true, 'Reminder sent to ' . h($sig['signer_email']));
    } else {
        jsonResponse(false, 'Failed to send reminder email.');
    }
}

// ──────────────────────────────────────────────────────
// Fallback
// ──────────────────────────────────────────────────────
http_response_code(400);
jsonResponse(false, 'Unknown action.');
