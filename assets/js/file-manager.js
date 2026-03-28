/**
 * File Manager JavaScript — Softandpix
 * Drag-drop upload, progress bars, selection, view toggle, context menu, modals
 */
(function () {
    'use strict';

    // ── State ────────────────────────────────────────────────
    let currentProjectId = null;
    let currentFolderId  = null;
    let selectedItems    = new Set();   // "file-{id}" or "folder-{id}"
    let viewMode         = 'grid';      // 'grid' | 'list'
    let ctxTarget        = null;        // current context menu target
    let csrfToken        = '';

    // ── Init ─────────────────────────────────────────────────
    function init(projectId, folderId, csrf) {
        currentProjectId = projectId;
        currentFolderId  = folderId || null;
        csrfToken        = csrf;

        setupDropzone();
        setupFileInput();
        setupViewToggle();
        setupSelectAll();
        setupSearch();
        setupContextMenu();
        setupKeyboard();
        setupCardClicks();
    }

    // ── Dropzone ─────────────────────────────────────────────
    function setupDropzone() {
        const zone = document.getElementById('fm-dropzone');
        if (!zone) return;

        zone.addEventListener('dragover',  function (e) { e.preventDefault(); zone.classList.add('dragover'); });
        zone.addEventListener('dragleave', function ()  { zone.classList.remove('dragover'); });
        zone.addEventListener('drop',      function (e) {
            e.preventDefault();
            zone.classList.remove('dragover');
            uploadFiles(Array.from(e.dataTransfer.files));
        });
        zone.addEventListener('click', function () {
            document.getElementById('fm-file-input').click();
        });
    }

    function setupFileInput() {
        const input = document.getElementById('fm-file-input');
        if (!input) return;
        input.addEventListener('change', function () {
            uploadFiles(Array.from(this.files));
            this.value = '';
        });
    }

    // ── Upload ───────────────────────────────────────────────
    function uploadFiles(files) {
        if (!files.length) return;

        const progressWrap = document.getElementById('fm-upload-progress');
        progressWrap.classList.add('active');
        progressWrap.innerHTML = '';

        files.forEach(function (file) {
            const itemId = 'upload-' + Date.now() + '-' + Math.random().toString(36).substr(2, 5);
            const el = document.createElement('div');
            el.className = 'upload-item';
            el.id = itemId;
            el.innerHTML =
                '<span class="upload-name" title="' + escHtml(file.name) + '">' + escHtml(file.name) + '</span>' +
                '<span class="upload-size">' + formatSize(file.size) + '</span>' +
                '<div class="upload-bar-wrap"><div class="upload-bar" style="width:0%"></div></div>' +
                '<span class="upload-status">0%</span>';
            progressWrap.appendChild(el);

            const fd = new FormData();
            fd.append('project_id', currentProjectId);
            if (currentFolderId) fd.append('folder_id', currentFolderId);
            fd.append('csrf_token', csrfToken);
            fd.append('file', file);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', getBase() + '/api/files/upload.php', true);

            xhr.upload.addEventListener('progress', function (e) {
                if (e.lengthComputable) {
                    const pct = Math.round((e.loaded / e.total) * 100);
                    el.querySelector('.upload-bar').style.width = pct + '%';
                    el.querySelector('.upload-status').textContent = pct + '%';
                }
            });

            xhr.addEventListener('load', function () {
                try {
                    const res = JSON.parse(xhr.responseText);
                    if (res.success) {
                        el.querySelector('.upload-bar').style.width = '100%';
                        el.querySelector('.upload-bar').style.background = '#22c55e';
                        el.querySelector('.upload-status').textContent = '✓ Done';
                        el.querySelector('.upload-status').className = 'upload-status done';
                        // Prepend new file card to grid/list
                        insertFileCard(res.file);
                    } else {
                        el.querySelector('.upload-status').textContent = '✗ ' + (res.error || 'Failed');
                        el.querySelector('.upload-status').className = 'upload-status error';
                    }
                } catch (err) {
                    el.querySelector('.upload-status').textContent = '✗ Error';
                    el.querySelector('.upload-status').className = 'upload-status error';
                }
            });

            xhr.addEventListener('error', function () {
                el.querySelector('.upload-status').textContent = '✗ Network error';
                el.querySelector('.upload-status').className = 'upload-status error';
            });

            xhr.send(fd);
        });
    }

    function insertFileCard(file) {
        const container = document.getElementById('fm-items-container');
        if (!container) return;

        const empty = container.querySelector('.fm-empty');
        if (empty) empty.remove();

        const ext = (file.file_extension || '').toLowerCase();
        const icon = getFileIcon(ext, file.mime_type);
        const isImage = file.mime_type && file.mime_type.startsWith('image/');

        if (viewMode === 'grid') {
            const card = document.createElement('div');
            card.className = 'fm-card';
            card.dataset.id = file.id;
            card.dataset.type = 'file';
            card.innerHTML =
                '<input type="checkbox" class="fm-checkbox form-check-input" data-id="' + file.id + '" data-type="file">' +
                (isImage
                    ? '<img src="' + getBase() + '/api/files/preview.php?file_id=' + file.id + '" class="fm-thumb" alt="">'
                    : '<i class="' + icon.cls + ' fm-icon ' + icon.color + '"></i>') +
                '<div class="fm-name">' + escHtml(file.original_name) + '</div>' +
                '<div class="fm-meta">' + formatSize(file.file_size) +
                (file.version > 1 ? ' <span class="version-badge">v' + file.version + '</span>' : '') + '</div>' +
                '<div class="fm-actions">' +
                '  <a href="' + getBase() + '/api/files/download.php?file_id=' + file.id + '" class="btn btn-sm btn-outline-primary" title="Download" onclick="event.stopPropagation()"><i class="bi bi-download"></i></a>' +
                '</div>';
            container.prepend(card);
        } else {
            const row = document.createElement('div');
            row.className = 'fm-list-row';
            row.dataset.id = file.id;
            row.dataset.type = 'file';
            row.innerHTML =
                '<input type="checkbox" class="fm-checkbox form-check-input me-2" data-id="' + file.id + '" data-type="file">' +
                '<i class="' + icon.cls + ' list-icon ' + icon.color + '"></i>' +
                '<span class="list-name">' + escHtml(file.original_name) + '</span>' +
                '<span class="list-meta">' + formatSize(file.file_size) + '</span>' +
                '<span class="list-meta">' + 'Just now' + '</span>' +
                '<div class="list-actions">' +
                '  <a href="' + getBase() + '/api/files/download.php?file_id=' + file.id + '" class="btn btn-sm btn-outline-primary" title="Download" onclick="event.stopPropagation()"><i class="bi bi-download"></i></a>' +
                '</div>';
            container.prepend(row);
        }

        setupCardClicks();
    }

    // ── View Toggle ──────────────────────────────────────────
    function setupViewToggle() {
        const btnGrid = document.getElementById('btn-view-grid');
        const btnList = document.getElementById('btn-view-list');
        const container = document.getElementById('fm-items-container');
        if (!btnGrid || !btnList || !container) return;

        btnGrid.addEventListener('click', function () {
            viewMode = 'grid';
            container.className = 'fm-grid';
            btnGrid.classList.add('active');
            btnList.classList.remove('active');
        });
        btnList.addEventListener('click', function () {
            viewMode = 'list';
            container.className = 'fm-list';
            btnList.classList.add('active');
            btnGrid.classList.remove('active');
        });
    }

    // ── Card Clicks (selection) ───────────────────────────────
    function setupCardClicks() {
        document.querySelectorAll('.fm-card, .fm-list-row').forEach(function (card) {
            if (card.dataset.clickBound) return;
            card.dataset.clickBound = '1';

            card.addEventListener('click', function (e) {
                // Don't select if clicking action buttons or links
                if (e.target.closest('a') || e.target.closest('.fm-actions') || e.target.closest('.list-actions')) return;

                const type = card.dataset.type;
                const id   = card.dataset.id;
                const key  = type + '-' + id;

                if (type === 'folder' && !e.ctrlKey && !e.metaKey && !e.shiftKey) {
                    // Navigate into folder
                    window.location.href = window.location.pathname + '?project_id=' + currentProjectId + '&folder_id=' + id;
                    return;
                }

                if (e.ctrlKey || e.metaKey) {
                    // Toggle selection
                    card.classList.toggle('selected');
                    if (card.classList.contains('selected')) selectedItems.add(key);
                    else selectedItems.delete(key);
                } else {
                    // Single select
                    clearSelection();
                    card.classList.add('selected');
                    selectedItems.add(key);
                }

                const cb = card.querySelector('.fm-checkbox');
                if (cb) cb.checked = card.classList.contains('selected');

                updateBulkBar();
            });

            // Double-click opens file details modal
            card.addEventListener('dblclick', function (e) {
                if (e.target.closest('a')) return;
                const type = card.dataset.type;
                const id   = card.dataset.id;
                if (type === 'file') openDetailsModal(id);
            });
        });
    }

    function clearSelection() {
        selectedItems.clear();
        document.querySelectorAll('.fm-card.selected, .fm-list-row.selected').forEach(function (el) {
            el.classList.remove('selected');
            const cb = el.querySelector('.fm-checkbox');
            if (cb) cb.checked = false;
        });
    }

    function updateBulkBar() {
        const bar = document.getElementById('fm-bulk-bar');
        if (!bar) return;
        const count = selectedItems.size;
        if (count > 0) {
            bar.classList.remove('d-none');
            const countEl = bar.querySelector('#bulk-count');
            if (countEl) countEl.textContent = count;
        } else {
            bar.classList.add('d-none');
        }
    }

    // ── Select All ───────────────────────────────────────────
    function setupSelectAll() {
        const btn = document.getElementById('btn-select-all');
        if (!btn) return;
        btn.addEventListener('click', function () {
            const cards = document.querySelectorAll('.fm-card, .fm-list-row');
            const allSelected = selectedItems.size === cards.length;
            cards.forEach(function (card) {
                if (allSelected) {
                    card.classList.remove('selected');
                    selectedItems.delete(card.dataset.type + '-' + card.dataset.id);
                } else {
                    card.classList.add('selected');
                    selectedItems.add(card.dataset.type + '-' + card.dataset.id);
                }
                const cb = card.querySelector('.fm-checkbox');
                if (cb) cb.checked = !allSelected;
            });
            updateBulkBar();
        });
    }

    // ── Search ───────────────────────────────────────────────
    function setupSearch() {
        const input = document.getElementById('fm-search');
        if (!input) return;
        input.addEventListener('input', function () {
            const q = this.value.trim().toLowerCase();
            document.querySelectorAll('.fm-card, .fm-list-row').forEach(function (card) {
                const nameEl = card.querySelector('.fm-name, .list-name');
                if (!nameEl) return;
                const name = nameEl.textContent.toLowerCase();
                card.style.display = (!q || name.includes(q)) ? '' : 'none';
            });
        });
    }

    // ── Context Menu ─────────────────────────────────────────
    function setupContextMenu() {
        const menu = document.getElementById('fm-context-menu');
        if (!menu) return;

        document.addEventListener('contextmenu', function (e) {
            const card = e.target.closest('.fm-card, .fm-list-row');
            if (!card) { hideContextMenu(); return; }
            e.preventDefault();
            ctxTarget = card;

            // Select if not selected
            if (!card.classList.contains('selected')) {
                clearSelection();
                card.classList.add('selected');
                selectedItems.add(card.dataset.type + '-' + card.dataset.id);
                updateBulkBar();
            }

            const isFile   = card.dataset.type === 'file';
            const isFolder = card.dataset.type === 'folder';

            menu.innerHTML = '';
            if (isFile) {
                addCtxItem(menu, 'bi bi-download',          'Download',        function () { window.location.href = getBase() + '/api/files/download.php?file_id=' + card.dataset.id; });
                addCtxItem(menu, 'bi bi-eye',               'Preview',         function () { window.open(getBase() + '/api/files/preview.php?file_id=' + card.dataset.id, '_blank'); });
                addCtxItem(menu, 'bi bi-info-circle',       'Details',         function () { openDetailsModal(card.dataset.id); });
                addCtxItem(menu, 'bi bi-clock-history',     'Version History', function () { openVersionsModal(card.dataset.id); });
                addCtxItem(menu, 'bi bi-chat-left-text',    'Comments',        function () { openCommentsModal(card.dataset.id); });
                addCtxItem(menu, 'bi bi-folder-symlink',    'Move',            function () { openMoveModal('file', card.dataset.id); });
                menu.appendChild(Object.assign(document.createElement('div'), { className: 'ctx-sep' }));
                addCtxItem(menu, 'bi bi-trash',             'Delete',          function () { deleteItem('file', card.dataset.id); }, 'ctx-danger');
            }
            if (isFolder) {
                addCtxItem(menu, 'bi bi-folder2-open',      'Open',            function () { window.location.href = window.location.pathname + '?project_id=' + currentProjectId + '&folder_id=' + card.dataset.id; });
                addCtxItem(menu, 'bi bi-pencil',            'Rename',          function () { openRenameFolderModal(card.dataset.id, card.querySelector('.fm-name, .list-name').textContent.trim()); });
                addCtxItem(menu, 'bi bi-folder-symlink',    'Move',            function () { openMoveModal('folder', card.dataset.id); });
                menu.appendChild(Object.assign(document.createElement('div'), { className: 'ctx-sep' }));
                addCtxItem(menu, 'bi bi-trash',             'Delete',          function () { deleteItem('folder', card.dataset.id); }, 'ctx-danger');
            }

            menu.style.left = Math.min(e.clientX, window.innerWidth  - 180) + 'px';
            menu.style.top  = Math.min(e.clientY, window.innerHeight - menu.scrollHeight - 10) + 'px';
            menu.style.display = 'block';
        });

        document.addEventListener('click', hideContextMenu);
    }

    function addCtxItem(menu, iconCls, label, onClick, extraCls) {
        const item = document.createElement('div');
        item.className = 'ctx-item' + (extraCls ? ' ' + extraCls : '');
        item.innerHTML = '<i class="' + iconCls + '"></i> ' + escHtml(label);
        item.addEventListener('click', function () { hideContextMenu(); onClick(); });
        menu.appendChild(item);
    }

    function hideContextMenu() {
        const menu = document.getElementById('fm-context-menu');
        if (menu) menu.style.display = 'none';
    }

    // ── Keyboard ─────────────────────────────────────────────
    function setupKeyboard() {
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { clearSelection(); updateBulkBar(); hideContextMenu(); }
            if (e.key === 'Delete' || e.key === 'Backspace') {
                if (selectedItems.size && !e.target.matches('input, textarea')) {
                    e.preventDefault();
                    bulkDelete();
                }
            }
            if ((e.ctrlKey || e.metaKey) && e.key === 'a') {
                if (!e.target.matches('input, textarea')) {
                    e.preventDefault();
                    document.getElementById('btn-select-all').click();
                }
            }
        });
    }

    // ── Bulk Actions ─────────────────────────────────────────
    window.bulkDownload = function () {
        const fileIds = [];
        selectedItems.forEach(function (key) {
            if (key.startsWith('file-')) fileIds.push(parseInt(key.split('-')[1]));
        });
        if (!fileIds.length) { alert('Please select files to download.'); return; }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = getBase() + '/api/files/download_zip.php';
        form.style.display = 'none';
        form.innerHTML = '<input name="csrf_token" value="' + escHtml(csrfToken) + '">';
        fileIds.forEach(function (id) {
            const inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'file_ids[]';
            inp.value = id;
            form.appendChild(inp);
        });
        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    };

    function bulkDelete() {
        if (!confirm('Delete ' + selectedItems.size + ' selected item(s)?')) return;
        selectedItems.forEach(function (key) {
            const parts = key.split('-');
            deleteItem(parts[0], parts[1], true);
        });
    }
    window.bulkDelete = bulkDelete;

    // ── Delete ───────────────────────────────────────────────
    function deleteItem(type, id, silent) {
        if (!silent && !confirm('Delete this ' + type + '?')) return;
        fetch(getBase() + '/api/files/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                const card = document.querySelector('[data-id="' + id + '"][data-type="' + type + '"]');
                if (card) card.remove();
                selectedItems.delete(type + '-' + id);
                updateBulkBar();
            } else {
                alert(res.error || 'Delete failed.');
            }
        })
        .catch(function () { alert('Network error.'); });
    }

    // ── Modals ───────────────────────────────────────────────
    function openDetailsModal(fileId) {
        const modal = new bootstrap.Modal(document.getElementById('modal-details'));
        const body  = document.getElementById('modal-details-body');
        body.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
        modal.show();

        fetch(getBase() + '/api/files/versions.php?file_id=' + fileId)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) { body.innerHTML = '<p class="text-danger">' + escHtml(res.error || 'Failed') + '</p>'; return; }
                const f = res.file;
                body.innerHTML =
                    '<table class="table table-sm table-borderless">' +
                    '<tr><th>Name</th><td>' + escHtml(f.original_name) + '</td></tr>' +
                    '<tr><th>Size</th><td>' + formatSize(f.file_size) + '</td></tr>' +
                    '<tr><th>Type</th><td>' + escHtml(f.mime_type || 'Unknown') + '</td></tr>' +
                    '<tr><th>Version</th><td><span class="version-badge">v' + f.version + '</span></td></tr>' +
                    '<tr><th>Uploaded by</th><td>' + escHtml(f.uploader_name || 'Unknown') + '</td></tr>' +
                    '<tr><th>Date</th><td>' + escHtml(f.created_at) + '</td></tr>' +
                    '<tr><th>Downloads</th><td>' + escHtml(String(f.download_count)) + '</td></tr>' +
                    '</table>' +
                    '<a href="' + getBase() + '/api/files/download.php?file_id=' + fileId + '" class="btn btn-primary btn-sm"><i class="bi bi-download me-1"></i>Download</a> ' +
                    '<a href="' + getBase() + '/api/files/preview.php?file_id=' + fileId + '" target="_blank" class="btn btn-outline-secondary btn-sm"><i class="bi bi-eye me-1"></i>Preview</a>';
            })
            .catch(function () { body.innerHTML = '<p class="text-danger">Network error.</p>'; });
    }

    function openVersionsModal(fileId) {
        const modal = new bootstrap.Modal(document.getElementById('modal-versions'));
        const body  = document.getElementById('modal-versions-body');
        body.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
        modal.show();

        fetch(getBase() + '/api/files/versions.php?file_id=' + fileId)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success || !res.versions || !res.versions.length) {
                    body.innerHTML = '<p class="text-muted text-center py-3">No version history found.</p>';
                    return;
                }
                body.innerHTML = res.versions.map(function (v) {
                    return '<div class="version-item">' +
                        '<span class="version-num">v' + v.version + '</span>' +
                        '<div class="flex-fill"><div class="fw-semibold" style="font-size:.85rem">' + escHtml(v.original_name) + '</div>' +
                        '<div style="font-size:.75rem;color:#64748b">' + escHtml(v.created_at) + ' · ' + formatSize(v.file_size) + '</div></div>' +
                        '<a href="' + getBase() + '/api/files/download.php?file_id=' + v.id + '" class="btn btn-sm btn-outline-primary"><i class="bi bi-download"></i></a>' +
                        '</div>';
                }).join('');
            })
            .catch(function () { body.innerHTML = '<p class="text-danger">Network error.</p>'; });
    }

    function openCommentsModal(fileId) {
        const modal = new bootstrap.Modal(document.getElementById('modal-comments'));
        const body  = document.getElementById('modal-comments-body');
        const form  = document.getElementById('modal-comments-form');
        if (form) form.dataset.fileId = fileId;
        body.innerHTML = '<div class="text-center py-3"><div class="spinner-border spinner-border-sm"></div></div>';
        modal.show();
        loadComments(fileId);
    }

    function loadComments(fileId) {
        const body = document.getElementById('modal-comments-body');
        fetch(getBase() + '/api/files/comment.php?file_id=' + fileId)
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (!res.success) { body.innerHTML = '<p class="text-muted text-center py-3">No comments yet.</p>'; return; }
                if (!res.comments || !res.comments.length) { body.innerHTML = '<p class="text-muted text-center py-3">No comments yet.</p>'; return; }
                body.innerHTML = res.comments.map(function (c) {
                    return '<div class="comment-item">' +
                        '<div class="comment-author">' + escHtml(c.author_name) + ' <span class="comment-time">' + escHtml(c.created_at) + '</span></div>' +
                        '<div class="comment-text">' + escHtml(c.comment) + '</div>' +
                        '</div>';
                }).join('');
            })
            .catch(function () { body.innerHTML = '<p class="text-danger">Network error.</p>'; });
    }

    window.submitComment = function () {
        const form   = document.getElementById('modal-comments-form');
        const input  = document.getElementById('comment-input');
        const fileId = form.dataset.fileId;
        const text   = input.value.trim();
        if (!text) return;

        fetch(getBase() + '/api/files/comment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'file_id=' + encodeURIComponent(fileId) + '&comment=' + encodeURIComponent(text) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                input.value = '';
                loadComments(fileId);
            } else {
                alert(res.error || 'Failed to post comment.');
            }
        })
        .catch(function () { alert('Network error.'); });
    };

    function openMoveModal(type, id) {
        const modal = new bootstrap.Modal(document.getElementById('modal-move'));
        const form  = document.getElementById('modal-move-form');
        form.dataset.type = type;
        form.dataset.id   = id;
        modal.show();
    }

    window.submitMove = function () {
        const form     = document.getElementById('modal-move-form');
        const type     = form.dataset.type;
        const id       = form.dataset.id;
        const targetId = document.getElementById('move-target-folder').value;

        fetch(getBase() + '/api/files/move.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'type=' + encodeURIComponent(type) + '&id=' + encodeURIComponent(id) + '&target_folder_id=' + encodeURIComponent(targetId) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.error || 'Move failed.');
            }
        })
        .catch(function () { alert('Network error.'); });
    };

    function openRenameFolderModal(folderId, currentName) {
        const modal = new bootstrap.Modal(document.getElementById('modal-rename-folder'));
        const input = document.getElementById('rename-folder-input');
        const form  = document.getElementById('modal-rename-form');
        input.value = currentName;
        form.dataset.id = folderId;
        modal.show();
    }

    window.submitRenameFolder = function () {
        const form     = document.getElementById('modal-rename-form');
        const folderId = form.dataset.id;
        const newName  = document.getElementById('rename-folder-input').value.trim();
        if (!newName) return;

        fetch(getBase() + '/api/files/create_folder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=rename&folder_id=' + encodeURIComponent(folderId) + '&name=' + encodeURIComponent(newName) + '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.error || 'Rename failed.');
            }
        })
        .catch(function () { alert('Network error.'); });
    };

    // ── New Folder Form ──────────────────────────────────────
    window.submitNewFolder = function () {
        const input = document.getElementById('new-folder-name');
        const name  = input.value.trim();
        if (!name) return;

        fetch(getBase() + '/api/files/create_folder.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'project_id=' + encodeURIComponent(currentProjectId) +
                  '&parent_id=' + encodeURIComponent(currentFolderId || '') +
                  '&name=' + encodeURIComponent(name) +
                  '&csrf_token=' + encodeURIComponent(csrfToken)
        })
        .then(function (r) { return r.json(); })
        .then(function (res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.error || 'Failed to create folder.');
            }
        })
        .catch(function () { alert('Network error.'); });
    };

    // ── Helpers ──────────────────────────────────────────────
    function getBase() {
        return window._fmBase || '';
    }

    function escHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function formatSize(bytes) {
        bytes = parseInt(bytes) || 0;
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        if (bytes < 1073741824) return (bytes / 1048576).toFixed(1) + ' MB';
        return (bytes / 1073741824).toFixed(2) + ' GB';
    }

    function getFileIcon(ext, mime) {
        const images  = ['jpg','jpeg','png','gif','webp','bmp','svg','ico'];
        const pdfs    = ['pdf'];
        const words   = ['doc','docx','odt','rtf'];
        const excels  = ['xls','xlsx','csv','ods'];
        const archives= ['zip','rar','7z','tar','gz','bz2'];
        const texts   = ['txt','md','log','ini','cfg','yaml','yml'];
        const codes   = ['php','js','ts','html','htm','css','json','xml','sql','py','rb','java','c','cpp','cs','go','sh','bash'];

        if (images.includes(ext))   return { cls: 'bi bi-file-image',   color: 'icon-image'   };
        if (pdfs.includes(ext))     return { cls: 'bi bi-file-pdf',     color: 'icon-pdf'     };
        if (words.includes(ext))    return { cls: 'bi bi-file-word',    color: 'icon-word'    };
        if (excels.includes(ext))   return { cls: 'bi bi-file-excel',   color: 'icon-excel'   };
        if (archives.includes(ext)) return { cls: 'bi bi-file-zip',     color: 'icon-archive' };
        if (texts.includes(ext))    return { cls: 'bi bi-file-text',    color: 'icon-text'    };
        if (codes.includes(ext))    return { cls: 'bi bi-file-code',    color: 'icon-code'    };
        if (mime && mime.startsWith('image/')) return { cls: 'bi bi-file-image', color: 'icon-image' };
        return { cls: 'bi bi-file-earmark', color: 'icon-file' };
    }

    // ── Expose init ──────────────────────────────────────────
    window.FileManager = { init: init };

})();
