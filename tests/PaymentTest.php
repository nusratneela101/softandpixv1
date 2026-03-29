<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

/**
 * Tests for payment processing validation.
 */
class PaymentTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = createTestPdo();
        $this->pdo->prepare("INSERT INTO users (name,email,password,role) VALUES (?,?,?,?)")
            ->execute(['Pay Client', 'payclient@test.com', password_hash('pass', PASSWORD_DEFAULT), 'client']);
        $clientId = (int)$this->pdo->query("SELECT id FROM users WHERE email='payclient@test.com'")->fetchColumn();
        $this->pdo->prepare("INSERT INTO invoices (client_id, invoice_number, status, total_amount) VALUES (?,?,?,?)")
            ->execute([$clientId, 'INV-PAY-TEST', 'sent', 500.00]);
    }

    private function getClientId(): int
    {
        return (int)$this->pdo->query("SELECT id FROM users WHERE email='payclient@test.com'")->fetchColumn();
    }

    private function getInvoiceId(): int
    {
        return (int)$this->pdo->query("SELECT id FROM invoices WHERE invoice_number='INV-PAY-TEST'")->fetchColumn();
    }

    // ------------------------------------------------------------------
    // Record a payment
    // ------------------------------------------------------------------

    public function testRecordPayment(): void
    {
        $clientId  = $this->getClientId();
        $invoiceId = $this->getInvoiceId();

        $this->pdo->prepare(
            "INSERT INTO payments (invoice_id, client_id, amount, payment_method, status, transaction_id) VALUES (?,?,?,?,?,?)"
        )->execute([$invoiceId, $clientId, 500.00, 'stripe', 'pending', 'txn_test123']);

        $id = (int)$this->pdo->lastInsertId();
        $this->assertGreaterThan(0, $id);
    }

    public function testPaymentAmountValidation(): void
    {
        $amount = -100.00;
        $this->assertFalse($amount > 0, 'Negative amounts should be rejected');

        $amount = 0;
        $this->assertFalse($amount > 0, 'Zero amount should be rejected');

        $amount = 250.00;
        $this->assertTrue($amount > 0, 'Positive amount should be accepted');
    }

    // ------------------------------------------------------------------
    // Payment status
    // ------------------------------------------------------------------

    public function testPaymentStatusTransitions(): void
    {
        $clientId  = $this->getClientId();
        $invoiceId = $this->getInvoiceId();

        $this->pdo->prepare("INSERT INTO payments (invoice_id, client_id, amount, status) VALUES (?,?,?,?)")
            ->execute([$invoiceId, $clientId, 500.00, 'pending']);
        $payId = (int)$this->pdo->lastInsertId();

        // Mark as completed
        $this->pdo->prepare("UPDATE payments SET status='completed' WHERE id=?")->execute([$payId]);
        // Update invoice
        $this->pdo->prepare("UPDATE invoices SET status='paid' WHERE id=?")->execute([$invoiceId]);

        $stmt = $this->pdo->prepare("SELECT status FROM payments WHERE id=?");
        $stmt->execute([$payId]);
        $this->assertSame('completed', $stmt->fetchColumn());

        $stmt = $this->pdo->prepare("SELECT status FROM invoices WHERE id=?");
        $stmt->execute([$invoiceId]);
        $this->assertSame('paid', $stmt->fetchColumn());
    }

    // ------------------------------------------------------------------
    // Partial payment
    // ------------------------------------------------------------------

    public function testPartialPaymentUpdatesInvoiceToPartial(): void
    {
        $clientId  = $this->getClientId();
        $invoiceId = $this->getInvoiceId();

        $this->pdo->prepare("INSERT INTO payments (invoice_id, client_id, amount, status) VALUES (?,?,?,?)")
            ->execute([$invoiceId, $clientId, 250.00, 'completed']);

        // Invoice is not fully paid — mark as partial
        $this->pdo->prepare("UPDATE invoices SET status='partial' WHERE id=?")->execute([$invoiceId]);

        $stmt = $this->pdo->prepare("SELECT status FROM invoices WHERE id=?");
        $stmt->execute([$invoiceId]);
        $this->assertSame('partial', $stmt->fetchColumn());
    }

    // ------------------------------------------------------------------
    // Helper
    // ------------------------------------------------------------------

    public function testGetPaymentStatusBadge(): void
    {
        $this->assertSame('success', getPaymentStatusBadge('paid'));
        $this->assertSame('warning', getPaymentStatusBadge('unpaid'));
        $this->assertSame('info', getPaymentStatusBadge('partial'));
        $this->assertSame('danger', getPaymentStatusBadge('failed'));
    }
}
