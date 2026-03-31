<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header('Location: projects.php'); exit; }

try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();

    $clients    = $pdo->query("SELECT id, name, email FROM users WHERE role = 'client' AND is_active = 1 ORDER BY name")->fetchAll();
    $developers = $pdo->query("SELECT id, name FROM users WHERE role IN ('developer','editor','ui_designer','seo_specialist') AND is_active = 1 ORDER BY name")->fetchAll();
} catch (Exception $e) { $project = null; $clients = []; $developers = []; }

if (!$project) { flashMessage('error', 'Project not found.'); header('Location: projects.php'); exit; }

$error      = '';
$csrf_token = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $title     = trim($_POST['title'] ?? '');
        $desc      = trim($_POST['description'] ?? '');
        $clientId  = (int)($_POST['client_id'] ?? 0) ?: null;
        $devId     = (int)($_POST['developer_id'] ?? 0) ?: null;
        $status    = $_POST['status'] ?? 'pending';
        $priority  = $_POST['priority'] ?? 'medium';
        $progress  = min(100, max(0, (int)($_POST['progress'] ?? 0)));
        $startDate = !empty($_POST['start_date']) ? $_POST['start_date'] : null;
        $deadline  = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
        $budget    = (float)($_POST['budget'] ?? 0);
        $currency  = $_POST['currency'] ?? 'USD';

        // Demo settings
        $demoSubdomain = strtolower(trim($_POST['demo_subdomain'] ?? ''));
        $demoUrl       = trim($_POST['demo_url'] ?? '');
        $demoEnabled   = isset($_POST['demo_enabled']) ? 1 : 0;
        $demoPassword  = trim($_POST['demo_password'] ?? '');
        $demoExpiresAt = !empty($_POST['demo_expires_at']) ? $_POST['demo_expires_at'] : null;

        // Hash new password only if a new one is provided; otherwise keep existing hash
        $demoPasswordHash = $project['demo_password']; // keep existing by default
        if ($demoPassword !== '') {
            $demoPasswordHash = password_hash($demoPassword, PASSWORD_BCRYPT);
        } elseif (isset($_POST['demo_clear_password'])) {
            $demoPasswordHash = null; // admin explicitly cleared it
        }

        $validStatuses   = ['pending', 'in_progress', 'on_hold', 'completed', 'cancelled'];
        $validPriorities = ['low', 'medium', 'high', 'urgent'];
        $validCurrencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD'];

        if (empty($title)) {
            $error = 'Project title is required.';
        } elseif (!in_array($status, $validStatuses)) {
            $error = 'Invalid status.';
        } elseif (!in_array($priority, $validPriorities)) {
            $error = 'Invalid priority.';
        } elseif (!in_array($currency, $validCurrencies)) {
            $error = 'Invalid currency.';
        } elseif (!empty($demoSubdomain) && !preg_match('/^[a-z0-9][a-z0-9-]{1,48}[a-z0-9]$|^[a-z0-9]{3,50}$/', $demoSubdomain)) {
            $error = 'Demo subdomain must be 3–50 characters: lowercase letters, numbers, and hyphens only.';
        } else {
            // Check subdomain uniqueness (only if provided and changed)
            if (!empty($demoSubdomain) && $demoSubdomain !== ($project['demo_subdomain'] ?? '')) {
                try {
                    $chk = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE demo_subdomain = ? AND id != ?");
                    $chk->execute([$demoSubdomain, $id]);
                    if ((int)$chk->fetchColumn() > 0) {
                        $error = 'This demo subdomain is already in use by another project.';
                    }
                } catch (Exception $e) { $error = 'Failed to validate subdomain.'; }
            }
        }
        if (empty($error)) {
            try {
                $pdo->prepare("UPDATE projects SET title=?, description=?, client_id=?, developer_id=?, status=?, priority=?, progress=?, start_date=?, deadline=?, budget=?, currency=?, demo_subdomain=?, demo_url=?, demo_enabled=?, demo_password=?, demo_expires_at=? WHERE id=?")
                    ->execute([$title, $desc, $clientId, $devId, $status, $priority, $progress, $startDate, $deadline, $budget, $currency, $demoSubdomain ?: null, $demoUrl ?: null, $demoEnabled, $demoPasswordHash, $demoExpiresAt, $id]);
                flashMessage('success', 'Project updated successfully!');
                header("Location: project_view.php?id=$id"); exit;
            } catch (PDOException $e) {
                $error = 'Failed to update project.';
            }
        }
    }
}

// Use POST values on error, otherwise project DB values
$v = ($_SERVER['REQUEST_METHOD'] === 'POST' && $error) ? $_POST : $project;
require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div><h1><i class="bi bi-pencil me-2"></i>Edit Project</h1></div>
    <div>
        <a href="project_view.php?id=<?php echo $id; ?>" class="btn btn-outline-info me-2"><i class="bi bi-eye me-1"></i>View</a>
        <a href="projects.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>
</div>
<div class="container-fluid">
    <?php if ($error): ?>
    <div class="alert alert-danger"><?php echo h($error); ?></div>
    <?php endif; ?>
    <div class="card">
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label fw-semibold">Project Title *</label>
                        <input type="text" name="title" class="form-control" value="<?php echo h($v['title'] ?? ''); ?>" required>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-semibold">Description</label>
                        <textarea name="description" class="form-control" rows="4"><?php echo h($v['description'] ?? ''); ?></textarea>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Client</label>
                        <select name="client_id" class="form-select">
                            <option value="">— Select Client —</option>
                            <?php foreach ($clients as $c): ?>
                            <option value="<?php echo (int)$c['id']; ?>" <?php echo ((int)($v['client_id'] ?? 0) === (int)$c['id']) ? 'selected' : ''; ?>>
                                <?php echo h($c['name']); ?> (<?php echo h($c['email']); ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Assigned Developer</label>
                        <select name="developer_id" class="form-select">
                            <option value="">— None —</option>
                            <?php foreach ($developers as $d): ?>
                            <option value="<?php echo (int)$d['id']; ?>" <?php echo ((int)($v['developer_id'] ?? 0) === (int)$d['id']) ? 'selected' : ''; ?>>
                                <?php echo h($d['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Status</label>
                        <select name="status" class="form-select">
                            <?php foreach (['pending', 'in_progress', 'on_hold', 'completed', 'cancelled'] as $s): ?>
                            <option value="<?php echo $s; ?>" <?php echo ($v['status'] ?? 'pending') === $s ? 'selected' : ''; ?>><?php echo ucwords(str_replace('_', ' ', $s)); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Priority</label>
                        <select name="priority" class="form-select">
                            <?php foreach (['low', 'medium', 'high', 'urgent'] as $p): ?>
                            <option value="<?php echo $p; ?>" <?php echo ($v['priority'] ?? 'medium') === $p ? 'selected' : ''; ?>><?php echo ucfirst($p); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Currency</label>
                        <select name="currency" class="form-select">
                            <?php foreach (['USD', 'EUR', 'GBP', 'CAD', 'AUD'] as $c): ?>
                            <option value="<?php echo $c; ?>" <?php echo ($v['currency'] ?? 'USD') === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-semibold">Budget</label>
                        <input type="number" name="budget" class="form-control" step="0.01" min="0" value="<?php echo h($v['budget'] ?? '0'); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?php echo h($v['start_date'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Deadline</label>
                        <input type="date" name="deadline" class="form-control" value="<?php echo h($v['deadline'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Progress: <span id="progressVal"><?php echo (int)($v['progress'] ?? 0); ?></span>%</label>
                        <input type="range" name="progress" id="progressSlider" class="form-range" min="0" max="100" step="1" value="<?php echo (int)($v['progress'] ?? 0); ?>">
                        <div class="progress mt-1" style="height:8px;">
                            <div class="progress-bar" id="progressBar" style="width:<?php echo (int)($v['progress'] ?? 0); ?>%"></div>
                        </div>
                    </div>
                </div>

                <!-- Demo Settings -->
                <hr class="my-4">
                <h6 class="fw-bold text-muted text-uppercase mb-3"><i class="bi bi-play-circle me-2"></i>Demo Settings</h6>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Demo Subdomain</label>
                        <div class="input-group">
                            <input type="text" id="demo_subdomain" name="demo_subdomain" class="form-control"
                                   placeholder="e.g. myproject"
                                   value="<?php echo h($v['demo_subdomain'] ?? ''); ?>"
                                   pattern="[a-z0-9][a-z0-9\-]{1,48}[a-z0-9]|[a-z0-9]{3,50}"
                                   title="3–50 lowercase letters, numbers, hyphens">
                            <span class="input-group-text">.softandpix.com</span>
                        </div>
                        <div id="subdomainStatus" class="form-text"></div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold">Demo URL <span class="text-muted fw-normal">(URL to show in iframe)</span></label>
                        <input type="url" name="demo_url" class="form-control" placeholder="https://your-demo-site.com"
                               value="<?php echo h($v['demo_url'] ?? ''); ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">New Demo Password <span class="text-muted fw-normal">(leave blank to keep current)</span></label>
                        <input type="password" name="demo_password" class="form-control" placeholder="Enter new password..."
                               autocomplete="new-password">
                        <?php if (!empty($project['demo_password'])): ?>
                        <div class="form-check mt-1">
                            <input class="form-check-input" type="checkbox" name="demo_clear_password" id="demo_clear_password" value="1">
                            <label class="form-check-label small text-danger" for="demo_clear_password">Remove password protection</label>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">Demo Expiry Date <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="datetime-local" name="demo_expires_at" class="form-control"
                               value="<?php echo h(!empty($v['demo_expires_at']) ? date('Y-m-d\TH:i', strtotime($v['demo_expires_at'])) : ''); ?>">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" role="switch" id="demo_enabled" name="demo_enabled" value="1"
                                <?php echo !empty($v['demo_enabled']) ? 'checked' : ''; ?>>
                            <label class="form-check-label fw-semibold" for="demo_enabled">Enable Live Demo</label>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <button type="submit" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Save Changes</button>
                    <a href="project_view.php?id=<?php echo $id; ?>" class="btn btn-outline-secondary ms-2">Cancel</a>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
var slider = document.getElementById('progressSlider');
var valDisplay = document.getElementById('progressVal');
var bar = document.getElementById('progressBar');
slider.addEventListener('input', function() {
    valDisplay.textContent = this.value;
    bar.style.width = this.value + '%';
});

// Live subdomain availability check
(function() {
    var input = document.getElementById('demo_subdomain');
    var status = document.getElementById('subdomainStatus');
    var timer = null;
    if (!input) return;
    input.addEventListener('input', function() {
        clearTimeout(timer);
        var val = this.value.trim().toLowerCase();
        if (val.length < 3) { status.textContent = ''; return; }
        status.textContent = 'Checking...';
        status.className = 'form-text text-muted';
        timer = setTimeout(function() {
            fetch('/api/project/demo_check.php?subdomain=' + encodeURIComponent(val) + '&exclude_id=<?php echo $id; ?>')
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    status.textContent = data.message;
                    status.className = 'form-text ' + (data.available ? 'text-success' : 'text-danger');
                })
                .catch(function() { status.textContent = ''; });
        }, 600);
    });
})();
</script>
<?php require_once 'includes/footer.php'; ?>
