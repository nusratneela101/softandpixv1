<?php
/**
 * Global Search Results Page
 */
session_start();
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/config/db.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/search_helper.php';

if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

$q        = trim($_GET['q'] ?? '');
$filter   = $_GET['filter'] ?? 'all';
$userId   = (int)($_SESSION['user_id'] ?? 0);
$userRole = $_SESSION['user_role'] ?? 'client';
$isAdmin  = isset($_SESSION['admin_id']) || $userRole === 'admin';

$results  = [];
$total    = 0;

ensureSearchTables($pdo);

if (strlen($q) >= 2) {
    $like = '%' . $q . '%';
    try {
        // Projects
        if ($filter === 'all' || $filter === 'project') {
            if ($isAdmin) {
                $stmt = $pdo->prepare("SELECT id, title, status, description FROM projects WHERE title LIKE ? OR description LIKE ? ORDER BY title LIMIT 20");
                $stmt->execute([$like, $like]);
            } elseif ($userRole === 'client') {
                $stmt = $pdo->prepare("SELECT id, title, status, description FROM projects WHERE client_id=? AND (title LIKE ? OR description LIKE ?) ORDER BY title LIMIT 20");
                $stmt->execute([$userId, $like, $like]);
            } else {
                $stmt = $pdo->prepare("SELECT id, title, status, description FROM projects WHERE developer_id=? AND (title LIKE ? OR description LIKE ?) ORDER BY title LIMIT 20");
                $stmt->execute([$userId, $like, $like]);
            }
            $baseUrl = $isAdmin ? '/admin/projects.php' : "/{$userRole}/projects.php";
            foreach ($stmt->fetchAll() as $row) {
                $results[] = ['type' => 'project', 'icon' => 'fa-project-diagram', 'label' => $row['title'], 'meta' => ucwords(str_replace('_', ' ', $row['status'])), 'desc' => $row['description'] ?? '', 'url' => $baseUrl . '?id=' . (int)$row['id']];
                $total++;
            }
        }

        // Tasks
        if ($filter === 'all' || $filter === 'task') {
            if ($isAdmin) {
                $tStmt = $pdo->prepare("SELECT t.id, t.title, t.status, t.description, p.title AS project FROM tasks t LEFT JOIN projects p ON p.id=t.project_id WHERE t.title LIKE ? OR t.description LIKE ? ORDER BY t.title LIMIT 20");
                $tStmt->execute([$like, $like]);
            } elseif ($userRole === 'developer') {
                $tStmt = $pdo->prepare("SELECT t.id, t.title, t.status, t.description, p.title AS project FROM tasks t LEFT JOIN projects p ON p.id=t.project_id WHERE t.assigned_to=? AND (t.title LIKE ? OR t.description LIKE ?) ORDER BY t.title LIMIT 20");
                $tStmt->execute([$userId, $like, $like]);
            } elseif ($userRole === 'client') {
                $tStmt = $pdo->prepare("SELECT t.id, t.title, t.status, t.description, p.title AS project FROM tasks t LEFT JOIN projects p ON p.id=t.project_id WHERE p.client_id=? AND (t.title LIKE ? OR t.description LIKE ?) ORDER BY t.title LIMIT 20");
                $tStmt->execute([$userId, $like, $like]);
            } else {
                $tStmt = null;
            }
            if (!empty($tStmt)) {
                foreach ($tStmt->fetchAll() as $row) {
                    $results[] = ['type' => 'task', 'icon' => 'fa-clipboard-list', 'label' => $row['title'], 'meta' => $row['project'] ?? '', 'desc' => $row['description'] ?? '', 'url' => "/{$userRole}/tasks.php"];
                    $total++;
                }
            }
        }

        // Users (admin only)
        if ($isAdmin && ($filter === 'all' || $filter === 'user')) {
            $uStmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE name LIKE ? OR email LIKE ? ORDER BY name LIMIT 20");
            $uStmt->execute([$like, $like]);
            foreach ($uStmt->fetchAll() as $row) {
                $results[] = ['type' => 'user', 'icon' => 'fa-user', 'label' => $row['name'], 'meta' => $row['email'], 'desc' => ucfirst($row['role']), 'url' => '/admin/users.php?id=' . (int)$row['id']];
                $total++;
            }
        }

        // Invoices
        if ($filter === 'all' || $filter === 'invoice') {
            if ($isAdmin) {
                $iStmt = $pdo->prepare("SELECT id, invoice_number, status, total_amount FROM invoices WHERE invoice_number LIKE ? ORDER BY id DESC LIMIT 20");
                $iStmt->execute([$like]);
            } elseif ($userRole === 'client') {
                $iStmt = $pdo->prepare("SELECT id, invoice_number, status, total_amount FROM invoices WHERE client_id=? AND invoice_number LIKE ? ORDER BY id DESC LIMIT 20");
                $iStmt->execute([$userId, $like]);
            } else {
                $iStmt = null;
            }
            if (!empty($iStmt)) {
                foreach ($iStmt->fetchAll() as $row) {
                    $results[] = ['type' => 'invoice', 'icon' => 'fa-file-invoice-dollar', 'label' => '#' . $row['invoice_number'], 'meta' => ucfirst($row['status']), 'desc' => 'Amount: ' . number_format((float)($row['total_amount'] ?? 0), 2), 'url' => '/invoice/view.php?id=' . (int)$row['id']];
                    $total++;
                }
            }
        }

        // Log search
        if ($userId) {
            logSearchQuery($pdo, $userId, $q, $total, $filter !== 'all' ? [$filter] : []);
        }
    } catch (Exception $e) {
        error_log('Search error: ' . $e->getMessage());
    }
}

$recentSearches = $userId ? getRecentSearches($pdo, $userId) : [];

$typeLabels = ['project' => 'Projects', 'task' => 'Tasks', 'user' => 'Users', 'invoice' => 'Invoices'];
$header = $isAdmin ? BASE_PATH . '/includes/sidebar_admin.php' : BASE_PATH . '/includes/sidebar_' . $userRole . '.php';
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Search Results — SoftandPix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="<?= e(BASE_URL) ?>/public/assets/css/style.css" rel="stylesheet">
</head><body>
<?php if (file_exists($header)) include $header; ?>
<div class="topbar">
  <div class="topbar-left">
    <button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
    <h5 class="mb-0">Search Results</h5>
  </div>
</div>
<div class="main-content">

<div class="card shadow-sm mb-4">
  <div class="card-body">
    <form method="get" action="">
      <div class="input-group input-group-lg">
        <span class="input-group-text"><i class="fas fa-search"></i></span>
        <input type="text" name="q" class="form-control" placeholder="Search projects, tasks, users, invoices…"
               value="<?= e($q) ?>" autofocus>
        <select name="filter" class="form-select" style="max-width:160px">
          <option value="all"<?= $filter === 'all' ? ' selected' : '' ?>>All Types</option>
          <option value="project"<?= $filter === 'project' ? ' selected' : '' ?>>Projects</option>
          <option value="task"<?= $filter === 'task' ? ' selected' : '' ?>>Tasks</option>
          <?php if ($isAdmin): ?>
          <option value="user"<?= $filter === 'user' ? ' selected' : '' ?>>Users</option>
          <?php endif; ?>
          <option value="invoice"<?= $filter === 'invoice' ? ' selected' : '' ?>>Invoices</option>
        </select>
        <button class="btn btn-primary" type="submit"><i class="fas fa-search me-1"></i>Search</button>
      </div>
    </form>
  </div>
</div>

<?php if ($q !== '' && strlen($q) < 2): ?>
  <div class="alert alert-warning">Please enter at least 2 characters to search.</div>
<?php elseif ($q !== ''): ?>
  <p class="text-muted mb-3">
    <?= $total ?> result<?= $total !== 1 ? 's' : '' ?> for <strong><?= e($q) ?></strong>
    <?= $filter !== 'all' ? ' in <em>' . e($typeLabels[$filter] ?? $filter) . '</em>' : '' ?>
  </p>

  <?php if (empty($results)): ?>
    <div class="text-center py-5 text-muted">
      <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
      <p class="fs-5">No results found for "<?= e($q) ?>"</p>
      <p class="small">Try different keywords or check the spelling.</p>
    </div>
  <?php else: ?>
    <?php
    $grouped = [];
    foreach ($results as $r) { $grouped[$r['type']][] = $r; }
    foreach ($grouped as $type => $items):
    ?>
    <div class="mb-4">
      <h6 class="text-uppercase text-muted small fw-bold mb-2"><?= e($typeLabels[$type] ?? ucfirst($type)) ?> (<?= count($items) ?>)</h6>
      <div class="list-group shadow-sm">
        <?php foreach ($items as $item): ?>
        <a href="<?= e(BASE_URL . $item['url']) ?>" class="list-group-item list-group-item-action d-flex align-items-start gap-3 py-3">
          <div class="rounded-circle bg-primary bg-opacity-10 text-primary d-flex align-items-center justify-content-center flex-shrink-0" style="width:36px;height:36px">
            <i class="fas <?= e($item['icon']) ?> small"></i>
          </div>
          <div class="overflow-hidden">
            <div class="fw-semibold text-truncate"><?= e($item['label']) ?></div>
            <?php if ($item['meta']): ?>
            <div class="small text-muted"><?= e($item['meta']) ?></div>
            <?php endif; ?>
            <?php if ($item['desc']): ?>
            <div class="small text-muted text-truncate"><?= e(substr(strip_tags($item['desc']), 0, 120)) ?></div>
            <?php endif; ?>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
<?php else: ?>
  <?php if (!empty($recentSearches)): ?>
  <div class="mb-4">
    <h6 class="text-muted fw-semibold mb-3"><i class="fas fa-history me-2"></i>Recent Searches</h6>
    <div class="d-flex flex-wrap gap-2">
      <?php foreach ($recentSearches as $rs): ?>
      <a href="?q=<?= urlencode($rs['query']) ?>" class="badge bg-light text-dark border text-decoration-none px-3 py-2">
        <?= e($rs['query']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
  <div class="text-center py-5 text-muted">
    <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
    <p>Enter a search term to get started.</p>
  </div>
<?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body></html>
