/**
 * SoftandPix Push Notification Manager
 * 
 * Client-side JavaScript to handle Web Push subscriptions.
 * Works with the existing SP namespace from app.js.
 */
(function (SP) {
    'use strict';

    const SPPush = {
        vapidPublicKey: null,
        isInitialized: false,
        swRegistration: null,

        /**
         * Initialize push notification manager
         */
        init: async function () {
            if (this.isInitialized) return;
            this.isInitialized = true;

            if (!this.isSupported()) {
                this.updateUI(false, 'not_supported');
                return;
            }

            try {
                // Get VAPID public key from server
                const response = await fetch('/api/push.php?action=vapid_key');
                const data = await response.json();

                if (!data.success || !data.enabled || !data.public_key) {
                    this.updateUI(false, 'disabled');
                    return;
                }

                this.vapidPublicKey = data.public_key;

                // Get service worker registration
                this.swRegistration = await navigator.serviceWorker.ready;

                // Check current subscription status
                const status = await this.getStatus();
                this.updateUI(status.subscribed, status.subscribed ? 'subscribed' : 'unsubscribed');

            } catch (error) {
                console.error('Push init error:', error);
                this.updateUI(false, 'error');
            }
        },

        /**
         * Check if push notifications are supported
         */
        isSupported: function () {
            return 'serviceWorker' in navigator &&
                   'PushManager' in window &&
                   'Notification' in window;
        },

        /**
         * Request notification permission
         */
        requestPermission: async function () {
            if (!this.isSupported()) {
                return { granted: false, reason: 'not_supported' };
            }

            const permission = await Notification.requestPermission();
            return {
                granted: permission === 'granted',
                reason: permission
            };
        },

        /**
         * Subscribe to push notifications
         */
        subscribe: async function () {
            if (!this.isSupported()) {
                SP.toast && SP.toast('Push notifications are not supported in this browser', 'error');
                return false;
            }

            // Request permission first
            const permResult = await this.requestPermission();
            if (!permResult.granted) {
                if (permResult.reason === 'denied') {
                    SP.toast && SP.toast('Notification permission was denied. Please enable in browser settings.', 'error');
                }
                return false;
            }

            try {
                // Subscribe to push manager
                const subscription = await this.swRegistration.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: this.urlBase64ToUint8Array(this.vapidPublicKey)
                });

                // Send to server
                const response = await fetch('/api/push.php?action=subscribe', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        endpoint: subscription.endpoint,
                        keys: {
                            p256dh: this.arrayBufferToBase64(subscription.getKey('p256dh')),
                            auth: this.arrayBufferToBase64(subscription.getKey('auth'))
                        }
                    }),
                    credentials: 'same-origin'
                });

                const data = await response.json();
                if (data.success) {
                    localStorage.setItem('sp_push_subscribed', 'true');
                    this.updateUI(true, 'subscribed');
                    SP.toast && SP.toast('Push notifications enabled!', 'success');
                    return true;
                }
            } catch (error) {
                console.error('Subscribe error:', error);
                SP.toast && SP.toast('Failed to enable push notifications', 'error');
            }

            return false;
        },

        /**
         * Unsubscribe from push notifications
         */
        unsubscribe: async function () {
            try {
                const subscription = await this.swRegistration.pushManager.getSubscription();
                if (subscription) {
                    // Tell server to remove
                    await fetch('/api/push.php?action=unsubscribe', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ endpoint: subscription.endpoint }),
                        credentials: 'same-origin'
                    });

                    // Unsubscribe locally
                    await subscription.unsubscribe();
                }

                localStorage.removeItem('sp_push_subscribed');
                this.updateUI(false, 'unsubscribed');
                SP.toast && SP.toast('Push notifications disabled', 'info');
                return true;
            } catch (error) {
                console.error('Unsubscribe error:', error);
                SP.toast && SP.toast('Failed to disable push notifications', 'error');
                return false;
            }
        },

        /**
         * Toggle subscription state
         */
        toggle: async function () {
            const status = await this.getStatus();
            if (status.subscribed) {
                return await this.unsubscribe();
            } else {
                return await this.subscribe();
            }
        },

        /**
         * Check current subscription status
         */
        getStatus: async function () {
            try {
                const response = await fetch('/api/push.php?action=status', { credentials: 'same-origin' });
                return await response.json();
            } catch (error) {
                return { success: false, subscribed: false, subscription_count: 0 };
            }
        },

        /**
         * Send test notification
         */
        sendTest: async function () {
            try {
                const response = await fetch('/api/push.php?action=test', {
                    method: 'POST',
                    credentials: 'same-origin'
                });
                const data = await response.json();
                if (data.success && data.sent > 0) {
                    SP.toast && SP.toast('Test notification sent!', 'success');
                } else {
                    SP.toast && SP.toast('No active subscriptions to send to', 'warning');
                }
                return data;
            } catch (error) {
                console.error('Test notification error:', error);
                SP.toast && SP.toast('Failed to send test notification', 'error');
                return { success: false };
            }
        },

        /**
         * Update UI elements
         */
        updateUI: function (subscribed, status) {
            // Update toggle switches
            const toggles = document.querySelectorAll('#pushToggle, .push-toggle');
            toggles.forEach(toggle => {
                toggle.checked = subscribed;
                toggle.disabled = (status === 'not_supported' || status === 'disabled');
            });

            // Update status text
            const statusEl = document.getElementById('push-status');
            if (statusEl) {
                const messages = {
                    'not_supported': 'Push notifications are not supported in this browser',
                    'disabled': 'Push notifications are not configured on this server',
                    'subscribed': 'Push notifications are enabled',
                    'unsubscribed': 'Push notifications are disabled',
                    'error': 'Unable to check push notification status'
                };
                statusEl.textContent = messages[status] || '';
                statusEl.className = 'small ' + (subscribed ? 'text-success' : 'text-muted');
            }

            // Show/hide test button
            const testBtn = document.getElementById('pushTestBtn');
            if (testBtn) {
                testBtn.classList.toggle('d-none', !subscribed);
            }
        },

        /**
         * Convert VAPID key from base64url to Uint8Array
         */
        urlBase64ToUint8Array: function (base64String) {
            const padding = '='.repeat((4 - base64String.length % 4) % 4);
            const base64 = (base64String + padding)
                .replace(/\-/g, '+')
                .replace(/_/g, '/');

            const rawData = window.atob(base64);
            const outputArray = new Uint8Array(rawData.length);

            for (let i = 0; i < rawData.length; ++i) {
                outputArray[i] = rawData.charCodeAt(i);
            }
            return outputArray;
        },

        /**
         * Convert ArrayBuffer to base64
         */
        arrayBufferToBase64: function (buffer) {
            const bytes = new Uint8Array(buffer);
            let binary = '';
            for (let i = 0; i < bytes.byteLength; i++) {
                binary += String.fromCharCode(bytes[i]);
            }
            return window.btoa(binary)
                .replace(/\+/g, '-')
                .replace(/\//g, '_')
                .replace(/=+$/, '');
        }
    };

    // Expose to SP namespace
    SP.Push = SPPush;

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function () {
        SP.Push.init();

        // Bind toggle switch events
        document.querySelectorAll('#pushToggle, .push-toggle').forEach(toggle => {
            toggle.addEventListener('change', function () {
                SP.Push.toggle();
            });
        });

        // Bind test button
        const testBtn = document.getElementById('pushTestBtn');
        if (testBtn) {
            testBtn.addEventListener('click', function () {
                SP.Push.sendTest();
            });
        }
    });

}(window.SP = window.SP || {}));
