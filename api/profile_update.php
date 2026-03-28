<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

if (!verifyCsrfToken($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Sanitize and validate fields
$name    = trim(htmlspecialchars($input['name'] ?? '', ENT_QUOTES, 'UTF-8'));
$phone   = trim(htmlspecialchars($input['phone'] ?? '', ENT_QUOTES, 'UTF-8'));
$bio     = trim(htmlspecialchars($input['bio'] ?? '', ENT_QUOTES, 'UTF-8'));
$skills  = trim(htmlspecialchars($input['skills'] ?? '', ENT_QUOTES, 'UTF-8'));
$company = trim(htmlspecialchars($input['company'] ?? '', ENT_QUOTES, 'UTF-8'));
$website = trim(htmlspecialchars($input['website'] ?? '', ENT_QUOTES, 'UTF-8'));
$address = trim(htmlspecialchars($input['address'] ?? '', ENT_QUOTES, 'UTF-8'));
$city    = trim(htmlspecialchars($input['city'] ?? '', ENT_QUOTES, 'UTF-8'));
$country = trim(htmlspecialchars($input['country'] ?? '', ENT_QUOTES, 'UTF-8'));
$timezone = trim(htmlspecialchars($input['timezone'] ?? 'UTC', ENT_QUOTES, 'UTF-8'));
$social_github   = trim(htmlspecialchars($input['social_github'] ?? '', ENT_QUOTES, 'UTF-8'));
$social_linkedin = trim(htmlspecialchars($input['social_linkedin'] ?? '', ENT_QUOTES, 'UTF-8'));
$social_twitter  = trim(htmlspecialchars($input['social_twitter'] ?? '', ENT_QUOTES, 'UTF-8'));

if (empty($name)) {
    echo json_encode(['ok' => false, 'error' => 'Name is required']);
    exit;
}

// Validate website URL if provided
if ($website && !filter_var($website, FILTER_VALIDATE_URL)) {
    echo json_encode(['ok' => false, 'error' => 'Invalid website URL']);
    exit;
}

// Core columns always present in schema
try {
    $pdo->prepare("UPDATE users SET name=?, phone=?, bio=?, skills=?, company=?, address=?, city=?, country=? WHERE id=?")
        ->execute([$name, $phone, $bio, $skills, $company, $address, $city, $country, $userId]);
} catch (Exception $e) {
    echo json_encode(['ok' => false, 'error' => 'Database error']);
    exit;
}

// Optional / newer columns — whitelist prevents SQL injection
$optCols   = ['timezone', 'website', 'social_github', 'social_linkedin', 'social_twitter'];
$optValues = [$timezone, $website, $social_github, $social_linkedin, $social_twitter];

// Developer-specific columns
$role = $_SESSION['user_role'] ?? '';
if (in_array($role, ['developer', 'editor', 'ui_designer', 'seo_specialist'])) {
    $availability = $input['availability_status'] ?? 'available';
    if (!in_array($availability, ['available', 'busy', 'on_leave'])) $availability = 'available';
    $specialization = trim(htmlspecialchars($input['specialization'] ?? '', ENT_QUOTES, 'UTF-8'));
    $portfolioUrl   = trim(htmlspecialchars($input['portfolio_url'] ?? '', ENT_QUOTES, 'UTF-8'));

    $optCols[]   = 'availability_status';
    $optValues[] = $availability;
    $optCols[]   = 'specialization';
    $optValues[] = $specialization;
    $optCols[]   = 'portfolio_url';
    $optValues[] = $portfolioUrl;
}

foreach ($optCols as $i => $col) {
    try {
        $pdo->prepare("UPDATE users SET `$col`=? WHERE id=?")->execute([$optValues[$i], $userId]);
    } catch (Exception $e) {
        // Silently ignore unknown column errors (column not yet migrated)
        if ($e->getCode() !== '42S22') {
            echo json_encode(['ok' => false, 'error' => 'Database error']);
            exit;
        }
    }
}

$_SESSION['user_name'] = $name;
echo json_encode(['ok' => true, 'message' => 'Profile updated successfully']);
