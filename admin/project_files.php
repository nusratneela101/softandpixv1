<?php
/**
 * admin/project_files.php
 * Google Drive-style file manager for a project.
 */

require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$projectId = (int)($_GET['project_id'] ?? 0);
$folderId  = !empty($_GET['folder_id']) ? (int)$_GET['folder_id'] : null;

if (!$projectId) { header('Location: projects.php'); exit; }

// ── Load project ──────────────────────────────────────────────
try {
    $projStmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $projStmt->execute([$projectId]);
    $project = $projStmt->fetch();
} catch (Exception $e) { $project = null; }
if (!$project) { header('Location: projects.php'); exit; }

// ── Load current folder ───────────────────────────────────────
$currentFolder = null;
if ($folderId) {
    try {
        $cfStmt = $pdo->prepare("SELECT * FROM project_folders WHERE id = ? AND project_id = ?");
        $cfStmt->execute([$folderId, $projectId]);
        $currentFolder = $cfStmt->fetch();
    } catch (Exception $e) {}
    if (!$currentFolder) { header('Location: project_files.php?project_id=' . $projectId); exit; }
}

// ── Build breadcrumb ──────────────────────────────────────────
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

// ── Load sub-folders ──────────────────────────────────────────
$folders = [];
try {
    $fStmt = $pdo->prepare(
        "SELECT f.*, u.name AS creator_name,
                (SELECT COUNT(*) FROM project_files pf WHERE pf.folder_id = f.id AND pf.is_deleted = 0) AS file_count
         FROM project_folders f
         LEFT JOIN users u ON u.id = f.created_by
         WHERE f.project_id = ?
           AND (f.parent_id = ? OR (f.parent_id IS NULL AND ? IS NULL))
         ORDER BY f.name"
    );
    $fStmt->execute([$projectId, $folderId, $folderId]);
    $folders = $fStmt->fetchAll();
} catch (Exception $e) {}

// ── Load files in current folder ─────────────────────────────
$files = [];
try {
    $fileStmt = $pdo->prepare(
        "SELECT pf.*, u.name AS uploader_name
         FROM project_files pf
         LEFT JOIN users u ON u.id = pf.uploaded_by
         WHERE pf.project_id = ?
           AND (pf.folder_id = ? OR (pf.folder_id IS NULL AND ? IS NULL))
           AND pf.is_deleted = 0
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

// ── All folders for move modal ────────────────────────────────
$allFolders = [];
try {
    $afStmt = $pdo->prepare("SELECT id, name, parent_id FROM project_folders WHERE project_id = ? ORDER BY name");
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
    if (in_array($e,['jpg','jpeg','png','gif','webp','bmp','svg','ico']) || strpos($m,'image/')===0) return ['bi bi-file-image','icon-image'];
    if ($e==='pdf') return ['bi bi-file-pdf','icon-pdf'];
    if (in_array($e,['doc','docx','odt','rtf'])) return ['bi bi-file-word','icon-word'];
    if (in_array($e,['xls','xlsx','csv','ods'])) return ['bi bi-file-excel','icon-excel'];
    if (in_array($e,['zip','rar','7z','tar','gz'])) return ['bi bi-file-zip','icon-archive'];
    if (in_array($e,['txt','md','log'])) return ['bi bi-file-text','icon-text'];
    if (in_array($e,['php','js','ts','html','css','json','xml','sql','py'])) return ['bi bi-file-code','icon-code'];
    return ['bi bi-file-earmark','icon-file'];
}
function isImage($ext, $mime = '') {
    return in_array(strtolower($ext),['jpg','jpeg','png','gif','webp','bmp','svg']) || strpos($mime,'image/')===0;
}

$csrf = generateCsrfToken();
require_once 'includes/header.php';
?>

<!-- Extra styles -->
<link href="../assets/css/file-manager.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.4.0/css/all.min.css" rel="stylesheet">

<div class="container-fluid py-3">

    <!-- Page title -->
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h4 class="mb-0">
                <i class="bi bi-folder2-open me-2 text-warning"></i>
                File Manager — <a href="project_view.php?id=<?php echo $projectId; ?>" class="text-decoration-none"><?php echo h($project['title']); ?></a>
            </h4>
        </div>
        <a href="project_view.php?id=<?php echo $projectId; ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Project
        </a>
    </div>

    <!-- Breadcrumb -->
    <div class="fm-breadcrumb mb-2">
        <i class="bi bi-folder2 me-1 icon-folder"></i>
        <a class="crumb" href="project_files.php?project_id=<?php echo $projectId; ?>">Root</a>
        <?php foreach ($breadcrumbs as $bc): ?>
            <span class="crumb-sep">/</span>
            <?php if ($bc['id'] != $folderId): ?>
                <a class="crumb" href="project_files.php?project_id=<?php echo $projectId; ?>&folder_id=<?php echo $bc['id']; ?>"><?php echo h($bc['name']); ?></a>
            <?php else: ?>
                <span class="crumb-current"><?php echo h($bc['name']); ?></span>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Toolbar -->
    <div class="fm-toolbar mb-2">
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modal-new-folder">
            <i class="bi bi-folder-plus me-1"></i>New Folder
        </button>
        <button class="btn btn-sm btn-success" onclick="document.getElementById('fm-file-input').click()">
            <i class="bi bi-cloud-upload me-1"></i>Upload Files
        </button>
        <input type="file" id="fm-file-input" multiple style="display:none">

        <div class="vr mx-1"></div>

        <button class="btn btn-sm btn-outline-secondary" id="btn-select-all" title="Select All (Ctrl+A)">
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

    <!-- Drop zone -->
    <div id="fm-dropzone" class="fm-dropzone mb-2">
        <div class="drop-icon"><i class="bi bi-cloud-upload"></i></div>
        <p>Drop files here to upload</p>
        <small>Max 25 MB per file · All types except .php, .exe, .sh</small>
    </div>

    <!-- Upload progress -->
    <div id="fm-upload-progress" class="fm-upload-progress mb-2"></div>

    <!-- Bulk action bar -->
    <div id="fm-bulk-bar" class="alert alert-info d-none d-flex align-items-center gap-2 py-2 mb-2">
        <span><strong id="bulk-count">0</strong> item(s) selected</span>
        <button class="btn btn-sm btn-primary ms-auto" onclick="bulkDownload()"><i class="bi bi-download me-1"></i>Download ZIP</button>
        <button class="btn btn-sm btn-danger" onclick="bulkDelete()"><i class="bi bi-trash me-1"></i>Delete</button>
        <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()"><i class="bi bi-x"></i></button>
    </div>

    <!-- Files & Folders -->
    <div class="bg-white border rounded">

        <?php if (!$folders && !$files): ?>
            <div class="fm-empty">
                <i class="bi bi-folder2-open"></i>
                <p class="mb-0">This folder is empty</p>
                <small>Upload files or create a folder to get started</small>
            </div>
        <?php else: ?>

        <div id="fm-items-container" class="fm-list p-2">

            <!-- Folders -->
            <?php foreach ($folders as $folder): ?>
            <div class="fm-list-row" data-id="<?php echo $folder['id']; ?>" data-type="folder">
                <input type="checkbox" class="fm-checkbox form-check-input me-2" data-id="<?php echo $folder['id']; ?>" data-type="folder">
                <i class="bi bi-folder-fill list-icon icon-folder"></i>
                <span class="list-name fw-semibold"><?php echo h($folder['name']); ?></span>
                <span class="list-meta"><?php echo (int)$folder['file_count']; ?> files</span>
                <span class="list-meta"><?php echo h(date('M j, Y', strtotime($folder['created_at']))); ?></span>
                <div class="list-actions">
                    <a href="project_files.php?project_id=<?php echo $projectId; ?>&folder_id=<?php echo $folder['id']; ?>"
                       class="btn btn-sm btn-outline-warning" title="Open" onclick="event.stopPropagation()">
                        <i class="bi bi-folder2-open"></i>
                    </a>
                    <button class="btn btn-sm btn-outline-secondary"
                            onclick="event.stopPropagation(); openRenameFolderModal(<?php echo $folder['id']; ?>, '<?php echo addslashes(h($folder['name'])); ?>')"
                            title="Rename">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger"
                            onclick="event.stopPropagation(); deleteItem('folder', <?php echo $folder['id']; ?>)"
                            title="Delete">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>

            <!-- Files -->
            <?php foreach ($files as $file):
                [$iconCls, $iconColor] = fileIconClass($file['file_extension'], $file['mime_type']);
                $imgPreview = isImage($file['file_extension'], $file['mime_type']);
            ?>
            <div class="fm-list-row" data-id="<?php echo $file['id']; ?>" data-type="file">
                <input type="checkbox" class="fm-checkbox form-check-input me-2" data-id="<?php echo $file['id']; ?>" data-type="file">
                <?php if ($imgPreview): ?>
                    <img src="../api/files/preview.php?file_id=<?php echo $file['id']; ?>"
                         style="width:32px;height:32px;object-fit:cover;border-radius:4px;flex-shrink:0"
                         alt="" class="list-icon">
                <?php else: ?>
                    <i class="<?php echo $iconCls; ?> list-icon <?php echo $iconColor; ?>"></i>
                <?php endif; ?>
                <span class="list-name">
                    <?php echo h($file['original_name']); ?>
                    <?php if ($file['version'] > 1): ?>
                        <span class="version-badge ms-1">v<?php echo (int)$file['version']; ?></span>
                    <?php endif; ?>
                </span>
                <span class="list-meta"><?php echo fmtSize($file['file_size']); ?></span>
                <span class="list-meta"><?php echo h($file['uploader_name'] ?? 'Unknown'); ?></span>
                <span class="list-meta"><?php echo h(date('M j, Y', strtotime($file['created_at']))); ?></span>
                <div class="list-actions">
                    <a href="../api/files/download.php?file_id=<?php echo $file['id']; ?>"
                       class="btn btn-sm btn-outline-primary" title="Download" onclick="event.stopPropagation()">
                        <i class="bi bi-download"></i>
                    </a>
                    <a href="../api/files/preview.php?file_id=<?php echo $file['id']; ?>"
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
                    <button class="btn btn-sm btn-outline-warning" title="Move"
                            onclick="event.stopPropagation(); openMoveModal('file', <?php echo $file['id']; ?>)">
                        <i class="bi bi-folder-symlink"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" title="Delete"
                            onclick="event.stopPropagation(); deleteItem('file', <?php echo $file['id']; ?>)">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>

        </div><!-- #fm-items-container -->
        <?php endif; ?>
    </div><!-- .bg-white -->
</div><!-- .container-fluid -->

<!-- Context Menu -->
<div id="fm-context-menu" class="fm-context-menu" style="display:none"></div>

<!-- ── Modals ─────────────────────────────────────────────────── -->

<!-- New Folder Modal -->
<div class="modal fade" id="modal-new-folder" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">New Folder</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input type="text" id="new-folder-name" class="form-control" placeholder="Folder name" maxlength="255" onkeydown="if(event.key==='Enter') submitNewFolder()">
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="submitNewFolder()"><i class="bi bi-folder-plus me-1"></i>Create</button>
            </div>
        </div>
    </div>
</div>

<!-- Rename Folder Modal -->
<div class="modal fade" id="modal-rename-folder" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Rename Folder</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <div id="modal-rename-form">
                    <input type="text" id="rename-folder-input" class="form-control" maxlength="255" onkeydown="if(event.key==='Enter') submitRenameFolder()">
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="submitRenameFolder()">Rename</button>
            </div>
        </div>
    </div>
</div>

<!-- File Details Modal -->
<div class="modal fade" id="modal-details" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-info-circle me-2"></i>File Details</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="modal-details-body"></div>
        </div>
    </div>
</div>

<!-- Version History Modal -->
<div class="modal fade" id="modal-versions" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Version History</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="modal-versions-body"></div>
        </div>
    </div>
</div>

<!-- Comments Modal -->
<div class="modal fade" id="modal-comments" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
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
        </div>
    </div>
</div>

<!-- Move Modal -->
<div class="modal fade" id="modal-move" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title"><i class="bi bi-folder-symlink me-2"></i>Move To</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body" id="modal-move-form">
                <label class="form-label">Target Folder</label>
                <select id="move-target-folder" class="form-select form-select-sm">
                    <option value="">— Root —</option>
                    <?php foreach ($allFolders as $af): ?>
                    <option value="<?php echo $af['id']; ?>"><?php echo h($af['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" onclick="submitMove()">Move</button>
            </div>
        </div>
    </div>
</div>

<!-- JS -->
<script>
window._fmBase  = '';
window._fmBase  = (function() {
    // Auto-detect base from script location
    var s = document.currentScript;
    var path = s ? s.src : '';
    var m = path.match(/^(https?:\/\/[^/]+)/);
    return m ? '' : '';
})();
</script>
<script src="../assets/js/file-manager.js"></script>
<script>
FileManager.init(
    <?php echo (int)$projectId; ?>,
    <?php echo $folderId ? (int)$folderId : 'null'; ?>,
    <?php echo json_encode($csrf); ?>
);

function openVersionsModal(id)       { FileManager.init(<?php echo (int)$projectId; ?>, <?php echo $folderId ? (int)$folderId : 'null'; ?>, <?php echo json_encode($csrf); ?>); window._openVersionsModal(id); }
function openCommentsModal(id)       { window._openCommentsModal(id); }
function openMoveModal(type, id)     { window._openMoveModal(type, id); }
function openRenameFolderModal(id,n) { window._openRenameFolderModal(id,n); }
function deleteItem(type, id)        { window._deleteItem(type, id); }

// Bind internal helpers so buttons can call them directly
window._openVersionsModal   = function(id) {
    var modal = new bootstrap.Modal(document.getElementById('modal-versions'));
    var body  = document.getElementById('modal-versions-body');
    body.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
    modal.show();
    fetch('/api/files/versions.php?file_id=' + id)
        .then(function(r){return r.json();})
        .then(function(res){
            if (!res.success || !res.versions || !res.versions.length) { body.innerHTML='<p class="text-muted text-center py-3">No version history found.</p>'; return; }
            body.innerHTML = res.versions.map(function(v){
                return '<div class="version-item"><span class="version-num">v'+v.version+'</span><div class="flex-fill"><div class="fw-semibold" style="font-size:.85rem">'+v.original_name+'</div><div style="font-size:.75rem;color:#64748b">'+v.created_at+' · '+formatSize(v.file_size)+'</div></div><a href="/api/files/download.php?file_id='+v.id+'" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a></div>';
            }).join('');
        });
};
window._openCommentsModal = function(id) {
    var modal = new bootstrap.Modal(document.getElementById('modal-comments'));
    var form  = document.getElementById('modal-comments-form');
    form.dataset.fileId = id;
    document.getElementById('modal-comments-body').innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
    modal.show();
    loadComments(id);
};
window._openMoveModal = function(type, id) {
    var modal = new bootstrap.Modal(document.getElementById('modal-move'));
    var form  = document.getElementById('modal-move-form');
    form.dataset.type = type;
    form.dataset.id   = id;
    modal.show();
};
window._openRenameFolderModal = function(id, name) {
    var modal = new bootstrap.Modal(document.getElementById('modal-rename-folder'));
    var input = document.getElementById('rename-folder-input');
    input.value = name;
    document.getElementById('modal-rename-form').dataset.id = id;
    modal.show();
};
window._deleteItem = function(type, id, silent) {
    if (!silent && !confirm('Delete this ' + type + '?')) return;
    fetch('/api/files/delete.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'type='+encodeURIComponent(type)+'&id='+encodeURIComponent(id)+'&csrf_token='+encodeURIComponent(<?php echo json_encode($csrf); ?>)
    }).then(function(r){return r.json();}).then(function(res){
        if (res.success) {
            var card = document.querySelector('[data-id="'+id+'"][data-type="'+type+'"]');
            if (card) card.remove();
        } else { alert(res.error||'Delete failed.'); }
    }).catch(function(){alert('Network error.');});
};
function loadComments(fileId) {
    var body = document.getElementById('modal-comments-body');
    fetch('/api/files/comment.php?file_id=' + fileId)
        .then(function(r){return r.json();})
        .then(function(res){
            if (!res.success || !res.comments || !res.comments.length) { body.innerHTML='<p class="text-muted text-center py-3">No comments yet.</p>'; return; }
            body.innerHTML = res.comments.map(function(c){
                return '<div class="comment-item"><div class="comment-author">'+c.author_name+' <span class="comment-time">'+c.created_at+'</span></div><div class="comment-text">'+c.comment+'</div></div>';
            }).join('');
        });
}
function submitComment() {
    var form = document.getElementById('modal-comments-form');
    var input = document.getElementById('comment-input');
    var fileId = form.dataset.fileId;
    var text = input.value.trim();
    if (!text) return;
    fetch('/api/files/comment.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'file_id='+encodeURIComponent(fileId)+'&comment='+encodeURIComponent(text)+'&csrf_token='+encodeURIComponent(<?php echo json_encode($csrf); ?>)
    }).then(function(r){return r.json();}).then(function(res){
        if (res.success) { input.value=''; loadComments(fileId); }
        else { alert(res.error||'Failed.'); }
    });
}
function submitMove() {
    var form = document.getElementById('modal-move-form');
    var type = form.dataset.type;
    var id   = form.dataset.id;
    var targetId = document.getElementById('move-target-folder').value;
    fetch('/api/files/move.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'type='+encodeURIComponent(type)+'&id='+encodeURIComponent(id)+'&target_folder_id='+encodeURIComponent(targetId)+'&csrf_token='+encodeURIComponent(<?php echo json_encode($csrf); ?>)
    }).then(function(r){return r.json();}).then(function(res){
        if (res.success) location.reload();
        else alert(res.error||'Move failed.');
    });
}
function submitRenameFolder() {
    var form = document.getElementById('modal-rename-form');
    var id = form.dataset.id;
    var newName = document.getElementById('rename-folder-input').value.trim();
    if (!newName) return;
    fetch('/api/files/create_folder.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=rename&folder_id='+encodeURIComponent(id)+'&name='+encodeURIComponent(newName)+'&csrf_token='+encodeURIComponent(<?php echo json_encode($csrf); ?>)
    }).then(function(r){return r.json();}).then(function(res){
        if (res.success) location.reload();
        else alert(res.error||'Rename failed.');
    });
}
function submitNewFolder() {
    var input = document.getElementById('new-folder-name');
    var name = input.value.trim();
    if (!name) return;
    fetch('/api/files/create_folder.php', {
        method:'POST',
        headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'project_id='+encodeURIComponent(<?php echo (int)$projectId; ?>)+'&parent_id='+encodeURIComponent(<?php echo $folderId ? (int)$folderId : ''; ?>)+'&name='+encodeURIComponent(name)+'&csrf_token='+encodeURIComponent(<?php echo json_encode($csrf); ?>)
    }).then(function(r){return r.json();}).then(function(res){
        if (res.success) location.reload();
        else alert(res.error||'Failed to create folder.');
    });
}
function formatSize(bytes) {
    bytes = parseInt(bytes)||0;
    if (bytes < 1024) return bytes+' B';
    if (bytes < 1048576) return (bytes/1024).toFixed(1)+' KB';
    if (bytes < 1073741824) return (bytes/1048576).toFixed(1)+' MB';
    return (bytes/1073741824).toFixed(2)+' GB';
}
</script>

<?php require_once 'includes/footer.php'; ?>
