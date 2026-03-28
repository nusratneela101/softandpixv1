<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$builtinRoles = ['admin', 'developer', 'client', 'editor', 'ui_designer', 'seo_specialist'];
$csrf_token   = generateCsrfToken();

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('error', 'Invalid request.'); header('Location: roles.php'); exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $rid       = (int)($_POST['role_id'] ?? 0);
        $roleName  = trim($_POST['role_name'] ?? '');
        $roleLabel = trim($_POST['role_label'] ?? '');
        $roleColor = trim($_POST['role_color'] ?? '#6c757d');
        $roleIcon  = trim($_POST['role_icon'] ?? 'bi-person');
        $desc      = trim($_POST['description'] ?? '');
        $pfFields  = $_POST['profile_fields'] ?? [];
        $perms     = $_POST['permissions'] ?? [];

        if (empty($roleName) || empty($roleLabel)) {
            flashMessage('error', 'Role name and label are required.'); header('Location: roles.php'); exit;
        }

        // Sanitize role_name: lowercase alphanumeric + underscore only
        $roleName = strtolower(preg_replace('/[^a-z0-9_]/', '', $roleName));
        if (empty($roleName)) {
            flashMessage('error', 'Role name contains invalid characters.'); header('Location: roles.php'); exit;
        }

        $pfJson   = json_encode(array_values((array)$pfFields));
        $permJson = json_encode(array_fill_keys(array_values((array)$perms), true));

        try {
            if ($action === 'edit' && $rid > 0) {
                $pdo->prepare("UPDATE custom_roles SET role_label=?, role_color=?, role_icon=?, description=?, profile_fields=?, permissions=? WHERE id=?")
                    ->execute([$roleLabel, $roleColor, $roleIcon, $desc, $pfJson, $permJson, $rid]);
                flashMessage('success', 'Role updated!');
            } else {
                $pdo->prepare("INSERT INTO custom_roles (role_name, role_label, role_color, role_icon, description, profile_fields, permissions) VALUES (?, ?, ?, ?, ?, ?, ?)")
                    ->execute([$roleName, $roleLabel, $roleColor, $roleIcon, $desc, $pfJson, $permJson]);
                flashMessage('success', 'Role created!');
            }
        } catch (Exception $e) {
            flashMessage('error', 'Operation failed: ' . $e->getMessage());
        }
        header('Location: roles.php'); exit;
    }

    if ($action === 'delete') {
        $rid      = (int)($_POST['role_id'] ?? 0);
        $roleName = trim($_POST['role_name_check'] ?? '');
        if (in_array($roleName, $builtinRoles)) {
            flashMessage('error', 'Cannot delete built-in roles.');
        } else {
            try {
                $pdo->prepare("DELETE FROM custom_roles WHERE id = ?")->execute([$rid]);
                flashMessage('success', 'Role deleted.');
            } catch (Exception $e) { flashMessage('error', 'Delete failed.'); }
        }
        header('Location: roles.php'); exit;
    }
}

try {
    $roles = $pdo->query("SELECT * FROM custom_roles ORDER BY role_label ASC")->fetchAll();
} catch (Exception $e) { $roles = []; }

$allProfileFields = ['name', 'email', 'phone', 'avatar', 'bio', 'skills', 'portfolio_url', 'github_url', 'linkedin_url', 'dribbble_url', 'behance_url', 'company', 'country', 'address', 'custom_field_1', 'custom_field_2', 'password'];
$allPermissions   = ['view_projects', 'update_progress', 'chat', 'deadline_request', 'upload_files', 'view_own_projects', 'view_invoices', 'make_payments', 'view_assigned_projects', 'all'];
$permLabels = [
    'view_projects'         => 'View Projects',
    'update_progress'       => 'Update Progress',
    'chat'                  => 'Chat Access',
    'deadline_request'      => 'Request Deadline Extension',
    'upload_files'          => 'Upload Files',
    'view_own_projects'     => 'View Own Projects',
    'view_invoices'         => 'View Invoices',
    'make_payments'         => 'Make Payments',
    'view_assigned_projects'=> 'View Assigned Projects',
    'all'                   => 'Full Admin Access',
];

require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div><h1><i class="bi bi-shield-check me-2"></i>Role Builder</h1></div>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#roleModal" id="btnAddRole">
        <i class="bi bi-plus-circle me-1"></i>Add New Role
    </button>
</div>
<div class="container-fluid">
    <div class="row g-3">
        <?php foreach ($roles as $role):
            $fields    = json_decode($role['profile_fields'] ?? '[]', true) ?: [];
            $perms     = json_decode($role['permissions'] ?? '{}', true) ?: [];
            $isBuiltin = in_array($role['role_name'], $builtinRoles);
        ?>
        <div class="col-md-6 col-xl-4">
            <div class="card border-0 shadow-sm h-100" style="border-top:4px solid <?php echo h($role['role_color']); ?> !important;">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div>
                            <span class="badge fs-6 mb-1" style="background:<?php echo h($role['role_color']); ?>;">
                                <i class="<?php echo h($role['role_icon'] ?? 'bi-person'); ?> me-1"></i>
                                <?php echo h($role['role_label']); ?>
                            </span>
                            <?php if ($isBuiltin): ?>
                            <span class="badge bg-secondary ms-1 small">Built-in</span>
                            <?php endif; ?>
                            <div class="small text-muted mt-1"><code><?php echo h($role['role_name']); ?></code></div>
                        </div>
                        <div class="d-flex gap-1">
                            <button class="btn btn-sm btn-outline-primary btn-edit-role"
                                data-role="<?php echo htmlspecialchars(json_encode($role), ENT_QUOTES); ?>"
                                title="Edit"><i class="bi bi-pencil"></i></button>
                            <?php if (!$isBuiltin): ?>
                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this role?')">
                                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="role_id" value="<?php echo (int)$role['id']; ?>">
                                <input type="hidden" name="role_name_check" value="<?php echo h($role['role_name']); ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if (!empty($role['description'])): ?>
                    <p class="small text-muted mb-2"><?php echo h($role['description']); ?></p>
                    <?php endif; ?>
                    <div class="small">
                        <strong>Profile Fields:</strong>
                        <?php echo !empty($fields) ? implode(', ', array_map('htmlspecialchars', $fields)) : '<span class="text-muted">none</span>'; ?>
                    </div>
                    <div class="small mt-1">
                        <strong>Permissions:</strong>
                        <?php echo !empty($perms) ? implode(', ', array_map('htmlspecialchars', array_keys($perms))) : '<span class="text-muted">none</span>'; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($roles)): ?>
        <div class="col-12"><div class="text-center py-5 text-muted">No roles found.</div></div>
        <?php endif; ?>
    </div>
</div>

<!-- Add/Edit Role Modal -->
<div class="modal fade" id="roleModal" tabindex="-1" aria-labelledby="roleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="roleModalLabel"><i class="bi bi-shield-plus me-2"></i>Add Role</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="roleForm">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="role_id" id="formRoleId" value="">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role Name (slug) *</label>
                            <input type="text" name="role_name" id="formRoleName" class="form-control" placeholder="my_role" pattern="[a-z0-9_]+" required>
                            <div class="form-text">Lowercase letters, numbers, underscores only.</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Role Label *</label>
                            <input type="text" name="role_label" id="formRoleLabel" class="form-control" placeholder="My Role" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Color</label>
                            <input type="color" name="role_color" id="formRoleColor" class="form-control form-control-color" value="#6c757d">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Icon <span class="text-muted fw-normal">(Bootstrap Icons class)</span></label>
                            <input type="text" name="role_icon" id="formRoleIcon" class="form-control" placeholder="bi-person-fill">
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold">Description</label>
                            <textarea name="description" id="formDesc" class="form-control" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold d-block mb-2">Profile Fields</label>
                            <div class="row g-2">
                                <?php foreach ($allProfileFields as $f): ?>
                                <div class="col-md-3 col-6">
                                    <div class="form-check">
                                        <input class="form-check-input pf-check" type="checkbox" name="profile_fields[]" value="<?php echo h($f); ?>" id="pf_<?php echo h($f); ?>">
                                        <label class="form-check-label small" for="pf_<?php echo h($f); ?>"><?php echo h($f); ?></label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold d-block mb-2">Permissions</label>
                            <div class="row g-2">
                                <?php foreach ($allPermissions as $p): ?>
                                <div class="col-md-4 col-6">
                                    <div class="form-check">
                                        <input class="form-check-input perm-check" type="checkbox" name="permissions[]" value="<?php echo h($p); ?>" id="perm_<?php echo h($p); ?>">
                                        <label class="form-check-label small" for="perm_<?php echo h($p); ?>"><?php echo h($permLabels[$p] ?? $p); ?></label>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Role</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
function resetModal() {
    document.getElementById('roleModalLabel').innerHTML = '<i class="bi bi-shield-plus me-2"></i>Add Role';
    document.getElementById('formAction').value   = 'add';
    document.getElementById('formRoleId').value   = '';
    document.getElementById('formRoleName').value = '';
    document.getElementById('formRoleName').readOnly = false;
    document.getElementById('formRoleLabel').value = '';
    document.getElementById('formRoleColor').value = '#6c757d';
    document.getElementById('formRoleIcon').value  = 'bi-person';
    document.getElementById('formDesc').value      = '';
    document.querySelectorAll('.pf-check, .perm-check').forEach(function(cb) { cb.checked = false; });
}

document.getElementById('btnAddRole').addEventListener('click', resetModal);

document.querySelectorAll('.btn-edit-role').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var role = JSON.parse(this.dataset.role);
        document.getElementById('roleModalLabel').innerHTML = '<i class="bi bi-pencil me-2"></i>Edit Role: ' + role.role_label;
        document.getElementById('formAction').value   = 'edit';
        document.getElementById('formRoleId').value   = role.id;
        document.getElementById('formRoleName').value = role.role_name;
        document.getElementById('formRoleName').readOnly = true;
        document.getElementById('formRoleLabel').value = role.role_label;
        document.getElementById('formRoleColor').value = role.role_color || '#6c757d';
        document.getElementById('formRoleIcon').value  = role.role_icon || 'bi-person';
        document.getElementById('formDesc').value      = role.description || '';

        document.querySelectorAll('.pf-check').forEach(function(cb) { cb.checked = false; });
        document.querySelectorAll('.perm-check').forEach(function(cb) { cb.checked = false; });

        try {
            var pf = role.profile_fields ? JSON.parse(role.profile_fields) : [];
            pf.forEach(function(f) { var cb = document.getElementById('pf_' + f); if (cb) cb.checked = true; });
        } catch(e) {}

        try {
            var perms = role.permissions ? JSON.parse(role.permissions) : {};
            Object.keys(perms).forEach(function(p) { var cb = document.getElementById('perm_' + p); if (cb) cb.checked = true; });
        } catch(e) {}

        new bootstrap.Modal(document.getElementById('roleModal')).show();
    });
});
</script>
<?php require_once 'includes/footer.php'; ?>
