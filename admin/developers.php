<?php
/**
 * Admin Developer Management
 */
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_role('admin');
update_online_status($pdo, $_SESSION['user_id']);
$csrf = generate_csrf_token();
$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        if ($name && $email && $password) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (name, email, password, role, is_active, email_verified) VALUES (?,?,?,'developer',1,1)")->execute([$name, $email, $hash]);
            set_flash('success', 'Developer created!');
        }
    } elseif ($action === 'delete') {
        $id = (int)$_POST['user_id'];
        $pdo->prepare("UPDATE users SET is_active=0 WHERE id=? AND role='developer'")->execute([$id]);
        set_flash('success', 'Developer deactivated.');
    }
    redirect(BASE_URL . '/admin/developers.php');
}

$developers = $pdo->query("SELECT * FROM users WHERE role='developer' ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Developers — SoftandPix Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="<?= e(BASE_URL) ?>/public/assets/css/style.css" rel="stylesheet"></head><body>
<?php include BASE_PATH . '/includes/sidebar_admin.php'; ?>
<div class="topbar"><div class="topbar-left"><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button><h5 class="mb-0">Developers</h5></div>
<div class="topbar-right"><button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal"><i class="fas fa-plus me-1"></i>Add Developer</button></div></div>
<div class="main-content">
<?php if($flash):?><div class="alert alert-<?= $flash['type']==='success'?'success':'danger' ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif;?>
<div class="table-card"><div class="card-header">Developers (<?= count($developers) ?>)</div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Name</th><th>Email</th><th>Status</th><th>Joined</th><th>Action</th></tr></thead><tbody>
<?php foreach($developers as $d):?>
<tr><td><?= e($d['name']) ?></td><td><?= e($d['email']) ?></td>
<td><span class="badge bg-<?= $d['is_active']?'success':'secondary' ?>"><?= $d['is_active']?'Active':'Inactive' ?></span></td>
<td><?= time_ago($d['created_at']) ?></td>
<td><form method="POST" style="display:inline" onsubmit="return confirm('Deactivate?')"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="user_id" value="<?= $d['id'] ?>">
<button class="btn btn-sm btn-outline-danger"><i class="fas fa-ban"></i></button></form></td></tr>
<?php endforeach;?></tbody></table></div></div></div>
<div class="modal fade" id="addModal"><div class="modal-dialog"><div class="modal-content"><div class="modal-header"><h5>Add Developer</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create">
<div class="modal-body">
<div class="mb-3"><label class="form-label">Name</label><input type="text" name="name" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
<div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required minlength="6"></div>
</div><div class="modal-footer"><button type="submit" class="btn btn-primary">Create</button></div></form></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
