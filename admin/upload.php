<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['image'])) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['image'];
$allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
$max_size = 5 * 1024 * 1024; // 5MB

if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Upload error: ' . $file['error']]);
    exit;
}

if (!in_array($file['type'], $allowed_types)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed: JPG, PNG, GIF, WebP']);
    exit;
}

if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size: 5MB']);
    exit;
}

$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array($ext, $allowed_exts)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file extension']);
    exit;
}

$upload_dir = '../assets/uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

$filename = uniqid('img_', true) . '.' . $ext;
$filepath = $upload_dir . $filename;

if (move_uploaded_file($file['tmp_name'], $filepath)) {
    echo json_encode(['success' => true, 'filename' => 'assets/uploads/' . $filename]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save file']);
}
