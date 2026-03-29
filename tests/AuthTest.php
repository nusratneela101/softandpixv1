<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for authentication helpers (password hashing, CSRF tokens, session).
 */
class AuthTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = createTestPdo();
    }

    // ------------------------------------------------------------------
    // Password hashing
    // ------------------------------------------------------------------

    public function testPasswordHashVerify(): void
    {
        $raw  = 'MyP@ssw0rd!';
        $hash = password_hash($raw, PASSWORD_DEFAULT);
        $this->assertTrue(password_verify($raw, $hash));
        $this->assertFalse(password_verify('wrongpass', $hash));
    }

    public function testPasswordHashIsBcrypt(): void
    {
        $hash = password_hash('test', PASSWORD_DEFAULT);
        $info = password_get_info($hash);
        // PASSWORD_DEFAULT is currently bcrypt (algo=1)
        $this->assertSame(PASSWORD_BCRYPT, $info['algo']);
    }

    // ------------------------------------------------------------------
    // CSRF tokens
    // ------------------------------------------------------------------

    public function testGenerateCsrfTokenReturnsSixtyFourHexChars(): void
    {
        $token = generateCsrfToken();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testCsrfTokenVerifiesCorrectly(): void
    {
        $token = generateCsrfToken();
        $this->assertTrue(verifyCsrfToken($token));
    }

    public function testCsrfTokenRejectsWrongToken(): void
    {
        generateCsrfToken(); // ensure one is in session
        $this->assertFalse(verifyCsrfToken('wrong_token_value'));
    }

    public function testCsrfTokenSameWithinSession(): void
    {
        $t1 = generateCsrfToken();
        $t2 = generateCsrfToken();
        $this->assertSame($t1, $t2);
    }

    // ------------------------------------------------------------------
    // Flash messages
    // ------------------------------------------------------------------

    public function testFlashMessageSetAndGet(): void
    {
        flashMessage('success', 'Test message');
        $flash = getFlashMessage();
        $this->assertIsArray($flash);
        $this->assertSame('success', $flash['type']);
        $this->assertSame('Test message', $flash['message']);
    }

    public function testFlashMessageClearedAfterGet(): void
    {
        flashMessage('info', 'Once only');
        getFlashMessage(); // consume it
        $this->assertNull(getFlashMessage());
    }

    // ------------------------------------------------------------------
    // Password Reset flow (token generation)
    // ------------------------------------------------------------------

    public function testPasswordResetTokenIsSecureRandom(): void
    {
        $token1 = bin2hex(random_bytes(32));
        $token2 = bin2hex(random_bytes(32));
        // Tokens should be 64 hex chars and different
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token1);
        $this->assertNotSame($token1, $token2);
    }

    public function testPasswordResetTokenStoredAndRetrieved(): void
    {
        $email   = 'test@example.com';
        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600);

        $this->pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)")
            ->execute([$email, $token, $expires]);

        $stmt = $this->pdo->prepare("SELECT * FROM password_resets WHERE token = ?");
        $stmt->execute([$token]);
        $row = $stmt->fetch();

        $this->assertNotFalse($row);
        $this->assertSame($email, $row['email']);
        $this->assertSame($token, $row['token']);
    }

    public function testExpiredTokenIsNotValid(): void
    {
        $email   = 'expired@example.com';
        $token   = bin2hex(random_bytes(32));
        $expired = date('Y-m-d H:i:s', time() - 7200); // 2 hours ago

        $this->pdo->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?,?,?)")
            ->execute([$email, $token, $expired]);

        $stmt = $this->pdo->prepare("SELECT * FROM password_resets WHERE token = ? AND expires_at > datetime('now')");
        $stmt->execute([$token]);
        $row = $stmt->fetch();
        $this->assertFalse($row);
    }

    // ------------------------------------------------------------------
    // Helper functions
    // ------------------------------------------------------------------

    public function testHtmlEscapeFunction(): void
    {
        $dangerous = '<script>alert("xss")</script>';
        $safe      = h($dangerous);
        $this->assertStringNotContainsString('<script>', $safe);
        $this->assertStringContainsString('&lt;script&gt;', $safe);
    }

    public function testTimeAgoFunction(): void
    {
        $now    = date('Y-m-d H:i:s');
        $result = timeAgo($now);
        $this->assertSame('just now', $result);
    }
}
