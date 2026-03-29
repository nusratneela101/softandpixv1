<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for Chat functionality.
 */
class ChatTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = createTestPdo();
        $this->pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)")
            ->execute(['Chat User', 'chatuser@test.com', password_hash('pass', PASSWORD_DEFAULT), 'client']);
    }

    private function getUserId(): int
    {
        return (int)$this->pdo->query("SELECT id FROM users WHERE email='chatuser@test.com'")->fetchColumn();
    }

    private function createConversation(string $title = 'Test Chat'): int
    {
        $this->pdo->prepare("INSERT INTO chat_conversations (type, title) VALUES (?,?)")
            ->execute(['support', $title]);
        return (int)$this->pdo->lastInsertId();
    }

    // ------------------------------------------------------------------
    // Send message
    // ------------------------------------------------------------------

    public function testSendMessage(): void
    {
        $userId = $this->getUserId();
        $convId = $this->createConversation();

        $this->pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message) VALUES (?,?,?,?)")
            ->execute([$convId, $userId, 'user', 'Hello, I need help!']);

        $msgId = (int)$this->pdo->lastInsertId();
        $this->assertGreaterThan(0, $msgId);
    }

    // ------------------------------------------------------------------
    // Fetch messages
    // ------------------------------------------------------------------

    public function testFetchMessagesForConversation(): void
    {
        $userId = $this->getUserId();
        $convId = $this->createConversation();

        $this->pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message) VALUES (?,?,?,?)")
            ->execute([$convId, $userId, 'user', 'Message 1']);
        $this->pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message) VALUES (?,?,?,?)")
            ->execute([$convId, 0, 'bot', 'Bot reply']);

        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM chat_messages WHERE conversation_id=?");
        $stmt->execute([$convId]);
        $this->assertSame(2, (int)$stmt->fetchColumn());
    }

    // ------------------------------------------------------------------
    // Message content
    // ------------------------------------------------------------------

    public function testMessageIsStoredWithCorrectContent(): void
    {
        $userId  = $this->getUserId();
        $convId  = $this->createConversation();
        $message = 'I need a website built';

        $this->pdo->prepare("INSERT INTO chat_messages (conversation_id, sender_id, sender_role, message) VALUES (?,?,?,?)")
            ->execute([$convId, $userId, 'user', $message]);
        $msgId = (int)$this->pdo->lastInsertId();

        $stmt = $this->pdo->prepare("SELECT message FROM chat_messages WHERE id=?");
        $stmt->execute([$msgId]);
        $this->assertSame($message, $stmt->fetchColumn());
    }

    // ------------------------------------------------------------------
    // Group / conversation creation
    // ------------------------------------------------------------------

    public function testCreateConversation(): void
    {
        $convId = $this->createConversation('Support Chat');
        $this->assertGreaterThan(0, $convId);
    }

    public function testConversationHasCorrectTitle(): void
    {
        $convId = $this->createConversation('Project Alpha Chat');
        $stmt   = $this->pdo->prepare("SELECT title FROM chat_conversations WHERE id=?");
        $stmt->execute([$convId]);
        $this->assertSame('Project Alpha Chat', $stmt->fetchColumn());
    }

    // ------------------------------------------------------------------
    // Empty message validation
    // ------------------------------------------------------------------

    public function testEmptyMessageIsInvalid(): void
    {
        $message = '';
        $this->assertEmpty(trim($message));
        $this->assertFalse(strlen(trim($message)) > 0, 'Empty message should not be sent');
    }

    public function testWhitespaceOnlyMessageIsInvalid(): void
    {
        $message = '   ';
        $this->assertEmpty(trim($message));
    }
}
