/**
 * live-chat-widget.js
 * Floating live chat widget for Softandpix website
 * Guests provide name+email → auto-account created → real-time chat with admin
 * Polls /api/live-contact/poll.php every 5 seconds
 */
(function () {
    'use strict';

    // Skip on admin/developer/client panels
    var path = window.location.pathname;
    if (path.indexOf('/admin') === 0 || path.indexOf('/developer') === 0 || path.indexOf('/client') === 0) return;

    var POLL_INTERVAL   = 5000;
    var STORAGE_TOKEN   = 'lcw_session_token';
    var STORAGE_CONTACT = 'lcw_contact_id';
    var STORAGE_NAME    = 'lcw_user_name';
    var API_BASE        = '/api/live-contact/';

    var contactId    = null;
    var sessionToken = null;
    var userName     = null;
    var lastMsgId    = 0;
    var pollTimer    = null;
    var unreadCount  = 0;
    var isOpen       = false;
    var soundEnabled = true;

    /* ------------------------------------------------------------------
       Build widget HTML
    ------------------------------------------------------------------ */
    var cssLink = document.createElement('link');
    cssLink.rel  = 'stylesheet';
    cssLink.href = '/assets/css/live-chat-widget.css';
    document.head.appendChild(cssLink);

    var widget = document.createElement('div');
    widget.id  = 'lcw-widget';
    widget.innerHTML =
        '<div id="lcw-popup">' +
            '<div id="lcw-header">' +
                '<div class="lcw-header-info">' +
                    '<div class="lcw-avatar-wrap">💬</div>' +
                    '<div class="lcw-header-text">' +
                        '<h6>Softandpix Support</h6>' +
                        '<span><span class="lcw-online-dot"></span>We reply quickly</span>' +
                    '</div>' +
                '</div>' +
                '<button id="lcw-close-btn" title="Close">✕</button>' +
            '</div>' +
            '<div id="lcw-body">' +
                '<div id="lcw-auth">' +
                    '<h6>👋 Chat with us!</h6>' +
                    '<p>Fill in your details and we\'ll be right with you.</p>' +
                    '<input type="text"  id="lcw-name"    class="lcw-field" placeholder="Your name *" maxlength="255">' +
                    '<input type="email" id="lcw-email"   class="lcw-field" placeholder="Email address *" maxlength="255">' +
                    '<input type="tel"   id="lcw-phone"   class="lcw-field" placeholder="Phone (optional)" maxlength="20">' +
                    '<textarea id="lcw-msg-init" class="lcw-field lcw-textarea" placeholder="Write your message (optional)..." maxlength="2000"></textarea>' +
                    '<button class="lcw-submit-btn" id="lcw-start-btn" onclick="window._lcwStart()">Start Chat →</button>' +
                    '<div id="lcw-error"></div>' +
                '</div>' +
                '<div id="lcw-messages"></div>' +
                '<div id="lcw-closed-notice">This conversation has been closed. <a href="/contact" style="color:#664d03;">Contact us again</a>.</div>' +
            '</div>' +
            '<div id="lcw-footer">' +
                '<textarea id="lcw-input" placeholder="Type a message..." rows="1" maxlength="5000" onkeydown="window._lcwKeyDown(event)"></textarea>' +
                '<button id="lcw-send-btn" onclick="window._lcwSend()" title="Send">&#10148;</button>' +
            '</div>' +
        '</div>' +
        '<div style="position:relative;text-align:right;">' +
            '<span id="lcw-badge"></span>' +
            '<button id="lcw-btn" title="Chat with us">💬</button>' +
        '</div>';

    document.body.appendChild(widget);

    /* ------------------------------------------------------------------
       Element refs
    ------------------------------------------------------------------ */
    var popup        = document.getElementById('lcw-popup');
    var btn          = document.getElementById('lcw-btn');
    var badge        = document.getElementById('lcw-badge');
    var closeBtn     = document.getElementById('lcw-close-btn');
    var authDiv      = document.getElementById('lcw-auth');
    var messagesDiv  = document.getElementById('lcw-messages');
    var footer       = document.getElementById('lcw-footer');
    var inputEl      = document.getElementById('lcw-input');
    var errorEl      = document.getElementById('lcw-error');
    var closedNotice = document.getElementById('lcw-closed-notice');
    var body         = document.getElementById('lcw-body');

    /* ------------------------------------------------------------------
       Toggle popup
    ------------------------------------------------------------------ */
    btn.addEventListener('click', function () {
        isOpen = !isOpen;
        if (isOpen) {
            popup.classList.add('lcw-open');
            clearUnread();
            if (contactId && sessionToken) startPoll();
        } else {
            popup.classList.remove('lcw-open');
            stopPoll();
        }
    });

    closeBtn.addEventListener('click', function () {
        isOpen = false;
        popup.classList.remove('lcw-open');
        stopPoll();
    });

    /* ------------------------------------------------------------------
       Utility functions
    ------------------------------------------------------------------ */
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s || '';
        return d.innerHTML;
    }

    function setError(msg) {
        errorEl.textContent = msg;
    }

    function clearError() {
        errorEl.textContent = '';
    }

    function formatTime(dateStr) {
        var d = new Date(dateStr.replace(' ', 'T'));
        return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    }

    function clearUnread() {
        unreadCount = 0;
        badge.style.display = 'none';
        badge.textContent   = '';
    }

    function addUnread() {
        unreadCount++;
        badge.textContent    = unreadCount > 99 ? '99+' : String(unreadCount);
        badge.style.display  = 'flex';
        if (soundEnabled) playSound();
    }

    function playSound() {
        try {
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var osc = ctx.createOscillator();
            var gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.3, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.4);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.4);
        } catch (e) {}
    }

    function scrollToBottom() {
        body.scrollTop = body.scrollHeight;
    }

    function showChat() {
        authDiv.style.display      = 'none';
        messagesDiv.style.display  = 'block';
        footer.style.display       = 'flex';
    }

    function appendMessage(msg) {
        var isGuest = msg.sender_type === 'guest';
        var div     = document.createElement('div');
        div.className = 'lcw-msg ' + (isGuest ? 'lcw-guest' : 'lcw-admin');
        div.innerHTML =
            '<div class="lcw-msg-label">' + (isGuest ? esc(userName || 'You') : 'Support') + '</div>' +
            '<div class="lcw-bubble">' + esc(msg.message) + '</div>' +
            '<div class="lcw-msg-time">' + (msg.created_at ? formatTime(msg.created_at) : '') + '</div>';
        messagesDiv.appendChild(div);
        if (parseInt(msg.id) > lastMsgId) lastMsgId = parseInt(msg.id);
    }

    function appendWelcome() {
        var div = document.createElement('div');
        div.className = 'lcw-msg lcw-admin';
        div.innerHTML =
            '<div class="lcw-msg-label">Support</div>' +
            '<div class="lcw-welcome-msg">Hi ' + esc(userName || 'there') + '! 👋 Thanks for reaching out. How can we help you today?</div>';
        messagesDiv.appendChild(div);
    }

    /* ------------------------------------------------------------------
       Polling
    ------------------------------------------------------------------ */
    function fetchMessages() {
        if (!contactId || !sessionToken) return;
        var url = API_BASE + 'poll.php?contact_id=' + contactId +
                  '&last_id=' + lastMsgId +
                  '&session_token=' + encodeURIComponent(sessionToken);
        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;

                if (data.status === 'closed') {
                    closedNotice.style.display = 'block';
                    footer.style.display       = 'none';
                    stopPoll();
                }

                if (data.messages && data.messages.length) {
                    data.messages.forEach(function (msg) {
                        appendMessage(msg);
                        if (msg.sender_type === 'admin' && !isOpen) addUnread();
                    });
                    scrollToBottom();
                }
            })
            .catch(function () {});
    }

    function startPoll() {
        stopPoll();
        fetchMessages();
        pollTimer = setInterval(fetchMessages, POLL_INTERVAL);
    }

    function stopPoll() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    /* ------------------------------------------------------------------
       Start chat (new session)
    ------------------------------------------------------------------ */
    window._lcwStart = function () {
        clearError();
        var name  = (document.getElementById('lcw-name').value  || '').trim();
        var email = (document.getElementById('lcw-email').value || '').trim();
        var phone = (document.getElementById('lcw-phone').value || '').trim();
        var msg   = (document.getElementById('lcw-msg-init').value || '').trim();

        if (!name)  { setError('Please enter your name.'); return; }
        if (!email) { setError('Please enter your email address.'); return; }
        var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRe.test(email)) { setError('Please enter a valid email address.'); return; }

        var startBtn = document.getElementById('lcw-start-btn');
        startBtn.disabled    = true;
        startBtn.textContent = 'Starting…';

        var fd = new FormData();
        fd.append('name', name);
        fd.append('email', email);
        fd.append('phone', phone);
        fd.append('message', msg);

        fetch(API_BASE + 'start.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                startBtn.disabled    = false;
                startBtn.textContent = 'Start Chat →';

                if (!data.success) {
                    setError(data.error || 'Something went wrong. Please try again.');
                    return;
                }

                contactId    = data.contact_id;
                sessionToken = data.session_token;
                userName     = name;
                lastMsgId    = 0;

                // Persist to localStorage
                try {
                    localStorage.setItem(STORAGE_TOKEN,   sessionToken);
                    localStorage.setItem(STORAGE_CONTACT, String(contactId));
                    localStorage.setItem(STORAGE_NAME,    name);
                } catch (e) {}

                showChat();

                // Show welcome message
                appendWelcome();

                // If initial message was provided, show it immediately
                if (msg) {
                    appendMessage({ sender_type: 'guest', message: msg, created_at: new Date().toISOString().replace('T', ' ').substring(0, 19), id: 0 });
                }

                scrollToBottom();
                startPoll();
            })
            .catch(function () {
                startBtn.disabled    = false;
                startBtn.textContent = 'Start Chat →';
                setError('Connection error. Please try again.');
            });
    };

    /* ------------------------------------------------------------------
       Send message
    ------------------------------------------------------------------ */
    window._lcwSend = function () {
        var msg = inputEl.value.trim();
        if (!msg || !contactId || !sessionToken) return;

        var sendBtn = document.getElementById('lcw-send-btn');
        sendBtn.disabled = true;

        var fd = new FormData();
        fd.append('contact_id',    contactId);
        fd.append('session_token', sessionToken);
        fd.append('message',       msg);

        fetch(API_BASE + 'send.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                sendBtn.disabled = false;
                if (data.success) {
                    inputEl.value = '';
                    inputEl.style.height = 'auto';
                    fetchMessages();
                } else {
                    setError(data.error || 'Failed to send message.');
                    setTimeout(function () { setError(''); }, 3000);
                }
            })
            .catch(function () {
                sendBtn.disabled = false;
                setError('Connection error.');
                setTimeout(function () { setError(''); }, 3000);
            });
    };

    /* ------------------------------------------------------------------
       Enter key to send (Shift+Enter for newline)
    ------------------------------------------------------------------ */
    window._lcwKeyDown = function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            window._lcwSend();
        }
    };

    /* Auto-resize textarea */
    inputEl.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 100) + 'px';
    });

    /* ------------------------------------------------------------------
       Resume existing session from localStorage
    ------------------------------------------------------------------ */
    function tryResume() {
        var storedToken = null, storedContact = null, storedName = null;
        try {
            storedToken   = localStorage.getItem(STORAGE_TOKEN);
            storedContact = localStorage.getItem(STORAGE_CONTACT);
            storedName    = localStorage.getItem(STORAGE_NAME);
        } catch (e) {}

        if (!storedToken || !storedContact) return;

        var fd = new FormData();
        fd.append('session_token', storedToken);

        fetch(API_BASE + 'resume.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    // Clear stale localStorage
                    try {
                        localStorage.removeItem(STORAGE_TOKEN);
                        localStorage.removeItem(STORAGE_CONTACT);
                        localStorage.removeItem(STORAGE_NAME);
                    } catch (e) {}
                    return;
                }

                contactId    = data.contact_id;
                sessionToken = storedToken;
                userName     = data.name || storedName || 'You';

                // Show chat interface
                showChat();

                // Load historical messages
                if (data.messages && data.messages.length) {
                    data.messages.forEach(function (msg) {
                        appendMessage(msg);
                        if (parseInt(msg.id) > lastMsgId) lastMsgId = parseInt(msg.id);
                    });
                    scrollToBottom();
                } else {
                    appendWelcome();
                    scrollToBottom();
                }

                if (data.status === 'closed') {
                    closedNotice.style.display = 'block';
                    footer.style.display       = 'none';
                }

                // Count unread admin messages and show badge
                if (data.messages) {
                    data.messages.forEach(function (msg) {
                        if (msg.sender_type === 'admin' && !parseInt(msg.is_read)) {
                            unreadCount++;
                        }
                    });
                    if (unreadCount > 0) {
                        badge.textContent   = unreadCount > 99 ? '99+' : String(unreadCount);
                        badge.style.display = 'flex';
                    }
                }

                if (isOpen) startPoll();
            })
            .catch(function () {});
    }

    // Try to resume on page load
    tryResume();

})();
