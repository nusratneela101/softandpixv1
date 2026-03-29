<?php
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
require_once BASE_PATH . '/includes/header.php';
require_role('admin');
update_online_status($pdo, $_SESSION['user_id']);

$agreements = $pdo->query("SELECT a.*, u.name as client_name FROM agreements a JOIN users u ON a.client_id=u.id ORDER BY a.created_at DESC")->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Agreements — SoftandPix Admin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="<?= e(BASE_URL) ?>/public/assets/css/style.css" rel="stylesheet"></head><body>
<?php include BASE_PATH . '/includes/sidebar_admin.php'; ?>
<div class="topbar"><div class="topbar-left"><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button><h5 class="mb-0">Agreements</h5></div></div>
<div class="main-content">
<div class="table-card"><div class="card-header">All Agreements (<?= count($agreements) ?>)</div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>Project</th><th>Client</th><th>Budget</th><th>Deadline</th><th>Status</th><th>Date</th></tr></thead><tbody>
<?php if(empty($agreements)):?><tr><td colspan="6" class="text-center text-muted py-4">No agreements. Send one from Chat.</td></tr>
<?php else: foreach($agreements as $a):?>
<tr><td><strong><?= e($a['project_name']) ?></strong></td><td><?= e($a['client_name']) ?></td>
<td><?= format_currency($a['total_budget']) ?></td><td><?= $a['deadline'] ? date('M j, Y', strtotime($a['deadline'])) : '—' ?></td>
<td><span class="badge bg-<?= $a['status']==='approved'?'success':($a['status']==='rejected'?'danger':'warning') ?>"><?= ucfirst($a['status']) ?></span></td>
<td><?= time_ago($a['created_at']) ?></td></tr>
<?php endforeach; endif;?></tbody></table></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
