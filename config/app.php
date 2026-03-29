<?php
/**
 * SoftandPix Application Settings
 */

// Default site settings
define('SITE_NAME', 'SoftandPix');
define('SITE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));

// Upload settings
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'rar', 'txt']);
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif']);

// Session settings
define('SESSION_LIFETIME', 86400); // 24 hours

// Chat polling interval (milliseconds)
define('CHAT_POLL_INTERVAL', 3000);

// Online status timeout (seconds)
define('ONLINE_TIMEOUT', 300); // 5 minutes

// Pagination
define('ITEMS_PER_PAGE', 20);
