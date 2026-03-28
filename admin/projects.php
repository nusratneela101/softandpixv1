<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

// Delete project
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_project'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $pid = (int)($_POST['project_id'] ?? 0);
        if ($pid > 0) {
            try {
                $pdo->prepare("DELETE FROM projects WHERE id = ?")->execute([$pid]);
                flashMessage('success', 'Project deleted.');
            } catch (Exception $e) { flashMessage('error', 'Delete failed.'); }
        }
    }
    header('Location: projects.php'); exit;
}

$search  = trim($_GET['search'] ?? '');
$statusF = trim($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (p.title LIKE ? OR u.name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($statusF) { $where .= " AND p.status = ?"; $params[] = $statusF; }

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM projects p LEFT JOIN users u ON u.id = p.client_id $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT p.*, u.name AS client_name, d.name AS developer_name FROM projects p LEFT JOIN users u ON u.id = p.client_id LEFT JOIN users d ON d.id = p.developer_id $where ORDER BY p.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $projects = $stmt->fetchAll();
} catch (Exception $e) { $projects = []; $total = 0; }

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
$csrf_token = generateCsrfToken();
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-kanban me-2"></i>Projects</h1>
        <p>Manage all client projects</p>
    </div>
    <a href="project_add.php" class="btn btn-primary"><i class="bi bi-plus-circle me-1"></i>New Project</a>
</div>
<div class="container-fluid">
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Search by title or client name..." value="<?php echo h($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <?php foreach (['pending', 'in_progress', 'on_hold', 'completed', 'cancelled'] as $s): ?>
                        <option value="<?php echo $s; ?>" <?php echo $statusF === $s ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_', ' ', $s)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-auto"><button type="submit" class="btn btn-outline-primary">Filter</button></div>
                <div class="col-auto"><a href="projects.php" class="btn btn-outline-secondary">Clear</a></div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Title</th>
                            <th>Client</th>
                            <th>Developer</th>
                            <th>Status</th>
                            <th>Priority</th>
                            <th>Progress</th>
                            <th>Deadline</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $p):
                            $statusColor   = ['pending' => 'warning', 'in_progress' => 'primary', 'on_hold' => 'secondary', 'completed' => 'success', 'cancelled' => 'danger'][$p['status']] ?? 'secondary';
                            $priorityColor = ['low' => 'success', 'medium' => 'warning', 'high' => 'danger', 'urgent' => 'danger'][$p['priority']] ?? 'secondary';
                            $isOverdue     = !empty($p['deadline']) && $p['status'] !== 'completed' && strtotime($p['deadline']) < time();
                        ?>
                        <tr>
                            <td><?php echo (int)$p['id']; ?></td>
                            <td><a href="project_view.php?id=<?php echo (int)$p['id']; ?>"><?php echo h($p['title']); ?></a></td>
                            <td><?php echo h($p['client_name'] ?? '—'); ?></td>
                            <td><?php echo h($p['developer_name'] ?? '—'); ?></td>
                            <td><span class="badge bg-<?php echo $statusColor; ?>"><?php echo h(ucwords(str_replace('_', ' ', $p['status']))); ?></span></td>
                            <td><span class="badge bg-<?php echo $priorityColor; ?>"><?php echo h(ucfirst($p['priority'] ?? '')); ?></span></td>
                            <td>
                                <div class="progress" style="height:6px;width:80px;">
                                    <div class="progress-bar" style="width:<?php echo (int)$p['progress']; ?>%"></div>
                                </div>
                                <small><?php echo (int)$p['progress']; ?>%</small>
                            </td>
                            <td class="<?php echo $isOverdue ? 'text-danger fw-semibold' : ''; ?>">
                                <?php echo $p['deadline'] ? h(date('M j, Y', strtotime($p['deadline']))) : '—'; ?>
                                <?php if ($isOverdue): ?><i class="bi bi-exclamation-triangle ms-1" title="Overdue"></i><?php endif; ?>
                            </td>
                            <td>
                                <a href="project_view.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-info" title="View"><i class="bi bi-eye"></i></a>
                                <a href="project_progress.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-success" title="Progress"><i class="bi bi-bar-chart-line"></i></a>
                                <a href="project_edit.php?id=<?php echo (int)$p['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this project?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                    <input type="hidden" name="project_id" value="<?php echo (int)$p['id']; ?>">
                                    <input type="hidden" name="delete_project" value="1">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($projects)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No projects found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php if ($totalPages > 1): ?>
        <div class="card-footer">
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&status=<?php echo urlencode($statusF); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
