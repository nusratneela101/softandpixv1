<?php
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();

$error = '';
$success = '';
$csrf_token = generateCsrfToken();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'save') {
            $id      = (int)($_POST['template_id'] ?? 0);
            $name    = trim($_POST['name'] ?? '');
            $subject = trim($_POST['subject'] ?? '');
            $body    = trim($_POST['body'] ?? '');

            if (empty($name) || empty($subject) || empty($body)) {
                $error = 'Name, subject and body are all required.';
            } else {
                try {
                    if ($id > 0) {
                        $pdo->prepare("UPDATE email_templates SET name=?, subject=?, body=?, updated_at=NOW() WHERE id=?")
                            ->execute([$name, $subject, $body, $id]);
                        flashMessage('success', 'Template updated successfully.');
                    } else {
                        $pdo->prepare("INSERT INTO email_templates (name, subject, body, created_at, updated_at) VALUES (?,?,?,NOW(),NOW())")
                            ->execute([$name, $subject, $body]);
                        flashMessage('success', 'Template created successfully.');
                    }
                } catch (Exception $e) {
                    $error = 'Failed to save template.';
                }
            }
        } elseif ($action === 'delete') {
            $id = (int)($_POST['template_id'] ?? 0);
            if ($id > 0) {
                try {
                    $pdo->prepare("DELETE FROM email_templates WHERE id=?")->execute([$id]);
                    flashMessage('success', 'Template deleted.');
                } catch (Exception $e) {
                    $error = 'Failed to delete template.';
                }
            }
        }

        if (empty($error)) {
            header('Location: email_templates.php'); exit;
        }
    }
}

// Fetch all templates
try {
    $templates = $pdo->query("SELECT * FROM email_templates ORDER BY name ASC")->fetchAll();
} catch (Exception $e) { $templates = []; }

// Fetch template for editing if ?edit=ID
$editTemplate = null;
if (!empty($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($templates as $t) {
        if ($t['id'] === $editId) { $editTemplate = $t; break; }
    }
}

require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div><h1><i class="bi bi-file-earmark-text me-2"></i>Email Templates</h1></div>
    <div class="d-flex gap-2">
        <a href="email_dashboard.php" class="btn btn-outline-secondary"><i class="bi bi-send me-1"></i>Send Email</a>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#templateModal" id="newTemplateBtn">
            <i class="bi bi-plus-circle me-1"></i>New Template
        </button>
    </div>
</div>

<div class="container-fluid">
    <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show"><i class="bi bi-exclamation-triangle me-2"></i><?php echo h($error); ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-dark">
                        <tr>
                            <th>Name</th>
                            <th>Subject</th>
                            <th>Variables</th>
                            <th>Last Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($templates)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">
                            <i class="bi bi-file-earmark-text" style="font-size:2rem;"></i>
                            <p class="mt-2 mb-0">No templates yet. Create your first template.</p>
                        </td></tr>
                        <?php endif; ?>
                        <?php foreach ($templates as $tpl): ?>
                        <tr>
                            <td class="fw-semibold"><?php echo h($tpl['name']); ?></td>
                            <td><?php echo h($tpl['subject']); ?></td>
                            <td>
                                <?php
                                // Extract {{variable}} placeholders from body
                                preg_match_all('/\{\{(\w+)\}\}/', $tpl['body'], $vars);
                                $unique = array_unique($vars[1] ?? []);
                                foreach (array_slice($unique, 0, 5) as $v):
                                ?>
                                <code class="small">{{<?php echo h($v); ?>}}</code>
                                <?php endforeach; ?>
                                <?php if (count($unique) > 5): ?>
                                <span class="text-muted small">+<?php echo count($unique)-5; ?> more</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?php echo $tpl['updated_at'] ? date('M j, Y H:i', strtotime($tpl['updated_at'])) : '—'; ?></td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-primary edit-btn"
                                    data-id="<?php echo (int)$tpl['id']; ?>"
                                    data-name="<?php echo h($tpl['name']); ?>"
                                    data-subject="<?php echo h($tpl['subject']); ?>"
                                    data-body="<?php echo h($tpl['body']); ?>"
                                    data-bs-toggle="modal" data-bs-target="#templateModal">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger delete-btn"
                                    data-id="<?php echo (int)$tpl['id']; ?>"
                                    data-name="<?php echo h($tpl['name']); ?>"
                                    data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Variables Reference Card -->
    <div class="card mt-3">
        <div class="card-header"><i class="bi bi-braces me-2"></i>Available Template Variables</div>
        <div class="card-body">
            <p class="text-muted small mb-2">Use these <code>{{variable}}</code> placeholders in your templates. They will be replaced when sending.</p>
            <div class="row g-2">
                <?php foreach ([
                    ['{{user_name}}',    'Recipient\'s full name'],
                    ['{{user_email}}',   'Recipient\'s email address'],
                    ['{{site_name}}',    'Website/company name'],
                    ['{{site_url}}',     'Website URL'],
                    ['{{project_name}}', 'Project title'],
                    ['{{invoice_no}}',   'Invoice number'],
                    ['{{due_date}}',     'Invoice or task due date'],
                    ['{{amount}}',       'Invoice amount'],
                    ['{{reset_link}}',   'Password reset link'],
                    ['{{verify_link}}',  'Email verification link'],
                ] as [$var, $desc]): ?>
                <div class="col-md-4 col-lg-3">
                    <div class="d-flex align-items-start gap-2 p-2 bg-light rounded">
                        <code class="small text-nowrap"><?php echo h($var); ?></code>
                        <span class="text-muted small"><?php echo h($desc); ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Template Modal -->
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="templateModalTitle">New Email Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="templateForm">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="template_id" id="templateId" value="0">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Template Name *</label>
                        <input type="text" name="name" id="tplName" class="form-control" placeholder="e.g. Welcome Email, Password Reset" required>
                        <div class="form-text">An internal name to identify this template.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Subject *</label>
                        <input type="text" name="subject" id="tplSubject" class="form-control" placeholder="Email subject line" required>
                        <div class="form-text">You can use <code>{{variable}}</code> placeholders here too.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Body *</label>
                        <textarea name="body" id="tplBody" class="form-control font-monospace" rows="12"
                            placeholder="Dear {{user_name}},&#10;&#10;Your message here...&#10;&#10;Regards,&#10;{{site_name}}" required></textarea>
                        <div class="form-text">Supports HTML. Use <code>{{variable}}</code> for dynamic content.</div>
                    </div>
                    <!-- Live preview -->
                    <div class="mb-0">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="previewToggle">
                            <i class="bi bi-eye me-1"></i>Toggle Preview
                        </button>
                        <div id="bodyPreview" class="border rounded p-3 mt-2 bg-white d-none" style="min-height:100px;"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Template</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="bi bi-exclamation-triangle me-2"></i>Delete Template</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete the template <strong id="deleteTemplateName"></strong>? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form method="POST" class="d-inline">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="template_id" id="deleteTemplateId" value="0">
                    <button type="submit" class="btn btn-danger"><i class="bi bi-trash me-1"></i>Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Edit button → populate modal
document.querySelectorAll('.edit-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('templateModalTitle').textContent = 'Edit Email Template';
        document.getElementById('templateId').value  = this.dataset.id;
        document.getElementById('tplName').value     = this.dataset.name;
        document.getElementById('tplSubject').value  = this.dataset.subject;
        document.getElementById('tplBody').value     = this.dataset.body;
    });
});

// New template button → reset modal
document.getElementById('newTemplateBtn').addEventListener('click', function() {
    document.getElementById('templateModalTitle').textContent = 'New Email Template';
    document.getElementById('templateId').value  = '0';
    document.getElementById('tplName').value     = '';
    document.getElementById('tplSubject').value  = '';
    document.getElementById('tplBody').value     = '';
    document.getElementById('bodyPreview').classList.add('d-none');
});

// Delete button → populate confirmation modal
document.querySelectorAll('.delete-btn').forEach(function(btn) {
    btn.addEventListener('click', function() {
        document.getElementById('deleteTemplateId').value = this.dataset.id;
        document.getElementById('deleteTemplateName').textContent = this.dataset.name;
    });
});

// Preview toggle
document.getElementById('previewToggle').addEventListener('click', function() {
    var preview = document.getElementById('bodyPreview');
    preview.innerHTML = document.getElementById('tplBody').value.replace(/\n/g, '<br>');
    preview.classList.toggle('d-none');
});
</script>
<?php require_once 'includes/footer.php'; ?>
