<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

// Delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    if (verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $uid = (int)($_POST['user_id'] ?? 0);
        if ($uid > 0) {
            try {
                $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]);
                flashMessage('success', 'User deleted.');
            } catch (Exception $e) { flashMessage('error', 'Delete failed.'); }
        }
    }
    header('Location: users.php'); exit;
}

$search     = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 20;
$offset     = ($page - 1) * $perPage;

$where  = "WHERE 1=1";
$params = [];
if ($search) { $where .= " AND (u.name LIKE ? OR u.email LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
if ($roleFilter) { $where .= " AND u.role = ?"; $params[] = $roleFilter; }

try {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM users u $where");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT u.*, cr.role_color, cr.role_label FROM users u LEFT JOIN custom_roles cr ON cr.role_name = u.role $where ORDER BY u.created_at DESC LIMIT $perPage OFFSET $offset");
    $stmt->execute($params);
    $users = $stmt->fetchAll();

    $roles = $pdo->query("SELECT * FROM custom_roles ORDER BY role_label")->fetchAll();
} catch (Exception $e) { $users = []; $roles = []; $total = 0; }

$totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;
$csrf_token = generateCsrfToken();
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-people me-2"></i>Users</h1>
        <p>Manage all registered users</p>
    </div>
    <div>
        <a href="users_add.php" class="btn btn-primary"><i class="bi bi-person-plus me-1"></i>Add User</a>
    </div>
</div>
<div class="container-fluid">
    <!-- Filters -->
    <div class="card mb-3">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-5">
                    <input type="text" name="search" class="form-control" placeholder="Search by name or email..." value="<?php echo h($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="role" class="form-select">
                        <option value="">All Roles</option>
                        <?php foreach ($roles as $r): ?>
                        <option value="<?php echo h($r['role_name']); ?>" <?php echo $roleFilter === $r['role_name'] ? 'selected' : ''; ?>>
                            <?php echo h($r['role_label']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
                </div>
                <div class="col-md-2">
                    <a href="users.php" class="btn btn-outline-secondary w-100">Clear</a>
                </div>
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
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th>Joined</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $u):
                            $color = $u['role_color'] ?? '#6c757d';
                            $label = $u['role_label'] ?? ucfirst($u['role'] ?? '');
                        ?>
                        <tr>
                            <td><?php echo (int)$u['id']; ?></td>
                            <td>
                                <?php if (!empty($u['avatar'])): ?>
                                <img src="../<?php echo h($u['avatar']); ?>" class="rounded-circle me-2" style="width:30px;height:30px;object-fit:cover;" alt="">
                                <?php else: ?>
                                <span class="badge text-white me-2" style="background:<?php echo h($color); ?>;font-size:1rem;"><?php echo h(strtoupper(substr($u['name'] ?? '?', 0, 1))); ?></span>
                                <?php endif; ?>
                                <a href="user_view.php?id=<?php echo (int)$u['id']; ?>"><?php echo h($u['name']); ?></a>
                            </td>
                            <td><?php echo h($u['email']); ?></td>
                            <td><span class="badge" style="background:<?php echo h($color); ?>;"><?php echo h($label); ?></span></td>
                            <td>
                                <?php if ($u['is_active']): ?>
                                <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                                <?php endif; ?>
                                <?php if (empty($u['email_verified'])): ?>
                                <span class="badge bg-warning text-dark">Unverified</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo h(date('M j, Y', strtotime($u['created_at']))); ?></td>
                            <td>
                                <a href="user_view.php?id=<?php echo (int)$u['id']; ?>" class="btn btn-sm btn-outline-info" title="View"><i class="bi bi-eye"></i></a>
                                <a href="users_edit.php?id=<?php echo (int)$u['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit"><i class="bi bi-pencil"></i></a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this user?')">
                                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                    <input type="hidden" name="delete_user" value="1">
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($users)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No users found.</td></tr>
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
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&role=<?php echo urlencode($roleFilter); ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php require_once 'includes/footer.php'; ?>
