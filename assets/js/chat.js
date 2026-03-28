/**
 * assets/js/chat.js
 * ChatManager — handles long-polling, message rendering, file uploads,
 * typing indicators, sound notifications and auto-scroll.
 *
 * Requires window.CHAT_CONFIG to be set before instantiation:
 * {
 *   userId:         <int>   current user id (0 for admin),
 *   csrfToken:      <str>   CSRF token for POST requests,
 *   basePath:       <str>   e.g. '' or '/subdir',
 *   initialLastId:  <int>   highest message id already rendered on page load,
 * }
 */
class ChatManager {
    constructor(config) {
        this.userId        = config.userId        || 0;
        this.csrfToken     = config.csrfToken     || '';
        this.basePath      = (config.basePath || '').replace(/\/$/, '');
        this.conversationId = 0;
        this.lastId        = config.initialLastId || 0;
        this.pollTimer     = null;
        this.typingTimer   = null;
        this.isTyping      = false;
        this.retryDelay    = 3000;
        this.maxRetry      = 30000;
        this._audioCtx     = null;

        // DOM refs — set by caller or auto-detected
        this.messagesContainer = document.getElementById('chatMessages');
        this.inputEl           = document.getElementById('chatInput');
        this.sendBtn           = document.getElementById('sendBtn');
        this.typingIndicator   = document.getElementById('typingIndicator');
        this.fileInput         = document.getElementById('chatFileInput');

        if (this.inputEl) {
            this.inputEl.addEventListener('input',  () => this._onInputChange());
            this.inputEl.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.sendMessage();
                }
            });
        }
        if (this.fileInput) {
            this.fileInput.addEventListener('change', (e) => {
                if (e.target.files[0]) this.uploadFile(e.target.files[0]);
            });
        }
        if (this.sendBtn) {
            this.sendBtn.addEventListener('click', () => this.sendMessage());
        }
    }

    /* ── Polling ─────────────────────────────────────────────────────────── */

    startPolling(conversationId) {
        this.conversationId = conversationId;
        this.retryDelay     = 3000;
        clearTimeout(this.pollTimer);
        this._poll();
    }

    stopPolling() {
        clearTimeout(this.pollTimer);
    }

    _poll() {
        if (!this.conversationId) return;
        const url = `${this.basePath}/api/chat/poll.php`
            + `?conversation_id=${this.conversationId}&last_id=${this.lastId}`;

        fetch(url, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (data.messages && data.messages.length) {
                    let hasNew = false;
                    data.messages.forEach(msg => {
                        if (msg.sender_id != this.userId) hasNew = true;
                        this.renderMessage(msg);
                        this.lastId = Math.max(this.lastId, parseInt(msg.id, 10));
                    });
                    if (hasNew) {
                        this.playNotificationSound();
                        this.scrollToBottom();
                    }
                }
                this._updateTypingIndicator(data.typing || []);
                this.retryDelay = 3000;
                this.pollTimer  = setTimeout(() => this._poll(), this.retryDelay);
            })
            .catch(() => {
                this.retryDelay = Math.min(this.retryDelay * 2, this.maxRetry);
                this.pollTimer  = setTimeout(() => this._poll(), this.retryDelay);
            });
    }

    /* ── Send message ────────────────────────────────────────────────────── */

    sendMessage(text) {
        const msg = (text || (this.inputEl ? this.inputEl.value.trim() : ''));
        if (!msg || !this.conversationId) return;
        if (this.inputEl) this.inputEl.value = '';
        this._stopTyping();

        const form = new FormData();
        form.append('conversation_id', this.conversationId);
        form.append('csrf_token', this.csrfToken);
        form.append('message', msg);
        form.append('message_type', msg.startsWith('http') ? 'link' : 'text');

        fetch(`${this.basePath}/api/chat/send.php`, {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
        })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.message) {
                    this.renderMessage(data.message);
                    this.lastId = Math.max(this.lastId, parseInt(data.message.id, 10));
                    this.scrollToBottom();
                }
            })
            .catch(() => {});
    }

    /* ── File upload ─────────────────────────────────────────────────────── */

    uploadFile(file) {
        if (!this.conversationId) return;
        const form = new FormData();
        form.append('conversation_id', this.conversationId);
        form.append('csrf_token', this.csrfToken);
        form.append('file', file);

        // Optimistic placeholder
        const placeholderId = 'up_' + Date.now();
        this._appendRawHTML(this._buildUploadPlaceholder(placeholderId, file.name));

        fetch(`${this.basePath}/api/chat/upload.php`, {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
        })
            .then(r => r.json())
            .then(data => {
                const ph = document.getElementById(placeholderId);
                if (ph) ph.remove();
                if (data.success && data.message) {
                    this.renderMessage(data.message);
                    this.lastId = Math.max(this.lastId, parseInt(data.message.id, 10));
                    this.scrollToBottom();
                }
            })
            .catch(() => {
                const ph = document.getElementById(placeholderId);
                if (ph) ph.innerHTML = '<span class="text-danger small">Upload failed</span>';
            });

        if (this.fileInput) this.fileInput.value = '';
    }

    /* ── Typing indicator ────────────────────────────────────────────────── */

    updateTyping() {
        if (!this.conversationId) return;
        if (!this.isTyping) {
            this.isTyping = true;
            this._sendTyping(1);
        }
        clearTimeout(this.typingTimer);
        this.typingTimer = setTimeout(() => this._stopTyping(), 4000);
    }

    _stopTyping() {
        if (!this.isTyping) return;
        this.isTyping = false;
        this._sendTyping(0);
    }

    _sendTyping(val) {
        const form = new FormData();
        form.append('conversation_id', this.conversationId);
        form.append('is_typing', val);
        fetch(`${this.basePath}/api/chat/typing.php`, {
            method: 'POST', body: form, credentials: 'same-origin',
        }).catch(() => {});
    }

    _onInputChange() {
        this.updateTyping();
    }

    _updateTypingIndicator(typingUsers) {
        if (!this.typingIndicator) return;
        if (!typingUsers || !typingUsers.length) {
            this.typingIndicator.style.display = 'none';
            return;
        }
        const names = typingUsers.map(t => t.name || 'Someone');
        this.typingIndicator.textContent =
            names.join(', ') + (names.length === 1 ? ' is typing…' : ' are typing…');
        this.typingIndicator.style.display = 'block';
    }

    /* ── Render a message bubble ─────────────────────────────────────────── */

    renderMessage(msg) {
        if (!this.messagesContainer) return;
        // Deduplicate: skip if already rendered
        if (document.getElementById('msg_' + msg.id)) return;

        const isMine  = (parseInt(msg.sender_id, 10) === this.userId);
        const name    = msg.sender_name || (msg.sender_id == 0 ? 'Admin' : 'User');
        const time    = this._timeAgo(msg.created_at);
        const type    = msg.message_type || 'text';

        let bodyHtml = '';
        if (type === 'image') {
            const src = this._escAttr(msg.file_path || msg.message);
            bodyHtml = `<a href="/${src}" data-lightbox="chat" target="_blank">
                <img src="/${src}" alt="${this._esc(msg.file_name || 'image')}"
                     style="max-width:220px;max-height:200px;border-radius:8px;cursor:pointer;">
            </a>`;
        } else if (type === 'file') {
            const href = this._escAttr(msg.file_path || '');
            const size = msg.file_size ? this._formatSize(msg.file_size) : '';
            bodyHtml = `<a href="/${href}" target="_blank" download class="d-flex align-items-center gap-2 text-decoration-none">
                <i class="bi bi-file-earmark-arrow-down fs-4"></i>
                <span><strong>${this._esc(msg.file_name || 'file')}</strong><br>
                <small class="text-muted">${size}</small></span>
            </a>`;
        } else if (type === 'link') {
            const url = this._esc(msg.message || '');
            bodyHtml = `<a href="${this._escAttr(msg.message)}" target="_blank" rel="noopener noreferrer">${url}</a>`;
        } else {
            bodyHtml = this._esc(msg.message || '').replace(/\n/g, '<br>');
        }

        const html = `
        <div id="msg_${msg.id}" class="d-flex ${isMine ? 'justify-content-end' : 'justify-content-start'} mb-2">
            <div class="chat-msg ${isMine ? 'chat-msg-mine' : 'chat-msg-other'}">
                ${!isMine ? `<div class="chat-msg-name">${this._esc(name)}</div>` : ''}
                <div class="chat-msg-body">${bodyHtml}</div>
                <div class="chat-msg-time">${time}</div>
            </div>
        </div>`;

        this._appendRawHTML(html);
    }

    /* ── Sound notification ──────────────────────────────────────────────── */

    playNotificationSound() {
        try {
            if (!this._audioCtx) {
                this._audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
            const ctx = this._audioCtx;
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.type = 'sine';
            osc.frequency.setValueAtTime(880, ctx.currentTime);
            gain.gain.setValueAtTime(0.15, ctx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.0001, ctx.currentTime + 0.3);
            osc.start(ctx.currentTime);
            osc.stop(ctx.currentTime + 0.3);
        } catch (e) {}
    }

    /* ── Scroll ──────────────────────────────────────────────────────────── */

    scrollToBottom(smooth) {
        const c = this.messagesContainer;
        if (!c) return;
        c.scrollTo({ top: c.scrollHeight, behavior: smooth ? 'smooth' : 'auto' });
    }

    /* ── Helpers ─────────────────────────────────────────────────────────── */

    _appendRawHTML(html) {
        const c = this.messagesContainer;
        if (!c) return;
        const wasAtBottom = (c.scrollHeight - c.clientHeight - c.scrollTop) < 80;
        const tmp = document.createElement('div');
        tmp.innerHTML = html;
        while (tmp.firstChild) c.appendChild(tmp.firstChild);
        if (wasAtBottom) this.scrollToBottom();
    }

    _buildUploadPlaceholder(id, name) {
        return `<div id="${id}" class="d-flex justify-content-end mb-2">
            <div class="chat-msg chat-msg-mine">
                <div class="chat-msg-body">
                    <i class="bi bi-arrow-up-circle me-1"></i>Uploading ${this._esc(name)}…
                </div>
            </div>
        </div>`;
    }

    _timeAgo(dateStr) {
        if (!dateStr) return '';
        const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
        if (diff < 5)    return 'just now';
        if (diff < 60)   return diff + 's ago';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400)return Math.floor(diff / 3600) + 'h ago';
        return Math.floor(diff / 86400) + 'd ago';
    }

    _formatSize(bytes) {
        if (bytes < 1024)       return bytes + ' B';
        if (bytes < 1048576)    return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    _esc(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    _escAttr(str) { return this._esc(str); }
}
