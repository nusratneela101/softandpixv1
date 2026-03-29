<?php
/**
 * Chat Translation Helper for SoftandPix
 * Uses Google Translate free API with DB caching and rate limiting.
 */

/**
 * Map SoftandPix language codes to Google Translate language codes.
 */
function _chat_lang_to_google(string $lang): string {
    $map = [
        'zh'    => 'zh-CN',
        'zh_tw' => 'zh-TW',
    ];
    return $map[$lang] ?? $lang;
}

/**
 * Map Google Translate language codes back to SoftandPix language codes.
 */
function _google_lang_to_chat(string $code): string {
    $map = [
        'zh-CN' => 'zh',
        'zh-cn' => 'zh',
        'zh-TW' => 'zh_tw',
        'zh-tw' => 'zh_tw',
    ];
    return $map[$code] ?? strtolower($code);
}

/**
 * Ensure the message_translations table exists.
 */
function _ensure_translations_table(PDO $pdo): void {
    try {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS message_translations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message_id INT NOT NULL,
                source_lang VARCHAR(10) DEFAULT 'auto',
                target_lang VARCHAR(10) NOT NULL,
                original_text TEXT NOT NULL,
                translated_text TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_translation (message_id, target_lang),
                INDEX idx_message (message_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    } catch (Exception $e) {
        error_log('_ensure_translations_table error: ' . $e->getMessage());
    }
}

/**
 * Call Google Translate free API.
 *
 * @return array ['success' => bool, 'translated' => string, 'source_lang' => string]
 */
function _call_google_translate(string $text, string $target_lang, string $source_lang = 'auto'): array {
    if (trim($text) === '') {
        return ['success' => true, 'translated' => $text, 'source_lang' => $source_lang];
    }

    $gt_target = _chat_lang_to_google($target_lang);
    $gt_source = ($source_lang === 'auto') ? 'auto' : _chat_lang_to_google($source_lang);

    $url = 'https://translate.googleapis.com/translate_a/single'
        . '?client=gtx'
        . '&sl=' . urlencode($gt_source)
        . '&tl=' . urlencode($gt_target)
        . '&dt=t'
        . '&q='  . urlencode($text);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (compatible; SoftandPix/1.0)',
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($curlErr || $httpCode !== 200 || !$response) {
        error_log("chat_translate API error: HTTP $httpCode, curl: $curlErr");
        return ['success' => false, 'error' => 'Translation service unavailable'];
    }

    $data = json_decode($response, true);
    if (!is_array($data) || empty($data[0])) {
        return ['success' => false, 'error' => 'Invalid translation response'];
    }

    // Reconstruct translated text from segments
    $translated = '';
    foreach ($data[0] as $segment) {
        if (isset($segment[0])) {
            $translated .= $segment[0];
        }
    }

    // Detect source language from response
    $detected_source = $source_lang;
    if ($source_lang === 'auto' && isset($data[2])) {
        $detected_source = _google_lang_to_chat((string)$data[2]);
    }

    return [
        'success'     => true,
        'translated'  => $translated,
        'source_lang' => $detected_source,
    ];
}

/**
 * Translate a chat message, caching results in the database.
 *
 * @param PDO    $pdo
 * @param int    $message_id    ID of the message in chat_messages table
 * @param string $message_text  Original message text
 * @param string $target_lang   Target language code (e.g. 'bn', 'fr')
 * @param string $source_lang   Source language code or 'auto'
 * @return array ['success' => bool, 'translated' => string, 'source_lang' => string, 'cached' => bool]
 */
function translate_message(PDO $pdo, int $message_id, string $message_text, string $target_lang, string $source_lang = 'auto'): array {
    $supported = defined('SUPPORTED_LANGS')
        ? SUPPORTED_LANGS
        : ['en', 'bn', 'fr', 'pa', 'zh', 'zh_tw', 'es', 'tl', 'ar', 'it', 'de', 'pt'];

    if (!in_array($target_lang, $supported, true)) {
        return ['success' => false, 'error' => 'Unsupported language'];
    }

    // Skip if source equals target
    if ($source_lang !== 'auto' && $source_lang === $target_lang) {
        return ['success' => true, 'translated' => $message_text, 'source_lang' => $source_lang, 'cached' => false];
    }

    // Ensure table exists
    _ensure_translations_table($pdo);

    // Check cache
    try {
        $stmt = $pdo->prepare(
            "SELECT translated_text, source_lang FROM message_translations
             WHERE message_id = ? AND target_lang = ? LIMIT 1"
        );
        $stmt->execute([$message_id, $target_lang]);
        $cached = $stmt->fetch();
        if ($cached) {
            return [
                'success'     => true,
                'translated'  => $cached['translated_text'],
                'source_lang' => $cached['source_lang'],
                'cached'      => true,
            ];
        }
    } catch (Exception $e) {
        error_log('chat_translate cache lookup error: ' . $e->getMessage());
    }

    // Call translation API
    $result = _call_google_translate($message_text, $target_lang, $source_lang);
    if (!$result['success']) {
        return $result;
    }

    $result['cached'] = false;

    // Store in cache
    try {
        $pdo->prepare(
            "INSERT INTO message_translations
                 (message_id, source_lang, target_lang, original_text, translated_text)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                 translated_text = VALUES(translated_text),
                 source_lang     = VALUES(source_lang)"
        )->execute([
            $message_id,
            $result['source_lang'],
            $target_lang,
            $message_text,
            $result['translated'],
        ]);
    } catch (Exception $e) {
        error_log('chat_translate cache write error: ' . $e->getMessage());
    }

    return $result;
}
