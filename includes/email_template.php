<?php
/**
 * Email Template Renderer for SoftandPix
 * Renders HTML email templates with placeholder replacement.
 */

/**
 * Render an email template with the given data.
 *
 * @param string $template_name  Template file name without .php (e.g., 'welcome')
 * @param array  $data           Key-value pairs for placeholder replacement
 * @return string                Rendered HTML string
 */
function render_email_template(string $template_name, array $data = []): string {
    $template_dir = __DIR__ . '/../email/templates/';
    $content_file = $template_dir . $template_name . '.php';
    $base_file    = $template_dir . 'base.php';

    if (!file_exists($content_file)) {
        error_log("Email template not found: {$content_file}");
        return '';
    }

    // Merge site defaults into data
    if (!isset($data['site_name']))  $data['site_name']  = defined('SITE_NAME') ? SITE_NAME : 'SoftandPix';
    if (!isset($data['site_url']))   $data['site_url']   = defined('SITE_URL') ? SITE_URL : 'https://softandpix.com';
    if (!isset($data['user_email'])) $data['user_email'] = '';
    if (!isset($data['logo_url']))   $data['logo_url']   = '';

    // Capture content template output
    ob_start();
    include $content_file;
    $content = ob_get_clean();

    // Capture base template output
    ob_start();
    include $base_file;
    $html = ob_get_clean();

    // Inject content into base {{content}} placeholder
    $html = str_replace('{{content}}', $content, $html);

    // Replace all remaining placeholders
    foreach ($data as $key => $value) {
        $html = str_replace('{{' . $key . '}}', htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8'), $html);
    }

    // Clean up any unreplaced placeholders
    $html = preg_replace('/\{\{[a-z_]+\}\}/', '', $html);

    return $html;
}

/**
 * Get the plain-text subject for a template.
 */
function get_email_subject(string $template_name, array $data = []): string {
    $subjects = [
        'welcome'           => 'Welcome to ' . ($data['site_name'] ?? 'SoftandPix') . '!',
        'password_reset'    => 'Password Reset Request',
        'invoice_created'   => 'New Invoice #' . ($data['invoice_number'] ?? ''),
        'invoice_paid'      => 'Payment Confirmed — Invoice #' . ($data['invoice_number'] ?? ''),
        'invoice_reminder'  => 'Payment Reminder — Invoice #' . ($data['invoice_number'] ?? ''),
        'task_assigned'     => 'New Task Assigned: ' . ($data['task_title'] ?? ''),
        'task_completed'    => 'Task Completed: ' . ($data['task_title'] ?? ''),
        'project_created'   => 'New Project: ' . ($data['project_name'] ?? ''),
        'chat_offline'      => 'New Message from ' . ($data['sender_name'] ?? ''),
        'agreement_created' => 'New Agreement: ' . ($data['project_name'] ?? ''),
    ];
    return $subjects[$template_name] ?? ('Notification from ' . ($data['site_name'] ?? 'SoftandPix'));
}
