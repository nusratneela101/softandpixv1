<?php
/**
 * API — Global Search
 * Searches across projects, tasks, users, and invoices.
 * Returns JSON results grouped by type.
 */
session_start();
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/search_helper.php';

header('Content-Type: application/json');
header('Cache-Control: no-store');

// Auth check
if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$q = trim($_GET['q'] ?? '');
if (strlen($q) < 2) {
    echo json_encode(['success' => true, 'results' => []]);
    exit;
}

$userId   = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['user_role'] ?? 'client';
$isAdmin  = isset($_SESSION['admin_id']) || $userRole === 'admin';
$like     = '%' . $q . '%';
$results  = [];

try {
    // ---------------------------------------------------------------
    // Projects
    // ---------------------------------------------------------------
    if ($isAdmin) {
        $stmt = $pdo->prepare("SELECT id, title, status FROM projects WHERE title LIKE ? OR description LIKE ? LIMIT 5");
        $stmt->execute([$like, $like]);
    } elseif ($userRole === 'client') {
        $stmt = $pdo->prepare("SELECT id, title, status FROM projects WHERE client_id=? AND (title LIKE ? OR description LIKE ?) LIMIT 5");
        $stmt->execute([$userId, $like, $like]);
    } else {
        $stmt = $pdo->prepare("SELECT id, title, status FROM projects WHERE developer_id=? AND (title LIKE ? OR description LIKE ?) LIMIT 5");
        $stmt->execute([$userId, $like, $like]);
    }
    foreach ($stmt->fetchAll() as $row) {
        $url = $isAdmin ? "/admin/projects.php" : "/{$userRole}/projects.php";
        $results[] = [
            'type'  => 'project',
            'label' => h($row['title']),
            'meta'  => ucwords(str_replace('_', ' ', $row['status'])),
            'url'   => $url . '?id=' . (int)$row['id'],
            'icon'  => 'bi-kanban',
        ];
    }

    // ---------------------------------------------------------------
    // Tasks
    // ---------------------------------------------------------------
    if ($isAdmin) {
        $tStmt = $pdo->prepare("SELECT t.id, t.title, t.status, p.title as project FROM tasks t LEFT JOIN projects p ON p.id=t.project_id WHERE t.title LIKE ? OR t.description LIKE ? LIMIT 5");
        $tStmt->execute([$like, $like]);
    } elseif ($userRole === 'developer') {
        $tStmt = $pdo->prepare("SELECT t.id, t.title, t.status, p.title as project FROM tasks t LEFT JOIN projects p ON p.id=t.project_id WHERE t.assigned_to=? AND (t.title LIKE ? OR t.description LIKE ?) LIMIT 5");
        $tStmt->execute([$userId, $like, $like]);
    } elseif ($userRole === 'client') {
        $tStmt = $pdo->prepare("SELECT t.id, t.title, t.status, p.title as project FROM tasks t LEFT JOIN projects p ON p.id=t.project_id WHERE p.client_id=? AND (t.title LIKE ? OR t.description LIKE ?) LIMIT 5");
        $tStmt->execute([$userId, $like, $like]);
    } else {
        $tStmt = null;
    }
    if ($tStmt) {
        foreach ($tStmt->fetchAll() as $row) {
            $results[] = [
                'type'  => 'task',
                'label' => h($row['title']),
                'meta'  => h($row['project'] ?? ''),
                'url'   => "/{$userRole}/tasks.php",
                'icon'  => 'bi-clipboard-check',
            ];
        }
    }

    // ---------------------------------------------------------------
    // Users (admin only)
    // ---------------------------------------------------------------
    if ($isAdmin) {
        $uStmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE name LIKE ? OR email LIKE ? LIMIT 5");
        $uStmt->execute([$like, $like]);
        foreach ($uStmt->fetchAll() as $row) {
            $results[] = [
                'type'  => 'user',
                'label' => h($row['name']),
                'meta'  => h($row['email']),
                'url'   => '/admin/users.php?id=' . (int)$row['id'],
                'icon'  => 'bi-person',
            ];
        }
    }

    // ---------------------------------------------------------------
    // Invoices
    // ---------------------------------------------------------------
    if ($isAdmin) {
        $iStmt = $pdo->prepare("SELECT i.id, i.invoice_number, i.status FROM invoices i WHERE i.invoice_number LIKE ? LIMIT 5");
        $iStmt->execute([$like]);
    } elseif ($userRole === 'client') {
        $iStmt = $pdo->prepare("SELECT id, invoice_number, status FROM invoices WHERE client_id=? AND invoice_number LIKE ? LIMIT 5");
        $iStmt->execute([$userId, $like]);
    } else {
        $iStmt = null;
    }
    if ($iStmt) {
        foreach ($iStmt->fetchAll() as $row) {
            $results[] = [
                'type'  => 'invoice',
                'label' => '#' . h($row['invoice_number']),
                'meta'  => ucfirst($row['status']),
                'url'   => '/invoice/view.php?id=' . (int)$row['id'],
                'icon'  => 'bi-receipt',
            ];
        }
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Search error: ' . $e->getMessage()]);
    exit;
}

// Log the search query
if ($userId) {
    try {
        ensureSearchTables($pdo);
        logSearchQuery($pdo, $userId, $q, count($results));
    } catch (Exception $e) {
        // Non-fatal: logging errors should not break search results
    }
}

echo json_encode(['success' => true, 'query' => h($q), 'results' => $results]);
