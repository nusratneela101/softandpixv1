<?php
/**
 * Admin — Advanced Reports & Analytics
 */
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
require_once dirname(__DIR__) . '/includes/language.php';
requireAuth();

$page_title = __('reports');
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

require_once 'includes/header.php';
?>
<div class="page-header">
    <h1><i class="fas fa-chart-bar me-2"></i><?= __('reports') ?></h1>
    <p><?= __('overview') ?> &amp; <?= __('invoice_analytics') ?></p>
</div>
<div class="container-fluid">
    <!-- Date Range Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label fw-semibold"><?= __('start_date') ?></label>
                    <input type="date" name="from" class="form-control" value="<?= h($from) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-semibold"><?= __('end_date') ?></label>
                    <input type="date" name="to" class="form-control" value="<?= h($to) ?>">
                </div>
                <div class="col-md-2">
                    <button class="btn btn-primary w-100"><?= __('filter') ?></button>
                </div>
                <div class="col-md-2">
                    <a href="?export=csv&from=<?= urlencode($from) ?>&to=<?= urlencode($to) ?>" class="btn btn-outline-success w-100">
                        <i class="fas fa-file-csv me-1"></i><?= __('export_csv') ?>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- CSV Export -->
    <?php if (isset($_GET['export']) && $_GET['export'] === 'csv'): ?>
    <?php
    $rows = [];
    try {
        $stmt = $pdo->prepare("SELECT DATE_FORMAT(created_at,'%Y-%m') as month, SUM(amount) as revenue FROM invoices WHERE status='paid' AND created_at BETWEEN ? AND ? GROUP BY month ORDER BY month");
        $stmt->execute([$from . ' 00:00:00', $to . ' 23:59:59']);
        $rows = $stmt->fetchAll();
    } catch (Exception $e) {}
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="report_' . date('Ymd') . '.csv"');
    echo "Month,Revenue\n";
    foreach ($rows as $r) {
        echo h($r['month']) . ',' . number_format((float)$r['revenue'], 2) . "\n";
    }
    exit;
    ?>
    <?php endif; ?>

    <!-- Charts Row 1 -->
    <div class="row g-4 mb-4">
        <!-- Revenue Chart -->
        <div class="col-lg-8">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-dollar-sign me-2 text-success"></i><?= __('revenue_report') ?>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="loadRevenue('monthly')"><?= __('monthly') ?></button>
                        <button class="btn btn-sm btn-outline-primary me-1" onclick="loadRevenue('quarterly')"><?= __('quarterly') ?></button>
                        <button class="btn btn-sm btn-outline-primary" onclick="loadRevenue('yearly')"><?= __('yearly') ?></button>
                    </div>
                    <div class="chart-wrapper"><canvas id="revenueChart"></canvas></div>
                </div>
            </div>
        </div>
        <!-- Project Status Pie -->
        <div class="col-lg-4">
            <div class="card h-100">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-project-diagram me-2 text-primary"></i><?= __('project_stats') ?>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper"><canvas id="projectPieChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row 2 -->
    <div class="row g-4 mb-4">
        <!-- Developer Performance -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-code me-2 text-info"></i><?= __('developer_performance') ?>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper"><canvas id="devPerfChart"></canvas></div>
                </div>
            </div>
        </div>
        <!-- Invoice Analytics -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-file-invoice me-2 text-warning"></i><?= __('invoice_analytics') ?>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper"><canvas id="invoiceDonutChart"></canvas></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Task Analytics -->
    <div class="row g-4 mb-4">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-clipboard-list me-2 text-secondary"></i><?= __('task_analytics') ?>
                </div>
                <div class="card-body">
                    <div class="chart-wrapper"><canvas id="taskPriorityChart"></canvas></div>
                </div>
            </div>
        </div>
        <!-- Client Overview Table -->
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header bg-white fw-bold">
                    <i class="fas fa-users me-2 text-success"></i><?= __('client_overview') ?>
                </div>
                <div class="card-body p-0">
                    <div id="clientTableWrap">
                        <div class="text-center py-4"><div class="spinner-border text-primary"></div></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
var FROM = '<?= h($from) ?>';
var TO   = '<?= h($to) ?>';
var revenueChart, projectPieChart, devPerfChart, invoiceDonutChart, taskPriorityChart;

function loadRevenue(period) {
    fetch('/api/reports.php?type=revenue&period=' + period + '&from=' + FROM + '&to=' + TO, {credentials:'same-origin'})
        .then(r => r.json()).then(data => {
            if (!data.success) return;
            if (revenueChart) revenueChart.destroy();
            var ctx = document.getElementById('revenueChart').getContext('2d');
            revenueChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: '<?= __('total_revenue') ?>',
                        data: data.values,
                        backgroundColor: 'rgba(102,126,234,0.7)',
                        borderColor: '#667eea',
                        borderWidth: 1,
                        borderRadius: 4,
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        });
}

function loadProjectPie() {
    fetch('/api/reports.php?type=projects&status=all', {credentials:'same-origin'})
        .then(r => r.json()).then(data => {
            if (!data.success) return;
            if (projectPieChart) projectPieChart.destroy();
            var ctx = document.getElementById('projectPieChart').getContext('2d');
            projectPieChart = new Chart(ctx, {
                type: 'doughnut',
                data: { labels: data.labels, datasets: [{ data: data.values, backgroundColor: ['#667eea','#28a745','#ffc107','#dc3545','#17a2b8'], borderWidth: 2 }] },
                options: { responsive: true, maintainAspectRatio: false }
            });
        });
}

function loadDevPerf() {
    fetch('/api/reports.php?type=developers&metric=tasks_completed&from=' + FROM + '&to=' + TO, {credentials:'same-origin'})
        .then(r => r.json()).then(data => {
            if (!data.success) return;
            if (devPerfChart) devPerfChart.destroy();
            var ctx = document.getElementById('devPerfChart').getContext('2d');
            devPerfChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{ label: '<?= __('tasks') ?>', data: data.values, backgroundColor: 'rgba(40,167,69,0.7)', borderColor: '#28a745', borderWidth: 1, borderRadius: 4 }]
                },
                options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } }
            });
        });
}

function loadInvoiceDonut() {
    fetch('/api/reports.php?type=invoices&status=all&from=' + FROM + '&to=' + TO, {credentials:'same-origin'})
        .then(r => r.json()).then(data => {
            if (!data.success) return;
            if (invoiceDonutChart) invoiceDonutChart.destroy();
            var ctx = document.getElementById('invoiceDonutChart').getContext('2d');
            invoiceDonutChart = new Chart(ctx, {
                type: 'doughnut',
                data: { labels: data.labels, datasets: [{ data: data.values, backgroundColor: ['#28a745','#ffc107','#dc3545'], borderWidth: 2 }] },
                options: { responsive: true, maintainAspectRatio: false }
            });
        });
}

function loadTaskPriority() {
    fetch('/api/reports.php?type=tasks&groupby=priority', {credentials:'same-origin'})
        .then(r => r.json()).then(data => {
            if (!data.success) return;
            if (taskPriorityChart) taskPriorityChart.destroy();
            var ctx = document.getElementById('taskPriorityChart').getContext('2d');
            taskPriorityChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: data.labels,
                    datasets: [{ label: '<?= __('tasks') ?>', data: data.values, backgroundColor: ['#28a745','#ffc107','#fd7e14','#dc3545'], borderRadius: 4 }]
                },
                options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
            });
        });
}

function loadClientTable() {
    fetch('/api/reports.php?type=clients&from=' + FROM + '&to=' + TO, {credentials:'same-origin'})
        .then(r => r.json()).then(data => {
            if (!data.success || !data.rows.length) {
                document.getElementById('clientTableWrap').innerHTML = '<div class="p-3 text-muted"><?= __('no_records') ?></div>';
                return;
            }
            var html = '<table class="table table-hover table-sm mb-0"><thead><tr><th><?= __('client') ?></th><th><?= __('projects') ?></th><th><?= __('total_revenue') ?></th></tr></thead><tbody>';
            data.rows.forEach(function(r) {
                html += '<tr><td>' + r.name + '</td><td>' + r.projects + '</td><td>$' + parseFloat(r.revenue).toFixed(2) + '</td></tr>';
            });
            html += '</tbody></table>';
            document.getElementById('clientTableWrap').innerHTML = html;
        });
}

document.addEventListener('DOMContentLoaded', function() {
    loadRevenue('monthly');
    loadProjectPie();
    loadDevPerf();
    loadInvoiceDonut();
    loadTaskPriority();
    loadClientTable();
});
</script>

<?php require_once 'includes/footer.php'; ?>
