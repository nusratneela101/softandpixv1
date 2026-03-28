<?php
/**
 * api/chat/upload.php
 * POST — upload a file/image within a conversation.
 * Stores in uploads/chat/{conversation_id}/ and inserts a chat_messages record.
 */
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';
header('Content-Type: application/json');

$isAdmin = isset($_SESSION['admin_id']);
$userId  = $isAdmin ? 0 : (int)($_SESSION['user_id'] ?? 0);

if (!$isAdmin && !$userId) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']); exit;
}

// CSRF
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']); exit;
}

$convId = (int)($_POST['conversation_id'] ?? 0);
if (!$convId || empty($_FILES['file'])) {
    echo json_encode(['success' => false, 'error' => 'Missing data']); exit;
}

try {
    // Verify participant (admin always allowed)
    if (!$isAdmin) {
        $check = $pdo->prepare("SELECT id FROM chat_participants WHERE conversation_id=? AND user_id=?");
        $check->execute([$convId, $userId]);
        if (!$check->fetch()) {
            echo json_encode(['success' => false, 'error' => 'Not a participant']); exit;
        }
    }

    $imageMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $docMimes   = [
        'application/pdf', 'application/zip',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/plain',
    ];
    $allowed = array_merge($imageMimes, $docMimes);

    $val = validateUploadedFile($_FILES['file'], $allowed, 10485760); // 10 MB
    if (!$val['ok']) {
        echo json_encode(['success' => false, 'error' => $val['error']]); exit;
    }

    $dir = __DIR__ . '/../../uploads/chat/' . $convId . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $fname = 'chat_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $val['ext'];
    if (!move_uploaded_file($_FILES['file']['tmp_name'], $dir . $fname)) {
        echo json_encode(['success' => false, 'error' => 'Upload failed']); exit;
    }

    $filePath  = 'uploads/chat/' . $convId . '/' . $fname;
    $fileName  = htmlspecialchars($_FILES['file']['name'], ENT_QUOTES, 'UTF-8');
    $fileSize  = (int)$_FILES['file']['size'];
    $msgType   = in_array($val['mime'], $imageMimes) ? 'image' : 'file';

    $stmt = $pdo->prepare(
        "INSERT INTO chat_messages
            (conversation_id, sender_id, message, message_type, file_path, file_name, file_size)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([$convId, $userId, $fileName, $msgType, $filePath, $fileName, $fileSize]);
    $msgId = (int)$pdo->lastInsertId();

    // Update conversation timestamp
    $pdo->prepare("UPDATE chat_conversations SET updated_at=NOW() WHERE id=?")->execute([$convId]);

    $fetch = $pdo->prepare(
        "SELECT id, conversation_id, sender_id, message, message_type,
                file_path, file_name, file_size, created_at
         FROM chat_messages WHERE id=?"
    );
    $fetch->execute([$msgId]);
    $msg = $fetch->fetch();

    echo json_encode(['success' => true, 'message' => $msg]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Server error']);
}
