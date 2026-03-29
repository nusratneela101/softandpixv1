<?php
/**
 * Reusable Global Search Modal (Ctrl+K)
 *
 * Include this file in any page to add the Ctrl+K search modal.
 * Requires Bootstrap 5 and Font Awesome.
 */
?>
<!-- Search Modal (Ctrl+K) -->
<div class="modal fade" id="searchModal" tabindex="-1" aria-label="Global Search" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content border-0 shadow-lg">
      <div class="modal-header border-0 pb-0">
        <div class="input-group">
          <span class="input-group-text border-0 bg-transparent ps-0">
            <i class="fas fa-search text-muted"></i>
          </span>
          <input type="text" id="searchModalInput" class="form-control border-0 fs-5 shadow-none"
                 placeholder="Search projects, tasks, users, invoices…" autocomplete="off">
          <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal" title="Close (Esc)">
            <kbd>Esc</kbd>
          </button>
        </div>
      </div>
      <hr class="my-0">
      <div class="modal-body pt-2 px-3" id="searchModalBody" style="min-height:120px;max-height:480px;overflow-y:auto">
        <div id="searchModalRecent" class="py-2">
          <p class="text-muted small text-center pt-3">Start typing to search…</p>
        </div>
        <div id="searchModalResults" class="d-none"></div>
        <div id="searchModalLoading" class="text-center py-4 d-none">
          <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
          <span class="ms-2 text-muted small">Searching…</span>
        </div>
      </div>
      <div class="modal-footer border-0 pt-0 pb-2 justify-content-start">
        <small class="text-muted"><kbd>↑</kbd><kbd>↓</kbd> navigate &nbsp; <kbd>Enter</kbd> open &nbsp; <kbd>Esc</kbd> close</small>
        <a href="<?= e(BASE_URL) ?>/search/" class="ms-auto text-muted small text-decoration-none">Advanced search →</a>
      </div>
    </div>
  </div>
</div>

<script>
(function () {
    'use strict';

    var modal       = null;
    var input       = null;
    var resultsEl   = null;
    var recentEl    = null;
    var loadingEl   = null;
    var debounceTimer = null;
    var selectedIdx = -1;
    var currentItems = [];

    document.addEventListener('DOMContentLoaded', function () {
        var el = document.getElementById('searchModal');
        if (!el) return;
        modal     = new bootstrap.Modal(el);
        input     = document.getElementById('searchModalInput');
        resultsEl = document.getElementById('searchModalResults');
        recentEl  = document.getElementById('searchModalRecent');
        loadingEl = document.getElementById('searchModalLoading');

        // Ctrl+K / Cmd+K
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                modal.show();
            }
        });

        el.addEventListener('shown.bs.modal', function () { input.focus(); });
        el.addEventListener('hidden.bs.modal', function () {
            input.value = '';
            clearResults();
        });

        input.addEventListener('input', function () {
            clearTimeout(debounceTimer);
            var q = input.value.trim();
            if (q.length < 2) { clearResults(); return; }
            debounceTimer = setTimeout(function () { doSearch(q); }, 250);
        });

        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') { e.preventDefault(); moveSel(1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); moveSel(-1); }
            else if (e.key === 'Enter') { e.preventDefault(); openSelected(); }
        });
    });

    function clearResults() {
        resultsEl.innerHTML = '';
        resultsEl.classList.add('d-none');
        recentEl.classList.remove('d-none');
        selectedIdx = -1;
        currentItems = [];
    }

    function doSearch(q) {
        loadingEl.classList.remove('d-none');
        recentEl.classList.add('d-none');
        resultsEl.classList.add('d-none');

        fetch('/api/search.php?q=' + encodeURIComponent(q), {credentials: 'same-origin'})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                loadingEl.classList.add('d-none');
                if (!data.success) { showEmpty(q); return; }
                renderResults(q, data.results || []);
            })
            .catch(function () {
                loadingEl.classList.add('d-none');
                showEmpty(q);
            });
    }

    function renderResults(q, items) {
        currentItems = items;
        selectedIdx = -1;

        if (!items.length) { showEmpty(q); return; }

        var html = '<ul class="list-unstyled mb-0">';
        items.forEach(function (item, i) {
            html += '<li><a href="' + escHtml(item.url) + '" data-idx="' + i + '" ' +
                'class="search-result-item d-flex align-items-center gap-3 px-3 py-2 rounded text-decoration-none text-dark hover-bg">' +
                '<span class="text-primary small"><i class="' + escHtml(iconFor(item.type)) + '"></i></span>' +
                '<span class="flex-grow-1 overflow-hidden">' +
                '<span class="fw-semibold d-block text-truncate">' + escHtml(item.label) + '</span>' +
                '<span class="small text-muted">' + escHtml(item.meta || '') + '</span>' +
                '</span>' +
                '<span class="badge bg-light text-muted border small">' + escHtml(item.type) + '</span>' +
                '</a></li>';
        });
        html += '</ul>';
        html += '<div class="px-3 pt-2 pb-1 border-top"><a href="/search/?q=' + encodeURIComponent(q) + '" class="small text-primary text-decoration-none">View all results for "' + escHtml(q) + '" →</a></div>';

        resultsEl.innerHTML = html;
        resultsEl.classList.remove('d-none');

        resultsEl.querySelectorAll('.search-result-item').forEach(function (el) {
            el.addEventListener('mouseenter', function () {
                selectedIdx = parseInt(el.dataset.idx, 10);
                updateSel();
            });
        });
    }

    function showEmpty(q) {
        resultsEl.innerHTML = '<div class="text-center py-4 text-muted"><i class="fas fa-search-minus fa-2x mb-2 opacity-25"></i><p class="small">No results for "' + escHtml(q) + '"</p><a href="/search/?q=' + encodeURIComponent(q) + '" class="small text-primary">Try advanced search →</a></div>';
        resultsEl.classList.remove('d-none');
    }

    function moveSel(dir) {
        if (!currentItems.length) return;
        selectedIdx = Math.min(Math.max(selectedIdx + dir, 0), currentItems.length - 1);
        updateSel();
    }

    function updateSel() {
        resultsEl.querySelectorAll('.search-result-item').forEach(function (el, i) {
            el.classList.toggle('bg-primary', i === selectedIdx);
            el.classList.toggle('bg-opacity-10', i === selectedIdx);
        });
    }

    function openSelected() {
        if (selectedIdx >= 0 && currentItems[selectedIdx]) {
            window.location.href = currentItems[selectedIdx].url;
        } else if (input.value.trim().length >= 2) {
            window.location.href = '/search/?q=' + encodeURIComponent(input.value.trim());
        }
    }

    function iconFor(type) {
        var icons = {project: 'fas fa-project-diagram', task: 'fas fa-clipboard-list', user: 'fas fa-user', invoice: 'fas fa-file-invoice-dollar'};
        return icons[type] || 'fas fa-file';
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }
}());
</script>
<style>
.search-result-item:hover { background: rgba(var(--bs-primary-rgb),.07); }
</style>
