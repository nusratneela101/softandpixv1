    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/app.js" defer></script>
    <script src="/assets/js/live-chat-widget.js" defer></script>

<?php if (isset($_SESSION['user_id'])): ?>
<!-- Session Timeout Warning Modal -->
<div class="modal fade" id="sessionTimeoutModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-warning">
            <div class="modal-header bg-warning">
                <h5 class="modal-title"><i class="bi bi-clock-history me-2"></i>Session Expiring Soon</h5>
            </div>
            <div class="modal-body">
                <p>Your session will expire in <strong id="sessionCountdown">5:00</strong> minutes due to inactivity.</p>
                <p class="text-muted small">Click "Extend Session" to stay logged in, or "Logout" to end your session now.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-warning" id="extendSessionBtn">
                    <i class="bi bi-arrow-clockwise me-1"></i>Extend Session
                </button>
                <a href="/logout.php" class="btn btn-outline-secondary">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </a>
            </div>
        </div>
    </div>
</div>
<script>
(function() {
    var SESSION_DURATION = 1800; // 30 minutes (match PHP session.gc_maxlifetime)
    var WARN_BEFORE     = 300;   // Show warning 5 minutes before expiry
    var lastActivity    = Date.now();
    var warningShown    = false;
    var modal           = null;
    var countdownInterval = null;

    function getModal() {
        if (!modal) {
            var el = document.getElementById('sessionTimeoutModal');
            if (el && typeof bootstrap !== 'undefined') {
                modal = new bootstrap.Modal(el);
            }
        }
        return modal;
    }

    function startCountdown(seconds) {
        var el = document.getElementById('sessionCountdown');
        if (countdownInterval) clearInterval(countdownInterval);
        countdownInterval = setInterval(function() {
            seconds--;
            if (seconds <= 0) {
                clearInterval(countdownInterval);
                window.location.href = '/logout.php?timeout=1';
                return;
            }
            var m = Math.floor(seconds / 60);
            var s = seconds % 60;
            if (el) el.textContent = m + ':' + (s < 10 ? '0' : '') + s;
        }, 1000);
    }

    function checkSession() {
        var elapsed = (Date.now() - lastActivity) / 1000;
        var remaining = SESSION_DURATION - elapsed;
        if (!warningShown && remaining <= WARN_BEFORE && remaining > 0) {
            warningShown = true;
            var m = getModal();
            if (m) { m.show(); startCountdown(Math.floor(remaining)); }
        }
        if (remaining <= 0) {
            window.location.href = '/logout.php?timeout=1';
        }
    }

    document.getElementById('extendSessionBtn').addEventListener('click', function() {
        // AJAX ping to extend session
        fetch('/api/extend_session.php', { method: 'POST', credentials: 'same-origin' })
            .then(function() {
                lastActivity = Date.now();
                warningShown = false;
                if (countdownInterval) clearInterval(countdownInterval);
                var m = getModal();
                if (m) m.hide();
            });
    });

    // Reset activity timer on user interaction
    ['mousemove', 'keydown', 'click', 'touchstart'].forEach(function(evt) {
        document.addEventListener(evt, function() { lastActivity = Date.now(); }, { passive: true });
    });

    setInterval(checkSession, 30000); // Check every 30s
})();
</script>
<?php endif; ?>
</body>
</html>
