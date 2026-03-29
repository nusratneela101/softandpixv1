 * VideoCallManager — WebRTC mesh-topology video call manager.
 * Handles peer connections, signaling, screen sharing, chat, and UI updates.
 */
class VideoCallManager {
    constructor(options = {}) {
        this.signalingUrl = options.signalingUrl || '/video/signaling.php';
        this.roomId       = options.roomId || 0;
        this.roomCode     = options.roomCode || '';
        this.userId       = options.userId || 0;
        this.userName     = options.userName || 'User';

        this.localStream  = null;
        this.screenStream = null;
        this.peers        = new Map(); // userId → RTCPeerConnection
        this.remoteStreams = new Map(); // userId → MediaStream
        this.participantNames = new Map(); // userId → name

        this.isMuted         = false;
        this.isCameraOff     = false;
        this.isScreenSharing = false;
        this.chatLastId      = 0;
        this.unreadChat      = 0;

        this.pollSignalTimer = null;
        this.pollChatTimer   = null;
        this.durationTimer   = null;
        this.startTime       = null;

        this.iceConfig = {
            iceServers: [
                { urls: 'stun:stun.l.google.com:19302' },
                { urls: 'stun:stun1.l.google.com:19302' },
                { urls: 'stun:stun2.l.google.com:19302' }
            ]
        };

        this._bindKeyboard();
    }

    // ------------------------------------------------------------------
    // Initialization
    // ------------------------------------------------------------------

    async init() {
        try {
            this.localStream = await navigator.mediaDevices.getUserMedia({
                video: { width: { ideal: 1280 }, height: { ideal: 720 } },
                audio: true
            });
            this._displayLocalVideo();
            return true;
        } catch (err) {
            console.error('getUserMedia error:', err);
            // Try audio-only
            try {
                this.localStream = await navigator.mediaDevices.getUserMedia({ audio: true });
                this.isCameraOff = true;
                this._displayLocalVideo();
                this._updateCameraBtn();
                return true;
            } catch (err2) {
                alert('Cannot access camera or microphone. Please allow permissions.');
                return false;
            }
        }
    }

    joinRoom(roomCode) {
        this.roomCode = roomCode;
        const fd = new FormData();
        fd.append('action', 'join_room');
        fd.append('room_code', roomCode);

        const pwdInput = document.getElementById('joinPassword');
        if (pwdInput && pwdInput.value) fd.append('password', pwdInput.value);

        return fetch(this.signalingUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) throw new Error(data.error || 'Join failed');
                this.roomId = data.room.id;
                this.startTime = Date.now();
                this._startDurationTimer();
                this._startPolling();
                return data.room;
            });
    }

    // ------------------------------------------------------------------
    // Peer connection management
    // ------------------------------------------------------------------

    handleNewParticipant(userId, userName) {
        if (this.peers.has(userId)) return;
        this.participantNames.set(userId, userName);

        const pc = this._createPeerConnection(userId);
        this.peers.set(userId, pc);

        // Add local tracks
        if (this.localStream) {
            this.localStream.getTracks().forEach(track => {
                pc.addTrack(track, this.localStream);
            });
        }

        // Create and send offer
        pc.createOffer()
            .then(offer => pc.setLocalDescription(offer))
            .then(() => {
                this._sendSignal(userId, 'offer', JSON.stringify(pc.localDescription));
            })
            .catch(err => console.error('Offer error:', err));
    }

    handleOffer(userId, offer) {
        let pc = this.peers.get(userId);
        if (!pc) {
            pc = this._createPeerConnection(userId);
            this.peers.set(userId, pc);
            if (this.localStream) {
                this.localStream.getTracks().forEach(track => {
                    pc.addTrack(track, this.localStream);
                });
            }
        }

        pc.setRemoteDescription(new RTCSessionDescription(JSON.parse(offer)))
            .then(() => pc.createAnswer())
            .then(answer => pc.setLocalDescription(answer))
            .then(() => {
                this._sendSignal(userId, 'answer', JSON.stringify(pc.localDescription));
            })
            .catch(err => console.error('Answer error:', err));
    }

    handleAnswer(userId, answer) {
        const pc = this.peers.get(userId);
        if (pc) {
            pc.setRemoteDescription(new RTCSessionDescription(JSON.parse(answer)))
                .catch(err => console.error('setRemoteDescription error:', err));
        }
    }

    handleIceCandidate(userId, candidate) {
        const pc = this.peers.get(userId);
        if (pc) {
            pc.addIceCandidate(new RTCIceCandidate(JSON.parse(candidate)))
                .catch(err => console.error('addIceCandidate error:', err));
        }
    }

    handleParticipantLeft(userId) {
        const pc = this.peers.get(userId);
        if (pc) {
            pc.close();
            this.peers.delete(userId);
        }
        this.remoteStreams.delete(userId);
        this.participantNames.delete(userId);

        const tile = document.getElementById('video-tile-' + userId);
        if (tile) tile.remove();

        this.updateVideoGrid();
        this._updateParticipantCount();
    }

    // ------------------------------------------------------------------
    // Media controls
    // ------------------------------------------------------------------

    toggleMic() {
        if (!this.localStream) return;
        const audioTrack = this.localStream.getAudioTracks()[0];
        if (!audioTrack) return;

        this.isMuted = !this.isMuted;
        audioTrack.enabled = !this.isMuted;
        this._updateMicBtn();

        const fd = new FormData();
        fd.append('action', 'toggle_mute');
        fd.append('room_id', this.roomId);
        fd.append('is_muted', this.isMuted ? 1 : 0);
        fetch(this.signalingUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
    }

    toggleCamera() {
        if (!this.localStream) return;
        const videoTrack = this.localStream.getVideoTracks()[0];
        if (!videoTrack) return;

        this.isCameraOff = !this.isCameraOff;
        videoTrack.enabled = !this.isCameraOff;
        this._updateCameraBtn();

        const fd = new FormData();
        fd.append('action', 'toggle_video');
        fd.append('room_id', this.roomId);
        fd.append('is_video_on', this.isCameraOff ? 0 : 1);
        fetch(this.signalingUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
    }

    async toggleScreenShare() {
        if (this.isScreenSharing) {
            // Stop sharing
            if (this.screenStream) {
                this.screenStream.getTracks().forEach(t => t.stop());
                this.screenStream = null;
            }
            // Replace with camera track
            const videoTrack = this.localStream.getVideoTracks()[0];
            if (videoTrack) {
                this.peers.forEach(pc => {
                    const sender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
                    if (sender) sender.replaceTrack(videoTrack);
                });
            }
            this.isScreenSharing = false;
        } else {
            try {
                this.screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
                const screenTrack = this.screenStream.getVideoTracks()[0];

                // Replace video track on all peers
                this.peers.forEach(pc => {
                    const sender = pc.getSenders().find(s => s.track && s.track.kind === 'video');
                    if (sender) sender.replaceTrack(screenTrack);
                });

                // Handle browser stop button
                screenTrack.onended = () => {
                    this.toggleScreenShare();
                };

                this.isScreenSharing = true;
            } catch (err) {
                console.log('Screen share cancelled');
                return;
            }
        }

        this._updateScreenBtn();

        const fd = new FormData();
        fd.append('action', 'toggle_screen');
        fd.append('room_id', this.roomId);
        fd.append('is_screen_sharing', this.isScreenSharing ? 1 : 0);
        fetch(this.signalingUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
    }

    // ------------------------------------------------------------------
    // Chat
    // ------------------------------------------------------------------

    sendChatMessage(text) {
        if (!text.trim()) return;
        const fd = new FormData();
        fd.append('action', 'send_chat');
        fd.append('room_id', this.roomId);
        fd.append('message', text.trim());
        fetch(this.signalingUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
    }

    // ------------------------------------------------------------------
    // Polling
    // ------------------------------------------------------------------

    _startPolling() {
        this.pollSignalTimer = setInterval(() => this.pollSignals(), 1000);
        this.pollChatTimer   = setInterval(() => this.pollChat(), 2000);
    }

    pollSignals() {
        fetch(`${this.signalingUrl}?action=get_signals&room_id=${this.roomId}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.success) return;
                data.signals.forEach(sig => {
                    const fromUser = parseInt(sig.from_user);
                    const sigData  = sig.signal_data;
                    switch (sig.signal_type) {
                        case 'join':
                            const joinData = JSON.parse(sigData);
                            this.participantNames.set(fromUser, joinData.user_name || 'User');
                            this.handleNewParticipant(fromUser, joinData.user_name || 'User');
                            this._updateParticipantCount();
                            break;
                        case 'leave':
                            const leaveData = JSON.parse(sigData);
                            this.handleParticipantLeft(fromUser);
                            if (leaveData.ended) {
                                this._onRoomEnded();
                            }
                            break;
                        case 'offer':
                            this.handleOffer(fromUser, sigData);
                            break;
                        case 'answer':
                            this.handleAnswer(fromUser, sigData);
                            break;
                        case 'ice-candidate':
                            this.handleIceCandidate(fromUser, sigData);
                            break;
                        case 'mute':
                        case 'unmute':
                            this._updateRemoteMuteStatus(fromUser, sig.signal_type === 'mute');
                            break;
                        case 'screen-start':
                        case 'screen-stop':
                            break;
                    }
                });
            })
            .catch(() => {});
    }

    pollChat() {
        fetch(`${this.signalingUrl}?action=get_chat&room_id=${this.roomId}&last_id=${this.chatLastId}`, { credentials: 'same-origin' })
            .then(r => r.json())
            .then(data => {
                if (!data.success || !data.messages.length) return;
                data.messages.forEach(msg => {
                    this._appendChatMessage(msg);
                    this.chatLastId = Math.max(this.chatLastId, parseInt(msg.id));
                });
                // Update unread badge if panel is closed
                const chatPanel = document.getElementById('chatPanel');
                if (!chatPanel || !chatPanel.classList.contains('open')) {
                    this.unreadChat += data.messages.length;
                    this._updateChatBadge();
                }
            })
            .catch(() => {});
    }

    // ------------------------------------------------------------------
    // End call
    // ------------------------------------------------------------------

    endCall(isHost = false) {
        // Stop polling
        clearInterval(this.pollSignalTimer);
        clearInterval(this.pollChatTimer);
        clearInterval(this.durationTimer);

        // Stop all tracks
        if (this.localStream) {
            this.localStream.getTracks().forEach(t => t.stop());
        }
        if (this.screenStream) {
            this.screenStream.getTracks().forEach(t => t.stop());
        }

        // Close all peer connections
        this.peers.forEach(pc => pc.close());
        this.peers.clear();

        // Send leave signal
        const fd = new FormData();
        fd.append('action', isHost ? 'end_room' : 'leave_room');
        fd.append('room_code', this.roomCode);
        fetch(this.signalingUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
            .finally(() => {
                // Redirect based on role
                const role = document.body.dataset.userRole || 'client';
                if (role === 'admin') {
                    window.location.href = '/admin/video_call.php';
                } else if (role === 'developer') {
                    window.location.href = '/developer/video_call.php';
                } else {
                    window.location.href = '/client/video_call.php';
                }
            });
    }

    // ------------------------------------------------------------------
    // Video grid management
    // ------------------------------------------------------------------

    updateVideoGrid() {
        const grid = document.getElementById('videoGrid');
        if (!grid) return;
        const tiles = grid.querySelectorAll('.video-tile:not(.local-pip)');
        const count = tiles.length;
        grid.className = 'video-grid grid-' + Math.min(count, 6);
    }

    // ------------------------------------------------------------------
    // Private methods
    // ------------------------------------------------------------------

    _createPeerConnection(userId) {
        const pc = new RTCPeerConnection(this.iceConfig);

        pc.onicecandidate = (event) => {
            if (event.candidate) {
                this._sendSignal(userId, 'ice-candidate', JSON.stringify(event.candidate));
            }
        };

        pc.ontrack = (event) => {
            const stream = event.streams[0];
            if (!stream) return;
            this.remoteStreams.set(userId, stream);
            this._addRemoteVideoTile(userId, stream);
        };

        pc.oniceconnectionstatechange = () => {
            const tile = document.getElementById('video-tile-' + userId);
            if (!tile) return;

            const dot = tile.querySelector('.connection-dot');
            const reconnecting = tile.querySelector('.reconnecting-overlay');

            switch (pc.iceConnectionState) {
                case 'connected':
                case 'completed':
                    if (dot) dot.className = 'connection-dot good';
                    if (reconnecting) reconnecting.style.display = 'none';
                    break;
                case 'checking':
                case 'disconnected':
                    if (dot) dot.className = 'connection-dot moderate';
                    if (reconnecting) {
                        reconnecting.style.display = 'flex';
                        reconnecting.textContent = 'Reconnecting...';
                    }
                    break;
                case 'failed':
                    if (dot) dot.className = 'connection-dot poor';
                    if (reconnecting) {
                        reconnecting.style.display = 'flex';
                        reconnecting.textContent = 'Connection failed';
                    }
                    break;
            }
        };

        return pc;
    }

    _sendSignal(toUser, signalType, signalData) {
        const fd = new FormData();
        fd.append('action', 'send_signal');
        fd.append('room_id', this.roomId);
        fd.append('to_user', toUser);
        fd.append('signal_type', signalType);
        fd.append('signal_data', signalData);
        fetch(this.signalingUrl, { method: 'POST', body: fd, credentials: 'same-origin' });
    }

    _displayLocalVideo() {
        const container = document.getElementById('videoGrid');
        if (!container) return;

        // PiP local tile
        let tile = document.getElementById('localVideoTile');
        if (!tile) {
            tile = document.createElement('div');
            tile.id = 'localVideoTile';
            tile.className = 'video-tile local-pip';
            tile.innerHTML = `
                <video id="localVideo" autoplay playsinline muted></video>
                <div class="tile-overlay">
                    <span class="tile-name">${this._escapeHtml(this.userName)} (You)</span>
                </div>
            `;
            document.querySelector('.video-grid-area').appendChild(tile);
        }

        const videoEl = document.getElementById('localVideo');
        if (videoEl && this.localStream) {
            videoEl.srcObject = this.localStream;
        }
    }

    _addRemoteVideoTile(userId, stream) {
        const grid = document.getElementById('videoGrid');
        if (!grid) return;

        let tile = document.getElementById('video-tile-' + userId);
        if (!tile) {
            const name = this.participantNames.get(userId) || 'User';
            tile = document.createElement('div');
            tile.id = 'video-tile-' + userId;
            tile.className = 'video-tile';
            tile.innerHTML = `
                <div class="live-badge"><span class="pulse-dot"></span> LIVE</div>
                <div class="buffering-overlay"><div class="spinner"></div><span class="buff-text">Buffering...</span></div>
                <div class="reconnecting-overlay" style="display:none;"></div>
                <div class="no-video-placeholder" style="display:none;">
                    <div class="avatar-circle">${name.charAt(0).toUpperCase()}</div>
                    <div class="avatar-name">${this._escapeHtml(name)}</div>
                </div>
                <video autoplay playsinline></video>
                <div class="tile-overlay">
                    <span class="tile-name">${this._escapeHtml(name)}</span>
                    <span class="tile-indicators">
                        <span class="mute-icon" style="display:none;"><i class="fas fa-microphone-slash"></i></span>
                        <span class="connection-dot good"></span>
                    </span>
                </div>
            `;
            grid.appendChild(tile);
        }

        const videoEl = tile.querySelector('video');
        if (videoEl) {
            videoEl.srcObject = stream;

            // Buffering/LIVE handling
            const buffOverlay = tile.querySelector('.buffering-overlay');
            const liveBadge   = tile.querySelector('.live-badge');

            if (buffOverlay) buffOverlay.style.display = 'flex';
            if (liveBadge) liveBadge.style.display = 'none';

            videoEl.addEventListener('loadeddata', () => {
                if (buffOverlay) buffOverlay.style.display = 'none';
                if (liveBadge) liveBadge.style.display = 'flex';
            });

            // Detect frozen video (no frames for 3+ seconds)
            let lastTime = 0;
            const frozenCheck = setInterval(() => {
                if (videoEl.readyState >= 2) {
                    if (videoEl.currentTime === lastTime && !videoEl.paused) {
                        if (buffOverlay) {
                            buffOverlay.style.display = 'flex';
                            buffOverlay.querySelector('.buff-text').textContent = 'Connection issues...';
                        }
                    } else {
                        if (buffOverlay) buffOverlay.style.display = 'none';
                    }
                    lastTime = videoEl.currentTime;
                }
            }, 3000);

            // Clean up on tile removal
            const observer = new MutationObserver(() => {
                if (!document.contains(tile)) {
                    clearInterval(frozenCheck);
                    observer.disconnect();
                }
            });
            observer.observe(grid, { childList: true });
        }

        this.updateVideoGrid();
        this._updateParticipantCount();
    }

    _updateRemoteMuteStatus(userId, isMuted) {
        const tile = document.getElementById('video-tile-' + userId);
        if (!tile) return;
        const icon = tile.querySelector('.mute-icon');
        if (icon) icon.style.display = isMuted ? 'inline' : 'none';
    }

    _appendChatMessage(msg) {
        const container = document.getElementById('chatMessages');
        if (!container) return;

        const isOwn = parseInt(msg.user_id) === this.userId;
        const div = document.createElement('div');
        div.className = 'chat-msg ' + (isOwn ? 'own' : 'other');

        const time = msg.created_at ? new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';
        div.innerHTML = `
            ${!isOwn ? `<div class="msg-sender">${this._escapeHtml(msg.user_name || 'User')}</div>` : ''}
            <div>${this._escapeHtml(msg.message)}</div>
            <div class="msg-time">${time}</div>
        `;

        container.appendChild(div);
        container.scrollTop = container.scrollHeight;
    }

    _updateMicBtn() {
        const btn = document.getElementById('btnMic');
        if (!btn) return;
        if (this.isMuted) {
            btn.className = 'ctrl-btn active-off';
            btn.innerHTML = '<i class="fas fa-microphone-slash"></i><span class="shortcut-hint">M</span>';
        } else {
            btn.className = 'ctrl-btn active-on';
            btn.innerHTML = '<i class="fas fa-microphone"></i><span class="shortcut-hint">M</span>';
        }
    }

    _updateCameraBtn() {
        const btn = document.getElementById('btnCamera');
        if (!btn) return;
        if (this.isCameraOff) {
            btn.className = 'ctrl-btn active-off';
            btn.innerHTML = '<i class="fas fa-video-slash"></i><span class="shortcut-hint">V</span>';
        } else {
            btn.className = 'ctrl-btn active-on';
            btn.innerHTML = '<i class="fas fa-video"></i><span class="shortcut-hint">V</span>';
        }
    }

    _updateScreenBtn() {
        const btn = document.getElementById('btnScreen');
        if (!btn) return;
        if (this.isScreenSharing) {
            btn.className = 'ctrl-btn screen-active';
            btn.innerHTML = '<i class="fas fa-desktop"></i><span class="shortcut-hint">S</span>';
        } else {
            btn.className = 'ctrl-btn active-on';
            btn.innerHTML = '<i class="fas fa-desktop"></i><span class="shortcut-hint">S</span>';
        }
    }

    _updateChatBadge() {
        const badge = document.getElementById('chatBadge');
        if (!badge) return;
        if (this.unreadChat > 0) {
            badge.textContent = this.unreadChat;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }

    _updateParticipantCount() {
        const el = document.getElementById('participantCount');
        if (el) {
            const count = this.peers.size + 1;
            el.textContent = count;
        }
    }

    _startDurationTimer() {
        const el = document.getElementById('roomTimer');
        if (!el) return;
        this.durationTimer = setInterval(() => {
            const elapsed = Math.floor((Date.now() - this.startTime) / 1000);
            const h = Math.floor(elapsed / 3600).toString().padStart(2, '0');
            const m = Math.floor((elapsed % 3600) / 60).toString().padStart(2, '0');
            const s = (elapsed % 60).toString().padStart(2, '0');
            el.textContent = `${h}:${m}:${s}`;
        }, 1000);
    }

    _onRoomEnded() {
        clearInterval(this.pollSignalTimer);
        clearInterval(this.pollChatTimer);
        clearInterval(this.durationTimer);
        if (this.localStream) this.localStream.getTracks().forEach(t => t.stop());
        if (this.screenStream) this.screenStream.getTracks().forEach(t => t.stop());
        this.peers.forEach(pc => pc.close());
        alert('The host has ended the meeting.');
        const role = document.body.dataset.userRole || 'client';
        if (role === 'admin') {
            window.location.href = '/admin/video_call.php';
        } else if (role === 'developer') {
            window.location.href = '/developer/video_call.php';
        } else {
            window.location.href = '/client/video_call.php';
        }
    }

    _bindKeyboard() {
        document.addEventListener('keydown', (e) => {
            // Don't trigger shortcuts when typing in inputs
            if (['INPUT', 'TEXTAREA', 'SELECT'].includes(e.target.tagName)) return;

            switch (e.key.toUpperCase()) {
                case 'M': this.toggleMic(); break;
                case 'V': this.toggleCamera(); break;
                case 'S': this.toggleScreenShare(); break;
            }
        });
    }

    _escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }
}
