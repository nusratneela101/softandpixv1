<?php
/**
 * Admin — Theme Customizer
 */
require_once '../config/db.php';
require_once '../includes/auth.php';
require_once '../includes/language.php';
require_once '../includes/theme.php';
require_once '../includes/activity_logger.php';
requireAdmin();

$page_title = __('theme');
$admin_id   = $_SESSION['admin_id'];

$allowed_settings = [
    'primary_color', 'secondary_color', 'sidebar_bg', 'sidebar_text',
    'header_bg', 'header_text', 'accent_color', 'body_bg',
    'font_family', 'dark_mode', 'logo_url', 'favicon_url',
];

$safe_fonts = ['Roboto','Open Sans','Lato','Montserrat','Poppins','Nunito','Inter','Raleway'];

// Handle save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['save_theme'])) {
    $saved = 0;
    foreach ($allowed_settings as $key) {
        if (!array_key_exists($key, $_POST)) continue;
        $val = $_POST[$key];

        // Validate per key
        if (in_array($key, ['primary_color','secondary_color','sidebar_bg','sidebar_text','header_bg','header_text','accent_color','body_bg'], true)) {
            if (!preg_match('/^#[0-9a-fA-F]{3,6}$/', $val)) continue;
        } elseif ($key === 'font_family') {
            if (!in_array($val, $safe_fonts, true)) continue;
        } elseif ($key === 'dark_mode') {
            $val = $val === '1' ? '1' : '0';
        } elseif (in_array($key, ['logo_url','favicon_url'], true)) {
            $val = mb_substr(filter_var($val, FILTER_SANITIZE_URL) ?? '', 0, 500);
        } else {
            continue;
        }

        try {
            $pdo->prepare(
                "INSERT INTO theme_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?"
            )->execute([$key, $val, $val]);
            $saved++;
        } catch (Exception $e) {
            error_log('theme save: ' . $e->getMessage());
        }
    }

    // Handle logo/favicon upload
    foreach (['logo' => 'logo_url', 'favicon' => 'favicon_url'] as $file_key => $db_key) {
        if (!empty($_FILES[$file_key]['tmp_name'])) {
            $ext = strtolower(pathinfo($_FILES[$file_key]['name'], PATHINFO_EXTENSION));
            if (in_array($ext, ['png','jpg','jpeg','gif','ico','svg'], true)) {
                $filename = $file_key . '_' . time() . '.' . $ext;
                $dest     = __DIR__ . '/../uploads/' . $filename;
                if (move_uploaded_file($_FILES[$file_key]['tmp_name'], $dest)) {
                    $url = '/uploads/' . $filename;
                    try {
                        $pdo->prepare("INSERT INTO theme_settings (setting_key, setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?")->execute([$db_key, $url, $url]);
                    } catch (Exception $e) {}
                }
            }
        }
    }

    log_activity($pdo, $admin_id, 'theme_updated', 'Theme settings saved', 'theme', null);
    flashMessage('success', __('saved_successfully'));
    header('Location: theme.php');
    exit;
}

// Handle reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['reset_theme'])) {
    try {
        $pdo->query("DELETE FROM theme_settings");
    } catch (Exception $e) {}
    flashMessage('info', __('reset') . ' — ' . __('success'));
    header('Location: theme.php');
    exit;
}

$theme = get_all_theme_settings($pdo);

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="fas fa-palette me-2"></i><?= __('theme') ?></h1>
</div>
<div class="container-fluid">

<form method="post" enctype="multipart/form-data">
<div class="row g-4">
    <!-- Color Pickers -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header bg-white fw-bold"><i class="fas fa-paint-brush me-2"></i><?= __('color') ?> Settings</div>
            <div class="card-body">
                <?php
                $color_fields = [
                    'primary_color'   => __('primary_color'),
                    'secondary_color' => __('secondary_color'),
                    'sidebar_bg'      => __('sidebar_bg'),
                    'sidebar_text'    => 'Sidebar Text Color',
                    'header_bg'       => __('header_bg'),
                    'header_text'     => 'Header Text Color',
                    'accent_color'    => 'Accent Color',
                    'body_bg'         => 'Body Background',
                ];
                foreach ($color_fields as $key => $label): ?>
                <div class="row align-items-center mb-3">
                    <div class="col-5">
                        <label class="form-label mb-0"><?= h($label) ?></label>
                    </div>
                    <div class="col-4">
                        <input type="color" name="<?= $key ?>" class="form-control form-control-color w-100"
                               value="<?= h($theme[$key]) ?>" id="color_<?= $key ?>"
                               onchange="updatePreview('<?= $key ?>', this.value)">
                    </div>
                    <div class="col-3">
                        <input type="text" class="form-control form-control-sm" value="<?= h($theme[$key]) ?>"
                               id="hex_<?= $key ?>" onchange="syncHex('<?= $key ?>', this.value)">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Font, Mode, Upload -->
    <div class="col-lg-6">
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold"><i class="fas fa-font me-2"></i><?= __('font_selection') ?></div>
            <div class="card-body">
                <select name="font_family" class="form-select" onchange="updateFontPreview(this.value)">
                    <?php foreach (['Roboto','Open Sans','Lato','Montserrat','Poppins','Nunito','Inter','Raleway'] as $f): ?>
                    <option value="<?= $f ?>" <?= $theme['font_family'] === $f ? 'selected' : '' ?>><?= $f ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="mt-2 text-muted" id="fontPreviewText" style="font-family: '<?= h($theme['font_family']) ?>', sans-serif;">
                    The quick brown fox jumps over the lazy dog.
                </p>
            </div>
        </div>
        <div class="card mb-4">
            <div class="card-header bg-white fw-bold"><i class="fas fa-moon me-2"></i><?= __('dark_mode') ?></div>
            <div class="card-body">
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" name="dark_mode" value="1" id="darkModeToggle"
                           <?= ($theme['dark_mode'] === '1') ? 'checked' : '' ?>>
                    <label class="form-check-label" for="darkModeToggle"><?= __('dark_mode') ?></label>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header bg-white fw-bold"><i class="fas fa-image me-2"></i>Logo &amp; Favicon</div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label"><?= __('logo_upload') ?></label>
                    <?php if ($theme['logo_url']): ?>
                    <div class="mb-2"><img src="<?= h($theme['logo_url']) ?>" style="height:40px;"> <input type="text" name="logo_url" class="form-control form-control-sm mt-1" value="<?= h($theme['logo_url']) ?>"></div>
                    <?php endif; ?>
                    <input type="file" name="logo" class="form-control" accept="image/*">
                </div>
                <div>
                    <label class="form-label"><?= __('favicon_upload') ?></label>
                    <input type="file" name="favicon" class="form-control" accept="image/*,.ico">
                </div>
            </div>
        </div>
    </div>

    <!-- Live Preview -->
    <div class="col-12">
        <div class="card">
            <div class="card-header bg-white fw-bold"><i class="fas fa-eye me-2"></i><?= __('preview_theme') ?></div>
            <div class="card-body p-0">
                <div id="themePreview" style="background: var(--preview-body-bg, #f4f6f9); border-radius: 0 0 8px 8px; overflow: hidden;">
                    <div style="display:flex; min-height: 200px;">
                        <div id="previewSidebar" style="width:200px; background: var(--preview-sidebar, #1a1a2e); color: #fff; padding: 16px; font-size: 13px;">
                            <div style="font-weight:700; margin-bottom:12px;">SoftandPix</div>
                            <div style="padding:8px; border-radius:4px; background: var(--preview-primary, #667eea); margin-bottom:4px;">Dashboard</div>
                            <div style="padding:8px; opacity:0.7; margin-bottom:4px;">Projects</div>
                            <div style="padding:8px; opacity:0.7;">Tasks</div>
                        </div>
                        <div style="flex:1; padding: 16px;">
                            <div id="previewHeader" style="background: var(--preview-header, #fff); padding:10px 16px; border-radius:6px; margin-bottom:12px; font-weight:600; color: var(--preview-header-text, #333);">
                                Dashboard
                            </div>
                            <div style="display:flex; gap:12px;">
                                <div style="flex:1; background: var(--preview-primary, #667eea); color:#fff; padding:14px; border-radius:8px; font-weight:600;">12 Projects</div>
                                <div style="flex:1; background: var(--preview-secondary, #764ba2); color:#fff; padding:14px; border-radius:8px; font-weight:600;">48 Tasks</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Save/Reset Buttons -->
    <div class="col-12 d-flex gap-3">
        <button type="submit" name="save_theme" value="1" class="btn btn-primary px-4">
            <i class="fas fa-save me-2"></i><?= __('save_theme') ?>
        </button>
        <button type="submit" name="reset_theme" value="1" class="btn btn-outline-secondary" onclick="return confirm('Reset all theme settings to defaults?')">
            <i class="fas fa-undo me-2"></i><?= __('reset_theme') ?>
        </button>
    </div>
</div>
</form>
</div>

<script>
function updatePreview(key, val) {
    document.getElementById('hex_' + key).value = val;
    var map = {
        primary_color:   '--preview-primary',
        secondary_color: '--preview-secondary',
        sidebar_bg:      '--preview-sidebar',
        header_bg:       '--preview-header',
        header_text:     '--preview-header-text',
        body_bg:         '--preview-body-bg',
    };
    if (map[key]) {
        document.getElementById('themePreview').style.setProperty(map[key], val);
    }
}
function syncHex(key, val) {
    if (/^#[0-9a-fA-F]{3,6}$/.test(val)) {
        document.getElementById('color_' + key).value = val;
        updatePreview(key, val);
    }
}
function updateFontPreview(font) {
    document.getElementById('fontPreviewText').style.fontFamily = "'" + font + "', sans-serif";
}
</script>

<?php require_once 'includes/footer.php'; ?>
