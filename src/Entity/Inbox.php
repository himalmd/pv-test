<?php

declare(strict_types=1);

namespace Snaply\Entity;

use DateTimeImmutable;

/**
 * Inbox entity representing a temporary email inbox.
 */
class Inbox
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ABANDONED = 'abandoned';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_DELETED = 'deleted';

    public ?int $id = null;
    public string $sessionTokenHash = '';
    public string $emailLocalPart = '';
    public string $emailDomain = '';
    public string $status = self::STATUS_ACTIVE;
    public int $ttlMinutes = 60;
    public ?DateTimeImmutable $lastAccessedAt = null;
    public ?DateTimeImmutable $expiredAt = null;
    public ?DateTimeImmutable $createdAt = null;
    public ?DateTimeImmutable $updatedAt = null;
    public ?DateTimeImmutable $deletedAt = null;

    /**
     * Create a new Inbox instance.
     */
    public function __construct(
        ?int $id = null,
        string $sessionTokenHash = '',
        string $emailLocalPart = '',
        string $emailDomain = '',
        string $status = self::STATUS_ACTIVE,
        int $ttlMinutes = 60
    ) {
        $this->id = $id;
        $this->sessionTokenHash = $sessionTokenHash;
        $this->emailLocalPart = $emailLocalPart;
        $this->emailDomain = $emailDomain;
        $this->status = $status;
        $this->ttlMinutes = $ttlMinutes;
    }

    /**
     * Create an Inbox from a database row.
     *
     * @param array<string, mixed> $row Database row
     */
    public static function fromRow(array $row): self
    {
        $inbox = new self();
        $inbox->id = isset($row['id']) ? (int) $row['id'] : null;
        $inbox->sessionTokenHash = $row['session_token_hash'] ?? '';
        $inbox->emailLocalPart = $row['email_local_part'] ?? '';
        $inbox->emailDomain = $row['email_domain'] ?? '';
        $inbox->status = $row['status'] ?? self::STATUS_ACTIVE;
        $inbox->ttlMinutes = isset($row['ttl_minutes']) ? (int) $row['ttl_minutes'] : 60;
        $inbox->lastAccessedAt = isset($row['last_accessed_at'])
            ? new DateTimeImmutable($row['last_accessed_at'])
            : null;
        $inbox->expiredAt = isset($row['expired_at'])
            ? new DateTimeImmutable($row['expired_at'])
            : null;
        $inbox->createdAt = isset($row['created_at'])
            ? new DateTimeImmutable($row['created_at'])
            : null;
        $inbox->updatedAt = isset($row['updated_at'])
            ? new DateTimeImmutable($row['updated_at'])
            : null;
        $inbox->deletedAt = isset($row['deleted_at'])
            ? new DateTimeImmutable($row['deleted_at'])
            : null;

        return $inbox;
    }

    /**
     * Convert to an array for database operations.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'session_token_hash' => $this->sessionTokenHash,
            'email_local_part' => $this->emailLocalPart,
            'email_domain' => $this->emailDomain,
            'status' => $this->status,
            'ttl_minutes' => $this->ttlMinutes,
        ];
    }

    /**
     * Check if this inbox is soft-deleted.
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Check if this inbox is active (not soft-deleted).
     */
    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    /**
     * Get the full email address.
     */
    public function getFullEmailAddress(): string
    {
        return $this->emailLocalPart . '@' . $this->emailDomain;
    }

    /**
     * Check if this inbox has expired based on TTL.
     */
    public function isExpired(): bool
    {
        if ($this->lastAccessedAt === null) {
            return false;
        }

        $expiryTime = $this->lastAccessedAt->modify("+{$this->ttlMinutes} minutes");
        return new DateTimeImmutable() > $expiryTime;
    }

    /**
     * Get valid status values.
     *
     * @return string[]
     */
    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_ACTIVE,
            self::STATUS_ABANDONED,
            self::STATUS_EXPIRED,
            self::STATUS_DELETED,
        ];
    }
}
