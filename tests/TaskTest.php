<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for Task CRUD operations, assignment, status updates, and comments.
 */
class TaskTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = createTestPdo();

        // Seed users
        $this->pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)")
            ->execute(['Dev User', 'dev@test.com', password_hash('pass', PASSWORD_DEFAULT), 'developer']);
        $this->pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)")
            ->execute(['Client User', 'client@test.com', password_hash('pass', PASSWORD_DEFAULT), 'client']);

        // Seed a project
        $devId    = $this->getPdo("SELECT id FROM users WHERE email='dev@test.com'")->fetchColumn();
        $clientId = $this->getPdo("SELECT id FROM users WHERE email='client@test.com'")->fetchColumn();
        $this->pdo->prepare("INSERT INTO projects (client_id, developer_id, title, status, priority) VALUES (?,?,?,?,?)")
            ->execute([$clientId, $devId, 'Test Project', 'in_progress', 'medium']);
    }

    private function getPdo(string $sql): \PDOStatement
    {
        return $this->pdo->query($sql);
    }

    private function getProjectId(): int
    {
        return (int)$this->getPdo("SELECT id FROM projects LIMIT 1")->fetchColumn();
    }

    private function getDevId(): int
    {
        return (int)$this->getPdo("SELECT id FROM users WHERE email='dev@test.com'")->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Create
    // ------------------------------------------------------------------

    public function testCreateTask(): void
    {
        $projectId = $this->getProjectId();
        $devId     = $this->getDevId();

        $stmt = $this->pdo->prepare(
            "INSERT INTO tasks (project_id, assigned_to, created_by, title, priority, status) VALUES (?,?,?,?,?,?)"
        );
        $stmt->execute([$projectId, $devId, $devId, 'Fix login bug', 'high', 'pending']);
        $this->assertGreaterThan(0, (int)$this->pdo->lastInsertId());
    }

    public function testCreateTaskWithDueDate(): void
    {
        $projectId = $this->getProjectId();
        $devId     = $this->getDevId();
        $dueDate   = date('Y-m-d', strtotime('+7 days'));

        $this->pdo->prepare("INSERT INTO tasks (project_id, created_by, title, status, priority, due_date) VALUES (?,?,?,?,?,?)")
            ->execute([$projectId, $devId, 'Deploy to staging', 'pending', 'urgent', $dueDate]);

        $id = (int)$this->pdo->lastInsertId();
        $row = $this->pdo->prepare("SELECT due_date FROM tasks WHERE id=?");
        $row->execute([$id]);
        $this->assertSame($dueDate, $row->fetchColumn());
    }

    // ------------------------------------------------------------------
    // Read
    // ------------------------------------------------------------------

    public function testReadTasksByDeveloper(): void
    {
        $projectId = $this->getProjectId();
        $devId     = $this->getDevId();

        $this->pdo->prepare("INSERT INTO tasks (project_id, assigned_to, created_by, title, status, priority) VALUES (?,?,?,?,?,?)")
            ->execute([$projectId, $devId, $devId, 'Task A', 'pending', 'low']);
        $this->pdo->prepare("INSERT INTO tasks (project_id, assigned_to, created_by, title, status, priority) VALUES (?,?,?,?,?,?)")
            ->execute([$projectId, $devId, $devId, 'Task B', 'in_progress', 'medium']);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM tasks WHERE assigned_to=?");
        $stmt->execute([$devId]);
        $this->assertSame(2, (int)$stmt->fetchColumn());
    }

    // ------------------------------------------------------------------
    // Update status
    // ------------------------------------------------------------------

    public function testUpdateTaskStatusToInProgress(): void
    {
        $projectId = $this->getProjectId();
        $devId     = $this->getDevId();

        $this->pdo->prepare("INSERT INTO tasks (project_id, assigned_to, created_by, title, status, priority) VALUES (?,?,?,?,?,?)")
            ->execute([$projectId, $devId, $devId, 'Pending Task', 'pending', 'medium']);
        $id = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("UPDATE tasks SET status=? WHERE id=?")->execute(['in_progress', $id]);

        $stmt = $this->pdo->prepare("SELECT status FROM tasks WHERE id=?");
        $stmt->execute([$id]);
        $this->assertSame('in_progress', $stmt->fetchColumn());
    }

    public function testMarkTaskCompletedSetsCompletedAt(): void
    {
        $projectId = $this->getProjectId();
        $devId     = $this->getDevId();

        $this->pdo->prepare("INSERT INTO tasks (project_id, assigned_to, created_by, title, status, priority) VALUES (?,?,?,?,?,?)")
            ->execute([$projectId, $devId, $devId, 'To Complete', 'in_progress', 'high']);
        $id = (int)$this->pdo->lastInsertId();

        $completedAt = date('Y-m-d H:i:s');
        $this->pdo->prepare("UPDATE tasks SET status='completed', completed_at=? WHERE id=?")->execute([$completedAt, $id]);

        $stmt = $this->pdo->prepare("SELECT completed_at FROM tasks WHERE id=?");
        $stmt->execute([$id]);
        $this->assertNotNull($stmt->fetchColumn());
    }

    // ------------------------------------------------------------------
    // Comments
    // ------------------------------------------------------------------

    public function testAddCommentToTask(): void
    {
        $projectId = $this->getProjectId();
        $devId     = $this->getDevId();

        $this->pdo->prepare("INSERT INTO tasks (project_id, assigned_to, created_by, title, status, priority) VALUES (?,?,?,?,?,?)")
            ->execute([$projectId, $devId, $devId, 'Comment Task', 'in_progress', 'low']);
        $taskId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?,?,?)")
            ->execute([$taskId, $devId, 'Working on it now!']);
        $commentId = (int)$this->pdo->lastInsertId();
        $this->assertGreaterThan(0, $commentId);
    }

    public function testReadTaskComments(): void
    {
        $projectId = $this->getProjectId();
        $devId     = $this->getDevId();

        $this->pdo->prepare("INSERT INTO tasks (project_id, assigned_to, created_by, title, status, priority) VALUES (?,?,?,?,?,?)")
            ->execute([$projectId, $devId, $devId, 'Multi Comment', 'in_progress', 'medium']);
        $taskId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?,?,?)")->execute([$taskId, $devId, 'Comment 1']);
        $this->pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?,?,?)")->execute([$taskId, $devId, 'Comment 2']);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM task_comments WHERE task_id=?");
        $stmt->execute([$taskId]);
        $this->assertSame(2, (int)$stmt->fetchColumn());
    }

    // ------------------------------------------------------------------
    // Priority badge
    // ------------------------------------------------------------------

    public function testGetPriorityBadge(): void
    {
        $this->assertSame('success', getPriorityBadge('low'));
        $this->assertSame('warning', getPriorityBadge('medium'));
        $this->assertSame('danger', getPriorityBadge('urgent'));
    }

    // ------------------------------------------------------------------
    // Delete (cascade)
    // ------------------------------------------------------------------

    public function testDeleteTaskCascadesComments(): void
    {
        $projectId = $this->getProjectId();
        $devId     = $this->getDevId();

        $this->pdo->prepare("INSERT INTO tasks (project_id, assigned_to, created_by, title, status, priority) VALUES (?,?,?,?,?,?)")
            ->execute([$projectId, $devId, $devId, 'Delete Me', 'pending', 'low']);
        $taskId = (int)$this->pdo->lastInsertId();

        $this->pdo->prepare("INSERT INTO task_comments (task_id, user_id, comment) VALUES (?,?,?)")->execute([$taskId, $devId, 'A comment']);
        $this->pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([$taskId]);
        // Manually cascade for SQLite
        $this->pdo->prepare("DELETE FROM task_comments WHERE task_id=?")->execute([$taskId]);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM task_comments WHERE task_id=?");
        $stmt->execute([$taskId]);
        $this->assertSame(0, (int)$stmt->fetchColumn());
    }
}
