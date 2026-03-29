/**
 * SoftandPix — Consolidated Application JavaScript
 */

(function (SP) {
    'use strict';

    // ─── CSRF Token Helper ───────────────────────────────────────────
    SP.getCsrf = function () {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    };

    // ─── AJAX Utility ────────────────────────────────────────────────
    SP.ajax = function (url, method, data, onSuccess, onError) {
        var body;
        if (data instanceof FormData) {
            body = data;
        } else {
            var fd = new FormData();
            Object.keys(data || {}).forEach(function (k) { fd.append(k, data[k]); });
            fd.append('csrf_token', SP.getCsrf());
            body = fd;
        }
        fetch(url, { method: method || 'POST', body: body, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(onSuccess || function () {})
            .catch(onError || function (e) { console.error('AJAX error:', e); });
    };

    // ─── Toast Notification ──────────────────────────────────────────
    SP.toast = function (message, type) {
        type = type || 'info';
        var container = document.getElementById('sp-toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'sp-toast-container';
            container.style.cssText = 'position:fixed;bottom:20px;left:20px;z-index:9998;display:flex;flex-direction:column;gap:8px;';
            document.body.appendChild(container);
        }
        var colors = { success: '#198754', error: '#dc3545', warning: '#ffc107', info: '#0dcaf0' };
        var toast = document.createElement('div');
        toast.style.cssText = 'background:' + (colors[type] || '#333') + ';color:#fff;padding:12px 20px;border-radius:8px;font-size:0.9rem;box-shadow:0 4px 12px rgba(0,0,0,0.2);max-width:320px;animation:slideUp 0.3s ease;';
        toast.textContent = message;
        container.appendChild(toast);
        setTimeout(function () { toast.style.opacity = '0'; toast.style.transition = 'opacity 0.4s'; setTimeout(function () { toast.remove(); }, 400); }, 3500);
    };

    // ─── Confirm Dialog ──────────────────────────────────────────────
    SP.confirm = function (message, onConfirm) {
        if (window.confirm(message)) onConfirm();
    };

    // ─── Delete with Confirm ─────────────────────────────────────────
    SP.deleteItem = function (url, onSuccess) {
        SP.confirm('Are you sure you want to delete this?', function () {
            SP.ajax(url, 'POST', { _method: 'DELETE' }, function (data) {
                if (data.success) {
                    SP.toast('Deleted successfully.', 'success');
                    if (onSuccess) onSuccess(data);
                } else {
                    SP.toast(data.message || 'An error occurred.', 'error');
                }
            });
        });
    };

    // ─── Format Duration ─────────────────────────────────────────────
    SP.formatDuration = function (minutes) {
        var h = Math.floor(minutes / 60);
        var m = minutes % 60;
        return (h > 0 ? h + 'h ' : '') + m + 'm';
    };

    // ─── Live Timer ──────────────────────────────────────────────────
    SP.startLiveTimer = function (startTime, displayEl) {
        if (!displayEl) return null;
        var start = startTime ? new Date(startTime).getTime() : Date.now();
        return setInterval(function () {
            var diff = Math.floor((Date.now() - start) / 1000);
            var h = Math.floor(diff / 3600);
            var m = Math.floor((diff % 3600) / 60);
            var s = diff % 60;
            displayEl.textContent = (h > 0 ? pad(h) + ':' : '') + pad(m) + ':' + pad(s);
        }, 1000);
    };

    function pad(n) { return n < 10 ? '0' + n : '' + n; }

    // ─── Language Switcher ───────────────────────────────────────────
    SP.switchLang = function (lang) {
        var fd = new FormData();
        fd.append('lang', lang);
        fetch('/api/language.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) window.location.href = window.location.pathname + '?lang=' + lang;
            })
            .catch(function () {
                window.location.href = window.location.pathname + '?lang=' + lang;
            });
    };

    // ─── PWA Install Banner ──────────────────────────────────────────
    var _deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        _deferredPrompt = e;
        var banner = document.getElementById('pwa-install-banner');
        if (banner) { banner.style.display = 'flex'; }
    });

    SP.installPWA = function () {
        if (!_deferredPrompt) return;
        _deferredPrompt.prompt();
        _deferredPrompt.userChoice.then(function () { _deferredPrompt = null; });
        var banner = document.getElementById('pwa-install-banner');
        if (banner) banner.style.display = 'none';
    };

    // ─── Service Worker Registration ─────────────────────────────────
    SP.registerSW = function () {
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(function (e) {
                console.warn('SW registration failed:', e);
            });
        }
    };

    // ─── Sidebar Toggle (mobile) ─────────────────────────────────────
    SP.toggleSidebar = function () {
        var sb = document.getElementById('sidebar');
        if (sb) sb.classList.toggle('open');
    };

    // ─── Initialize on DOM ready ─────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {
        SP.registerSW();

        // Mobile sidebar toggle button
        var toggleBtn = document.getElementById('sidebarToggleBtn');
        if (toggleBtn) toggleBtn.addEventListener('click', SP.toggleSidebar);

        // PWA install banner close
        var closeBtn = document.querySelector('.close-banner');
        if (closeBtn) {
            closeBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                var banner = document.getElementById('pwa-install-banner');
                if (banner) banner.style.display = 'none';
            });
        }

        // Language switch buttons
        document.querySelectorAll('[data-lang-switch]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                SP.switchLang(this.getAttribute('data-lang-switch'));
            });
        });
    });

}(window.SP = window.SP || {}));
