<?php
/**
 * Theme management for SoftandPix
 * Loads theme settings from database and provides CSS variable output
 */

$_theme_cache = null;

/**
 * Get all theme settings (cached per request).
 */
function get_all_theme_settings($pdo): array {
    global $_theme_cache;
    if ($_theme_cache !== null) return $_theme_cache;

    $defaults = [
        'primary_color'    => '#667eea',
        'secondary_color'  => '#764ba2',
        'sidebar_bg'       => '#1a1a2e',
        'sidebar_text'     => '#ffffff',
        'header_bg'        => '#ffffff',
        'header_text'      => '#333333',
        'accent_color'     => '#16213e',
        'body_bg'          => '#f4f6f9',
        'font_family'      => 'Roboto',
        'dark_mode'        => '0',
        'logo_url'         => '',
        'favicon_url'      => '',
    ];

    if (!$pdo) {
        $_theme_cache = $defaults;
        return $defaults;
    }

    try {
        $rows = $pdo->query("SELECT setting_key, setting_value FROM theme_settings")->fetchAll();
        foreach ($rows as $row) {
            $defaults[$row['setting_key']] = $row['setting_value'];
        }
    } catch (Exception $e) {
        // Table may not exist yet, use defaults
    }

    $_theme_cache = $defaults;
    return $defaults;
}

/**
 * Get a single theme value.
 */
function get_theme($pdo, string $key, string $default = ''): string {
    $settings = get_all_theme_settings($pdo);
    return $settings[$key] ?? $default;
}

/**
 * Sanitize a hex color value.
 */
function sanitize_color(string $color): string {
    if (preg_match('/^#[0-9a-fA-F]{3,6}$/', $color)) {
        return $color;
    }
    return '#667eea';
}

/**
 * Output a <style> block with CSS custom properties from theme settings.
 */
function render_theme_css($pdo): void {
    $t = get_all_theme_settings($pdo);
    $primary   = sanitize_color($t['primary_color']);
    $secondary = sanitize_color($t['secondary_color']);
    $sidebar   = sanitize_color($t['sidebar_bg']);
    $stext     = sanitize_color($t['sidebar_text']);
    $headerBg  = sanitize_color($t['header_bg']);
    $headerTxt = sanitize_color($t['header_text']);
    $accent    = sanitize_color($t['accent_color']);
    $bodyBg    = sanitize_color($t['body_bg']);
    $font      = htmlspecialchars($t['font_family'], ENT_QUOTES, 'UTF-8');
    echo "<style>
:root {
    --sp-primary:    {$primary};
    --sp-secondary:  {$secondary};
    --sp-sidebar-bg: {$sidebar};
    --sp-sidebar-text: {$stext};
    --sp-header-bg:  {$headerBg};
    --sp-header-text:{$headerTxt};
    --sp-accent:     {$accent};
    --sp-body-bg:    {$bodyBg};
    --sp-font:       '{$font}', sans-serif;
}
body { background-color: var(--sp-body-bg); font-family: var(--sp-font); }
.sidebar { background: var(--sp-sidebar-bg) !important; color: var(--sp-sidebar-text) !important; }
.sidebar .nav-item { color: var(--sp-sidebar-text) !important; }
.sidebar .nav-item.active, .sidebar .nav-item:hover { background: var(--sp-primary) !important; }
.navbar, .page-header { background: var(--sp-header-bg) !important; color: var(--sp-header-text) !important; }
.btn-primary { background-color: var(--sp-primary); border-color: var(--sp-primary); }
.btn-primary:hover { background-color: var(--sp-secondary); border-color: var(--sp-secondary); }
</style>\n";
}

/**
 * Return Google Fonts link tag for the selected font (if not Roboto default).
 */
function render_google_font($pdo): void {
    $font = get_theme($pdo, 'font_family', 'Roboto');
    $safe_fonts = ['Roboto','Open Sans','Lato','Montserrat','Poppins','Nunito','Inter','Raleway'];
    if (!in_array($font, $safe_fonts, true)) return;
    $encoded = urlencode($font) . ':wght@300;400;600;700';
    echo "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">\n";
    echo "<link href=\"https://fonts.googleapis.com/css2?family={$encoded}&display=swap\" rel=\"stylesheet\">\n";
}
