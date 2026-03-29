<?php
/**
 * Developer Sidebar Navigation
 */
$current = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <i class="fas fa-cube me-2"></i>SoftandPix
    </div>
    <div class="px-3 py-2">
        <div class="position-relative" id="globalSearchWrap">
            <input type="text" id="globalSearch" class="form-control form-control-sm" placeholder="&#xf002; Search..." style="padding-left:30px;font-family:'Font Awesome 6 Free','FontAwesome',sans-serif;">
            <div id="searchResults" class="position-absolute bg-white rounded shadow-lg w-100 mt-1 d-none" style="z-index:9999;max-height:300px;overflow-y:auto;"></div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <a href="<?= e(BASE_URL) ?>/developer/" class="nav-item <?= $current === 'index.php' ? 'active' : '' ?>">
            <i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
        <a href="<?= e(BASE_URL) ?>/developer/chat.php" class="nav-item <?= $current === 'chat.php' ? 'active' : '' ?>">
            <i class="fas fa-comments"></i><span>Chat</span></a>
        <a href="<?= e(BASE_URL) ?>/developer/video_call.php" class="nav-item <?= $current === 'video_call.php' ? 'active' : '' ?>">
            <i class="fas fa-video"></i><span>Video Calls</span></a>
        <a href="<?= e(BASE_URL) ?>/developer/projects.php" class="nav-item <?= $current === 'projects.php' ? 'active' : '' ?>">
            <i class="fas fa-project-diagram"></i><span>Projects</span></a>
        <a href="<?= e(BASE_URL) ?>/developer/tasks.php" class="nav-item <?= $current === 'tasks.php' ? 'active' : '' ?>">
            <i class="fas fa-clipboard-list"></i><span>Tasks</span></a>
        <a href="<?= e(BASE_URL) ?>/developer/files.php" class="nav-item <?= $current === 'files.php' ? 'active' : '' ?>">
            <i class="fas fa-folder-open"></i><span>Files</span></a>
        <a href="<?= e(BASE_URL) ?>/developer/progress.php" class="nav-item <?= $current === 'progress.php' ? 'active' : '' ?>">
            <i class="fas fa-chart-line"></i><span>Progress</span></a>
        <a href="<?= e(BASE_URL) ?>/developer/email.php" class="nav-item <?= $current === 'email.php' ? 'active' : '' ?>">
            <i class="fas fa-envelope"></i><span>Email</span></a>
        <a href="<?= e(BASE_URL) ?>/developer/time_tracking.php" class="nav-item <?= $current === 'time_tracking.php' ? 'active' : '' ?>">
            <i class="fas fa-clock"></i><span>Time Tracking</span></a>
        <a href="<?= e(BASE_URL) ?>/developer/profile.php" class="nav-item <?= $current === 'profile.php' ? 'active' : '' ?>">
            <i class="fas fa-user"></i><span>Profile</span></a>
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
                            return '<a href="' + r.url + '" class="d-block px-3 py-2 text-decoration-none border-bottom" style="font-size:13px;color:#333">'
                                + '<strong>' + r.label + '</strong>'
                                + (r.meta ? '<span class="text-muted ms-2">' + r.meta + '</span>' : '')
                                + '<span class="badge bg-secondary float-end">' + r.type + '</span>'
                                + '</a>';
                        }).join('');
                    }
                    results.classList.remove('d-none');
                }).catch(function() { results.classList.add('d-none'); });
        }, 300);
    });
    document.addEventListener('click', function(e) {
        if (!document.getElementById('globalSearchWrap').contains(e.target)) results.classList.add('d-none');
    });
})();
</script>
