<?php
/**
 * Reusable progress widget
 * Usage: include once Chart.js CDN has been loaded.
 * Variables expected:
 *   $project  — project row with progress_percent
 *   $widgetId — unique string for canvas IDs (default: 'pw_' . project id)
 *   $taskCounts (optional) — ['todo'=>n,'in_progress'=>n,'review'=>n,'completed'=>n,'total'=>n]
 */
if (!isset($project)) return;

$_pct      = (int)($project['progress_percent'] ?? $project['progress'] ?? 0);
$_wid      = $widgetId ?? ('pw_' . (int)($project['id'] ?? 0));
$_total    = isset($taskCounts) ? (int)$taskCounts['total']     : 0;
$_done     = isset($taskCounts) ? (int)$taskCounts['completed'] : 0;
$_inprog   = isset($taskCounts) ? (int)$taskCounts['in_progress'] : 0;
$_review   = isset($taskCounts) ? (int)$taskCounts['review']    : 0;
$_todo     = isset($taskCounts) ? (int)$taskCounts['todo']      : 0;
?>
<div class="progress-widget card border-0 shadow-sm">
    <div class="card-body text-center">
        <div style="max-width:160px;margin:0 auto;">
            <canvas id="canvas_<?php echo h($_wid); ?>" width="160" height="160"></canvas>
        </div>
        <div class="mt-2">
            <span class="fs-3 fw-bold text-primary"><?php echo $_pct; ?>%</span>
            <div class="text-muted small">Overall Progress</div>
        </div>
        <?php if ($_total > 0): ?>
        <div class="mt-3">
            <div class="d-flex justify-content-between small mb-1">
                <span class="text-muted">Tasks</span>
                <span class="fw-semibold"><?php echo $_done; ?>/<?php echo $_total; ?> done</span>
            </div>
            <div class="progress" style="height:8px;">
                <div class="progress-bar bg-success"    style="width:<?php echo $_total ? round($_done/_total*100) : 0; ?>%"></div>
                <div class="progress-bar bg-warning"    style="width:<?php echo $_total ? round($_review/$_total*100) : 0; ?>%"></div>
                <div class="progress-bar bg-primary"    style="width:<?php echo $_total ? round($_inprog/$_total*100) : 0; ?>%"></div>
            </div>
            <div class="d-flex justify-content-between mt-2">
                <span class="badge bg-secondary" title="Todo"><?php echo $_todo; ?> todo</span>
                <span class="badge bg-primary"   title="In Progress"><?php echo $_inprog; ?> active</span>
                <span class="badge bg-warning text-dark" title="Review"><?php echo $_review; ?> review</span>
                <span class="badge bg-success"   title="Done"><?php echo $_done; ?> done</span>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<script>
(function(){
    var ctx = document.getElementById('canvas_<?php echo h($_wid); ?>');
    if (!ctx || typeof Chart === 'undefined') return;
    new Chart(ctx, {
        type: 'doughnut',
        data: {
            datasets: [{
                data: [<?php echo $_pct; ?>, <?php echo 100 - $_pct; ?>],
                backgroundColor: ['#0d6efd', '#e9ecef'],
                borderWidth: 0
            }]
        },
        options: {
            cutout: '75%',
            plugins: { legend: { display: false }, tooltip: { enabled: false } },
            animation: { duration: 800 }
        }
    });
})();
</script>
