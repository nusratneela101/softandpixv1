<?php
/**
 * includes/live-chat-widget.php
 * Include this file in any public page to activate the floating chat widget.
 *
 * Usage:
 *   <?php include 'includes/live-chat-widget.php'; ?>
 *   (place just before </body>)
 */

// Auto-detect base URL — works on shared hosting regardless of subfolder
$lcw_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$lcw_host     = $_SERVER['HTTP_HOST'] ?? 'localhost';
$lcw_base     = $lcw_protocol . '://' . $lcw_host;
?>
<link rel="stylesheet" href="<?php echo $lcw_base; ?>/assets/css/live-chat-widget.css">
<script src="<?php echo $lcw_base; ?>/assets/js/live-chat-widget.js" defer></script>
