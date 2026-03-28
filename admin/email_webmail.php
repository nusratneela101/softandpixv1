<?php
require_once '../config/db.php';
require_once 'includes/auth.php';
requireAuth();

$csrf_token = generateCsrfToken();

// Load account labels
$settings = [];
try {
    $rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'email_account_%'")->fetchAll();
    foreach ($rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (Exception $e) {}

$account1_label = htmlspecialchars($settings['email_account_1_label'] ?? 'Info (Zoho)', ENT_QUOTES, 'UTF-8');
$account2_label = htmlspecialchars($settings['email_account_2_label'] ?? 'Support (Hosting)', ENT_QUOTES, 'UTF-8');

require_once 'includes/header.php';
?>
<div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
        <h1><i class="bi bi-mailbox me-2"></i>Webmail</h1>
        <p>Read and send emails directly from the admin panel</p>
    </div>
    <button class="btn btn-primary" id="composeBtn" data-bs-toggle="modal" data-bs-target="#composeModal">
        <i class="bi bi-pencil-square me-1"></i>Compose
    </button>
</div>

<div class="container-fluid">
    <!-- Controls Row -->
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="row g-2 align-items-center">
                <div class="col-auto">
                    <select id="accountSelect" class="form-select form-select-sm">
                        <option value="1"><?php echo $account1_label; ?></option>
                        <option value="2"><?php echo $account2_label; ?></option>
                    </select>
                </div>
                <div class="col-auto">
                    <ul class="nav nav-pills nav-sm" id="folderTabs">
                        <li class="nav-item"><a class="nav-link active py-1 px-3" href="#" data-folder="INBOX">Inbox</a></li>
                        <li class="nav-item"><a class="nav-link py-1 px-3" href="#" data-folder="Sent">Sent</a></li>
                        <li class="nav-item"><a class="nav-link py-1 px-3" href="#" data-folder="Drafts">Drafts</a></li>
                        <li class="nav-item"><a class="nav-link py-1 px-3" href="#" data-folder="Trash">Trash</a></li>
                    </ul>
                </div>
                <div class="col">
                    <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Search emails...">
                </div>
                <div class="col-auto">
                    <button class="btn btn-outline-secondary btn-sm" id="refreshBtn" title="Refresh">
                        <i class="bi bi-arrow-clockwise"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Email List -->
        <div class="col-md-4" id="emailListCol">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <span id="folderTitle" class="fw-bold">Inbox</span>
                    <div class="d-flex gap-1">
                        <button class="btn btn-outline-danger btn-sm d-none" id="deleteSelectedBtn"><i class="bi bi-trash"></i> Delete</button>
                        <button class="btn btn-outline-secondary btn-sm d-none" id="markReadBtn"><i class="bi bi-envelope-open"></i></button>
                        <button class="btn btn-outline-secondary btn-sm d-none" id="markUnreadBtn"><i class="bi bi-envelope"></i></button>
                    </div>
                </div>
                <div class="card-body p-0" style="overflow-y:auto;max-height:600px;">
                    <div id="emailList"><div class="text-center text-muted p-4"><i class="bi bi-envelope" style="font-size:2rem;"></i><br>Loading emails...</div></div>
                </div>
                <div class="card-footer d-flex justify-content-between align-items-center py-2">
                    <button class="btn btn-sm btn-outline-secondary" id="prevPageBtn" disabled>&laquo; Prev</button>
                    <span id="pageInfo" class="small text-muted">Page 1</span>
                    <button class="btn btn-sm btn-outline-secondary" id="nextPageBtn" disabled>Next &raquo;</button>
                </div>
            </div>
        </div>

        <!-- Email Reader -->
        <div class="col-md-8" id="emailReaderCol">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center py-2" id="emailReaderHeader">
                    <span class="text-muted small">Select an email to read</span>
                    <div class="d-flex gap-1" id="emailActions" style="display:none!important;">
                        <button class="btn btn-sm btn-outline-primary" id="replyBtn" style="display:none;"><i class="bi bi-reply"></i> Reply</button>
                        <button class="btn btn-sm btn-outline-secondary" id="forwardBtn" style="display:none;"><i class="bi bi-forward"></i> Forward</button>
                        <button class="btn btn-sm btn-outline-danger" id="deleteEmailBtn" style="display:none;"><i class="bi bi-trash"></i> Delete</button>
                    </div>
                </div>
                <div class="card-body" id="emailReaderBody" style="overflow-y:auto;max-height:600px;">
                    <div class="text-center text-muted p-5">
                        <i class="bi bi-envelope-open" style="font-size:3rem;"></i>
                        <p class="mt-2">Select an email from the list to read it</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Compose Modal -->
<div class="modal fade" id="composeModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil-square me-2"></i>Compose Email</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label fw-bold">From Account</label>
                    <select id="composeAccount" class="form-select">
                        <option value="1"><?php echo $account1_label; ?></option>
                        <option value="2"><?php echo $account2_label; ?></option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">To</label>
                    <input type="email" id="composeTo" class="form-control" placeholder="recipient@example.com">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Subject</label>
                    <input type="text" id="composeSubject" class="form-control" placeholder="Subject">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Message</label>
                    <textarea id="composeBody" class="form-control" rows="10"></textarea>
                </div>
                <input type="hidden" id="composeInReplyTo" value="">
                <div id="sendResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="sendEmailBtn">
                    <i class="bi bi-send me-1"></i>Send
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var CSRF = '<?php echo h($csrf_token); ?>';
var currentAccount = 1;
var currentFolder = 'INBOX';
var currentPage = 1;
var totalPages = 1;
var currentUid = null;
var selectedUids = [];

function loadEmails() {
    var search = document.getElementById('searchInput').value;
    document.getElementById('emailList').innerHTML = '<div class="text-center text-muted p-4"><div class="spinner-border spinner-border-sm"></div> Loading...</div>';
    fetch('../api/email_fetch.php?action=list&account=' + currentAccount + '&folder=' + encodeURIComponent(currentFolder) + '&page=' + currentPage + '&search=' + encodeURIComponent(search))
        .then(r => r.json())
        .then(function(res) {
            if (!res.success) {
                document.getElementById('emailList').innerHTML = '<div class="alert alert-danger m-2">' + (res.message || 'Failed to load emails') + '</div>';
                return;
            }
            totalPages = res.total_pages || 1;
            document.getElementById('pageInfo').textContent = 'Page ' + currentPage + ' of ' + totalPages;
            document.getElementById('prevPageBtn').disabled = currentPage <= 1;
            document.getElementById('nextPageBtn').disabled = currentPage >= totalPages;

            var emails = res.emails || [];
            if (emails.length === 0) {
                document.getElementById('emailList').innerHTML = '<div class="text-center text-muted p-4">No emails found</div>';
                return;
            }
            var html = '<div class="list-group list-group-flush">';
            emails.forEach(function(em) {
                var unread = em.unseen ? 'fw-bold' : '';
                var badge = em.unseen ? '<span class="badge bg-primary ms-1">New</span>' : '';
                html += '<div class="list-group-item list-group-item-action ' + unread + ' email-item" data-uid="' + em.uid + '" style="cursor:pointer;">'
                    + '<div class="d-flex justify-content-between align-items-start">'
                    + '<div class="d-flex align-items-center gap-2">'
                    + '<input type="checkbox" class="email-checkbox form-check-input" data-uid="' + em.uid + '" onclick="event.stopPropagation();">'
                    + '<div><div class="small">' + escHtml(em.from) + badge + '</div>'
                    + '<div>' + escHtml(em.subject || '(no subject)') + '</div></div></div>'
                    + '<small class="text-muted">' + escHtml(em.date) + '</small></div></div>';
            });
            html += '</div>';
            document.getElementById('emailList').innerHTML = html;

            document.querySelectorAll('.email-item').forEach(function(el) {
                el.addEventListener('click', function() {
                    var uid = this.dataset.uid;
                    readEmail(uid);
                    document.querySelectorAll('.email-item').forEach(e => e.classList.remove('active'));
                    this.classList.add('active');
                });
            });

            document.querySelectorAll('.email-checkbox').forEach(function(cb) {
                cb.addEventListener('change', updateSelectedUids);
            });
        })
        .catch(function() {
            document.getElementById('emailList').innerHTML = '<div class="alert alert-danger m-2">Failed to load emails.</div>';
        });
}

function readEmail(uid) {
    currentUid = uid;
    document.getElementById('emailReaderBody').innerHTML = '<div class="text-center p-4"><div class="spinner-border"></div></div>';
    document.getElementById('replyBtn').style.display = '';
    document.getElementById('forwardBtn').style.display = '';
    document.getElementById('deleteEmailBtn').style.display = '';
    document.getElementById('emailActions').style.display = '';
    fetch('../api/email_fetch.php?action=read&account=' + currentAccount + '&uid=' + uid + '&folder=' + encodeURIComponent(currentFolder))
        .then(r => r.json())
        .then(function(res) {
            if (!res.success) {
                document.getElementById('emailReaderBody').innerHTML = '<div class="alert alert-danger">' + (res.message || 'Failed') + '</div>';
                return;
            }
            var e = res.email;
            document.getElementById('emailReaderHeader').querySelector('span').textContent = e.subject || '(no subject)';
            var html = '<div class="mb-3 border-bottom pb-3">'
                + '<h5>' + escHtml(e.subject || '(no subject)') + '</h5>'
                + '<div class="text-muted small">From: ' + escHtml(e.from) + '</div>'
                + '<div class="text-muted small">To: ' + escHtml(e.to) + '</div>'
                + '<div class="text-muted small">Date: ' + escHtml(e.date) + '</div>'
                + '</div>'
                + '<iframe srcdoc="' + e.body_html.replace(/"/g, '&quot;') + '" sandbox="allow-popups" style="width:100%;min-height:400px;border:none;" onload="this.style.height=(this.contentWindow.document.body ? this.contentWindow.document.body.scrollHeight : 400)+\'px\'"></iframe>';
            document.getElementById('emailReaderBody').innerHTML = html;

            // Reply button
            document.getElementById('replyBtn').onclick = function() {
                document.getElementById('composeAccount').value = currentAccount;
                document.getElementById('composeTo').value = e.reply_to || e.from_email || e.from;
                document.getElementById('composeSubject').value = 'Re: ' + (e.subject || '');
                document.getElementById('composeBody').value = '\n\n--- Original Message ---\nFrom: ' + e.from + '\nDate: ' + e.date + '\n\n' + (e.body_text || '');
                document.getElementById('composeInReplyTo').value = e.message_id || '';
                new bootstrap.Modal(document.getElementById('composeModal')).show();
            };

            // Forward button
            document.getElementById('forwardBtn').onclick = function() {
                document.getElementById('composeAccount').value = currentAccount;
                document.getElementById('composeTo').value = '';
                document.getElementById('composeSubject').value = 'Fwd: ' + (e.subject || '');
                document.getElementById('composeBody').value = '\n\n--- Forwarded Message ---\nFrom: ' + e.from + '\nDate: ' + e.date + '\n\n' + (e.body_text || '');
                document.getElementById('composeInReplyTo').value = '';
                new bootstrap.Modal(document.getElementById('composeModal')).show();
            };
        })
        .catch(function() {
            document.getElementById('emailReaderBody').innerHTML = '<div class="alert alert-danger">Failed to load email.</div>';
        });
}

function updateSelectedUids() {
    selectedUids = [];
    document.querySelectorAll('.email-checkbox:checked').forEach(function(cb) {
        selectedUids.push(cb.dataset.uid);
    });
    var hasSelected = selectedUids.length > 0;
    document.getElementById('deleteSelectedBtn').classList.toggle('d-none', !hasSelected);
    document.getElementById('markReadBtn').classList.toggle('d-none', !hasSelected);
    document.getElementById('markUnreadBtn').classList.toggle('d-none', !hasSelected);
}

function emailAction(action, uid, destination) {
    var data = new FormData();
    data.append('csrf_token', CSRF);
    data.append('account', currentAccount);
    data.append('action', action);
    data.append('uid', uid);
    data.append('folder', currentFolder);
    if (destination) data.append('destination', destination);
    return fetch('../api/email_action.php', { method: 'POST', body: data }).then(r => r.json());
}

document.getElementById('deleteEmailBtn').addEventListener('click', function() {
    if (!currentUid) return;
    if (!confirm('Delete this email?')) return;
    emailAction('delete', currentUid).then(function(res) {
        if (res.success) {
            document.getElementById('emailReaderBody').innerHTML = '<div class="text-center text-muted p-5"><i class="bi bi-trash" style="font-size:3rem;"></i><p class="mt-2">Email deleted.</p></div>';
            loadEmails();
        } else {
            alert('Delete failed: ' + res.message);
        }
    });
});

document.getElementById('deleteSelectedBtn').addEventListener('click', function() {
    if (!selectedUids.length) return;
    if (!confirm('Delete ' + selectedUids.length + ' email(s)?')) return;
    var promises = selectedUids.map(uid => emailAction('delete', uid));
    Promise.all(promises).then(loadEmails);
});

document.getElementById('markReadBtn').addEventListener('click', function() {
    var promises = selectedUids.map(uid => emailAction('mark_read', uid));
    Promise.all(promises).then(loadEmails);
});

document.getElementById('markUnreadBtn').addEventListener('click', function() {
    var promises = selectedUids.map(uid => emailAction('mark_unread', uid));
    Promise.all(promises).then(loadEmails);
});

document.getElementById('accountSelect').addEventListener('change', function() {
    currentAccount = this.value;
    currentPage = 1;
    loadEmails();
});

document.querySelectorAll('[data-folder]').forEach(function(tab) {
    tab.addEventListener('click', function(e) {
        e.preventDefault();
        currentFolder = this.dataset.folder;
        currentPage = 1;
        document.querySelectorAll('[data-folder]').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('folderTitle').textContent = this.textContent;
        loadEmails();
    });
});

document.getElementById('refreshBtn').addEventListener('click', function() { loadEmails(); });
document.getElementById('prevPageBtn').addEventListener('click', function() { if (currentPage > 1) { currentPage--; loadEmails(); } });
document.getElementById('nextPageBtn').addEventListener('click', function() { if (currentPage < totalPages) { currentPage++; loadEmails(); } });

var searchTimer;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(function() { currentPage = 1; loadEmails(); }, 500);
});

document.getElementById('sendEmailBtn').addEventListener('click', function() {
    var btn = this;
    btn.disabled = true;
    var result = document.getElementById('sendResult');
    result.innerHTML = '<span class="text-muted">Sending...</span>';
    var data = new FormData();
    data.append('csrf_token', CSRF);
    data.append('account', document.getElementById('composeAccount').value);
    data.append('to', document.getElementById('composeTo').value);
    data.append('subject', document.getElementById('composeSubject').value);
    data.append('body', document.getElementById('composeBody').value);
    data.append('in_reply_to', document.getElementById('composeInReplyTo').value);
    fetch('../api/email_send.php', { method: 'POST', body: data })
        .then(r => r.json())
        .then(function(res) {
            if (res.success) {
                result.innerHTML = '<span class="text-success"><i class="bi bi-check-circle me-1"></i>Email sent successfully!</span>';
                setTimeout(function() {
                    bootstrap.Modal.getInstance(document.getElementById('composeModal')).hide();
                    result.innerHTML = '';
                    document.getElementById('composeTo').value = '';
                    document.getElementById('composeSubject').value = '';
                    document.getElementById('composeBody').value = '';
                    document.getElementById('composeInReplyTo').value = '';
                }, 1500);
            } else {
                result.innerHTML = '<span class="text-danger"><i class="bi bi-x-circle me-1"></i>' + (res.message || 'Failed to send') + '</span>';
            }
        })
        .catch(function() {
            result.innerHTML = '<span class="text-danger">Request failed.</span>';
        })
        .finally(function() { btn.disabled = false; });
});

function escHtml(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Initial load
loadEmails();
</script>
<?php require_once 'includes/footer.php'; ?>
