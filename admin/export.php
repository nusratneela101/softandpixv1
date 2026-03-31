<?php
/**
 * Admin Export Dashboard
 * Choose data type, filters, format — then download.
 */
require_once dirname(__DIR__) . '/config/db.php';
require_once 'includes/auth.php';
requireAuth();
require_once dirname(__DIR__) . '/includes/export_helper.php';
ensureExportTables($pdo);

$csrf_token = generateCsrfToken();

// Recent export history
try {
    $history = $pdo->query(
        "SELECT eh.*, u.name AS user_name FROM export_history eh
         LEFT JOIN users u ON u.id = eh.user_id
         ORDER BY eh.created_at DESC LIMIT 20"
    )->fetchAll();
} catch (Exception $e) {
    $history = [];
}

$dataTypes = getExportableDataTypes();

require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center">
    <div>
        <h1><i class="bi bi-download me-2"></i>Export Data</h1>
    </div>
    <div>
        <span class="text-muted small"><i class="bi bi-file-earmark-arrow-down me-1"></i>CSV / Excel / PDF</span>
    </div>
</div>
<div class="container-fluid">
<div class="row g-4">
  <!-- Export Form -->
  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold"><i class="bi bi-file-earmark-arrow-down me-2 text-primary"></i>Generate Export</div>
      <div class="card-body">
        <form id="exportForm" action="/api/export.php" method="POST">
          <input type="hidden" name="csrf_token" value="<?= h($csrf_token) ?>">

          <div class="mb-3">
            <label class="form-label fw-semibold">Data Type</label>
            <select name="data_type" id="dataType" class="form-select" required>
              <option value="">— Select data type —</option>
              <?php foreach ($dataTypes as $key => $info): ?>
              <option value="<?= h($key) ?>"><?= h(is_array($info) ? $info['label'] : $info) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Export Format</label>
            <div class="d-flex gap-3">
              <div class="form-check">
                <input class="form-check-input" type="radio" name="export_type" id="fmtCsv" value="csv" checked>
                <label class="form-check-label" for="fmtCsv"><i class="fas fa-file-csv me-1 text-success"></i>CSV</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="export_type" id="fmtExcel" value="excel">
                <label class="form-check-label" for="fmtExcel"><i class="fas fa-file-excel me-1 text-success"></i>Excel</label>
              </div>
              <div class="form-check">
                <input class="form-check-input" type="radio" name="export_type" id="fmtPdf" value="pdf">
                <label class="form-check-label" for="fmtPdf"><i class="fas fa-file-pdf me-1 text-danger"></i>PDF</label>
              </div>
            </div>
          </div>

          <!-- Date filters -->
          <div class="mb-3">
            <label class="form-label fw-semibold">Date Range <span class="text-muted fw-normal">(optional)</span></label>
            <div class="row g-2">
              <div class="col-6"><input type="date" name="date_from" class="form-control" placeholder="From"></div>
              <div class="col-6"><input type="date" name="date_to" class="form-control" placeholder="To"></div>
            </div>
          </div>

          <!-- Status filter (shown for relevant types) -->
          <div class="mb-3" id="statusFilterRow">
            <label class="form-label fw-semibold">Status <span class="text-muted fw-normal">(optional)</span></label>
            <select name="status" class="form-select">
              <option value="">All statuses</option>
              <option value="active">Active</option>
              <option value="completed">Completed</option>
              <option value="pending">Pending</option>
              <option value="cancelled">Cancelled</option>
              <option value="paid">Paid</option>
              <option value="unpaid">Unpaid</option>
              <option value="overdue">Overdue</option>
            </select>
          </div>

          <div class="d-grid gap-2">
            <button type="submit" class="btn btn-primary" id="exportBtn">
              <i class="fas fa-download me-2"></i>Download Export
            </button>
            <button type="button" class="btn btn-outline-secondary" id="previewBtn">
              <i class="fas fa-eye me-2"></i>Preview (first 10 rows)
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Preview area -->
    <div id="previewArea" class="mt-3 d-none">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i class="fas fa-table me-2 text-info"></i>Preview</div>
        <div class="card-body p-0">
          <div class="table-responsive" style="max-height:320px;overflow-y:auto;">
            <table class="table table-sm table-hover mb-0" id="previewTable"></table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Export History -->
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold"><i class="fas fa-history me-2 text-secondary"></i>Export History</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light"><tr><th>Type</th><th>Format</th><th>Records</th><th>Size</th><th>By</th><th>Date</th><th></th></tr></thead>
          <tbody>
          <?php if (empty($history)): ?>
            <tr><td colspan="7" class="text-center text-muted py-4">No exports yet.</td></tr>
          <?php else: foreach ($history as $h): ?>
            <tr>
              <td><span class="badge bg-light text-dark border"><?= h(ucfirst(str_replace('_', ' ', $h['data_type']))) ?></span></td>
              <td><span class="badge bg-<?= $h['export_type'] === 'pdf' ? 'danger' : 'success' ?>"><?= strtoupper(h($h['export_type'])) ?></span></td>
              <td><?= number_format((int)$h['record_count']) ?></td>
              <td><?= formatFileSize((int)$h['file_size']) ?></td>
              <td><?= h($h['user_name'] ?? '—') ?></td>
              <td><?= date('M j, Y H:i', strtotime($h['created_at'])) ?></td>
              <td>
                <?php if (!empty($h['file_path']) && file_exists(dirname(__DIR__) . '/' . ltrim($h['file_path'], '/'))): ?>
                  <a href="<?= h('/' . ltrim($h['file_path'], '/')) ?>" class="btn btn-xs btn-outline-primary" download>
                    <i class="bi bi-download"></i>
                  </a>
                <?php else: ?>
                  <span class="text-muted small">expired</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
</div>

<script>
document.getElementById('exportForm').addEventListener('submit', function(e) {
    var btn = document.getElementById('exportBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';
    setTimeout(function() { btn.disabled = false; btn.innerHTML = '<i class="bi bi-download me-2"></i>Download Export'; }, 8000);
});

document.getElementById('previewBtn').addEventListener('click', function() {
    var form = document.getElementById('exportForm');
    var dataType = document.getElementById('dataType').value;
    if (!dataType) { alert('Please select a data type first.'); return; }
    var fd = new FormData(form);
    fd.set('preview', '1');
    fd.set('export_type', 'json');
    fetch('/api/export.php', {method: 'POST', body: fd, credentials: 'same-origin'})
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var area = document.getElementById('previewArea');
            var tbl  = document.getElementById('previewTable');
            if (!data.success || !data.rows || !data.rows.length) {
                tbl.innerHTML = '<tbody><tr><td class="text-muted py-3 text-center">No data found.</td></tr></tbody>';
            } else {
                var heads = Object.keys(data.rows[0]);
                var th = '<thead class="table-light"><tr>' + heads.map(function(h) { return '<th>' + h + '</th>'; }).join('') + '</tr></thead>';
                var rows = data.rows.map(function(r) {
                    return '<tr>' + heads.map(function(h) { return '<td>' + (r[h] ?? '') + '</td>'; }).join('') + '</tr>';
                }).join('');
                tbl.innerHTML = th + '<tbody>' + rows + '</tbody>';
            }
            area.classList.remove('d-none');
        })
        .catch(function() { alert('Preview failed.'); });
});
</script>
<?php require_once 'includes/footer.php'; ?>
