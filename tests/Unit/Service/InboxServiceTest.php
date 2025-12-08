<?php

declare(strict_types=1);

namespace Snaply\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Snaply\Database\Connection;
use Snaply\Entity\Inbox;
use Snaply\Repository\InboxAddressCooldownRepository;
use Snaply\Repository\InboxRepository;
use Snaply\Repository\MessageRepository;
use Snaply\Service\InboxService;

/**
 * Unit tests for InboxService.
 */
class InboxServiceTest extends TestCase
{
    private InboxService $service;
    private Connection $connection;
    private InboxRepository $inboxRepository;
    private MessageRepository $messageRepository;
    private InboxAddressCooldownRepository $cooldownRepository;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->inboxRepository = $this->createMock(InboxRepository::class);
        $this->messageRepository = $this->createMock(MessageRepository::class);
        $this->cooldownRepository = $this->createMock(InboxAddressCooldownRepository::class);

        $this->service = new InboxService(
            $this->connection,
            $this->inboxRepository,
            $this->messageRepository,
            $this->cooldownRepository,
            [
                'domain' => 'test.example.com',
                'ttl_minutes' => 60,
                'cooldown_hours' => 24,
                'address_length' => 10,
                'max_retry_attempts' => 10,
            ]
        );
    }

    public function testGetOrCreateActiveInboxForSessionReturnsExistingInbox(): void
    {
        $sessionToken = 'test-session-token';
        $existingInbox = new Inbox(
            id: 123,
            emailLocalPart: 'existing123',
            emailDomain: 'test.example.com'
        );

        $this->inboxRepository
            ->expects($this->once())
            ->method('findActiveBySessionTokenHash')
            ->willReturn($existingInbox);

        $this->inboxRepository
            ->expects($this->once())
            ->method('updateLastAccessed')
            ->with(123)
            ->willReturn(true);

        $this->inboxRepository
            ->expects($this->once())
            ->method('findOrFail')
            ->with(123)
            ->willReturn($existingInbox);

        $result = $this->service->getOrCreateActiveInboxForSession($sessionToken);

        $this->assertSame($existingInbox, $result);
    }

    public function testGetOrCreateActiveInboxForSessionCreatesNewInbox(): void
    {
        $sessionToken = 'test-session-token';
        $newInbox = new Inbox(
            id: 456,
            emailLocalPart: 'new456',
            emailDomain: 'test.example.com'
        );

        $this->inboxRepository
            ->expects($this->once())
            ->method('findActiveBySessionTokenHash')
            ->willReturn(null);

        $this->connection
            ->expects($this->once())
            ->method('transaction')
            ->willReturnCallback(function ($callback) {
                return $callback();
            });

        $this->inboxRepository
            ->expects($this->once())
            ->method('emailAddressExists')
            ->willReturn(false);

        $this->cooldownRepository
            ->expects($this->once())
            ->method('isAddressInCooldown')
            ->willReturn(false);

        $this->inboxRepository
            ->expects($this->once())
            ->method('save')
            ->willReturn($newInbox);

        $this->cooldownRepository
            ->expects($this->once())
            ->method('recordAddressUsage')
            ->willReturn(true);

        $result = $this->service->getOrCreateActiveInboxForSession($sessionToken);

        $this->assertSame($newInbox, $result);
    }

    public function testGenerateUniqueAddressReturnsValidAddress(): void
    {
        $this->inboxRepository
            ->expects($this->once())
            ->method('emailAddressExists')
            ->willReturn(false);

        $this->cooldownRepository
            ->expects($this->once())
            ->method('isAddressInCooldown')
            ->willReturn(false);

        $result = $this->service->generateUniqueAddress('test.com');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('local_part', $result);
        $this->assertArrayHasKey('domain', $result);
        $this->assertSame('test.com', $result['domain']);
        $this->assertSame(10, strlen($result['local_part']));
        $this->assertMatchesRegularExpression('/^[a-z0-9]+$/', $result['local_part']);
    }

    public function testGenerateUniqueAddressThrowsExceptionAfterMaxRetries(): void
    {
        $this->inboxRepository
            ->expects($this->exactly(10))
            ->method('emailAddressExists')
            ->willReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to generate unique address after 10 attempts');

        $this->service->generateUniqueAddress('test.com');
    }

    public function testTouchInboxAccessCallsRepository(): void
    {
        $this->inboxRepository
            ->expects($this->once())
            ->method('updateLastAccessed')
            ->with(123)
            ->willReturn(true);

        $result = $this->service->touchInboxAccess(123);

        $this->assertTrue($result);
    }

    public function testMarkInboxAsExpiredCallsRepository(): void
    {
        $this->inboxRepository
            ->expects($this->once())
            ->method('markAsExpired')
            ->with(456)
            ->willReturn(true);

        $result = $this->service->markInboxAsExpired(456);

        $this->assertTrue($result);
    }

    public function testProcessExpiredInboxesMarksMultipleInboxes(): void
    {
        $expiredInboxes = [
            new Inbox(id: 1),
            new Inbox(id: 2),
            new Inbox(id: 3),
        ];

        $this->inboxRepository
            ->expects($this->once())
            ->method('findExpired')
            ->with(100)
            ->willReturn($expiredInboxes);

        $this->inboxRepository
            ->expects($this->exactly(3))
            ->method('markAsExpired')
            ->willReturn(true);

        $result = $this->service->processExpiredInboxes(100);

        $this->assertSame(3, $result);
    }

    public function testCleanupOldInboxesCallsRepository(): void
    {
        $this->inboxRepository
            ->expects($this->once())
            ->method('hardDeleteExpired')
            ->with(60, 100)
            ->willReturn(5);

        $result = $this->service->cleanupOldInboxes(60, 100);

        $this->assertSame(5, $result);
    }

    public function testCleanupExpiredCooldownsCallsRepository(): void
    {
        $this->cooldownRepository
            ->expects($this->once())
            ->method('hardDeleteExpired')
            ->with(100)
            ->willReturn(10);

        $result = $this->service->cleanupExpiredCooldowns(100);

        $this->assertSame(10, $result);
    }
}
