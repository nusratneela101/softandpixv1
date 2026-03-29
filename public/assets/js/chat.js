/**
 * ChatManager — SoftandPix Chat UI Controller
 *
 * Usage:
 *   var chat = new ChatManager({ userId, csrfToken, basePath, initialLastId });
 *   chat.startPolling(convId);
 *   chat.scrollToBottom();
 *   chat.bindAgreementButtons();
 */
(function (global) {
    'use strict';

    /**
     * @param {object} opts
     * @param {number} opts.userId
     * @param {string} opts.csrfToken
     * @param {string} opts.basePath   e.g. '' or '/admin'
     * @param {number} opts.initialLastId
     * @param {string} [opts.currentLang]  e.g. 'en', 'bn', 'fr'
     * @param {string} [opts.strings]  i18n strings object
     */
    function ChatManager(opts) {
        this.userId         = opts.userId     || 0;
        this.csrfToken      = opts.csrfToken  || '';
        this.basePath       = opts.basePath   || '';
        this.lastId         = opts.initialLastId || 0;
        this.currentLang    = opts.currentLang || 'en';
        this.strings        = opts.strings    || {};
        this.convId         = 0;
        this._pollTimer     = null;
        this._autoTranslate = false;

        // Restore auto-translate preference from localStorage
        var savedAT = localStorage.getItem('chat_auto_translate');
        if (savedAT === '1') {
            this._autoTranslate = true;
        }
    }

    // ─── i18n helper ───────────────────────────────────────────────────
    ChatManager.prototype.t = function (key, fallback) {
        return this.strings[key] || fallback || key;
    };

    // ─── Polling ───────────────────────────────────────────────────────
    ChatManager.prototype.startPolling = function (convId) {
        this.convId = convId;
        var self = this;
        if (this._pollTimer) clearInterval(this._pollTimer);
        this._poll();
        this._pollTimer = setInterval(function () { self._poll(); }, 3000);
    };

    ChatManager.prototype.stopPolling = function () {
        if (this._pollTimer) {
            clearInterval(this._pollTimer);
            this._pollTimer = null;
        }
    };

    ChatManager.prototype._poll = function () {
        var self   = this;
        var params = 'conversation_id=' + encodeURIComponent(this.convId)
                   + '&last_id='        + encodeURIComponent(this.lastId)
                   + '&auto_translate=' + (this._autoTranslate ? '1' : '0')
                   + '&target_lang='    + encodeURIComponent(this.currentLang);

        fetch('/chat/fetch.php?' + params, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success || !data.messages || !data.messages.length) return;
                data.messages.forEach(function (msg) {
                    self._appendMessage(msg);
                    if (msg.id > self.lastId) self.lastId = msg.id;
                });
                self.scrollToBottom();
            })
            .catch(function (err) { /* silent — keep polling */ });
    };

    // ─── Render a message ──────────────────────────────────────────────
    ChatManager.prototype._appendMessage = function (msg) {
        var msgEl = document.getElementById('msg_' + msg.id);
        if (msgEl) return; // already rendered

        var isMine = (parseInt(msg.sender_id) === this.userId)
                  || (this.userId === 0 && parseInt(msg.sender_id) === 0);
        var isBot  = (msg.sender_role === 'bot');

        var wrapper = document.createElement('div');
        wrapper.id = 'msg_' + msg.id;
        wrapper.className = 'd-flex ' + (isMine ? 'justify-content-end' : 'justify-content-start') + ' mb-2';

        var bubbleClass = isMine
            ? 'chat-msg chat-msg-mine'
            : (isBot ? 'chat-msg chat-msg-bot' : 'chat-msg chat-msg-other');

        var innerHtml = '<div class="' + bubbleClass + '">';

        // Sender name (others only)
        if (!isMine) {
            if (isBot) {
                innerHtml += '<div class="chat-msg-bot-header chat-msg-name">'
                    + '<span>🤖</span><span class="chat-bot-badge">BOT</span>'
                    + this._esc(msg.sender_name || '')
                    + '</div>';
            } else {
                innerHtml += '<div class="chat-msg-name">' + this._esc(msg.sender_name || '') + '</div>';
            }
        }

        // Message body
        innerHtml += '<div class="chat-msg-body">';
        var type = msg.message_type || 'text';
        if (type === 'image') {
            innerHtml += '<a href="/' + this._esc(msg.file_path || '') + '" target="_blank">'
                + '<img src="/' + this._esc(msg.file_path || '') + '" alt="' + this._esc(msg.file_name || '') + '"'
                + ' style="max-width:200px;max-height:180px;border-radius:8px;cursor:pointer;"></a>';
        } else if (type === 'file') {
            var fileColor = isMine ? 'text-white' : '';
            innerHtml += '<a href="/' + this._esc(msg.file_path || '') + '" download'
                + ' class="d-flex align-items-center gap-2 text-decoration-none ' + fileColor + '">'
                + '<i class="bi bi-file-earmark-arrow-down fs-4"></i>'
                + '<span>' + this._esc(msg.file_name || msg.message || '') + '</span></a>';
        } else if (type === 'agreement') {
            innerHtml += this._renderAgreement(msg, isMine);
        } else {
            innerHtml += this._nl2br(this._esc(msg.message || ''));
        }
        innerHtml += '</div>'; // .chat-msg-body

        // Translate area (only for others' text messages)
        if (!isMine && ['text', 'ai', ''].indexOf(type) !== -1 && msg.message) {
            innerHtml += this._renderTranslateArea(msg);
        }

        // Timestamp
        var timeStr = msg.time || '';
        innerHtml += '<div class="chat-msg-time">' + this._esc(timeStr) + '</div>';

        innerHtml += '</div>'; // .chat-msg bubble
        wrapper.innerHTML = innerHtml;

        var container = document.getElementById('chatMessages');
        if (container) container.appendChild(wrapper);

        // Bind translate button
        var btn = wrapper.querySelector('.chat-translate-btn');
        if (btn) this._bindTranslateBtn(btn, msg);
    };

    // ─── Translation area HTML ─────────────────────────────────────────
    ChatManager.prototype._renderTranslateArea = function (msg) {
        var html = '<div class="chat-translate-area mt-1">';

        // If auto-translated, show result immediately
        if (msg.translated_text) {
            html += '<div class="chat-translated-text text-muted small fst-italic" style="border-top:1px solid rgba(0,0,0,.08);padding-top:4px;margin-top:4px;">'
                + this._nl2br(this._esc(msg.translated_text))
                + '</div>'
                + '<div class="chat-translate-label" style="font-size:.65rem;color:#94a3b8;margin-top:2px;">'
                + this.t('translated_from', 'Translated from') + ' ' + this._esc(msg.source_lang || '')
                + ' &nbsp;·&nbsp; <a href="#" class="chat-toggle-original" data-msg-id="' + msg.id + '" style="color:#94a3b8;">'
                + this.t('show_original', 'Show original') + '</a></div>';
        } else {
            // Show translate button
            html += '<button class="chat-translate-btn btn btn-link btn-sm p-0 text-muted" data-msg-id="' + msg.id + '" title="' + this.t('translate', 'Translate') + '">'
                + '<i class="bi bi-translate" style="font-size:.75rem;"></i> '
                + '<span style="font-size:.72rem;">' + this.t('translate', 'Translate') + '</span>'
                + '</button>'
                + '<span class="chat-translating-indicator d-none" style="font-size:.72rem;color:#94a3b8;">'
                + this.t('translating', 'Translating…') + '</span>'
                + '<div class="chat-translated-text d-none text-muted small fst-italic" style="border-top:1px solid rgba(0,0,0,.08);padding-top:4px;margin-top:4px;"></div>'
                + '<div class="chat-translate-label d-none" style="font-size:.65rem;color:#94a3b8;margin-top:2px;"></div>';
        }

        html += '</div>';
        return html;
    };

    // ─── Bind translate button click ───────────────────────────────────
    ChatManager.prototype._bindTranslateBtn = function (btn, msg) {
        var self = this;
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            self.translateMessage(msg.id, msg.message, btn.closest('.chat-translate-area'));
        });
    };

    // ─── Translate a specific message ──────────────────────────────────
    ChatManager.prototype.translateMessage = function (msgId, msgText, areaEl) {
        var self        = this;
        var indicator   = areaEl ? areaEl.querySelector('.chat-translating-indicator') : null;
        var btn         = areaEl ? areaEl.querySelector('.chat-translate-btn') : null;
        var resultEl    = areaEl ? areaEl.querySelector('.chat-translated-text') : null;
        var labelEl     = areaEl ? areaEl.querySelector('.chat-translate-label') : null;

        if (btn)       btn.classList.add('d-none');
        if (indicator) indicator.classList.remove('d-none');

        var fd = new FormData();
        fd.append('csrf_token', this.csrfToken);
        fd.append('message_id', msgId);
        fd.append('target_lang', this.currentLang);

        fetch('/chat/translate.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (indicator) indicator.classList.add('d-none');
                if (!data.success) {
                    if (btn) {
                        btn.classList.remove('d-none');
                        btn.title = self.t('translation_failed', 'Translation failed');
                    }
                    return;
                }
                if (resultEl) {
                    resultEl.innerHTML = self._nl2br(self._esc(data.translated || ''));
                    resultEl.classList.remove('d-none');
                }
                if (labelEl) {
                    var srcLang = data.source_lang || '';
                    labelEl.innerHTML = self.t('translated_from', 'Translated from') + ' '
                        + self._esc(srcLang)
                        + ' &nbsp;·&nbsp; <a href="#" class="chat-toggle-original" data-msg-id="'
                        + msgId + '" style="color:#94a3b8;">'
                        + self.t('show_original', 'Show original') + '</a>';
                    labelEl.classList.remove('d-none');
                    // Bind "show original" toggle
                    var toggleLink = labelEl.querySelector('.chat-toggle-original');
                    if (toggleLink) {
                        self._bindShowOriginalToggle(toggleLink, msgText, resultEl, labelEl);
                    }
                }
            })
            .catch(function () {
                if (indicator) indicator.classList.add('d-none');
                if (btn) btn.classList.remove('d-none');
            });
    };

    // ─── Show original / translation toggle ───────────────────────────
    ChatManager.prototype._bindShowOriginalToggle = function (link, originalText, resultEl, labelEl) {
        var self       = this;
        var showingOrig = false;
        link.addEventListener('click', function (e) {
            e.preventDefault();
            showingOrig = !showingOrig;
            if (showingOrig) {
                resultEl.innerHTML = self._nl2br(self._esc(originalText));
                link.textContent = self.t('show_translation', 'Show translation');
            } else {
                // Re-fetch from data stored in resultEl's data- attribute or re-call
                // For simplicity, re-translate
                link.click(); // will be handled by translateMessage again; just toggle text
                link.textContent = self.t('show_original', 'Show original');
            }
        });
    };

    // ─── Auto-translate toggle ─────────────────────────────────────────
    ChatManager.prototype.setAutoTranslate = function (enabled) {
        this._autoTranslate = !!enabled;
        localStorage.setItem('chat_auto_translate', enabled ? '1' : '0');
        // Notify server via next poll (auto_translate param is passed in _poll)
    };

    ChatManager.prototype.isAutoTranslate = function () {
        return this._autoTranslate;
    };

    // ─── Translate all existing messages on the page ───────────────────
    ChatManager.prototype.translateExistingMessages = function () {
        var self = this;
        document.querySelectorAll('.chat-translate-btn').forEach(function (btn) {
            var msgId = btn.getAttribute('data-msg-id');
            if (msgId) {
                var area = btn.closest('.chat-translate-area');
                // Get the original text from the message body
                var msgEl    = document.getElementById('msg_' + msgId);
                var bodyEl   = msgEl ? msgEl.querySelector('.chat-msg-body') : null;
                var msgText  = bodyEl ? (bodyEl.textContent || '').trim() : '';
                self.translateMessage(parseInt(msgId), msgText, area);
            }
        });
    };

    // ─── Agreement buttons ─────────────────────────────────────────────
    ChatManager.prototype.bindAgreementButtons = function () {
        var self = this;
        document.addEventListener('click', function (e) {
            var approveBtn = e.target.closest('.approve-agreement-btn');
            var rejectBtn  = e.target.closest('.reject-agreement-btn');
            if (approveBtn) {
                e.preventDefault();
                self._respondAgreement(approveBtn.getAttribute('data-agreement-id'), 'approved');
            } else if (rejectBtn) {
                e.preventDefault();
                self._respondAgreement(rejectBtn.getAttribute('data-agreement-id'), 'rejected');
            }
        });
    };

    ChatManager.prototype._respondAgreement = function (agreementId, status) {
        var fd = new FormData();
        fd.append('csrf_token', this.csrfToken);
        fd.append('agreement_id', agreementId);
        fd.append('status', status);

        fetch('/api/chat/respond_agreement.php', {
            method: 'POST', body: fd, credentials: 'same-origin'
        }).then(function (r) { return r.json(); })
          .then(function (data) {
              if (data.success) location.reload();
          })
          .catch(function () {});
    };

    // ─── Send a text message ───────────────────────────────────────────
    ChatManager.prototype.sendMessage = function (convId, text) {
        var fd = new FormData();
        fd.append('csrf_token', this.csrfToken);
        fd.append('conversation_id', convId);
        fd.append('message', text);

        return fetch('/chat/send.php', { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(function (r) { return r.json(); });
    };

    // ─── Scroll helpers ────────────────────────────────────────────────
    ChatManager.prototype.scrollToBottom = function () {
        var el = document.getElementById('chatMessages');
        if (el) el.scrollTop = el.scrollHeight;
    };

    // ─── Utility ──────────────────────────────────────────────────────
    ChatManager.prototype._esc = function (str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    };

    ChatManager.prototype._nl2br = function (str) {
        return str.replace(/\n/g, '<br>');
    };

    // ─── Agreement card renderer ───────────────────────────────────────
    ChatManager.prototype._renderAgreement = function (msg, isMine) {
        var status = msg.agreement_status || 'pending';
        var html = '<div class="agreement-card border border-success rounded p-3 my-1">'
            + '<div class="d-flex align-items-center mb-2">'
            + '<i class="bi bi-file-earmark-text text-success me-2 fs-5"></i>'
            + '<strong class="text-success">Agreement Paper</strong></div>'
            + '<div class="agreement-content small" style="white-space:pre-wrap;">'
            + this._nl2br(this._esc(msg.message || '')) + '</div>';

        if (status === 'approved') {
            html += '<div class="mt-2"><span class="badge bg-success fs-6 px-3 py-2">✅ Approved</span></div>';
        } else if (status === 'rejected') {
            html += '<div class="mt-2"><span class="badge bg-danger fs-6 px-3 py-2">❌ Rejected</span></div>';
        } else if (!isMine) {
            html += '<div class="mt-3 d-flex gap-2 flex-wrap">'
                + '<button class="btn btn-success btn-sm approve-agreement-btn" data-agreement-id="' + (msg.agreement_id || '') + '">'
                + '<i class="bi bi-check-circle me-1"></i>Approve Agreement</button>'
                + '<button class="btn btn-danger btn-sm reject-agreement-btn" data-agreement-id="' + (msg.agreement_id || '') + '">'
                + '<i class="bi bi-x-circle me-1"></i>Reject</button></div>';
        }
        html += '</div>';
        return html;
    };

    global.ChatManager = ChatManager;

}(window));
