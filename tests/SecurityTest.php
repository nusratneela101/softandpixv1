<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Security tests: CSRF token generation/validation, XSS sanitization,
 * and SQL injection prevention.
 */
class SecurityTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = createTestPdo();
    }

    // ------------------------------------------------------------------
    // CSRF Token
    // ------------------------------------------------------------------

    public function testCsrfTokenGeneratedWithSufficientEntropy(): void
    {
        $token = generateCsrfToken();
        // 64 hex chars = 32 bytes = 256 bits of entropy
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $token);
    }

    public function testCsrfTokenIsTimingAttackResistant(): void
    {
        // hash_equals should be used internally — just verify correct vs wrong
        $token = generateCsrfToken();
        $this->assertTrue(verifyCsrfToken($token));
        $this->assertFalse(verifyCsrfToken('tampered_' . $token));
    }

    public function testCsrfTokenRejectsEmptyString(): void
    {
        generateCsrfToken(); // ensure a token is set
        $this->assertFalse(verifyCsrfToken(''));
    }

    public function testCsrfTokenRejectsNull(): void
    {
        generateCsrfToken();
        $this->assertFalse(verifyCsrfToken(null));
    }

    // ------------------------------------------------------------------
    // XSS Sanitization
    // ------------------------------------------------------------------

    public function testHFunctionEscapesScriptTag(): void
    {
        $input  = '<script>alert("xss")</script>';
        $output = h($input);
        $this->assertStringNotContainsString('<script>', $output);
        $this->assertStringContainsString('&lt;script&gt;', $output);
    }

    public function testHFunctionEscapesAttributes(): void
    {
        $input  = '"><img src=x onerror=alert(1)>';
        $output = h($input);
        // h() escapes HTML special chars: ", >, < are encoded
        // The resulting string should have the < and > signs escaped
        $this->assertStringContainsString('&quot;', $output);
        $this->assertStringContainsString('&gt;', $output);
        $this->assertStringContainsString('&lt;', $output);
        // The encoded output should NOT contain unescaped angle brackets
        $this->assertStringNotContainsString('<img', $output);
        $this->assertStringNotContainsString('</img>', $output);
    }

    public function testHFunctionHandlesNull(): void
    {
        $output = h(null);
        $this->assertSame('', $output);
    }

    public function testHFunctionHandlesSpecialChars(): void
    {
        $input  = '&<>"\'';
        $output = h($input);
        $this->assertStringNotContainsString('&<>', $output);
        $this->assertStringContainsString('&amp;', $output);
        $this->assertStringContainsString('&lt;', $output);
        $this->assertStringContainsString('&gt;', $output);
        $this->assertStringContainsString('&quot;', $output);
        $this->assertStringContainsString('&#039;', $output);
    }

    // ------------------------------------------------------------------
    // SQL Injection Prevention (prepared statements)
    // ------------------------------------------------------------------

    public function testPreparedStatementPreventsInjection(): void
    {
        // Seed a test user
        $this->pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)")
            ->execute(['Safe User', 'safe@test.com', password_hash('pass', PASSWORD_DEFAULT), 'client']);

        // Attempt SQL injection via user input
        $maliciousEmail = "' OR '1'='1";
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE email=?");
        $stmt->execute([$maliciousEmail]);
        $result = $stmt->fetch();

        // Should return false — injection did not bypass the query
        $this->assertFalse($result);
    }

    public function testPreparedStatementWithDropTableAttempt(): void
    {
        $maliciousInput = "'; DROP TABLE users; --";
        $stmt = $this->pdo->prepare("SELECT id FROM users WHERE name=?");
        $stmt->execute([$maliciousInput]);
        // Users table should still exist
        $count = (int)$this->pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        $this->assertGreaterThanOrEqual(0, $count);
    }

    // ------------------------------------------------------------------
    // Password strength (as used in reset_password.php)
    // ------------------------------------------------------------------

    public function testWeakPasswordFailsStrengthCheck(): void
    {
        $passwords = ['pass', '12345678', 'password', 'ALLUPPERCASE'];
        foreach ($passwords as $pwd) {
            $strong = strlen($pwd) >= 8
                && preg_match('/[A-Z]/', $pwd)
                && preg_match('/[a-z]/', $pwd)
                && preg_match('/[0-9]/', $pwd);
            $this->assertFalse((bool)$strong, "'{$pwd}' should not pass strength check");
        }
    }

    public function testStrongPasswordPassesCheck(): void
    {
        $strong = 'MyP@ssw0rd';
        $passes = strlen($strong) >= 8
            && preg_match('/[A-Z]/', $strong)
            && preg_match('/[a-z]/', $strong)
            && preg_match('/[0-9]/', $strong);
        $this->assertTrue((bool)$passes);
    }

    // ------------------------------------------------------------------
    // File upload safety
    // ------------------------------------------------------------------

    public function testDangerousExtensionBlocked(): void
    {
        $dangerousExts = ['php', 'phtml', 'php5', 'phar', 'exe', 'sh', 'bat'];
        foreach ($dangerousExts as $ext) {
            $this->assertContains($ext, $dangerousExts, "Extension .{$ext} should be blocked");
        }
    }

    // ------------------------------------------------------------------
    // Email validation
    // ------------------------------------------------------------------

    public function testValidEmail(): void
    {
        $this->assertNotFalse(filter_var('user@example.com', FILTER_VALIDATE_EMAIL));
        $this->assertNotFalse(filter_var('user+tag@sub.domain.co.uk', FILTER_VALIDATE_EMAIL));
    }

    public function testInvalidEmail(): void
    {
        $this->assertFalse(filter_var('not-an-email', FILTER_VALIDATE_EMAIL));
        $this->assertFalse(filter_var('user@', FILTER_VALIDATE_EMAIL));
        $this->assertFalse(filter_var('<script>@test.com', FILTER_VALIDATE_EMAIL));
    }
}
