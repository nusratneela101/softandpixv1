<?php
/**
 * AJAX endpoint to switch language
 */
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/language.php';

$lang = $_POST['lang'] ?? $_GET['lang'] ?? 'en';

if (!in_array($lang, SUPPORTED_LANGS, true)) {
    echo json_encode(['success' => false, 'message' => 'Invalid language']);
    exit;
}

$_SESSION['lang'] = $lang;
setcookie('lang', $lang, time() + (86400 * 30), '/', '', false, true);

echo json_encode(['success' => true, 'lang' => $lang]);
