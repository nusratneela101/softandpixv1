#!/usr/bin/env php
<?php
/**
 * CLI Tool: Generate VAPID Key Pair
 * Run: php cli/generate_vapid_keys.php
 *
 * Generates a P-256 VAPID key pair and outputs base64url-encoded keys.
 * Optionally writes them to a .env file.
 */

if (PHP_SAPI !== 'cli') {
    exit('This script must be run from the command line.' . PHP_EOL);
}

// ── Generate EC key pair on P-256 / prime256v1 curve ──────────────────────────
$key = openssl_pkey_new([
    'curve_name'        => 'prime256v1',
    'private_key_type'  => OPENSSL_KEYTYPE_EC,
]);

if ($key === false) {
    fwrite(STDERR, 'ERROR: Failed to generate EC key pair. Ensure OpenSSL is installed with EC support.' . PHP_EOL);
    exit(1);
}

$details = openssl_pkey_get_details($key);
if ($details === false || !isset($details['ec'])) {
    fwrite(STDERR, 'ERROR: Could not extract EC key details.' . PHP_EOL);
    exit(1);
}

$ec = $details['ec'];

// Public key: 0x04 || x || y  (uncompressed point format, 65 bytes for P-256)
$x = str_pad($ec['x'], 32, "\x00", STR_PAD_LEFT);
$y = str_pad($ec['y'], 32, "\x00", STR_PAD_LEFT);
$publicKeyBytes  = "\x04" . $x . $y;

// Private key: raw d value (32 bytes for P-256)
$privateKeyBytes = str_pad($ec['d'], 32, "\x00", STR_PAD_LEFT);

$publicKey  = base64url_encode($publicKeyBytes);
$privateKey = base64url_encode($privateKeyBytes);

// ── Output ─────────────────────────────────────────────────────────────────────
echo PHP_EOL;
echo '╔══════════════════════════════════════════════════════════════════╗' . PHP_EOL;
echo '║              SoftandPix — VAPID Key Generator                   ║' . PHP_EOL;
echo '╚══════════════════════════════════════════════════════════════════╝' . PHP_EOL;
echo PHP_EOL;
echo 'VAPID_PUBLIC_KEY=' . $publicKey . PHP_EOL;
echo 'VAPID_PRIVATE_KEY=' . $privateKey . PHP_EOL;
echo PHP_EOL;
echo '──────────────────────────────────────────────────────────────────' . PHP_EOL;
echo 'Add these lines to your .env file or set them as environment vars.' . PHP_EOL;
echo PHP_EOL;
echo 'To write to .env automatically, run:' . PHP_EOL;
echo '  php cli/generate_vapid_keys.php --write-env' . PHP_EOL;
echo PHP_EOL;

// ── Optionally write to .env ───────────────────────────────────────────────────
if (in_array('--write-env', $argv ?? [], true)) {
    $envPath = dirname(__DIR__) . '/.env';
    $lines   = [];

    if (file_exists($envPath)) {
        $existing = file($envPath, FILE_IGNORE_NEW_LINES);
        foreach ($existing as $line) {
            if (!preg_match('/^VAPID_(PUBLIC|PRIVATE)_KEY\s*=/', $line)) {
                $lines[] = $line;
            }
        }
    }

    $lines[] = 'VAPID_PUBLIC_KEY=' . $publicKey;
    $lines[] = 'VAPID_PRIVATE_KEY=' . $privateKey;

    if (file_put_contents($envPath, implode(PHP_EOL, $lines) . PHP_EOL) !== false) {
        echo '✓ Keys written to ' . $envPath . PHP_EOL;
    } else {
        fwrite(STDERR, 'ERROR: Could not write to ' . $envPath . PHP_EOL);
        exit(1);
    }
}

// ── Helper ─────────────────────────────────────────────────────────────────────
function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}
