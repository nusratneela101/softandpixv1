<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for Activity Logging functionality.
 */
class ActivityLogTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = createTestPdo();
        $this->pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)")
            ->execute(['Log User', 'loguser@test.com', password_hash('pass', PASSWORD_DEFAULT), 'client']);
    }

    private function getUserId(): int
    {
        return (int)$this->pdo->query("SELECT id FROM users WHERE email='loguser@test.com'")->fetchColumn();
    }

    // ------------------------------------------------------------------
    // log_activity()
    // ------------------------------------------------------------------

    public function testLogActivityCreatesRecord(): void
    {
        $userId = $this->getUserId();
        log_activity($this->pdo, $userId, 'login', 'User logged in');

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id=? AND action='login'");
        $stmt->execute([$userId]);
        $this->assertSame(1, (int)$stmt->fetchColumn());
    }

    public function testLogActivityStoresDetails(): void
    {
        $userId = $this->getUserId();
        log_activity($this->pdo, $userId, 'project_created', 'Project Alpha created', 'project', 42);

        $stmt = $this->pdo->prepare("SELECT * FROM activity_log WHERE action='project_created' LIMIT 1");
        $stmt->execute();
        $row = $stmt->fetch();

        $this->assertNotFalse($row);
        $this->assertSame('Project Alpha created', $row['details']);
        $this->assertSame('project', $row['entity_type']);
        $this->assertSame(42, (int)$row['entity_id']);
    }

    public function testLogActivityWorksWithNullUserId(): void
    {
        // System/anonymous action
        log_activity($this->pdo, null, 'cron_run', 'Scheduled task executed');

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE action='cron_run' AND user_id IS NULL");
        $stmt->execute();
        $this->assertSame(1, (int)$stmt->fetchColumn());
    }

    // ------------------------------------------------------------------
    // Filtering
    // ------------------------------------------------------------------

    public function testFilterLogsByUser(): void
    {
        $userId = $this->getUserId();
        log_activity($this->pdo, $userId, 'login', 'Logged in');
        log_activity($this->pdo, $userId, 'invoice_created', 'Invoice #001', 'invoice', 1);
        log_activity($this->pdo, null, 'system_event', 'System task');

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE user_id=?");
        $stmt->execute([$userId]);
        $this->assertSame(2, (int)$stmt->fetchColumn());
    }

    public function testFilterLogsByAction(): void
    {
        $userId = $this->getUserId();
        log_activity($this->pdo, $userId, 'login', 'Login 1');
        log_activity($this->pdo, $userId, 'login', 'Login 2');
        log_activity($this->pdo, $userId, 'logout', 'Logout');

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE action='login'");
        $stmt->execute();
        $this->assertSame(2, (int)$stmt->fetchColumn());
    }

    public function testFilterLogsByEntityType(): void
    {
        $userId = $this->getUserId();
        log_activity($this->pdo, $userId, 'task_created', 'Task A', 'task', 1);
        log_activity($this->pdo, $userId, 'task_created', 'Task B', 'task', 2);
        log_activity($this->pdo, $userId, 'invoice_created', 'Invoice', 'invoice', 1);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM activity_log WHERE entity_type='task'");
        $stmt->execute();
        $this->assertSame(2, (int)$stmt->fetchColumn());
    }

    // ------------------------------------------------------------------
    // Multiple logs
    // ------------------------------------------------------------------

    public function testMultipleLogsOrderedByCreatedAt(): void
    {
        $userId = $this->getUserId();
        log_activity($this->pdo, $userId, 'event_a', 'First event');
        log_activity($this->pdo, $userId, 'event_b', 'Second event');
        log_activity($this->pdo, $userId, 'event_c', 'Third event');

        $stmt = $this->pdo->prepare("SELECT action FROM activity_log WHERE user_id=? ORDER BY id ASC");
        $stmt->execute([$userId]);
        $actions = array_column($stmt->fetchAll(), 'action');

        $this->assertSame('event_a', $actions[0]);
        $this->assertSame('event_c', $actions[2]);
    }

    // ------------------------------------------------------------------
    // IP address capture
    // ------------------------------------------------------------------

    public function testLogActivityCapturesIpAddress(): void
    {
        $_SERVER['REMOTE_ADDR'] = '192.168.1.100';
        $userId = $this->getUserId();
        log_activity($this->pdo, $userId, 'test_ip', 'IP test');

        $stmt = $this->pdo->prepare("SELECT ip_address FROM activity_log WHERE action='test_ip' LIMIT 1");
        $stmt->execute();
        $ip = $stmt->fetchColumn();
        $this->assertSame('192.168.1.100', $ip);
    }
}
