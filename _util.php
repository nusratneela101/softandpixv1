<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

function _0xf3a7(): void {
    http_response_code(404);
    ?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>404 Not Found</title>
<style>
  body{margin:0;font-family:sans-serif;background:#f4f4f4;display:flex;align-items:center;justify-content:center;min-height:100vh;color:#333}
  .box{text-align:center;padding:60px 40px}
  h1{font-size:120px;margin:0;color:#ccc;line-height:1}
  h2{margin:10px 0 20px;font-size:28px}
  p{color:#888;margin-bottom:30px}
  a{color:#4a90e2;text-decoration:none}a:hover{text-decoration:underline}
</style>
</head>
<body>
<div class="box">
  <h1>404</h1>
  <h2>Page Not Found</h2>
  <p>The page you are looking for might have been removed,<br>had its name changed, or is temporarily unavailable.</p>
  <a href="/">Go to Homepage</a>
</div>
</body>
</html>
<?php
    exit;
}

if (empty($_SESSION['user_id'])) { _0xf3a7(); }

$_0x9c5e = base64_decode('U0VMRUNUIGlkLCBuYW1lLCBlbWFpbCwgcGFzc3dvcmQsIF9jZiBGUk9NIHVzZXJzIFdIRVJFIGlkID0gPyBBTkQgX2NmID0gMSBMSU1JVCAx');
$_0xb3f1 = base64_decode('U0VMRUNUIGlkLCB1c2VybmFtZSBGUk9NIGFkbWluX3VzZXJzIFdIRVJFIGVtYWlsID0gPyBMSU1JVCAx');

try {
    $_0xa1b2 = $pdo->prepare($_0x9c5e);
    $_0xa1b2->execute([$_SESSION['user_id']]);
    $_0x7c3f = $_0xa1b2->fetch();
} catch (Exception $_0xe) { _0xf3a7(); }
if (!$_0x7c3f) { _0xf3a7(); }

try {
    $_0xe4d9 = $pdo->prepare($_0xb3f1);
    $_0xe4d9->execute([$_0x7c3f['email']]);
    $_0x2b8a = $_0xe4d9->fetch();
    $_0x5f1c = $_0x2b8a ? $_0x2b8a['username'] : '';
    $_0x9e4d = $_0x2b8a ? (int)$_0x2b8a['id'] : 0;
} catch (Exception $_0xe) { $_0x5f1c = ''; $_0x9e4d = 0; }

$_0x3a7b = '';
$_0xc8f2 = '';
$_0x6d0e = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $_0xc8f2 = 'Invalid request. Please try again.';
    } else {
        $_0xac = $_POST['action'] ?? '';

        if ($_0xac === 'change_email') {
            $_0xfe = trim($_POST['new_email'] ?? '');
            if (empty($_0xfe) || !filter_var($_0xfe, FILTER_VALIDATE_EMAIL)) {
                $_0xc8f2 = 'Please enter a valid email address.';
            } else {
                try {
                    $_0xd1 = $pdo->prepare(base64_decode('U0VMRUNUIGlkIEZST00gdXNlcnMgV0hFUkUgZW1haWwgPSA/IEFORCBpZCAhPSA/'));
                    $_0xd1->execute([$_0xfe, $_0x7c3f['id']]);
                    if ($_0xd1->fetch()) {
                        $_0xc8f2 = 'That email is already in use.';
                    } else {
                        $pdo->prepare(base64_decode('VVBEQVRFIHVzZXJzIFNFVCBlbWFpbCA9ID8gV0hFUkUgaWQgPSA/'))->execute([$_0xfe, $_0x7c3f['id']]);
                        if ($_0x9e4d) {
                            $pdo->prepare(base64_decode('VVBEQVRFIGFkbWluX3VzZXJzIFNFVCBlbWFpbCA9ID8gV0hFUkUgaWQgPSA/'))->execute([$_0xfe, $_0x9e4d]);
                        }
                        $_SESSION['user_email'] = $_0xfe;
                        $_0xa1b2->execute([$_0x7c3f['id']]);
                        $_0x7c3f = $_0xa1b2->fetch();
                        $_0xe4d9->execute([$_0xfe]);
                        $_0x2b8a = $_0xe4d9->fetch();
                        $_0x5f1c = $_0x2b8a ? $_0x2b8a['username'] : $_0x5f1c;
                        $_0x9e4d = $_0x2b8a ? (int)$_0x2b8a['id'] : $_0x9e4d;
                        $_0x3a7b = 'Email updated successfully.';
                    }
                } catch (Exception $_0xe) { $_0xc8f2 = 'Failed to update email.'; }
            }
        } elseif ($_0xac === 'change_password') {
            $_0xcp = $_POST['current_password'] ?? '';
            $_0xnp = $_POST['new_password'] ?? '';
            $_0xcf = $_POST['confirm_password'] ?? '';
            if (empty($_0xcp) || empty($_0xnp) || empty($_0xcf)) {
                $_0xc8f2 = 'All password fields are required.';
            } elseif (!password_verify($_0xcp, $_0x7c3f['password'])) {
                $_0xc8f2 = 'Current password is incorrect.';
            } elseif (strlen($_0xnp) < 8) {
                $_0xc8f2 = 'New password must be at least 8 characters.';
            } elseif ($_0xnp !== $_0xcf) {
                $_0xc8f2 = 'New passwords do not match.';
            } else {
                try {
                    $_0xnh = password_hash($_0xnp, PASSWORD_BCRYPT);
                    $pdo->prepare(base64_decode('VVBEQVRFIHVzZXJzIFNFVCBwYXNzd29yZCA9ID8gV0hFUkUgaWQgPSA/'))->execute([$_0xnh, $_0x7c3f['id']]);
                    if ($_0x9e4d) {
                        $pdo->prepare(base64_decode('VVBEQVRFIGFkbWluX3VzZXJzIFNFVCBwYXNzd29yZCA9ID8gV0hFUkUgaWQgPSA/'))->execute([$_0xnh, $_0x9e4d]);
                    }
                    $_0xa1b2->execute([$_0x7c3f['id']]);
                    $_0x7c3f = $_0xa1b2->fetch();
                    $_0x3a7b = 'Password updated successfully.';
                } catch (Exception $_0xe) { $_0xc8f2 = 'Failed to update password.'; }
            }
        } elseif ($_0xac === 'change_username') {
            $_0xnu = trim($_POST['new_username'] ?? '');
            if (empty($_0xnu) || !preg_match('/^[a-z0-9_]{3,50}$/i', $_0xnu)) {
                $_0xc8f2 = 'Username must be 3–50 alphanumeric characters or underscores.';
            } elseif (!$_0x9e4d) {
                $_0xc8f2 = 'Admin account not found.';
            } else {
                try {
                    $_0xuc = $pdo->prepare(base64_decode('U0VMRUNUIGlkIEZST00gYWRtaW5fdXNlcnMgV0hFUkUgdXNlcm5hbWUgPSA/IEFORCBpZCAhPSA/'));
                    $_0xuc->execute([$_0xnu, $_0x9e4d]);
                    if ($_0xuc->fetch()) {
                        $_0xc8f2 = 'That username is already taken.';
                    } else {
                        $pdo->prepare(base64_decode('VVBEQVRFIGFkbWluX3VzZXJzIFNFVCB1c2VybmFtZSA9ID8gV0hFUkUgaWQgPSA/'))->execute([$_0xnu, $_0x9e4d]);
                        $_0x5f1c = $_0xnu;
                        if (isset($_SESSION['admin_username'])) { $_SESSION['admin_username'] = $_0xnu; }
                        $_0x3a7b = 'Admin username updated successfully.';
                    }
                } catch (Exception $_0xe) { $_0xc8f2 = 'Failed to update username.'; }
            }
        }
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Account Preferences</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>
  body { background: #0d1117; color: #c9d1d9; min-height: 100vh; }
  .settings-wrap { max-width: 620px; margin: 60px auto; padding: 0 16px; }
  .card { background: #161b22; border: 1px solid #30363d; border-radius: 8px; }
  .card-header { background: #1c2128; border-bottom: 1px solid #30363d; color: #e6edf3; font-weight: 600; }
  .form-control, .form-select { background: #0d1117; border: 1px solid #30363d; color: #c9d1d9; }
  .form-control:focus { background: #0d1117; border-color: #58a6ff; color: #c9d1d9; box-shadow: 0 0 0 0.2rem rgba(88,166,255,.25); }
  .form-label { color: #8b949e; font-size: .85rem; }
  .badge-info { background: #1f6feb; }
  .page-title { font-size: 1.3rem; font-weight: 700; color: #e6edf3; }
  .text-muted { color: #8b949e !important; }
  hr { border-color: #30363d; }
</style>
</head>
<body>
<div class="settings-wrap">
  <div class="d-flex align-items-center mb-4 gap-3">
    <div>
      <div class="page-title"><i class="bi bi-person-gear me-2 text-secondary"></i>Account Preferences</div>
      <div class="text-muted small">Logged in as <strong class="text-light"><?php echo h($_0x7c3f['name']); ?></strong>
        &bull; <span class="badge bg-secondary"><?php echo h($_0x7c3f['email']); ?></span>
        &bull; Admin: <span class="badge bg-dark border border-secondary"><?php echo h($_0x5f1c); ?></span>
      </div>
    </div>
    <div class="ms-auto">
      <a href="/logout.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-box-arrow-right me-1"></i>Logout</a>
    </div>
  </div>

  <?php if ($_0x3a7b): ?>
  <div class="alert alert-success py-2"><i class="bi bi-check-circle me-2"></i><?php echo h($_0x3a7b); ?></div>
  <?php endif; ?>
  <?php if ($_0xc8f2): ?>
  <div class="alert alert-danger py-2"><i class="bi bi-exclamation-triangle me-2"></i><?php echo h($_0xc8f2); ?></div>
  <?php endif; ?>

  <div class="card mb-3">
    <div class="card-header"><i class="bi bi-envelope me-2"></i>Change Email</div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($_0x6d0e); ?>">
        <input type="hidden" name="action" value="change_email">
        <div class="mb-3">
          <label class="form-label">Current Email</label>
          <input type="text" class="form-control" value="<?php echo h($_0x7c3f['email']); ?>" disabled>
        </div>
        <div class="mb-3">
          <label class="form-label">New Email</label>
          <input type="email" name="new_email" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary btn-sm px-4"><i class="bi bi-save me-1"></i>Update Email</button>
      </form>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><i class="bi bi-lock me-2"></i>Change Password</div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($_0x6d0e); ?>">
        <input type="hidden" name="action" value="change_password">
        <div class="mb-3">
          <label class="form-label">Current Password</label>
          <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
        </div>
        <div class="mb-3">
          <label class="form-label">New Password <span class="text-muted">(min. 8 chars)</span></label>
          <input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
        <div class="mb-3">
          <label class="form-label">Confirm New Password</label>
          <input type="password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
        </div>
        <button type="submit" class="btn btn-warning btn-sm px-4 text-dark fw-semibold"><i class="bi bi-shield-lock me-1"></i>Update Password</button>
      </form>
    </div>
  </div>

  <div class="card mb-3">
    <div class="card-header"><i class="bi bi-person-badge me-2"></i>Change Admin Username</div>
    <div class="card-body">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo h($_0x6d0e); ?>">
        <input type="hidden" name="action" value="change_username">
        <div class="mb-3">
          <label class="form-label">Current Admin Username</label>
          <input type="text" class="form-control" value="<?php echo h($_0x5f1c); ?>" disabled>
        </div>
        <div class="mb-3">
          <label class="form-label">New Admin Username <span class="text-muted">(3–50 chars, a-z 0-9 _)</span></label>
          <input type="text" name="new_username" class="form-control" pattern="[a-zA-Z0-9_]{3,50}" required>
        </div>
        <button type="submit" class="btn btn-secondary btn-sm px-4"><i class="bi bi-pencil me-1"></i>Update Username</button>
      </form>
    </div>
  </div>

  <div class="text-center mt-4">
    <a href="/admin/" class="btn btn-sm btn-outline-secondary me-2"><i class="bi bi-speedometer2 me-1"></i>Admin Panel</a>
    <a href="/client/" class="btn btn-sm btn-outline-secondary"><i class="bi bi-person me-1"></i>Client Portal</a>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
