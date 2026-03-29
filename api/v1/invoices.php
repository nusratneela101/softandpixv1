<?php
/**
 * API v1 — Invoices endpoints
 *
 * GET    /api/v1/invoices
 * GET    /api/v1/invoices/{id}
 * POST   /api/v1/invoices
 */

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/app.php';
require_once __DIR__ . '/../../includes/api_helper.php';
require_once __DIR__ . '/../../includes/activity_logger.php';

$api_user = get_api_user();

$method   = $_SERVER['REQUEST_METHOD'];
$uri      = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$segments = array_values(array_filter(explode('/', $uri)));

$inv_idx    = array_search('invoices', $segments);
$invoice_id = isset($segments[$inv_idx + 1]) ? (int)$segments[$inv_idx + 1] : null;

if ($invoice_id && $invoice_id <= 0) {
    api_error('Invalid invoice ID.', 400);
}

switch ($method) {
    case 'GET':
        $invoice_id ? get_invoice($pdo, $api_user, $invoice_id) : list_invoices($pdo, $api_user);
        break;
    case 'POST':
        create_invoice($pdo, $api_user);
        break;
    default:
        api_error('Method not allowed.', 405);
}

// ---------------------------------------------------------------------------
// Handlers
// ---------------------------------------------------------------------------

function list_invoices(PDO $pdo, array $api_user): never {
    $where  = [];
    $params = [];

    if ($api_user['role'] === 'client') {
        $where[]  = 'i.client_id = ?';
        $params[] = $api_user['user_id'];
    } elseif ($api_user['role'] === 'developer') {
        // Developers can see invoices for projects they work on.
        $where[]  = 'p.developer_id = ?';
        $params[] = $api_user['user_id'];
    }

    if (isset($_GET['status'])) {
        $where[]  = 'i.status = ?';
        $params[] = $_GET['status'];
    }
    if (isset($_GET['project_id'])) {
        $where[]  = 'i.project_id = ?';
        $params[] = (int)$_GET['project_id'];
    }

    $sql = "SELECT i.*, u.name AS client_name, p.name AS project_name
            FROM invoices i
            LEFT JOIN users u ON u.id = i.client_id
            LEFT JOIN projects p ON p.id = i.project_id";

    if ($where) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY i.created_at DESC';

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

function get_invoice(PDO $pdo, array $api_user, int $id): never {
    $stmt = $pdo->prepare(
        "SELECT i.*, u.name AS client_name, p.name AS project_name, p.developer_id
         FROM invoices i
         LEFT JOIN users u ON u.id = i.client_id
         LEFT JOIN projects p ON p.id = i.project_id
         WHERE i.id = ? LIMIT 1"
    );
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();

    if (!$invoice) {
        api_error('Invoice not found.', 404);
    }

    if (!can_access_invoice($api_user, $invoice)) {
        api_error('Access denied.', 403);
    }

    // Fetch line items.
    $istmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC");
    $istmt->execute([$id]);
    $invoice['items'] = $istmt->fetchAll();

    json_response(['success' => true, 'data' => $invoice]);
}

function create_invoice(PDO $pdo, array $api_user): never {
    if ($api_user['role'] !== 'admin') {
        api_error('Only admins can create invoices.', 403);
    }

    $input          = json_decode(file_get_contents('php://input'), true) ?? [];
    $client_id      = (int)($input['client_id'] ?? 0);
    $total          = (float)($input['total'] ?? 0);
    $invoice_number = trim($input['invoice_number'] ?? '');

    if ($client_id <= 0) {
        api_error('A valid client_id is required.');
    }
    if ($total <= 0) {
        api_error('Invoice total must be greater than zero.');
    }
    if (empty($invoice_number)) {
        $invoice_number = 'INV-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    $stmt = $pdo->prepare(
        "INSERT INTO invoices
            (invoice_number, client_id, project_id, subtotal, tax_percent, tax_amount, discount, total,
             due_date, notes, status, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
    );
    $stmt->execute([
        $invoice_number,
        $client_id,
        (int)($input['project_id'] ?? 0) ?: null,
        (float)($input['subtotal'] ?? $total),
        (float)($input['tax_percent'] ?? 0),
        (float)($input['tax_amount'] ?? 0),
        (float)($input['discount'] ?? 0),
        $total,
        $input['due_date'] ?? null,
        $input['notes'] ?? null,
        $input['status'] ?? 'pending',
    ]);

    $invoice_id = (int)$pdo->lastInsertId();

    // Insert line items if provided.
    if (!empty($input['items']) && is_array($input['items'])) {
        $item_stmt = $pdo->prepare(
            "INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total)
             VALUES (?, ?, ?, ?, ?)"
        );
        foreach ($input['items'] as $item) {
            $qty   = (float)($item['quantity'] ?? 1);
            $price = (float)($item['unit_price'] ?? 0);
            $item_stmt->execute([
                $invoice_id,
                $item['description'] ?? '',
                $qty,
                $price,
                $qty * $price,
            ]);
        }
    }

    log_activity($pdo, $api_user['user_id'], 'api_invoice_created', "Invoice #{$invoice_id} created", 'invoice', $invoice_id);

    json_response(['success' => true, 'id' => $invoice_id, 'invoice_number' => $invoice_number, 'message' => 'Invoice created.'], 201);
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function can_access_invoice(array $api_user, array $invoice): bool {
    if ($api_user['role'] === 'admin') {
        return true;
    }
    if ($api_user['role'] === 'client') {
        return (int)$invoice['client_id'] === (int)$api_user['user_id'];
    }
    if ($api_user['role'] === 'developer') {
        return (int)($invoice['developer_id'] ?? 0) === (int)$api_user['user_id'];
    }
    return false;
}
