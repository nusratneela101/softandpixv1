<?php
/**
 * Language detection and loading for SoftandPix
 * Detects language from: URL param, session, cookie, Accept-Language header
 * Provides __($key) helper for translations and lang_url($url) helper
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

define('SUPPORTED_LANGS', ['en', 'bn', 'fr', 'pa', 'zh', 'zh_tw', 'es', 'tl', 'ar', 'it', 'de', 'pt']);
define('DEFAULT_LANG', 'en');

/**
 * Detect and set the current language.
 */
function detect_language(): string {
    // 1. URL parameter ?lang=xx
    if (!empty($_GET['lang']) && in_array($_GET['lang'], SUPPORTED_LANGS, true)) {
        $lang = $_GET['lang'];
        $_SESSION['lang'] = $lang;
        setcookie('lang', $lang, time() + (86400 * 30), '/', '', false, true);
        return $lang;
    }

    // 2. Session
    if (!empty($_SESSION['lang']) && in_array($_SESSION['lang'], SUPPORTED_LANGS, true)) {
        return $_SESSION['lang'];
    }

    // 3. Cookie
    if (!empty($_COOKIE['lang']) && in_array($_COOKIE['lang'], SUPPORTED_LANGS, true)) {
        $_SESSION['lang'] = $_COOKIE['lang'];
        return $_COOKIE['lang'];
    }

    // 4. Browser Accept-Language header
    if (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $accept = strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        foreach (['bn', 'fr', 'pa', 'zh', 'es', 'tl', 'ar', 'it', 'de', 'pt'] as $code) {
            if (strpos($accept, $code) !== false) {
                $_SESSION['lang'] = $code;
                return $code;
            }
        }
    }

    // Default to English
    $_SESSION['lang'] = DEFAULT_LANG;
    return DEFAULT_LANG;
}

// Detect language and load translations
$GLOBALS['_current_lang'] = detect_language();

$_lang_file = __DIR__ . '/lang/' . $GLOBALS['_current_lang'] . '.php';
if (file_exists($_lang_file)) {
    $GLOBALS['_translations'] = require $_lang_file;
} else {
    $GLOBALS['_translations'] = require __DIR__ . '/lang/en.php';
}

// Load English as fallback
$GLOBALS['_translations_en'] = require __DIR__ . '/lang/en.php';

/**
 * Translate a key. Falls back to English, then to the key itself.
 */
function __($key): string {
    $translations = $GLOBALS['_translations'] ?? [];
    if (isset($translations[$key])) {
        return $translations[$key];
    }
    // Fallback to English
    $en = $GLOBALS['_translations_en'] ?? [];
    return $en[$key] ?? $key;
}

/**
 * Get the current language code.
 */
function current_lang(): string {
    return $GLOBALS['_current_lang'] ?? DEFAULT_LANG;
}

/**
 * Append current language to a URL.
 */
function lang_url(string $url): string {
    $lang = current_lang();
    $sep = (strpos($url, '?') !== false) ? '&' : '?';
    return $url . $sep . 'lang=' . urlencode($lang);
}

/**
 * Check if the current language is RTL.
 */
function is_rtl(): bool {
    return in_array(current_lang(), ['ar'], true);
}

/**
 * Get the text direction for the current language.
 */
function text_direction(): string {
    return is_rtl() ? 'rtl' : 'ltr';
}

/**
 * Get all supported languages with their native names.
 */
function get_supported_languages(): array {
    return [
        'en'    => ['name' => 'English', 'native' => 'English', 'flag' => '🇬🇧', 'dir' => 'ltr'],
        'bn'    => ['name' => 'Bengali', 'native' => 'বাংলা', 'flag' => '🇧🇩', 'dir' => 'ltr'],
        'fr'    => ['name' => 'French', 'native' => 'Français', 'flag' => '🇫🇷', 'dir' => 'ltr'],
        'pa'    => ['name' => 'Punjabi', 'native' => 'ਪੰਜਾਬੀ', 'flag' => '🇮🇳', 'dir' => 'ltr'],
        'zh'    => ['name' => 'Chinese Simplified', 'native' => '简体中文', 'flag' => '🇨🇳', 'dir' => 'ltr'],
        'zh_tw' => ['name' => 'Chinese Traditional', 'native' => '繁體中文', 'flag' => '🇹🇼', 'dir' => 'ltr'],
        'es'    => ['name' => 'Spanish', 'native' => 'Español', 'flag' => '🇪🇸', 'dir' => 'ltr'],
        'tl'    => ['name' => 'Tagalog', 'native' => 'Filipino', 'flag' => '🇵🇭', 'dir' => 'ltr'],
        'ar'    => ['name' => 'Arabic', 'native' => 'العربية', 'flag' => '🇸🇦', 'dir' => 'rtl'],
        'it'    => ['name' => 'Italian', 'native' => 'Italiano', 'flag' => '🇮🇹', 'dir' => 'ltr'],
        'de'    => ['name' => 'German', 'native' => 'Deutsch', 'flag' => '🇩🇪', 'dir' => 'ltr'],
        'pt'    => ['name' => 'Portuguese', 'native' => 'Português', 'flag' => '🇧🇷', 'dir' => 'ltr'],
    ];
}

