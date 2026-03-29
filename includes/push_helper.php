<?php
/**
 * Push Notification Helper — Web Push / VAPID (pure PHP, no Composer)
 *
 * Implements RFC 8291 payload encryption (aes128gcm) and RFC 7515 JWT for VAPID.
 */

// ── Database helpers ────────────────────────────────────────────────────────────

function _ensure_push_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        endpoint TEXT NOT NULL,
        p256dh VARCHAR(500) NOT NULL,
        auth VARCHAR(500) NOT NULL,
        user_agent VARCHAR(500) DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        last_used_at DATETIME DEFAULT NULL,
        last_error TEXT DEFAULT NULL,
        error_count INT DEFAULT 0,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        INDEX idx_user_active (user_id, is_active),
        INDEX idx_endpoint (endpoint(255)),
        UNIQUE KEY unique_endpoint (endpoint(500))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function save_push_subscription(PDO $pdo, int $userId, string $endpoint, string $p256dh, string $auth, ?string $userAgent): bool {
    try {
        _ensure_push_table($pdo);
        $stmt = $pdo->prepare("
            INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth, user_agent, is_active, error_count)
            VALUES (?, ?, ?, ?, ?, 1, 0)
            ON DUPLICATE KEY UPDATE
                user_id     = VALUES(user_id),
                p256dh      = VALUES(p256dh),
                auth        = VALUES(auth),
                user_agent  = VALUES(user_agent),
                is_active   = 1,
                error_count = 0,
                last_error  = NULL,
                updated_at  = CURRENT_TIMESTAMP
        ");
        $stmt->execute([$userId, $endpoint, $p256dh, $auth, $userAgent]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function remove_push_subscription(PDO $pdo, int $userId, string $endpoint): bool {
    try {
        _ensure_push_table($pdo);
        $stmt = $pdo->prepare("DELETE FROM push_subscriptions WHERE user_id = ? AND endpoint = ?");
        $stmt->execute([$userId, $endpoint]);
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function get_user_subscriptions(PDO $pdo, int $userId): array {
    try {
        _ensure_push_table($pdo);
        $stmt = $pdo->prepare("SELECT * FROM push_subscriptions WHERE user_id = ? AND is_active = 1");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function cleanup_stale_subscriptions(PDO $pdo, int $maxErrors = 3): int {
    try {
        _ensure_push_table($pdo);
        $stmt = $pdo->prepare("
            DELETE FROM push_subscriptions
            WHERE error_count >= ?
               OR (last_used_at IS NOT NULL AND last_used_at < DATE_SUB(NOW(), INTERVAL 90 DAY))
               OR (last_used_at IS NULL AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY))
        ");
        $stmt->execute([$maxErrors]);
        return $stmt->rowCount();
    } catch (Exception $e) {
        return 0;
    }
}

// ── High-level push dispatch ────────────────────────────────────────────────────

function send_push_notification(PDO $pdo, int $userId, string $title, string $body, ?string $url = null, ?string $icon = null): int {
    $vapidConfig = _load_vapid_config();
    if (empty($vapidConfig['vapid_public_key']) || empty($vapidConfig['vapid_private_key'])) {
        return 0;
    }
    if (!($vapidConfig['enabled'] ?? true)) {
        return 0;
    }

    $subscriptions = get_user_subscriptions($pdo, $userId);
    if (empty($subscriptions)) {
        return 0;
    }

    $payload = [
        'title'           => $title,
        'body'            => $body,
        'url'             => $url ?? '/',
        'icon'            => $icon ?? '/public/assets/icons/icon-192x192.png',
        'timestamp'       => time() * 1000,
        'tag'             => 'softandpix-' . time(),
    ];

    $sent = 0;
    foreach ($subscriptions as $sub) {
        $result = send_push_to_subscription($sub, $payload, $vapidConfig);
        if ($result === true) {
            $sent++;
            // Update last_used_at
            try {
                $pdo->prepare("UPDATE push_subscriptions SET last_used_at = NOW() WHERE id = ?")
                    ->execute([$sub['id']]);
            } catch (Exception $e) {}
        } else {
            // Record error
            try {
                $pdo->prepare("
                    UPDATE push_subscriptions
                    SET error_count = error_count + 1,
                        last_error  = ?,
                        is_active   = IF(error_count + 1 >= 3, 0, 1)
                    WHERE id = ?
                ")->execute([$result, $sub['id']]);
            } catch (Exception $e) {}
        }
    }

    return $sent;
}

/**
 * Low-level: encrypt payload and send via cURL.
 * Returns true on success or an error string.
 */
function send_push_to_subscription(array $subscription, array $payload, array $vapidConfig): bool|string {
    $endpoint  = $subscription['endpoint'];
    $p256dh    = $subscription['p256dh'];
    $authToken = $subscription['auth'];

    $payloadJson = json_encode($payload);

    // ── Encrypt payload (RFC 8291, aes128gcm) ──────────────────────────────────
    try {
        $encrypted = _encrypt_payload($payloadJson, $p256dh, $authToken);
    } catch (Throwable $e) {
        return 'Encryption failed: ' . $e->getMessage();
    }

    // ── Build VAPID JWT ────────────────────────────────────────────────────────
    $parsedUrl = parse_url($endpoint);
    $audience  = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

    try {
        $jwt = create_vapid_jwt(
            $audience,
            $vapidConfig['vapid_subject'],
            $vapidConfig['vapid_private_key'],
            $vapidConfig['vapid_public_key']
        );
    } catch (Throwable $e) {
        return 'JWT creation failed: ' . $e->getMessage();
    }

    $authHeader = 'vapid t=' . $jwt . ', k=' . $vapidConfig['vapid_public_key'];
    $ttl        = (int)($vapidConfig['ttl'] ?? 86400);
    $urgency    = $vapidConfig['urgency'] ?? 'normal';

    // ── Send via cURL ──────────────────────────────────────────────────────────
    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Authorization: ' . $authHeader,
            'TTL: ' . $ttl,
            'Urgency: ' . $urgency,
        ],
        CURLOPT_POSTFIELDS     => $encrypted,
    ]);

    $response   = curl_exec($ch);
    $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError  = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        return 'cURL error: ' . $curlError;
    }

    // 201 Created or 200 OK are success; 410 Gone means subscription expired
    if ($httpCode === 201 || $httpCode === 200) {
        return true;
    }

    return 'HTTP ' . $httpCode . ': ' . substr((string)$response, 0, 200);
}

// ── VAPID JWT ───────────────────────────────────────────────────────────────────

function create_vapid_jwt(string $audience, string $subject, string $privateKeyB64u, string $publicKeyB64u): string {
    $header  = base64url_encode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']));
    $payload = base64url_encode(json_encode([
        'aud' => $audience,
        'exp' => time() + 43200, // 12 hours
        'sub' => $subject,
    ]));

    $signingInput = $header . '.' . $payload;

    // Reconstruct PEM private key from raw d + public point
    $privBytes = base64url_decode($privateKeyB64u);
    $pubBytes  = base64url_decode($publicKeyB64u);

    $pem = _build_ec_private_pem($privBytes, $pubBytes);

    $privateKey = openssl_pkey_get_private($pem);
    if ($privateKey === false) {
        throw new RuntimeException('Invalid VAPID private key');
    }

    $signature = '';
    if (!openssl_sign($signingInput, $signature, $privateKey, 'SHA256')) {
        throw new RuntimeException('openssl_sign failed');
    }

    // Convert DER-encoded signature to raw r||s (64 bytes for P-256)
    $rawSig = _der_to_raw_ecdsa($signature);

    return $signingInput . '.' . base64url_encode($rawSig);
}

// ── Payload encryption (RFC 8291, aes128gcm) ────────────────────────────────────

function _encrypt_payload(string $plaintext, string $p256dhB64u, string $authB64u): string {
    // Decode client public key (65-byte uncompressed point) and auth secret (16 bytes)
    $clientPublicKey = base64url_decode($p256dhB64u);
    $authSecret      = base64url_decode($authB64u);

    // Generate server ephemeral EC key pair on P-256
    $serverKey = openssl_pkey_new([
        'curve_name'       => 'prime256v1',
        'private_key_type' => OPENSSL_KEYTYPE_EC,
    ]);
    if ($serverKey === false) {
        throw new RuntimeException('Failed to generate server EC key');
    }
    $serverDetails = openssl_pkey_get_details($serverKey);
    $ec            = $serverDetails['ec'];

    $serverPublicBytes = "\x04"
        . str_pad($ec['x'], 32, "\x00", STR_PAD_LEFT)
        . str_pad($ec['y'], 32, "\x00", STR_PAD_LEFT);

    // Reconstruct client public key as an openssl key object for ECDH
    $clientPem = _public_key_from_uncompressed($clientPublicKey);
    $clientKey = openssl_pkey_get_public($clientPem);
    if ($clientKey === false) {
        throw new RuntimeException('Invalid client public key');
    }

    // ECDH shared secret
    if (!openssl_pkey_derive($sharedSecret, $serverKey, $clientKey)) {
        throw new RuntimeException('ECDH derive failed');
    }

    // Salt: 16 random bytes
    $salt = random_bytes(16);

    // Key derivation (RFC 8291 §3.4)
    // ikm = HKDF-Extract(auth_secret, ecdh_secret || "WebPush: info\0" || client_pub || server_pub)
    $ikm = _hkdf(
        $authSecret,
        $sharedSecret . "WebPush: info\0" . $clientPublicKey . $serverPublicBytes,
        "Content-Encoding: auth\0",
        32
    );

    // cek = HKDF-Expand(salt, ikm, "Content-Encoding: aes128gcm\0", 16)
    $cek = _hkdf($salt, $ikm, "Content-Encoding: aes128gcm\0", 16);
    // nonce = HKDF-Expand(salt, ikm, "Content-Encoding: nonce\0", 12)
    $nonce = _hkdf($salt, $ikm, "Content-Encoding: nonce\0", 12);

    // Pad plaintext: record size is 4096, add \x02 delimiter
    $paddedPlaintext = $plaintext . "\x02";

    // AES-128-GCM encryption
    $tag       = '';
    $ciphertext = openssl_encrypt($paddedPlaintext, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ciphertext === false) {
        throw new RuntimeException('AES-128-GCM encryption failed');
    }

    // Build aes128gcm content-encoding header (RFC 8188):
    // salt (16) || rs (4) || keylen (1) || server_public_key (65) || ciphertext || tag
    $recordSize = pack('N', 4096); // 4 bytes big-endian
    $keyLen     = chr(strlen($serverPublicBytes)); // 1 byte

    return $salt . $recordSize . $keyLen . $serverPublicBytes . $ciphertext . $tag;
}

// ── HKDF ───────────────────────────────────────────────────────────────────────

function _hkdf(string $salt, string $ikm, string $info, int $length): string {
    // Extract
    $prk = hash_hmac('sha256', $ikm, $salt, true);
    // Expand
    $t   = '';
    $okm = '';
    for ($i = 1; strlen($okm) < $length; $i++) {
        $t    = hash_hmac('sha256', $t . $info . chr($i), $prk, true);
        $okm .= $t;
    }
    return substr($okm, 0, $length);
}

// ── Key format helpers ──────────────────────────────────────────────────────────

/**
 * Build a PEM-encoded EC private key from raw bytes.
 * Uses SEC1 / PKCS#8 DER structure for prime256v1.
 */
function _build_ec_private_pem(string $d, string $publicPoint): string {
    // ECPrivateKey ::= SEQUENCE {
    //   version INTEGER { ecPrivkeyVer1(1) },
    //   privateKey OCTET STRING,
    //   [0] EXPLICIT ECParameters OPTIONAL,
    //   [1] EXPLICIT BIT STRING OPTIONAL
    // }
    // Wrap inside PKCS#8 PrivateKeyInfo for openssl compatibility
    $oidP256 = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"; // OID for prime256v1

    // Build ECPrivateKey (RFC 5915)
    $ecPrivKey = _der_sequence(
        _der_integer(1) .
        _der_octet_string($d) .
        "\xa1" . _der_len(strlen($publicPoint) + 2) . "\x03" . _der_len(strlen($publicPoint) + 1) . "\x00" . $publicPoint
    );

    // Wrap in PKCS#8: AlgorithmIdentifier + ECPrivateKey
    $algorithmIdentifier = _der_sequence(
        "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01" . // OID id-ecPublicKey
        $oidP256
    );

    $pkcs8 = _der_sequence(
        _der_integer(0) .
        $algorithmIdentifier .
        _der_octet_string($ecPrivKey)
    );

    return "-----BEGIN PRIVATE KEY-----\n" . chunk_split(base64_encode($pkcs8), 64, "\n") . "-----END PRIVATE KEY-----\n";
}

/**
 * Build a PEM-encoded EC public key from an uncompressed point (0x04 || x || y).
 */
function _public_key_from_uncompressed(string $point): string {
    $oidEcPublicKey = "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01";
    $oidP256        = "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07";

    $algorithmIdentifier = _der_sequence($oidEcPublicKey . $oidP256);
    $bitString           = "\x03" . _der_len(strlen($point) + 1) . "\x00" . $point;

    $spki = _der_sequence($algorithmIdentifier . $bitString);

    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($spki), 64, "\n") . "-----END PUBLIC KEY-----\n";
}

/**
 * Convert a DER-encoded ECDSA signature to raw r||s bytes.
 */
function _der_to_raw_ecdsa(string $der): string {
    $offset = 2; // SEQUENCE tag + length
    // r
    $offset++; // INTEGER tag
    $rLen = ord($der[$offset++]);
    $r    = substr($der, $offset, $rLen);
    $offset += $rLen;
    // s
    $offset++; // INTEGER tag
    $sLen = ord($der[$offset++]);
    $s    = substr($der, $offset, $sLen);

    // Strip leading zero padding, then left-pad to 32 bytes
    $r = ltrim($r, "\x00");
    $s = ltrim($s, "\x00");
    $r = str_pad($r, 32, "\x00", STR_PAD_LEFT);
    $s = str_pad($s, 32, "\x00", STR_PAD_LEFT);

    return $r . $s;
}

// ── Minimal DER encoding helpers ───────────────────────────────────────────────

function _der_sequence(string $content): string {
    return "\x30" . _der_len(strlen($content)) . $content;
}

function _der_integer(int $value): string {
    return "\x02\x01" . chr($value);
}

function _der_octet_string(string $data): string {
    return "\x04" . _der_len(strlen($data)) . $data;
}

function _der_len(int $len): string {
    if ($len < 0x80) {
        return chr($len);
    }
    if ($len < 0x100) {
        return "\x81" . chr($len);
    }
    return "\x82" . chr($len >> 8) . chr($len & 0xff);
}

// ── base64url helpers ───────────────────────────────────────────────────────────

function base64url_encode(string $data): string {
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode(string $data): string {
    $pad  = strlen($data) % 4;
    if ($pad) {
        $data .= str_repeat('=', 4 - $pad);
    }
    return base64_decode(strtr($data, '-_', '+/'));
}

// ── Config loader ──────────────────────────────────────────────────────────────

function _load_vapid_config(): array {
    $configPath = defined('BASE_PATH') ? BASE_PATH . '/config/push.php' : dirname(__DIR__) . '/config/push.php';
    if (file_exists($configPath)) {
        return require $configPath;
    }
    return [
        'vapid_public_key'  => '',
        'vapid_private_key' => '',
        'vapid_subject'     => 'mailto:support@softandpix.com',
        'enabled'           => false,
        'ttl'               => 86400,
        'urgency'           => 'normal',
        'batch_size'        => 50,
    ];
}
