<?php
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();
if ($_SESSION['user_role'] !== 'developer') { redirect(BASE_URL . '/' . $_SESSION['user_role'] . '/'); }
update_online_status($pdo, $_SESSION['user_id']);
$csrf = generate_csrf_token();
$flash = get_flash();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? '');
    $project_id = (int)$_POST['project_id'];
    $update_text = trim($_POST['update_text'] ?? '');
    $progress = min(100, max(0, (int)($_POST['progress_percent'] ?? 0)));
    
    $proj = $pdo->prepare("SELECT * FROM projects WHERE id=? AND developer_id=?"); $proj->execute([$project_id, $_SESSION['user_id']]); $proj = $proj->fetch();
    if ($proj && $update_text) {
        $pdo->prepare("INSERT INTO progress_updates (project_id, user_id, update_text, progress_percent) VALUES (?,?,?,?)")
            ->execute([$project_id, $_SESSION['user_id'], $update_text, $progress]);
        $pdo->prepare("UPDATE projects SET progress=? WHERE id=?")->execute([$progress, $project_id]);
        set_flash('success', 'Progress updated!');
    }
    redirect(BASE_URL . '/developer/progress.php');
}

$my_projects = $pdo->prepare("SELECT id, name, progress FROM projects WHERE developer_id=?"); $my_projects->execute([$_SESSION['user_id']]); $my_projects = $my_projects->fetchAll();
$updates = $pdo->prepare("SELECT pu.*, p.name as project_name FROM progress_updates pu JOIN projects p ON pu.project_id=p.id WHERE pu.user_id=? ORDER BY pu.created_at DESC LIMIT 20");
$updates->execute([$_SESSION['user_id']]); $updates = $updates->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Progress — SoftandPix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="<?= e(BASE_URL) ?>/public/assets/css/style.css" rel="stylesheet"></head><body>
<?php include BASE_PATH . '/includes/sidebar_developer.php'; ?>
<div class="topbar"><div class="topbar-left"><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button><h5 class="mb-0">Update Progress</h5></div></div>
<div class="main-content">
<?php if($flash):?><div class="alert alert-<?= $flash['type']==='success'?'success':'danger' ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif;?>
<div class="table-card p-4 mb-4">
<h6>Submit Progress Update</h6>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<div class="row g-3">
<div class="col-md-4"><select name="project_id" class="form-select" required><option value="">Select Project</option>
<?php foreach($my_projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?> (<?= $p['progress'] ?>%)</option><?php endforeach;?></select></div>
<div class="col-md-2"><input type="number" name="progress_percent" class="form-control" placeholder="%" min="0" max="100" required></div>
<div class="col-md-6"><input type="text" name="update_text" class="form-control" placeholder="What did you work on today?" required></div>
</div>
<button type="submit" class="btn btn-primary mt-3"><i class="fas fa-save me-1"></i>Submit Update</button>
</form></div>
<div class="table-card"><div class="card-header">Recent Updates</div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Project</th><th>Update</th><th>Progress</th><th>Date</th></tr></thead><tbody>
<?php if(empty($updates)):?><tr><td colspan="4" class="text-center text-muted py-4">No updates yet</td></tr>
<?php else: foreach($updates as $u):?>
<tr><td><?= e($u['project_name']) ?></td><td><?= e($u['update_text']) ?></td><td><?= $u['progress_percent'] ?>%</td><td><?= time_ago($u['created_at']) ?></td></tr>
<?php endforeach; endif;?></tbody></table></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
