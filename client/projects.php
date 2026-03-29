<?php
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_login();
if ($_SESSION['user_role'] !== 'client') { redirect(BASE_URL . '/' . $_SESSION['user_role'] . '/'); }
update_online_status($pdo, $_SESSION['user_id']);

$projects = $pdo->prepare("SELECT p.*, d.name as developer_name FROM projects p LEFT JOIN users d ON p.developer_id=d.id WHERE p.client_id=? ORDER BY p.created_at DESC");
$projects->execute([$_SESSION['user_id']]); $projects = $projects->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>My Projects — SoftandPix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="<?= e(BASE_URL) ?>/public/assets/css/style.css" rel="stylesheet"></head><body>
<?php include BASE_PATH . '/includes/sidebar_client.php'; ?>
<div class="topbar"><div class="topbar-left"><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button><h5 class="mb-0">My Projects</h5></div></div>
<div class="main-content">
<div class="table-card"><div class="card-header">My Projects (<?= count($projects) ?>)</div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Project</th><th>Developer</th><th>Deadline</th><th>Status</th><th>Progress</th></tr></thead><tbody>
<?php if(empty($projects)):?><tr><td colspan="5" class="text-center text-muted py-4">No projects yet</td></tr>
<?php else: foreach($projects as $p):?>
<tr><td><strong><?= e($p['name']) ?></strong><br><small class="text-muted"><?= e(substr($p['description']??'',0,60)) ?></small></td>
<td><?= $p['developer_name'] ? e($p['developer_name']) : '<span class="text-muted">Pending</span>' ?></td>
<td><?= $p['deadline'] ? date('M j, Y', strtotime($p['deadline'])) : '—' ?></td>
<td><span class="badge-status badge-<?= $p['status'] ?>"><?= ucfirst(str_replace('_',' ',$p['status'])) ?></span></td>
<td><div class="progress-bar-custom" style="width:80px"><div class="progress-fill" style="width:<?= (int)$p['progress'] ?>%"></div></div><small><?= (int)$p['progress'] ?>%</small></td></tr>
<?php endforeach; endif;?></tbody></table></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="<?= e(BASE_URL) ?>/public/assets/js/main.js"></script></body></html>
