<?php
/**
 * Push Notification Settings UI Card
 *
 * Include this file in any page where you want to show push notification toggle.
 * E.g., profile.php, admin/push_settings.php, notifications pages
 */
?>
<!-- Push Notification Settings Card -->
<div class="card mb-4" id="push-notification-settings">
    <div class="card-header">
        <i class="fas fa-bell me-2"></i><?= __('push_notifications') ?>
    </div>
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <h6 class="mb-1"><?= __('push_settings') ?></h6>
                <small class="text-muted"><?= __('push_permission_prompt') ?></small>
            </div>
            <div class="form-check form-switch">
                <input class="form-check-input push-toggle" type="checkbox" id="pushToggle" style="width:3em;height:1.5em;">
            </div>
        </div>
        <div id="push-status" class="small text-muted"></div>
        <button class="btn btn-sm btn-outline-primary mt-2 d-none" id="pushTestBtn">
            <i class="fas fa-paper-plane me-1"></i><?= __('push_test') ?>
        </button>
    </div>
</div>
<script src="<?= e(BASE_URL ?? '') ?>/public/assets/js/push-notifications.js"></script>
