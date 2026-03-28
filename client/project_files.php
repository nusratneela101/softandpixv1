<?php
/**
 * client/project_files.php
 * Client-facing file manager — can upload, download, comment; cannot delete.
 */

session_start();
require_once '../config/db.php';
require_once 'includes/auth.php';
requireClient();

$userId    = $_SESSION['user_id'];
$projectId = (int)($_GET['project_id'] ?? 0);
$folderId  = !empty($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;

if (!$projectId) { header('Location: /client/'); exit; }

// ── Verify access ─────────────────────────────────────────────
try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND (client_id = ? OR ? = 'admin')");
    $stmt->execute([$projectId, $userId, $_SESSION['user_role']]);
    $project = $stmt->fetch();
} catch (Exception $e) { $project = null; }
if (!$project) { header('Location: /client/'); exit; }

// ── Current folder ────────────────────────────────────────────
$currentFolder = null;
if ($folderId) {
    try {
        $cfStmt = $pdo->prepare("SELECT * FROM project_folders WHERE id = ? AND project_id = ?");
        $cfStmt->execute([$folderId, $projectId]);
        $currentFolder = $cfStmt->fetch();
    } catch (Exception $e) {}
    if (!$currentFolder) { header('Location: /client/project_files.php?project_id=' . $projectId); exit; }
}

// ── Breadcrumb ────────────────────────────────────────────────
$breadcrumbs = [];
if ($folderId) {
    $tmpId = $folderId;
    while ($tmpId) {
        try {
            $bcStmt = $pdo->prepare("SELECT * FROM project_folders WHERE id = ?");
            $bcStmt->execute([$tmpId]);
            $bc = $bcStmt->fetch();
        } catch (Exception $e) { break; }
        if (!$bc) break;
        array_unshift($breadcrumbs, $bc);
        $tmpId = $bc['parent_id'] ?: 0;
    }
}

// ── Folders ───────────────────────────────────────────────────
$folders = [];
try {
    $fStmt = $pdo->prepare(
        "SELECT f.*,
                (SELECT COUNT(*) FROM project_files pf WHERE pf.folder_id = f.id AND pf.is_deleted = 0) AS file_count
         FROM project_folders f
         WHERE f.project_id = ?
           AND (f.parent_id = ? OR (f.parent_id IS NULL AND ? IS NULL))
         ORDER BY f.name"
    );
    $fStmt->execute([$projectId, $folderId, $folderId]);
    $folders = $fStmt->fetchAll();
} catch (Exception $e) {}

// ── Files ─────────────────────────────────────────────────────
$files = [];
try {
    $fileStmt = $pdo->prepare(
        "SELECT pf.*, u.name AS uploader_name
         FROM project_files pf
         LEFT JOIN users u ON u.id = pf.uploaded_by
         WHERE pf.project_id = ?
           AND (pf.folder_id = ? OR (pf.folder_id IS NULL AND ? IS NULL))
           AND pf.is_deleted = 0
           AND (pf.is_private IS NULL OR pf.is_private = 0)
           AND (pf.version = (
               SELECT MAX(pf2.version) FROM project_files pf2
               WHERE pf2.project_id = pf.project_id
                 AND pf2.original_name = pf.original_name
                 AND (pf2.folder_id = pf.folder_id OR (pf2.folder_id IS NULL AND pf.folder_id IS NULL))
                 AND pf2.is_deleted = 0
           ))
         ORDER BY pf.original_name"
    );
    $fileStmt->execute([$projectId, $folderId, $folderId]);
    $files = $fileStmt->fetchAll();
} catch (Exception $e) {}

// ── All folders for move dropdown ─────────────────────────────
$allFolders = [];
try {
    $afStmt = $pdo->prepare("SELECT id, name FROM project_folders WHERE project_id = ? ORDER BY name");
    $afStmt->execute([$projectId]);
    $allFolders = $afStmt->fetchAll();
} catch (Exception $e) {}

// ── Helpers ───────────────────────────────────────────────────
function h($s) { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
function fmtSize($bytes) {
    $bytes = (int)$bytes;
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return round($bytes/1024,1) . ' KB';
    if ($bytes < 1073741824) return round($bytes/1048576,1) . ' MB';
    return round($bytes/1073741824,2) . ' GB';
}
function fileIconClass($ext, $mime = '') {
    $e = strtolower($ext ?? '');
    $m = strtolower($mime ?? '');
    if (in_array($e,['jpg','jpeg','png','gif','webp','bmp','svg','ico'])||strpos($m,'image/')===0) return ['bi bi-file-image','icon-image'];
    if ($e==='pdf') return ['bi bi-file-pdf','icon-pdf'];
    if (in_array($e,['doc','docx','odt','rtf'])) return ['bi bi-file-word','icon-word'];
    if (in_array($e,['xls','xlsx','csv','ods'])) return ['bi bi-file-excel','icon-excel'];
    if (in_array($e,['zip','rar','7z','tar','gz'])) return ['bi bi-file-zip','icon-archive'];
    if (in_array($e,['txt','md','log'])) return ['bi bi-file-text','icon-text'];
    if (in_array($e,['php','js','ts','html','css','json','xml','sql','py'])) return ['bi bi-file-code','icon-code'];
    return ['bi bi-file-earmark','icon-file'];
}
function isImage($ext, $mime='') {
    return in_array(strtolower($ext),['jpg','jpeg','png','gif','webp','bmp','svg'])||strpos($mime,'image/')===0;
}

$csrf = generateCsrfToken();

// ── Unread notifications ──────────────────────────────────────
$unreadNotifs = 0;
try {
    $un = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
    $un->execute([$userId]); $unreadNotifs = (int)$un->fetchColumn();
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Project Files — <?php echo h($project['title']); ?></title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<link href="/assets/css/file-manager.css" rel="stylesheet">
<style>
body { background:#f8fafc; }
.top-nav { background:#fff; border-bottom:1px solid #e2e8f0; padding:10px 20px; display:flex; align-items:center; justify-content:space-between; }
.top-nav .brand { font-weight:700; color:#1e293b; font-size:1.1rem; text-decoration:none; }
</style>
</head>
<body>
<!-- Top Nav -->
<nav class="top-nav">
    <a href="/client/" class="brand"><i class="bi bi-grid-1x2-fill me-2 text-primary"></i>Client Portal</a>
    <div class="d-flex align-items-center gap-3">
        <a href="/client/notifications.php" class="text-decoration-none position-relative">
            <i class="bi bi-bell fs-5"></i>
            <?php if ($unreadNotifs > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem"><?php echo $unreadNotifs; ?></span>
            <?php endif; ?>
        </a>
        <span class="text-muted small"><?php echo h($_SESSION['user_name'] ?? ''); ?></span>
        <a href="/logout.php" class="btn btn-sm btn-outline-danger">Logout</a>
    </div>
</nav>

<div class="container-fluid py-3">

    <!-- Title -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">
            <i class="bi bi-folder2-open me-2 text-warning"></i>
            Files — <a href="/client/project_view.php?id=<?php echo $projectId; ?>" class="text-decoration-none"><?php echo h($project['title']); ?></a>
        </h4>
        <a href="/client/project_view.php?id=<?php echo $projectId; ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Project
        </a>
    </div>

    <!-- Breadcrumb -->
    <div class="fm-breadcrumb mb-2">
        <i class="bi bi-folder2 me-1 icon-folder"></i>
        <a class="crumb" href="/client/project_files.php?project_id=<?php echo $projectId; ?>">Root</a>
        <?php foreach ($breadcrumbs as $bc): ?>
            <span class="crumb-sep">/</span>
            <?php if ($bc['id'] != $folderId): ?>
                <a class="crumb" href="/client/project_files.php?project_id=<?php echo $projectId; ?>&folder_id=<?php echo $bc['id']; ?>"><?php echo h($bc['name']); ?></a>
            <?php else: ?>
                <span class="crumb-current"><?php echo h($bc['name']); ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Toolbar -->
    <div class="fm-toolbar mb-2">
        <button class="btn btn-sm btn-success" onclick="document.getElementById('fm-file-input').click()">
            <i class="bi bi-cloud-upload me-1"></i>Upload Files
        </button>
        <input type="file" id="fm-file-input" multiple style="display:none">

        <div class="vr mx-1"></div>
        <button class="btn btn-sm btn-outline-secondary" id="btn-select-all" title="Select All">
            <i class="bi bi-check-all"></i>
        </button>
        <button class="btn btn-sm btn-outline-secondary" id="btn-view-grid" title="Grid View" style="border-radius:4px 0 0 4px">
            <i class="bi bi-grid"></i>
        </button>
        <button class="btn btn-sm btn-outline-secondary active" id="btn-view-list" title="List View" style="border-radius:0 4px 4px 0">
            <i class="bi bi-list-ul"></i>
        </button>
        <div class="fm-search">
            <input type="text" id="fm-search" class="form-control form-control-sm" placeholder="Search files…">
        </div>
    </div>

    <!-- Drop Zone -->
    <div id="fm-dropzone" class="fm-dropzone mb-2">
        <div class="drop-icon"><i class="bi bi-cloud-upload"></i></div>
        <p>Drop files here to upload</p>
        <small>Max 25 MB per file</small>
    </div>

    <!-- Upload progress -->
    <div id="fm-upload-progress" class="fm-upload-progress mb-2"></div>

    <!-- Bulk bar -->
    <div id="fm-bulk-bar" class="alert alert-info d-none d-flex align-items-center gap-2 py-2 mb-2">
        <span><strong id="bulk-count">0</strong> item(s) selected</span>
        <button class="btn btn-sm btn-primary ms-auto" onclick="bulkDownload()"><i class="bi bi-download me-1"></i>Download ZIP</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()"><i class="bi bi-x"></i></button>
    </div>

    <!-- Files & Folders -->
    <div class="bg-white border rounded">
        <?php if (!$folders && !$files): ?>
            <div class="fm-empty">
                <i class="bi bi-folder2-open"></i>
                <p class="mb-0">No files yet</p>
                <small>Upload a file to get started</small>
            </div>
        <?php else: ?>
        <div id="fm-items-container" class="fm-list p-2">

            <?php foreach ($folders as $folder): ?>
            <div class="fm-list-row" data-id="<?php echo $folder['id']; ?>" data-type="folder">
                <input type="checkbox" class="fm-checkbox form-check-input me-2" data-id="<?php echo $folder['id']; ?>" data-type="folder">
                <i class="bi bi-folder-fill list-icon icon-folder"></i>
                <span class="list-name fw-semibold"><?php echo h($folder['name']); ?></span>
                <span class="list-meta"><?php echo (int)$folder['file_count']; ?> files</span>
                <div class="list-actions">
                    <a href="/client/project_files.php?project_id=<?php echo $projectId; ?>&folder_id=<?php echo $folder['id']; ?>"
                       class="btn btn-sm btn-outline-warning" onclick="event.stopPropagation()">
                        <i class="bi bi-folder2-open"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>

            <?php foreach ($files as $file):
                [$iconCls, $iconColor] = fileIconClass($file['file_extension'], $file['mime_type']);
                $imgPrev = isImage($file['file_extension'], $file['mime_type']);
            ?>
            <div class="fm-list-row" data-id="<?php echo $file['id']; ?>" data-type="file">
                <input type="checkbox" class="fm-checkbox form-check-input me-2" data-id="<?php echo $file['id']; ?>" data-type="file">
                <?php if ($imgPrev): ?>
                    <img src="/api/files/preview.php?file_id=<?php echo $file['id']; ?>"
                         style="width:32px;height:32px;object-fit:cover;border-radius:4px;flex-shrink:0" alt="" class="list-icon">
                <?php else: ?>
                    <i class="<?php echo $iconCls; ?> list-icon <?php echo $iconColor; ?>"></i>
                <?php endif; ?>
                <span class="list-name">
                    <?php echo h($file['original_name']); ?>
                    <?php if ($file['version'] > 1): ?><span class="version-badge ms-1">v<?php echo (int)$file['version']; ?></span><?php endif; ?>
                </span>
                <span class="list-meta"><?php echo fmtSize($file['file_size']); ?></span>
                <span class="list-meta"><?php echo h(date('M j, Y', strtotime($file['created_at']))); ?></span>
                <div class="list-actions">
                    <a href="/api/files/download.php?file_id=<?php echo $file['id']; ?>"
                       class="btn btn-sm btn-outline-primary" title="Download" onclick="event.stopPropagation()">
                        <i class="bi bi-download"></i>
                    </a>
                    <a href="/api/files/preview.php?file_id=<?php echo $file['id']; ?>"
                       target="_blank" class="btn btn-sm btn-outline-secondary" title="Preview" onclick="event.stopPropagation()">
                        <i class="bi bi-eye"></i>
                    </a>
                    <button class="btn btn-sm btn-outline-info" title="Version History"
                            onclick="event.stopPropagation(); openVersionsModal(<?php echo $file['id']; ?>)">
                        <i class="bi bi-clock-history"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-success" title="Comments"
                            onclick="event.stopPropagation(); openCommentsModal(<?php echo $file['id']; ?>)">
                        <i class="bi bi-chat-left-text"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
        <?php endif; ?>
    </div>
</div><!-- .container-fluid -->

<!-- Context Menu -->
<div id="fm-context-menu" class="fm-context-menu" style="display:none"></div>

<!-- Modals -->
<div class="modal fade" id="modal-versions" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Version History</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body" id="modal-versions-body"></div>
    </div></div>
</div>
<div class="modal fade" id="modal-comments" tabindex="-1">
    <div class="modal-dialog"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title"><i class="bi bi-chat-left-text me-2"></i>Comments</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div id="modal-comments-body" style="max-height:300px;overflow-y:auto"></div>
            <div class="mt-3" id="modal-comments-form">
                <div class="input-group">
                    <input type="text" id="comment-input" class="form-control form-control-sm" placeholder="Add a comment…" onkeydown="if(event.key==='Enter') submitComment()">
                    <button class="btn btn-primary btn-sm" onclick="submitComment()"><i class="bi bi-send"></i></button>
                </div>
            </div>
        </div>
    </div></div>
</div>

<!-- No new-folder/delete modals for clients -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="/assets/js/file-manager.js"></script>
<script>
window._fmBase = '';
FileManager.init(
    <?php echo (int)$projectId; ?>,
    <?php echo $folderId ? (int)$folderId : 'null'; ?>,
    <?php echo json_encode($csrf); ?>
);

function openVersionsModal(id) {
    var modal = new bootstrap.Modal(document.getElementById('modal-versions'));
    var body  = document.getElementById('modal-versions-body');
    body.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
    modal.show();
    fetch('/api/files/versions.php?file_id='+id).then(function(r){return r.json();}).then(function(res){
        if (!res.success||!res.versions||!res.versions.length){body.innerHTML='<p class="text-muted text-center py-3">No versions found.</p>';return;}
        body.innerHTML=res.versions.map(function(v){
            return '<div class="version-item"><span class="version-num">v'+v.version+'</span><div class="flex-fill"><div class="fw-semibold" style="font-size:.85rem">'+v.original_name+'</div><div style="font-size:.75rem;color:#64748b">'+v.created_at+'</div></div><a href="/api/files/download.php?file_id='+v.id+'" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a></div>';
        }).join('');
    });
}
function openCommentsModal(id) {
    var modal = new bootstrap.Modal(document.getElementById('modal-comments'));
    var form  = document.getElementById('modal-comments-form');
    form.dataset.fileId = id;
    document.getElementById('modal-comments-body').innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
    modal.show();
    loadComments(id);
}
function loadComments(id) {
    var body = document.getElementById('modal-comments-body');
    fetch('/api/files/comment.php?file_id='+id).then(function(r){return r.json();}).then(function(res){
        if (!res.success||!res.comments||!res.comments.length){body.innerHTML='<p class="text-muted text-center py-3">No comments yet.</p>';return;}
        body.innerHTML=res.comments.map(function(c){
            return '<div class="comment-item"><div class="comment-author">'+c.author_name+' <span class="comment-time">'+c.created_at+'</span></div><div class="comment-text">'+c.comment+'</div></div>';
        }).join('');
    });
}
function submitComment() {
    var form=document.getElementById('modal-comments-form');
    var input=document.getElementById('comment-input');
    var fileId=form.dataset.fileId;
    var text=input.value.trim();
    if (!text) return;
    fetch('/api/files/comment.php',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'file_id='+encodeURIComponent(fileId)+'&comment='+encodeURIComponent(text)+'&csrf_token='+encodeURIComponent(<?php echo json_encode($csrf); ?>)
    }).then(function(r){return r.json();}).then(function(res){
        if (res.success){input.value='';loadComments(fileId);}else{alert(res.error||'Failed.');}
    });
}
function formatSize(b){b=parseInt(b)||0;if(b<1024)return b+' B';if(b<1048576)return(b/1024).toFixed(1)+' KB';if(b<1073741824)return(b/1048576).toFixed(1)+' MB';return(b/1073741824).toFixed(2)+' GB';}
</script>
</body>
</html>
