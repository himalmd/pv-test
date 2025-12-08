<?php

declare(strict_types=1);

namespace Snaply\Tests\Unit\Entity;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Snaply\Entity\Inbox;

/**
 * Unit tests for Inbox entity.
 */
class InboxTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $inbox = new Inbox(
            id: 123,
            sessionTokenHash: 'hash123',
            emailLocalPart: 'test123',
            emailDomain: 'example.com',
            status: Inbox::STATUS_ACTIVE,
            ttlMinutes: 60
        );

        $this->assertSame(123, $inbox->id);
        $this->assertSame('hash123', $inbox->sessionTokenHash);
        $this->assertSame('test123', $inbox->emailLocalPart);
        $this->assertSame('example.com', $inbox->emailDomain);
        $this->assertSame(Inbox::STATUS_ACTIVE, $inbox->status);
        $this->assertSame(60, $inbox->ttlMinutes);
    }

    public function testFromRowCreatesInbox(): void
    {
        $row = [
            'id' => 456,
            'session_token_hash' => 'hash456',
            'email_local_part' => 'abc123',
            'email_domain' => 'tempinbox.pro',
            'status' => Inbox::STATUS_ACTIVE,
            'ttl_minutes' => 60,
            'last_accessed_at' => '2025-12-08 10:00:00',
            'created_at' => '2025-12-08 09:00:00',
        ];

        $inbox = Inbox::fromRow($row);

        $this->assertSame(456, $inbox->id);
        $this->assertSame('hash456', $inbox->sessionTokenHash);
        $this->assertSame('abc123', $inbox->emailLocalPart);
        $this->assertSame('tempinbox.pro', $inbox->emailDomain);
        $this->assertSame(Inbox::STATUS_ACTIVE, $inbox->status);
        $this->assertInstanceOf(DateTimeImmutable::class, $inbox->lastAccessedAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $inbox->createdAt);
    }

    public function testGetFullEmailAddress(): void
    {
        $inbox = new Inbox(
            emailLocalPart: 'test123',
            emailDomain: 'example.com'
        );

        $this->assertSame('test123@example.com', $inbox->getFullEmailAddress());
    }

    public function testIsDeletedReturnsTrueWhenDeletedAtSet(): void
    {
        $inbox = new Inbox();
        $inbox->deletedAt = new DateTimeImmutable();

        $this->assertTrue($inbox->isDeleted());
        $this->assertFalse($inbox->isActive());
    }

    public function testIsActiveReturnsTrueWhenNotDeleted(): void
    {
        $inbox = new Inbox();
        $inbox->deletedAt = null;

        $this->assertTrue($inbox->isActive());
        $this->assertFalse($inbox->isDeleted());
    }

    public function testIsExpiredReturnsTrueWhenTtlExceeded(): void
    {
        $inbox = new Inbox();
        $inbox->ttlMinutes = 60;
        $inbox->lastAccessedAt = (new DateTimeImmutable())->modify('-61 minutes');

        $this->assertTrue($inbox->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenWithinTtl(): void
    {
        $inbox = new Inbox();
        $inbox->ttlMinutes = 60;
        $inbox->lastAccessedAt = (new DateTimeImmutable())->modify('-30 minutes');

        $this->assertFalse($inbox->isExpired());
    }

    public function testIsExpiredReturnsFalseWhenLastAccessedAtNull(): void
    {
        $inbox = new Inbox();
        $inbox->ttlMinutes = 60;
        $inbox->lastAccessedAt = null;

        $this->assertFalse($inbox->isExpired());
    }

    public function testGetValidStatusesReturnsAllStatuses(): void
    {
        $statuses = Inbox::getValidStatuses();

        $this->assertContains(Inbox::STATUS_ACTIVE, $statuses);
        $this->assertContains(Inbox::STATUS_ABANDONED, $statuses);
        $this->assertContains(Inbox::STATUS_EXPIRED, $statuses);
        $this->assertContains(Inbox::STATUS_DELETED, $statuses);
        $this->assertCount(4, $statuses);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $inbox = new Inbox(
            id: 789,
            sessionTokenHash: 'hash789',
            emailLocalPart: 'xyz789',
            emailDomain: 'test.com',
            status: Inbox::STATUS_ACTIVE,
            ttlMinutes: 120
        );

        $array = $inbox->toArray();

        $this->assertSame(789, $array['id']);
        $this->assertSame('hash789', $array['session_token_hash']);
        $this->assertSame('xyz789', $array['email_local_part']);
        $this->assertSame('test.com', $array['email_domain']);
        $this->assertSame(Inbox::STATUS_ACTIVE, $array['status']);
        $this->assertSame(120, $array['ttl_minutes']);
    }
}
