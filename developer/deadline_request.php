<?php
session_start();
require_once '../config/db.php';
require_once 'includes/auth.php';
requireDeveloper();

$userId     = $_SESSION['user_id'];
$projectId  = (int)($_GET['project_id'] ?? 0);
$error      = '';
$csrf_token = generateCsrfToken();

try {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id=? AND developer_id=?");
    $stmt->execute([$projectId, $userId]);
    $project = $stmt->fetch();
} catch (Exception $e) { $project = null; }

if (!$project) {
    header('Location: /developer/');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $requestedDate = $_POST['requested_deadline'] ?? '';
        $reason        = trim($_POST['reason'] ?? '');

        if (empty($requestedDate) || empty($reason)) {
            $error = 'All fields are required.';
        } elseif ($project['deadline'] && strtotime($requestedDate) <= strtotime($project['deadline'])) {
            $error = 'Requested date must be after current deadline.';
        } else {
            try {
                $pdo->prepare("INSERT INTO deadline_extension_requests
                    (project_id, developer_id, current_deadline, requested_deadline, reason)
                    VALUES (?,?,?,?,?)")
                    ->execute([$projectId, $userId, $project['deadline'], $requestedDate, $reason]);
                flashMessage('success', 'Extension request submitted. Admin will review it.');
                header('Location: /developer/project_view.php?id=' . $projectId);
                exit;
            } catch (PDOException $e) {
                $error = 'Failed to submit request.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Request Deadline Extension - Softandpix</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
<style>body { background: #f0f7ff; }</style>
</head>
<body>
<nav class="navbar navbar-light bg-white shadow-sm mb-4">
    <div class="container">
        <a class="navbar-brand" href="/developer/"><i class="bi bi-arrow-left me-2"></i>Back to Dashboard</a>
    </div>
</nav>
<div class="container" style="max-width:600px;">
    <div class="card shadow-sm border-0" style="border-radius:12px;">
        <div class="card-header bg-warning text-dark fw-bold">
            <i class="bi bi-calendar-x me-2"></i>Request Deadline Extension
        </div>
        <div class="card-body p-4">
            <div class="alert alert-light border mb-3">
                <strong><?php echo h($project['title']); ?></strong><br>
                <small class="text-muted">
                    Current deadline:
                    <?php echo $project['deadline'] ? date('F j, Y', strtotime($project['deadline'])) : 'Not set'; ?>
                </small>
            </div>
            <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo h($error); ?></div>
            <?php endif; ?>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo h($csrf_token); ?>">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Requested New Deadline *</label>
                    <input type="date" name="requested_deadline" class="form-control"
                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Reason for Extension *</label>
                    <textarea name="reason" class="form-control" rows="5"
                              placeholder="Explain why you need more time..." required></textarea>
                </div>
                <button type="submit" class="btn btn-warning w-100 fw-bold">
                    <i class="bi bi-send me-2"></i>Submit Request
                </button>
            </form>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
