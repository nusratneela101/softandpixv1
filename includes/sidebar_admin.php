<?php
/**
 * Admin Sidebar Navigation
 */
$current = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-cube me-2"></i>SoftandPix
    </div>
    <div class="px-3 py-2">
        <div class="position-relative" id="globalSearchWrap">
            <input type="text" id="globalSearch" class="form-control form-control-sm"
                placeholder="&#xf002; Search..." style="padding-left:30px;font-family:'Font Awesome 6 Free','FontAwesome',sans-serif;">
            <div id="searchResults" class="position-absolute bg-white rounded shadow-lg w-100 mt-1 d-none" style="z-index:9999;max-height:300px;overflow-y:auto;"></div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="<?= e(BASE_URL) ?>/admin/" class="nav-item <?= $current === 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/chat.php" class="nav-item <?= $current === 'chat.php' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i><span>Chat</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/video_call.php" class="nav-item <?= $current === 'video_call.php' ? 'active' : '' ?>">
            <i class="fas fa-video"></i><span>Video Calls</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/agreements.php" class="nav-item <?= $current === 'agreements.php' ? 'active' : '' ?>">
            <i class="fas fa-file-contract"></i><span>Agreements</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/esign.php" class="nav-item <?= $current === 'esign.php' ? 'active' : '' ?>">
            <i class="fas fa-signature"></i><span>E-Signatures</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/projects.php" class="nav-item <?= $current === 'projects.php' ? 'active' : '' ?>">
            <i class="fas fa-project-diagram"></i><span>Projects</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/tasks.php" class="nav-item <?= $current === 'tasks.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i><span>Tasks</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/invoices.php" class="nav-item <?= $current === 'invoices.php' ? 'active' : '' ?>">
            <i class="fas fa-file-invoice-dollar"></i><span>Invoices</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/recurring_invoices.php" class="nav-item <?= $current === 'recurring_invoices.php' ? 'active' : '' ?>">
            <i class="fas fa-redo"></i><span>Recurring Invoices</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/payments.php" class="nav-item <?= $current === 'payments.php' ? 'active' : '' ?>">
            <i class="fas fa-credit-card"></i><span>Payments</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/payment_settings.php" class="nav-item <?= $current === 'payment_settings.php' ? 'active' : '' ?>">
            <i class="fas fa-cogs"></i><span>Payment Gateways</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/email.php" class="nav-item <?= $current === 'email.php' ? 'active' : '' ?>">
            <i class="fas fa-envelope"></i><span>Email</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/users.php" class="nav-item <?= $current === 'users.php' ? 'active' : '' ?>">
            <i class="fas fa-users"></i><span>Users</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/developers.php" class="nav-item <?= $current === 'developers.php' ? 'active' : '' ?>">
            <i class="fas fa-code"></i><span>Developers</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/activity_log.php" class="nav-item <?= $current === 'activity_log.php' ? 'active' : '' ?>">
            <i class="fas fa-history"></i><span>Activity Log</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/reports.php" class="nav-item <?= $current === 'reports.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-bar"></i><span>Reports</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/export.php" class="nav-item <?= $current === 'export.php' ? 'active' : '' ?>">
            <i class="fas fa-download"></i><span>Export Data</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/time_tracking.php" class="nav-item <?= $current === 'time_tracking.php' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i><span>Time Tracking</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/email_templates.php" class="nav-item <?= $current === 'email_templates.php' ? 'active' : '' ?>">
            <i class="fas fa-envelope-open-text"></i><span>Email Templates</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/push_settings.php" class="nav-item <?= $current === 'push_settings.php' ? 'active' : '' ?>">
            <i class="fas fa-bell"></i><span>Push Notifications</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/settings.php" class="nav-item <?= $current === 'settings.php' ? 'active' : '' ?>">
            <i class="fas fa-cog"></i><span>Settings</span></a>
        <a href="<?= e(BASE_URL) ?>/admin/theme.php" class="nav-item <?= $current === 'theme.php' ? 'active' : '' ?>">
            <i class="fas fa-palette"></i><span>Theme</span></a>
        <hr class="my-2 mx-3 border-secondary">
        <a href="<?= e(BASE_URL) ?>/logout.php" class="nav-item text-danger">
            <i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
    </nav>
</div>
<script>
(function() {
    var input = document.getElementById('globalSearch');
    var results = document.getElementById('searchResults');
    if (!input || !results) return;
    var timer;
    input.addEventListener('input', function() {
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2) { results.classList.add('d-none'); results.innerHTML=''; return; }
        timer = setTimeout(function() {
            fetch('/api/search.php?q=' + encodeURIComponent(q), {credentials:'same-origin'})
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (!data.success || !data.results.length) {
                        results.innerHTML = '<div class="px-3 py-2 text-muted small">No results found.</div>';
                    } else {
                        results.innerHTML = data.results.map(function(r) {
                            return '<a href="' + r.url + '" class="d-block px-3 py-2 text-decoration-none border-bottom hover-bg" style="font-size:13px;color:#333">'
                                + '<i class="bi ' + r.icon + ' me-2 text-primary"></i>'
                                + '<strong>' + r.label + '</strong>'
                                + (r.meta ? '<span class="text-muted ms-2">' + r.meta + '</span>' : '')
                                + '<span class="badge bg-secondary float-end">' + r.type + '</span>'
                                + '</a>';
                        }).join('');
                    }
                    results.classList.remove('d-none');
                })
                .catch(function() { results.classList.add('d-none'); });
        }, 300);
    });
    document.addEventListener('click', function(e) {
        if (!document.getElementById('globalSearchWrap').contains(e.target)) {
            results.classList.add('d-none');
        }
    });
})();
</script>
