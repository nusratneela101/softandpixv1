<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: projects.php'); exit; }

try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
} catch (Exception $e) { $project = null; }

if (!$project) { flashMessage('error', 'Project not found.'); header('Location: projects.php'); exit; }

$error      = '';
$success    = '';
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $demoSubdomain = strtolower(trim($_POST['demo_subdomain'] ?? ''));
        $demoUrl       = trim($_POST['demo_url'] ?? '');
        $demoEnabled   = isset($_POST['demo_enabled']) ? 1 : 0;
        $demoPassword  = trim($_POST['demo_password'] ?? '');
        $demoExpiresAt = !empty($_POST['demo_expires_at']) ? $_POST['demo_expires_at'] : null;

        // Validate subdomain
        if (!empty($demoSubdomain) && !preg_match('/^[a-z0-9][a-z0-9-]{1,48}[a-z0-9]$|^[a-z0-9]{3,50}$/', $demoSubdomain)) {
            $error = 'Demo subdomain must be 3–50 characters: lowercase letters, numbers, and hyphens only.';
        } elseif (!empty($demoSubdomain) && $demoSubdomain !== ($project['demo_subdomain'] ?? '')) {
            // Check uniqueness
            $chk = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE demo_subdomain = ? AND id != ?");
            $chk->execute([$demoSubdomain, $id]);
            if ((int)$chk->fetchColumn() > 0) {
                $error = 'This demo subdomain is already in use by another project.';
            }
        }

        if (empty($error)) {
            // Handle password
            $demoPasswordHash = $project['demo_password'];
            if ($demoPassword !== '') {
                $demoPasswordHash = password_hash($demoPassword, PASSWORD_BCRYPT);
            } elseif (isset($_POST['demo_clear_password'])) {
                $demoPasswordHash = null;
            }

            try {
                $pdo->prepare("UPDATE projects SET demo_subdomain=?, demo_url=?, demo_enabled=?, demo_password=?, demo_expires_at=? WHERE id=?")
                    ->execute([$demoSubdomain ?: null, $demoUrl ?: null, $demoEnabled, $demoPasswordHash, $demoExpiresAt, $id]);

                // Refresh project data
                $stmt->execute([$id]);
                $project = $stmt->fetch();
                $success = 'Demo settings saved successfully!';
            } catch (PDOException $e) {
                $error = 'Failed to save demo settings.';
            }
        }
    }
}

$demoLink = !empty($project['demo_subdomain']) ? 'https://' . $project['demo_subdomain'] . '.softandpix.com' : '';
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-broadcast me-2 text-info"></i>Demo Settings</h1>
        <p class="mb-0 text-muted"><?php echo h($project['title']); ?></p>
    </div>
    <div>
        <?php if (!empty($project['demo_enabled']) && !empty($project['demo_subdomain'])): ?>
        <a href="<?php echo h($demoLink); ?>" target="_blank" rel="noopener" class="btn btn-info text-white me-2">
            <i class="bi bi-box-arrow-up-right me-1"></i>View Live Demo
        </a>
        <?php endif; ?>
        <a href="project_view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Project
        </a>
    </div>
</div>

<div class="container-fluid">
    <?php if ($error): ?>
    <div class="alert alert-danger"><i class="bi bi-exclamation-circle me-2"></i><?php echo h($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
    <div class="alert alert-success"><i class="bi bi-check-circle me-2"></i><?php echo h($success); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Settings Form -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-gear me-2"></i>Configure Demo</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Demo Subdomain</label>
                            <div class="input-group">
                                <input type="text" id="demo_subdomain" name="demo_subdomain" class="form-control"
                                       placeholder="e.g. myproject"
                                       value="<?php echo h($project['demo_subdomain'] ?? ''); ?>"
                                       pattern="[a-z0-9][a-z0-9\-]{1,48}[a-z0-9]|[a-z0-9]{3,50}"
                                       title="3–50 lowercase letters, numbers, hyphens">
                                <span class="input-group-text">.softandpix.com</span>
                            </div>
                            <div id="subdomainStatus" class="form-text"></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Demo URL <span class="text-muted fw-normal">(the actual website to show in the iframe)</span></label>
                            <input type="url" name="demo_url" class="form-control" placeholder="https://your-demo-site.com"
                                   value="<?php echo h($project['demo_url'] ?? ''); ?>">
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="demo_enabled" name="demo_enabled" value="1"
                                    <?php echo !empty($project['demo_enabled']) ? 'checked' : ''; ?>>
                                <label class="form-check-label fw-semibold" for="demo_enabled">Enable Live Demo</label>
                            </div>
                            <div class="form-text">When enabled, visitors to <code><?php echo h($project['demo_subdomain'] ?? 'subdomain'); ?>.softandpix.com</code> can view the demo.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Demo Password <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="password" name="demo_password" class="form-control" placeholder="Leave blank to keep current"
                                   autocomplete="new-password">
                            <?php if (!empty($project['demo_password'])): ?>
                            <div class="mt-1 d-flex align-items-center gap-2">
                                <span class="badge bg-warning text-dark"><i class="bi bi-lock-fill me-1"></i>Password is set</span>
                                <div class="form-check mb-0">
                                    <input class="form-check-input" type="checkbox" name="demo_clear_password" id="demo_clear_password" value="1">
                                    <label class="form-check-label small text-danger" for="demo_clear_password">Remove password</label>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="form-text">No password set — demo is publicly accessible.</div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-4">
                            <label class="form-label fw-semibold">Demo Expiry Date &amp; Time <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="datetime-local" name="demo_expires_at" class="form-control"
                                   value="<?php echo h(!empty($project['demo_expires_at']) ? date('Y-m-d\TH:i', strtotime($project['demo_expires_at'])) : ''); ?>">
                            <div class="form-text">After this date the demo will show an expiry message to visitors.</div>
                        </div>

                        <button type="submit" class="btn btn-primary px-4">
                            <i class="bi bi-save me-2"></i>Save Demo Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Status Panel + Preview -->
        <div class="col-lg-5">
            <!-- Status Card -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-light fw-semibold"><i class="bi bi-info-circle me-2"></i>Demo Status</div>
                <div class="card-body">
                    <?php if (!empty($project['demo_subdomain'])): ?>
                    <div class="mb-3">
                        <div class="small text-muted mb-1">Subdomain</div>
                        <div class="d-flex align-items-center gap-2">
                            <a href="<?php echo h($demoLink); ?>" target="_blank" rel="noopener" class="fw-semibold">
                                <?php echo h($project['demo_subdomain']); ?>.softandpix.com
                            </a>
                            <button class="btn btn-sm btn-outline-secondary py-0 px-1" onclick="copyLink()" title="Copy link">
                                <i class="bi bi-clipboard" id="copyIcon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted mb-1">Status</div>
                        <?php if (!empty($project['demo_enabled'])): ?>
                        <span class="badge bg-success fs-6"><i class="bi bi-circle-fill me-1" style="font-size:.5rem;vertical-align:middle;"></i>Live</span>
                        <?php else: ?>
                        <span class="badge bg-secondary fs-6"><i class="bi bi-circle me-1" style="font-size:.5rem;vertical-align:middle;"></i>Disabled</span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($project['demo_password'])): ?>
                    <div class="mb-3">
                        <span class="badge bg-warning text-dark"><i class="bi bi-lock-fill me-1"></i>Password Protected</span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($project['demo_expires_at'])): ?>
                    <div class="mb-3">
                        <div class="small text-muted mb-1">Expires</div>
                        <div class="fw-semibold <?php echo strtotime($project['demo_expires_at']) < time() ? 'text-danger' : ''; ?>">
                            <?php echo h(date('M j, Y g:i A', strtotime($project['demo_expires_at']))); ?>
                            <?php if (strtotime($project['demo_expires_at']) < time()): ?>
                            <span class="badge bg-danger ms-1">Expired</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div class="d-grid gap-2 mt-3">
                        <a href="<?php echo h($demoLink); ?>" target="_blank" rel="noopener" class="btn btn-outline-info">
                            <i class="bi bi-box-arrow-up-right me-1"></i>Open Demo in New Tab
                        </a>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-3">
                        <div class="text-muted mb-2"><i class="bi bi-broadcast-pin" style="font-size:2rem;"></i></div>
                        <p class="text-muted small">No demo configured yet. Fill in the subdomain and URL to get started.</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Inline Preview -->
            <?php if (!empty($project['demo_enabled']) && !empty($project['demo_url'])): ?>
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-eye me-2"></i>Preview</span>
                    <a href="<?php echo h($demoLink); ?>" target="_blank" rel="noopener" class="btn btn-sm btn-outline-info">
                        <i class="bi bi-fullscreen me-1"></i>Full Screen
                    </a>
                </div>
                <div class="card-body p-0">
                    <iframe
                        src="<?php echo h($project['demo_url']); ?>"
                        style="width:100%;height:400px;border:none;"
                        title="Demo Preview"
                        sandbox="allow-scripts allow-same-origin allow-forms allow-popups allow-popups-to-escape-sandbox allow-top-navigation-by-user-activation"
                        loading="lazy"
                    ></iframe>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
// ── Get disk usage and file list for uploaded demo ───────────────────────────
$demoSiteDir   = dirname(__DIR__) . '/demo/sites/' . ($project['demo_subdomain'] ?? '');
$demoHasFiles  = !empty($project['demo_has_files']) && is_dir($demoSiteDir);
$demoFileList  = [];
$demoDiskUsage = 0;
if ($demoHasFiles) {
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($demoSiteDir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && $f->getFilename() !== '.htaccess') {
            $rel = str_replace($demoSiteDir . '/', '', $f->getPathname());
            $sz  = $f->getSize();
            $demoDiskUsage += $sz;
            $demoFileList[] = ['name' => $rel, 'size' => $sz];
        }
    }
    usort($demoFileList, function($a, $b) { return strcmp($a['name'], $b['name']); });
}
function fmtDemoSize(int $bytes): string {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024)    return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
?>

<!-- Upload Demo Files Section -->
<div class="container-fluid mt-4">
    <div class="row g-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-light fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-cloud-upload me-2 text-primary"></i>Upload Demo Files</span>
                    <?php if ($demoHasFiles): ?>
                    <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Live Demo Active (uploaded files)</span>
                    <?php elseif (!empty($project['demo_enabled']) && !empty($project['demo_url'])): ?>
                    <span class="badge bg-info text-white"><i class="bi bi-link-45deg me-1"></i>Live Demo Active (external URL)</span>
                    <?php else: ?>
                    <span class="badge bg-secondary"><i class="bi bi-dash-circle me-1"></i>No Demo</span>
                    <?php endif; ?>
                </div>
                <div class="card-body">
                    <?php if (empty($project['demo_subdomain'])): ?>
                    <div class="alert alert-warning mb-0">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        Please configure a <strong>demo subdomain</strong> above before uploading files.
                    </div>
                    <?php else: ?>

                    <p class="text-muted small mb-3">
                        Upload a ZIP file containing your full demo project (HTML, CSS, JS, PHP, images).
                        It will be auto-extracted and served live at
                        <a href="<?php echo h($demoLink); ?>" target="_blank" rel="noopener"><strong><?php echo h($project['demo_subdomain']); ?>.softandpix.com</strong></a>.
                        Max ZIP size: <strong>50MB</strong>. Max extracted: <strong>100MB</strong>.
                    </p>

                    <!-- Upload Zone -->
                    <div id="demoDropZone" class="border border-2 border-dashed rounded p-4 text-center mb-3 position-relative"
                         style="border-color:#c8d3df!important;cursor:pointer;transition:background .2s;"
                         ondragover="demoDragOver(event)" ondragleave="demoDragLeave(event)" ondrop="demoDrop(event)"
                         onclick="document.getElementById('demoZipInput').click()">
                        <i class="bi bi-file-zip text-primary" style="font-size:2.5rem;"></i>
                        <p class="mt-2 mb-1 fw-semibold">Drag &amp; drop ZIP file here</p>
                        <p class="text-muted small mb-0">or click to browse — ZIP files only, max 50MB</p>
                        <input type="file" id="demoZipInput" accept=".zip,application/zip" class="d-none" onchange="demoFileSelected(this)">
                    </div>

                    <!-- Selected file info + Upload button -->
                    <div id="demoFileInfo" class="d-none mb-3">
                        <div class="d-flex align-items-center gap-3">
                            <i class="bi bi-file-zip text-primary fs-4"></i>
                            <div class="flex-grow-1">
                                <div id="demoFileName" class="fw-semibold"></div>
                                <div id="demoFileSize" class="text-muted small"></div>
                            </div>
                            <button type="button" class="btn btn-primary" id="demoUploadBtn" onclick="demoStartUpload()">
                                <i class="bi bi-cloud-upload me-1"></i>Upload &amp; Deploy
                            </button>
                        </div>
                    </div>

                    <!-- Progress bar -->
                    <div id="demoProgressWrap" class="d-none mb-3">
                        <div class="d-flex justify-content-between mb-1 small">
                            <span id="demoProgressLabel">Uploading...</span>
                            <span id="demoProgressPct">0%</span>
                        </div>
                        <div class="progress" style="height:8px;">
                            <div id="demoProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" style="width:0%"></div>
                        </div>
                    </div>

                    <!-- Status message -->
                    <div id="demoStatusMsg" class="d-none"></div>

                    <?php if ($demoHasFiles && !empty($demoFileList)): ?>
                    <!-- Uploaded Files List -->
                    <div id="demoFileListWrap" class="mt-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="mb-0 fw-semibold"><i class="bi bi-folder2-open me-2 text-warning"></i>Uploaded Files
                                <span class="badge bg-secondary ms-1"><?php echo count($demoFileList); ?> files</span>
                                <span class="badge bg-light text-dark border ms-1"><?php echo fmtDemoSize($demoDiskUsage); ?></span>
                            </h6>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="demoConfirmDelete()">
                                <i class="bi bi-trash me-1"></i>Delete All Demo Files
                            </button>
                        </div>
                        <div class="table-responsive" style="max-height:260px;overflow-y:auto;">
                            <table class="table table-sm table-hover mb-0">
                                <thead class="table-light sticky-top">
                                    <tr><th>File</th><th class="text-end">Size</th></tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($demoFileList as $df): ?>
                                    <tr>
                                        <td class="text-truncate" style="max-width:400px;">
                                            <i class="bi bi-file-earmark text-muted me-1"></i><?php echo h($df['name']); ?>
                                        </td>
                                        <td class="text-end text-muted small"><?php echo fmtDemoSize((int)$df['size']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php elseif ($demoHasFiles): ?>
                    <div class="alert alert-info mt-3 mb-0">
                        <i class="bi bi-info-circle me-2"></i>Demo files are uploaded. No files to display (they may have been filtered during extraction).
                        <button type="button" class="btn btn-sm btn-outline-danger ms-3" onclick="demoConfirmDelete()">
                            <i class="bi bi-trash me-1"></i>Delete All Demo Files
                        </button>
                    </div>
                    <?php endif; ?>

                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Copy demo link
function copyLink() {
    var url = <?php echo json_encode($demoLink ?: ''); ?>;
    if (!url) return;
    if (navigator.clipboard) {
        navigator.clipboard.writeText(url);
    } else {
        var ta = document.createElement('textarea');
        ta.value = url; document.body.appendChild(ta); ta.select();
        document.execCommand('copy'); document.body.removeChild(ta);
    }
    var icon = document.getElementById('copyIcon');
    icon.className = 'bi bi-check2 text-success';
    setTimeout(function() { icon.className = 'bi bi-clipboard'; }, 2000);
}

// Live subdomain availability check
(function() {
    var input = document.getElementById('demo_subdomain');
    var status = document.getElementById('subdomainStatus');
    var timer = null;
    if (!input || !status) return;
    input.addEventListener('input', function() {
        clearTimeout(timer);
        var val = this.value.trim().toLowerCase();
        if (val.length < 3) { status.textContent = ''; return; }
        status.textContent = 'Checking...';
        status.className = 'form-text text-muted';
        timer = setTimeout(function() {
            fetch('/api/project/demo_check.php?subdomain=' + encodeURIComponent(val) + '&exclude_id=<?php echo (int)$id; ?>')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    status.textContent = data.message;
                    status.className = 'form-text ' + (data.available ? 'text-success' : 'text-danger');
                })
                .catch(function() { status.textContent = ''; });
        }, 600);
    });
})();

// ── Demo Upload ───────────────────────────────────────────────────────────────
var demoSelectedFile = null;

function demoDragOver(e) {
    e.preventDefault();
    document.getElementById('demoDropZone').style.background = '#eef2ff';
}
function demoDragLeave(e) {
    document.getElementById('demoDropZone').style.background = '';
}
function demoDrop(e) {
    e.preventDefault();
    document.getElementById('demoDropZone').style.background = '';
    var file = e.dataTransfer.files[0];
    if (file) demoSetFile(file);
}
function demoFileSelected(input) {
    if (input.files && input.files[0]) demoSetFile(input.files[0]);
}
function demoSetFile(file) {
    if (!file.name.toLowerCase().endsWith('.zip') && file.type !== 'application/zip') {
        demoShowStatus('danger', '<i class="bi bi-exclamation-circle me-2"></i>Please select a valid ZIP file.');
        return;
    }
    var maxMB = 50 * 1024 * 1024;
    if (file.size > maxMB) {
        demoShowStatus('danger', '<i class="bi bi-exclamation-circle me-2"></i>File exceeds the 50MB limit (' + (file.size / 1048576).toFixed(1) + ' MB).');
        return;
    }
    demoSelectedFile = file;
    document.getElementById('demoFileName').textContent = file.name;
    document.getElementById('demoFileSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
    document.getElementById('demoFileInfo').classList.remove('d-none');
    document.getElementById('demoStatusMsg').classList.add('d-none');
}
function demoStartUpload() {
    if (!demoSelectedFile) return;
    var formData = new FormData();
    formData.append('demo_zip', demoSelectedFile);
    formData.append('project_id', '<?php echo (int)$id; ?>');
    formData.append('csrf_token', '<?php echo h($csrf_token); ?>');

    document.getElementById('demoFileInfo').classList.add('d-none');
    document.getElementById('demoProgressWrap').classList.remove('d-none');
    document.getElementById('demoStatusMsg').classList.add('d-none');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', '/api/project/demo_upload.php', true);

    xhr.upload.onprogress = function(e) {
        if (e.lengthComputable) {
            var pct = Math.round(e.loaded / e.total * 100);
            document.getElementById('demoProgressBar').style.width = pct + '%';
            document.getElementById('demoProgressPct').textContent = pct + '%';
            if (pct >= 100) {
                document.getElementById('demoProgressLabel').textContent = 'Extracting files...';
            }
        }
    };

    xhr.onload = function() {
        document.getElementById('demoProgressWrap').classList.add('d-none');
        try {
            var resp = JSON.parse(xhr.responseText);
            if (resp.success) {
                var msg = '<i class="bi bi-check-circle me-2"></i><strong>Deployed successfully!</strong> ' +
                    resp.files_count + ' file(s) extracted (' + formatDemoBytes(resp.total_size) + ').';
                if (resp.skipped_count > 0) {
                    msg += ' <span class="text-warning">' + resp.skipped_count + ' file(s) skipped for security.</span>';
                }
                demoShowStatus('success', msg);
                setTimeout(function() { window.location.reload(); }, 1500);
            } else {
                demoShowStatus('danger', '<i class="bi bi-exclamation-circle me-2"></i>' + (resp.error || 'Upload failed.'));
                document.getElementById('demoFileInfo').classList.remove('d-none');
            }
        } catch(ex) {
            demoShowStatus('danger', '<i class="bi bi-exclamation-circle me-2"></i>Unexpected server response.');
            document.getElementById('demoFileInfo').classList.remove('d-none');
        }
        demoSelectedFile = null;
    };

    xhr.onerror = function() {
        document.getElementById('demoProgressWrap').classList.add('d-none');
        demoShowStatus('danger', '<i class="bi bi-exclamation-circle me-2"></i>Network error. Please try again.');
        document.getElementById('demoFileInfo').classList.remove('d-none');
    };

    xhr.send(formData);
}

function demoConfirmDelete() {
    if (!confirm('Are you sure you want to delete ALL uploaded demo files? This cannot be undone.')) return;
    fetch('/api/project/demo_delete_files.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'project_id=<?php echo (int)$id; ?>&csrf_token=<?php echo urlencode($csrf_token); ?>'
    }).then(function(r) { return r.json(); }).then(function(resp) {
        if (resp.success) {
            demoShowStatus('success', '<i class="bi bi-check-circle me-2"></i>Demo files deleted successfully.');
            setTimeout(function() { window.location.reload(); }, 1200);
        } else {
            demoShowStatus('danger', '<i class="bi bi-exclamation-circle me-2"></i>' + (resp.error || 'Delete failed.'));
        }
    }).catch(function() {
        demoShowStatus('danger', '<i class="bi bi-exclamation-circle me-2"></i>Network error. Please try again.');
    });
}

function demoShowStatus(type, html) {
    var el = document.getElementById('demoStatusMsg');
    el.className = 'alert alert-' + type;
    el.innerHTML = html;
    el.classList.remove('d-none');
}
function formatDemoBytes(bytes) {
    if (bytes >= 1048576) return (bytes / 1048576).toFixed(2) + ' MB';
    if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
    return bytes + ' B';
}
</script>
<?php require_once 'includes/footer.php'; ?>
