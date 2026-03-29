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
    $proj = $pdo->prepare("SELECT * FROM projects WHERE id=? AND developer_id=?"); $proj->execute([$project_id, $_SESSION['user_id']]); $proj = $proj->fetch();
    if ($proj && !empty($_FILES['file']['name'])) {
        if (validate_file_upload($_FILES['file'])) {
            $path = upload_file($_FILES['file'], 'projects/' . $project_id);
            if ($path) {
                $pdo->prepare("INSERT INTO project_files (project_id, uploaded_by, file_name, file_path, file_size) VALUES (?,?,?,?,?)")
                    ->execute([$project_id, $_SESSION['user_id'], $_FILES['file']['name'], $path, $_FILES['file']['size']]);
                set_flash('success', 'File uploaded!');
            }
        } else { set_flash('error', 'Invalid file type or too large.'); }
    }
    redirect(BASE_URL . '/developer/files.php');
}

$my_projects = $pdo->prepare("SELECT id, name FROM projects WHERE developer_id=?"); $my_projects->execute([$_SESSION['user_id']]); $my_projects = $my_projects->fetchAll();
$files = $pdo->prepare("SELECT pf.*, p.name as project_name FROM project_files pf JOIN projects p ON pf.project_id=p.id WHERE pf.uploaded_by=? ORDER BY pf.created_at DESC");
$files->execute([$_SESSION['user_id']]); $files = $files->fetchAll();
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title>Files — SoftandPix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<link href="<?= e(BASE_URL) ?>/public/assets/css/style.css" rel="stylesheet"></head><body>
<?php include BASE_PATH . '/includes/sidebar_developer.php'; ?>
<div class="topbar"><div class="topbar-left"><button class="sidebar-toggle" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button><h5 class="mb-0">Project Files</h5></div></div>
<div class="main-content">
<?php if($flash):?><div class="alert alert-<?= $flash['type']==='success'?'success':'danger' ?>"><?= htmlspecialchars($flash['message']) ?></div><?php endif;?>
<div class="table-card p-4 mb-4">
<h6>Upload File</h6>
<form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
<div class="row g-3">
<div class="col-md-4"><select name="project_id" class="form-select" required><option value="">Select Project</option>
<?php foreach($my_projects as $p):?><option value="<?= $p['id'] ?>"><?= e($p['name']) ?></option><?php endforeach;?></select></div>
<div class="col-md-5"><input type="file" name="file" class="form-control" required></div>
<div class="col-md-3"><button type="submit" class="btn btn-primary w-100"><i class="fas fa-upload me-1"></i>Upload</button></div>
</div></form></div>
<div class="table-card"><div class="card-header">Uploaded Files (<?= count($files) ?>)</div>
<div class="table-responsive"><table class="table table-hover mb-0"><thead class="table-light"><tr><th>File</th><th>Project</th><th>Size</th><th>Date</th></tr></thead><tbody>
<?php if(empty($files)):?><tr><td colspan="4" class="text-center text-muted py-4">No files uploaded yet</td></tr>
<?php else: foreach($files as $f):?>
<tr><td><i class="fas fa-file me-2"></i><?= e($f['file_name']) ?></td><td><?= e($f['project_name']) ?></td>
<td><?= $f['file_size'] ? round($f['file_size']/1024) . ' KB' : '—' ?></td><td><?= time_ago($f['created_at']) ?></td></tr>
<?php endforeach; endif;?></tbody></table></div></div></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script></body></html>
