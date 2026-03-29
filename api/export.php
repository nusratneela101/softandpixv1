<?php
/**
 * API — Export Generation Endpoint
 *
 * POST /api/export.php
 * Params: data_type, export_type (csv|excel|pdf|json), date_from, date_to, status, preview
 * Returns file download or JSON preview.
 */
session_start();
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/export_helper.php';

// Auth check
$userId   = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['user_role'] ?? '';
$isAdmin  = (isset($_SESSION['admin_id']) || $userRole === 'admin');

if (!$userId && !isset($_SESSION['admin_id'])) {
    header('Content-Type: application/json');
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// CSRF check (skip for preview JSON responses — same origin enforced)
if (empty($_POST['preview'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        header('Content-Type: application/json');
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }
}

ensureExportTables($pdo);

$dataType   = trim($_POST['data_type'] ?? '');
$exportType = trim($_POST['export_type'] ?? 'csv');
$isPreview  = !empty($_POST['preview']);
$filters    = [
    'date_from' => trim($_POST['date_from'] ?? ''),
    'date_to'   => trim($_POST['date_to']   ?? ''),
    'status'    => trim($_POST['status']    ?? ''),
];

// Validate data type
$allowedTypes = array_keys(getExportableDataTypes());
if (!in_array($dataType, $allowedTypes, true)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid data type.']);
    exit;
}

// Validate export type
$allowedFormats = ['csv', 'excel', 'pdf', 'json'];
if (!in_array($exportType, $allowedFormats, true)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid export format.']);
    exit;
}

// Fetch data (preview limits to 10 rows)
try {
    $data    = fetchExportData($pdo, $dataType, $filters, [], $userId, $userRole);
    $columns = getExportColumns($dataType);
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Data fetch error: ' . $e->getMessage()]);
    exit;
}

// ── Preview ──────────────────────────────────────────────
if ($isPreview) {
    header('Content-Type: application/json');
    $rows = array_slice($data, 0, 10);
    // Re-map to column labels for friendlier preview
    $mapped = [];
    foreach ($rows as $row) {
        $r = [];
        if (!empty($columns)) {
            foreach ($columns as $key => $label) {
                $r[$label] = $row[$key] ?? '';
            }
        } else {
            $r = $row;
        }
        $mapped[] = $r;
    }
    echo json_encode(['success' => true, 'total' => count($data), 'rows' => $mapped]);
    exit;
}

// ── Full export ───────────────────────────────────────────
$dataTypesInfo = getExportableDataTypes();
$typeLabel     = is_array($dataTypesInfo[$dataType] ?? null)
    ? ($dataTypesInfo[$dataType]['label'] ?? ucfirst($dataType))
    : ucfirst($dataType);
$title = $typeLabel . ' Export — ' . date('Y-m-d');

switch ($exportType) {
    case 'csv':
        $content  = generateCsvContent($data, $columns);
        $ext      = 'csv';
        $mime     = 'text/csv; charset=UTF-8';
        break;

    case 'excel':
        $content  = generateExcelContent($data, $columns);
        $ext      = 'xls';
        $mime     = 'application/vnd.ms-excel';
        break;

    case 'pdf':
        $content  = generatePdfHtml($data, $columns, $title, $filters);
        $ext      = 'html';
        $mime     = 'text/html; charset=UTF-8';
        break;

    default:
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown format']);
        exit;
}

// Save to history
$saved = saveExportFile($pdo, $userId, $dataType, $exportType, $content, $filters, count($data));

// Stream file
$filename = $dataType . '_' . date('Y-m-d_His') . '.' . $ext;
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($content));
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
echo $content;
exit;
