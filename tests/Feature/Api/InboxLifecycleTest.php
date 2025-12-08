<?php

declare(strict_types=1);

namespace Snaply\Tests\Feature\Api;

use PHPUnit\Framework\TestCase;
use Snaply\Api\InboxController;
use Snaply\Config\CleanupConfig;
use Snaply\Database\Connection;
use Snaply\Entity\Inbox;
use Snaply\Repository\InboxAddressCooldownRepository;
use Snaply\Repository\InboxRepository;
use Snaply\Repository\MessageRepository;
use Snaply\Service\CleanupService;
use Snaply\Service\InboxService;

/**
 * Feature tests for inbox lifecycle operations.
 *
 * These tests verify the complete end-to-end inbox lifecycle:
 * - Automatic creation on first visit
 * - Session-based inbox retrieval
 * - Inbox rotation (abandon + new)
 * - Immediate deletion + new inbox creation
 * - Expiry processing
 * - Cleanup operations
 */
class InboxLifecycleTest extends TestCase
{
    private Connection $connection;
    private InboxService $inboxService;
    private InboxRepository $inboxRepository;

    protected function setUp(): void
    {
        // Note: These tests use mocks since we don't have a test database configured
        // In a real environment, you would set up a test database and run actual queries

        $this->connection = $this->createMock(Connection::class);
        $cooldownRepository = $this->createMock(InboxAddressCooldownRepository::class);
        $this->inboxRepository = $this->createMock(InboxRepository::class);
        $messageRepository = $this->createMock(MessageRepository::class);

        $this->inboxService = new InboxService(
            $this->connection,
            $this->inboxRepository,
            $messageRepository,
            $cooldownRepository,
            [
                'domain' => 'test.example.com',
                'ttl_minutes' => 60,
                'cooldown_hours' => 24,
                'address_length' => 10,
                'max_retry_attempts' => 10,
            ]
        );
    }

    /**
     * Test: First visit creates an inbox automatically.
     *
     * Acceptance Criteria:
     * - On first page load for a new browser session, the system automatically
     *   creates an inbox and a corresponding unique email address
     */
    public function testFirstVisitCreatesInboxAutomatically(): void
    {
        $sessionToken = 'new-session-token-' . bin2hex(random_bytes(16));
        $newInbox = new Inbox(
            id: 1,
            sessionTokenHash: hash('sha256', $sessionToken),
            emailLocalPart: 'abc123xyz',
            emailDomain: 'test.example.com',
            status: Inbox::STATUS_ACTIVE,
            ttlMinutes: 60
        );

        // No existing inbox found
        $this->inboxRepository
            ->method('findActiveBySessionTokenHash')
            ->willReturn(null);

        // Transaction callback executes
        $this->connection
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        // Address is available
        $this->inboxRepository
            ->method('emailAddressExists')
            ->willReturn(false);

        // Save returns new inbox
        $this->inboxRepository
            ->method('save')
            ->willReturn($newInbox);

        $inbox = $this->inboxService->getOrCreateActiveInboxForSession($sessionToken);

        $this->assertInstanceOf(Inbox::class, $inbox);
        $this->assertSame(Inbox::STATUS_ACTIVE, $inbox->status);
        $this->assertSame('test.example.com', $inbox->emailDomain);
        $this->assertSame(60, $inbox->ttlMinutes);
    }

    /**
     * Test: Reload within TTL keeps the same inbox.
     *
     * Acceptance Criteria:
     * - All tabs sharing the same browser session share the same active inbox
     * - Reload within TTL maintains the same inbox
     */
    public function testReloadWithinTtlKeepsSameInbox(): void
    {
        $sessionToken = 'existing-session-token';
        $existingInbox = new Inbox(
            id: 123,
            sessionTokenHash: hash('sha256', $sessionToken),
            emailLocalPart: 'existing123',
            emailDomain: 'test.example.com',
            status: Inbox::STATUS_ACTIVE,
            ttlMinutes: 60
        );

        // Existing inbox found
        $this->inboxRepository
            ->method('findActiveBySessionTokenHash')
            ->willReturn($existingInbox);

        $this->inboxRepository
            ->method('updateLastAccessed')
            ->with(123)
            ->willReturn(true);

        $this->inboxRepository
            ->method('findOrFail')
            ->with(123)
            ->willReturn($existingInbox);

        $inbox = $this->inboxService->getOrCreateActiveInboxForSession($sessionToken);

        $this->assertSame(123, $inbox->id);
        $this->assertSame('existing123', $inbox->emailLocalPart);
    }

    /**
     * Test: Rotate abandons current inbox and creates new one.
     *
     * Acceptance Criteria:
     * - User can trigger "New address / Rotate" action
     * - Current inbox is marked as abandoned
     * - New inbox with fresh address is created
     */
    public function testRotateAbandonsCurrentAndCreatesNew(): void
    {
        $sessionToken = 'session-token-for-rotation';
        $currentInbox = new Inbox(
            id: 100,
            sessionTokenHash: hash('sha256', $sessionToken),
            emailLocalPart: 'old100',
            emailDomain: 'test.example.com',
            status: Inbox::STATUS_ACTIVE,
            ttlMinutes: 60
        );

        $newInbox = new Inbox(
            id: 101,
            sessionTokenHash: hash('sha256', $sessionToken),
            emailLocalPart: 'new101',
            emailDomain: 'test.example.com',
            status: Inbox::STATUS_ACTIVE,
            ttlMinutes: 60
        );

        // Current inbox exists
        $this->inboxRepository
            ->method('findBySessionTokenHash')
            ->willReturn($currentInbox);

        // Transaction callback executes
        $this->connection
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        // Mark as abandoned called
        $this->inboxRepository
            ->expects($this->once())
            ->method('markAsAbandoned')
            ->with(100);

        // New address is available
        $this->inboxRepository
            ->method('emailAddressExists')
            ->willReturn(false);

        // Save returns new inbox
        $this->inboxRepository
            ->method('save')
            ->willReturn($newInbox);

        $result = $this->inboxService->rotateInboxForSession($sessionToken);

        $this->assertSame(101, $result->id);
        $this->assertSame('new101', $result->emailLocalPart);
    }

    /**
     * Test: Delete-now removes current inbox and creates new empty one.
     *
     * Acceptance Criteria:
     * - User can trigger "Delete inbox now" action
     * - Current inbox and messages are soft-deleted
     * - New empty inbox with fresh address is created
     */
    public function testDeleteNowRemovesCurrentAndCreatesNew(): void
    {
        $sessionToken = 'session-token-for-deletion';
        $currentInbox = new Inbox(
            id: 200,
            sessionTokenHash: hash('sha256', $sessionToken),
            emailLocalPart: 'delete200',
            emailDomain: 'test.example.com',
            status: Inbox::STATUS_ACTIVE,
            ttlMinutes: 60
        );

        $newInbox = new Inbox(
            id: 201,
            sessionTokenHash: hash('sha256', $sessionToken),
            emailLocalPart: 'fresh201',
            emailDomain: 'test.example.com',
            status: Inbox::STATUS_ACTIVE,
            ttlMinutes: 60
        );

        // Current inbox exists
        $this->inboxRepository
            ->method('findBySessionTokenHash')
            ->willReturn($currentInbox);

        // Transaction callback executes
        $this->connection
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        // Delete called
        $this->inboxRepository
            ->expects($this->once())
            ->method('delete')
            ->with(200);

        // New address is available
        $this->inboxRepository
            ->method('emailAddressExists')
            ->willReturn(false);

        // Save returns new inbox
        $this->inboxRepository
            ->method('save')
            ->willReturn($newInbox);

        $result = $this->inboxService->deleteInboxNowForSession($sessionToken);

        $this->assertSame(201, $result->id);
        $this->assertSame('fresh201', $result->emailLocalPart);
    }

    /**
     * Test: Expired inboxes are marked correctly.
     *
     * Acceptance Criteria:
     * - Inboxes exceeding TTL are identified and marked as expired
     * - Batch processing handles multiple expired inboxes
     */
    public function testExpiredInboxesAreMarkedCorrectly(): void
    {
        $expiredInboxes = [
            new Inbox(id: 1),
            new Inbox(id: 2),
            new Inbox(id: 3),
        ];

        $this->inboxRepository
            ->method('findExpired')
            ->with(100)
            ->willReturn($expiredInboxes);

        $this->inboxRepository
            ->method('markAsExpired')
            ->willReturn(true);

        $count = $this->inboxService->processExpiredInboxes(100);

        $this->assertSame(3, $count);
    }

    /**
     * Test: Cleanup service orchestrates all phases.
     *
     * Acceptance Criteria:
     * - Cleanup runs in three phases: mark expired, hard delete, clean cooldowns
     * - Statistics are tracked and returned
     * - Operations complete within timeout limits
     */
    public function testCleanupServiceOrchestatesAllPhases(): void
    {
        $config = new CleanupConfig(
            inboxAgeMinutes: 60,
            batchSize: 1000,
            maxRuntimeSeconds: 300,
            verbose: false,
            dryRun: false
        );

        $this->inboxRepository
            ->method('findExpired')
            ->willReturn([]);

        $this->inboxRepository
            ->method('hardDeleteExpired')
            ->willReturn(5);

        $cleanupService = new CleanupService($this->inboxService, $config);
        $stats = $cleanupService->runFullCleanup();

        $this->assertInstanceOf(\Snaply\Value\CleanupStats::class, $stats);
        $this->assertTrue($stats->completed);
        $this->assertGreaterThan(0, $stats->executionTimeSeconds);
    }
}
