<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for Project CRUD operations.
 */
class ProjectTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = createTestPdo();

        // Seed a client user
        $this->pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)")
            ->execute(['Test Client', 'client@test.com', password_hash('pass', PASSWORD_DEFAULT), 'client']);
    }

    private function getClientId(): int
    {
        return (int)$this->pdo->query("SELECT id FROM users WHERE email='client@test.com' LIMIT 1")->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------

    public function testCreateProject(): void
    {
        $clientId = $this->getClientId();
        $stmt = $this->pdo->prepare(
            "INSERT INTO projects (client_id, title, description, status, priority) VALUES (?,?,?,?,?)"
        );
        $stmt->execute([$clientId, 'Test Project', 'A test project', 'pending', 'medium']);
        $id = (int)$this->pdo->lastInsertId();
        $this->assertGreaterThan(0, $id);
    }

    public function testCreateProjectReturnsCorrectTitle(): void
    {
        $clientId = $this->getClientId();
        $this->pdo->prepare("INSERT INTO projects (client_id, title, status, priority) VALUES (?,?,?,?)")
            ->execute([$clientId, 'My Website', 'pending', 'high']);
        $id = (int)$this->pdo->lastInsertId();

        $row = $this->pdo->prepare("SELECT * FROM projects WHERE id=?");
        $row->execute([$id]);
        $project = $row->fetch();

        $this->assertSame('My Website', $project['title']);
        $this->assertSame('pending', $project['status']);
    }

    // ------------------------------------------------------------------
    // Read
    // ------------------------------------------------------------------

    public function testReadProjectsByClient(): void
    {
        $clientId = $this->getClientId();
        $this->pdo->prepare("INSERT INTO projects (client_id, title, status, priority) VALUES (?,?,?,?)")
            ->execute([$clientId, 'Project A', 'pending', 'low']);
        $this->pdo->prepare("INSERT INTO projects (client_id, title, status, priority) VALUES (?,?,?,?)")
            ->execute([$clientId, 'Project B', 'in_progress', 'medium']);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM projects WHERE client_id=?");
        $stmt->execute([$clientId]);
        $this->assertSame(2, (int)$stmt->fetchColumn());
    }

    // ------------------------------------------------------------------
    // Update
    // ------------------------------------------------------------------

    public function testUpdateProjectStatus(): void
    {
        $clientId = $this->getClientId();
        $this->pdo->prepare("INSERT INTO projects (client_id, title, status, priority) VALUES (?,?,?,?)")
            ->execute([$clientId, 'Updatable', 'pending', 'medium']);
        $id = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("UPDATE projects SET status=? WHERE id=?")->execute(['completed', $id]);

        $stmt = $this->pdo->prepare("SELECT status FROM projects WHERE id=?");
        $stmt->execute([$id]);
        $this->assertSame('completed', $stmt->fetchColumn());
    }

    public function testUpdateProjectProgress(): void
    {
        $clientId = $this->getClientId();
        $this->pdo->prepare("INSERT INTO projects (client_id, title, status, priority, progress) VALUES (?,?,?,?,?)")
            ->execute([$clientId, 'Progress Test', 'in_progress', 'high', 0]);
        $id = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("UPDATE projects SET progress=? WHERE id=?")->execute([75, $id]);

        $stmt = $this->pdo->prepare("SELECT progress FROM projects WHERE id=?");
        $stmt->execute([$id]);
        $this->assertSame(75, (int)$stmt->fetchColumn());
    }

    // ------------------------------------------------------------------
    // Delete
    // ------------------------------------------------------------------

    public function testDeleteProject(): void
    {
        $clientId = $this->getClientId();
        $this->pdo->prepare("INSERT INTO projects (client_id, title, status, priority) VALUES (?,?,?,?)")
            ->execute([$clientId, 'To Delete', 'pending', 'low']);
        $id = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("DELETE FROM projects WHERE id=?")->execute([$id]);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM projects WHERE id=?");
        $stmt->execute([$id]);
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }

    // ------------------------------------------------------------------
    // Validation helpers
    // ------------------------------------------------------------------

    public function testStatusValues(): void
    {
        $validStatuses = ['pending', 'in_progress', 'on_hold', 'completed', 'cancelled'];
        foreach ($validStatuses as $s) {
            $this->assertContains($s, $validStatuses);
        }
        $this->assertNotContains('unknown_status', $validStatuses);
    }

    public function testGetStatusBadgeReturnsString(): void
    {
        $badge = getStatusBadge('pending');
        $this->assertIsString($badge);
        $this->assertSame('warning', $badge);
    }
}
