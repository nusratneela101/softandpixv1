<?php
session_start();
require_once '../config/db.php';
require_once 'includes/auth.php';
requireDeveloper();

$userId    = $_SESSION['user_id'];
$projectId = (int)($_GET['id'] ?? 0);
$flash     = getFlashMessage();

if (!$projectId) { header('Location: /developer/'); exit; }

try {
    $stmt = $pdo->prepare("SELECT p.*, u.name AS client_name FROM projects p
        LEFT JOIN users u ON u.id = p.client_id
        WHERE p.id=? AND (p.developer_id=? OR ? = 'admin')");
    $stmt->execute([$projectId, $userId, $_SESSION['user_role']]);
    $project = $stmt->fetch();
} catch (Exception $e) { $project = null; }

if (!$project) { header('Location: /developer/'); exit; }

// Milestones
$milestones = [];
try {
    $ms = $pdo->prepare("SELECT * FROM project_milestones WHERE project_id=? ORDER BY due_date ASC");
    $ms->execute([$projectId]);
    $milestones = $ms->fetchAll();
} catch (Exception $e) {}

// Activity / updates
$updates = [];
try {
    $upd = $pdo->prepare("SELECT pu.*, u.name AS author_name FROM project_updates pu
        LEFT JOIN users u ON u.id = pu.user_id
        WHERE pu.project_id=? ORDER BY pu.created_at DESC LIMIT 20");
    $upd->execute([$projectId]);
    $updates = $upd->fetchAll();
} catch (Exception $e) {}

// Uploaded files
$files = [];
try {
    $pf = $pdo->prepare("SELECT pf.*, u.name AS uploader_name FROM project_files pf
        LEFT JOIN users u ON u.id = pf.uploaded_by
        WHERE pf.project_id=? ORDER BY pf.created_at DESC");
    $pf->execute([$projectId]);
    $files = $pf->fetchAll();
} catch (Exception $e) {}

// Handle POST: post a text update
$updateError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $csrfOk = verifyCsrfToken($_POST['csrf_token'] ?? '');
    if (!$csrfOk) {
        $updateError = 'Invalid CSRF token.';
    } elseif ($_POST['action'] === 'post_update') {
        $message = trim($_POST['update_message'] ?? '');
        if (empty($message)) {
            $updateError = 'Update message cannot be empty.';
        } else {
            try {
                $pdo->prepare("INSERT INTO project_updates (project_id, user_id, message) VALUES (?,?,?)")
                    ->execute([$projectId, $userId, $message]);
                flashMessage('success', 'Update posted.');
                header("Location: /developer/project_view.php?id=$projectId"); exit;
            } catch (PDOException $e) {
                $updateError = 'Failed to post update.';
            }
        }
    }
}

$csrf_token  = generateCsrfToken();
$statusColor = ['pending'=>'warning','in_progress'=>'primary','on_hold'=>'secondary','completed'=>'success','cancelled'=>'danger'][$project['status']] ?? 'secondary';
$priorityColor = ['low'=>'success','medium'=>'warning','high'=>'danger','urgent'=>'danger'][$project['priority'] ?? 'medium'] ?? 'secondary';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo h($project['title']); ?> - Developer Portal</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
body { background: #f0f7ff; }
.sidebar { background: linear-gradient(180deg,#0c4a6e,#0ea5e9); width:240px; min-height:100vh; position:fixed; top:0; left:0; z-index:100; }
.sidebar .brand { padding:20px; border-bottom:1px solid rgba(255,255,255,.2); }
.sidebar .nav-link { color:rgba(255,255,255,.85); padding:10px 20px; display:flex; align-items:center; gap:10px; }
.sidebar .nav-link:hover { background:rgba(255,255,255,.15); color:#fff; border-radius:8px; margin:2px 8px; padding:10px 12px; }
.main-content { margin-left:240px; padding:24px; }
.section-card { border-radius:12px; border:1px solid #e2e8f0; background:#fff; }
.progress { height:12px; border-radius:8px; }
.milestone-item { border-left:3px solid #dee2e6; padding:8px 12px; margin-bottom:8px; }
.milestone-item.completed { border-color:#10b981; background:#f0fdf4; }
.file-item { border:1px solid #e2e8f0; border-radius:8px; padding:10px; }
</style>
</head>
<body>

<!-- Sidebar -->
<div class="sidebar">
    <div class="brand">
        <img src="/assets/img/SoftandPix -LOGO.png" alt="Softandpix" style="max-height:35px;filter:brightness(10);">
    </div>
    <div class="mt-3 px-3 pb-2 small text-white-50"><?php echo h($_SESSION['user_name'] ?? ''); ?></div>
    <nav class="nav flex-column mt-2">
        <a class="nav-link" href="/developer/"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a class="nav-link" href="/developer/chat.php"><i class="bi bi-chat-dots"></i> Chat</a>
        <a class="nav-link" href="/developer/notifications.php"><i class="bi bi-bell"></i> Notifications</a>
        <a class="nav-link" href="/profile.php"><i class="bi bi-person"></i> Profile</a>
        <hr style="border-color:rgba(255,255,255,.2);margin:8px 16px;">
        <a class="nav-link" href="/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a>
    </nav>
</div>

<!-- Main Content -->
<div class="main-content">
    <?php if ($flash): ?>
    <div class="alert alert-<?php echo $flash['type']==='success'?'success':'danger'; ?> alert-dismissible fade show">
        <?php echo h($flash['message']); ?><button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>
    <?php if ($updateError): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <?php echo h($updateError); ?><button class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/developer/">Dashboard</a></li>
            <li class="breadcrumb-item active"><?php echo h($project['title']); ?></li>
        </ol>
    </nav>

    <!-- Project Header -->
    <div class="section-card p-4 mb-4">
        <div class="row align-items-start">
            <div class="col-md-8">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <h4 class="fw-bold mb-0"><?php echo h($project['title']); ?></h4>
                    <span class="badge bg-<?php echo $statusColor; ?>"><?php echo ucwords(str_replace('_',' ',$project['status'])); ?></span>
                    <?php if (!empty($project['priority'])): ?>
                    <span class="badge bg-<?php echo $priorityColor; ?> bg-opacity-75">
                        <?php echo ucfirst($project['priority']); ?> priority
                    </span>
                    <?php endif; ?>
                </div>
                <?php if (!empty($project['description'])): ?>
                <p class="text-muted mb-3"><?php echo nl2br(h($project['description'])); ?></p>
                <?php endif; ?>
                <div class="row g-2 text-sm small">
                    <?php if (!empty($project['client_name'])): ?>
                    <div class="col-auto"><i class="bi bi-person me-1 text-muted"></i><strong>Client:</strong> <?php echo h($project['client_name']); ?></div>
                    <?php endif; ?>
                    <?php if ($project['start_date']): ?>
                    <div class="col-auto"><i class="bi bi-calendar me-1 text-muted"></i><strong>Start:</strong> <?php echo date('M j, Y', strtotime($project['start_date'])); ?></div>
                    <?php endif; ?>
                    <?php if ($project['deadline']): ?>
                    <div class="col-auto"><i class="bi bi-calendar-x me-1 text-muted"></i><strong>Deadline:</strong>
                        <span class="<?php echo strtotime($project['deadline']) < time() && $project['status'] !== 'completed' ? 'text-danger fw-semibold' : ''; ?>">
                            <?php echo date('M j, Y', strtotime($project['deadline'])); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($project['budget'])): ?>
                    <div class="col-auto"><i class="bi bi-currency-dollar me-1 text-muted"></i><strong>Budget:</strong> $<?php echo number_format($project['budget'], 2); ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                <?php if (!empty($project['demo_enabled']) && !empty($project['demo_subdomain'])): ?>
                <a href="https://<?php echo h($project['demo_subdomain']); ?>.softandpix.com"
                   target="_blank" rel="noopener"
                   class="btn btn-info text-white btn-sm mb-2 me-2">
                    <i class="bi bi-play-circle me-1"></i>View Live Demo
                </a>
                <?php endif; ?>
                <a href="/developer/deadline_request.php?project_id=<?php echo (int)$project['id']; ?>"
                   class="btn btn-outline-warning btn-sm">
                    <i class="bi bi-calendar-x me-1"></i>Request Extension
                </a>
            </div>
        </div>

        <!-- Progress Update -->
        <hr>
        <div class="row align-items-center g-3">
            <div class="col-md-8">
                <label class="form-label fw-semibold mb-1">
                    Progress: <span id="progressLabel"><?php echo (int)($project['progress'] ?? 0); ?></span>%
                </label>
                <input type="range" class="form-range" id="progressSlider"
                       min="0" max="100" value="<?php echo (int)($project['progress'] ?? 0); ?>">
                <div class="progress mt-2">
                    <div class="progress-bar bg-primary" id="progressBar"
                         style="width:<?php echo (int)($project['progress'] ?? 0); ?>%;"></div>
                </div>
            </div>
            <div class="col-md-4">
                <button class="btn btn-primary w-100" id="saveProgressBtn" onclick="saveProgress()">
                    <i class="bi bi-check2 me-1"></i>Update Progress
                </button>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <!-- Left column: Milestones + Updates -->
        <div class="col-lg-8">
            <!-- Milestones -->
            <?php if (!empty($milestones)): ?>
            <div class="section-card p-4 mb-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-flag me-2 text-primary"></i>Milestones</h6>
                <?php foreach ($milestones as $m):
                    $mDone = $m['status'] === 'completed';
                ?>
                <div class="milestone-item <?php echo $mDone ? 'completed' : ''; ?> d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-semibold <?php echo $mDone ? 'text-decoration-line-through text-muted' : ''; ?>">
                            <?php echo h($m['title']); ?>
                        </div>
                        <?php if ($m['due_date']): ?>
                        <small class="text-muted">Due: <?php echo date('M j, Y', strtotime($m['due_date'])); ?></small>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-<?php echo $mDone ? 'success' : 'secondary'; ?>">
                        <?php echo $mDone ? 'Done' : ucfirst($m['status']); ?>
                    </span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Post Update -->
            <div class="section-card p-4 mb-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-pencil me-2 text-primary"></i>Post an Update</h6>
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="action" value="post_update">
                    <textarea name="update_message" class="form-control mb-2" rows="3"
                              placeholder="Share a progress update, note, or question..."></textarea>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-send me-1"></i>Post Update
                    </button>
                </form>
            </div>

            <!-- Activity Feed -->
            <?php if (!empty($updates)): ?>
            <div class="section-card p-4 mb-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-activity me-2 text-primary"></i>Activity</h6>
                <?php foreach ($updates as $u): ?>
                <div class="d-flex gap-3 mb-3">
                    <div class="flex-shrink-0">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center"
                             style="width:36px;height:36px;font-size:.85rem;">
                            <?php echo strtoupper(substr($u['author_name'] ?? 'U', 0, 1)); ?>
                        </div>
                    </div>
                    <div>
                        <div class="fw-semibold small"><?php echo h($u['author_name'] ?? 'Unknown'); ?>
                            <span class="text-muted fw-normal ms-1"><?php echo timeAgo($u['created_at']); ?></span>
                        </div>
                        <div class="mt-1"><?php echo nl2br(h($u['message'])); ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Right column: Files -->
        <div class="col-lg-4">
            <!-- Upload File -->
            <div class="section-card p-4 mb-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-upload me-2 text-primary"></i>Upload File</h6>
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="hidden" name="project_id" value="<?php echo (int)$project['id']; ?>">
                    <input type="file" name="file" class="form-control form-control-sm mb-2" id="fileInput">
                    <button type="submit" class="btn btn-outline-primary btn-sm w-100">
                        <i class="bi bi-cloud-upload me-1"></i>Upload
                    </button>
                </form>
                <div id="uploadMsg" class="mt-2 small"></div>
            </div>

            <!-- Files List -->
            <div class="section-card p-4">
                <h6 class="fw-bold mb-3"><i class="bi bi-folder2 me-2 text-primary"></i>Project Files</h6>
                <?php if (empty($files)): ?>
                <p class="text-muted small">No files uploaded yet.</p>
                <?php else: ?>
                <?php foreach ($files as $f): ?>
                <div class="file-item d-flex align-items-center gap-2 mb-2">
                    <?php
                    $ext = strtolower(pathinfo($f['file_name'] ?? $f['file_path'] ?? '', PATHINFO_EXTENSION));
                    $icon = in_array($ext, ['jpg','jpeg','png','gif','webp']) ? 'bi-image' :
                            ($ext === 'pdf' ? 'bi-file-pdf' :
                            ($ext === 'zip' ? 'bi-file-zip' : 'bi-file-earmark'));
                    ?>
                    <i class="bi <?php echo $icon; ?> text-primary fs-5"></i>
                    <div class="flex-grow-1 overflow-hidden">
                        <div class="small fw-semibold text-truncate"><?php echo h($f['file_name'] ?? basename($f['file_path'] ?? 'File')); ?></div>
                        <div class="text-muted" style="font-size:.75rem;"><?php echo h($f['uploader_name'] ?? ''); ?> · <?php echo timeAgo($f['created_at']); ?></div>
                    </div>
                    <a href="<?php echo h($f['file_path']); ?>" target="_blank" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Progress slider
const slider = document.getElementById('progressSlider');
const label  = document.getElementById('progressLabel');
const bar    = document.getElementById('progressBar');

slider.addEventListener('input', function() {
    label.textContent = this.value;
    bar.style.width   = this.value + '%';
});

function saveProgress() {
    const btn = document.getElementById('saveProgressBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Saving...';

    const formData = new FormData();
    formData.append('project_id', <?php echo (int)$project['id']; ?>);
    formData.append('progress', slider.value);

    fetch('/api/project/update_progress.php', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Saved!';
                btn.classList.replace('btn-primary', 'btn-success');
                setTimeout(() => {
                    btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Update Progress';
                    btn.classList.replace('btn-success', 'btn-primary');
                    btn.disabled = false;
                }, 2000);
            } else {
                alert(data.error || 'Failed to update progress.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Update Progress';
            }
        })
        .catch(() => {
            alert('Network error.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check2 me-1"></i>Update Progress';
        });
}

// File upload
document.getElementById('uploadForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const msgEl  = document.getElementById('uploadMsg');
    const file   = document.getElementById('fileInput').files[0];
    if (!file) return;

    const fd = new FormData(this);
    msgEl.innerHTML = '<span class="text-muted">Uploading...</span>';

    fetch('/api/project/upload_file.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                msgEl.innerHTML = '<span class="text-success"><i class="bi bi-check2"></i> Uploaded successfully.</span>';
                setTimeout(() => location.reload(), 1500);
            } else {
                msgEl.innerHTML = '<span class="text-danger">' + (data.error || 'Upload failed.') + '</span>';
            }
        })
        .catch(() => { msgEl.innerHTML = '<span class="text-danger">Network error.</span>'; });
});
</script>
</body>
</html>
