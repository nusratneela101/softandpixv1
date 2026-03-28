<?php
/**
 * api/files/comment.php
 * GET:  file_id — returns comments for a file
 * POST: file_id, comment, csrf_token — add a comment
 */

if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once '../../config/db.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}
$userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'];

// ── GET: fetch comments ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $fileId = (int)($_GET['file_id'] ?? 0);
    if (!$fileId) {
        echo json_encode(['success' => false, 'error' => 'Missing file_id']);
        exit;
    }
    try {
        $stmt = $pdo->prepare(
            "SELECT fc.*, u.name AS author_name
             FROM file_comments fc
             LEFT JOIN users u ON u.id = fc.user_id
             WHERE fc.file_id = ?
             ORDER BY fc.created_at ASC"
        );
        $stmt->execute([$fileId]);
        $comments = $stmt->fetchAll();
        echo json_encode(['success' => true, 'comments' => $comments]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Database error']);
    }
    exit;
}

// ── POST: add comment ─────────────────────────────────────────
$token = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$fileId  = (int)($_POST['file_id'] ?? 0);
$comment = trim($_POST['comment'] ?? '');

if (!$fileId)   { echo json_encode(['success' => false, 'error' => 'Missing file_id']); exit; }
if (!$comment)  { echo json_encode(['success' => false, 'error' => 'Comment cannot be empty']); exit; }
if (strlen($comment) > 5000) { echo json_encode(['success' => false, 'error' => 'Comment too long']); exit; }

try {
    $stmt = $pdo->prepare(
        "INSERT INTO file_comments (file_id, user_id, comment) VALUES (?, ?, ?)"
    );
    $stmt->execute([$fileId, $userId, $comment]);
    $commentId = $pdo->lastInsertId();
    echo json_encode([
        'success'    => true,
        'comment_id' => (int)$commentId,
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error']);
}
