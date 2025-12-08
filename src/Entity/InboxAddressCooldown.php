<?php

declare(strict_types=1);

namespace Snaply\Entity;

use DateTimeImmutable;

/**
 * InboxAddressCooldown entity for tracking recently used email addresses.
 */
class InboxAddressCooldown
{
    public ?int $id = null;
    public string $emailLocalPart = '';
    public string $emailDomain = '';
    public ?DateTimeImmutable $lastUsedAt = null;
    public ?DateTimeImmutable $cooldownUntil = null;
    public ?DateTimeImmutable $createdAt = null;

    /**
     * Create a new InboxAddressCooldown instance.
     */
    public function __construct(
        ?int $id = null,
        string $emailLocalPart = '',
        string $emailDomain = '',
        ?DateTimeImmutable $cooldownUntil = null
    ) {
        $this->id = $id;
        $this->emailLocalPart = $emailLocalPart;
        $this->emailDomain = $emailDomain;
        $this->cooldownUntil = $cooldownUntil;
    }

    /**
     * Create an InboxAddressCooldown from a database row.
     *
     * @param array<string, mixed> $row Database row
     */
    public static function fromRow(array $row): self
    {
        $cooldown = new self();
        $cooldown->id = isset($row['id']) ? (int) $row['id'] : null;
        $cooldown->emailLocalPart = $row['email_local_part'] ?? '';
        $cooldown->emailDomain = $row['email_domain'] ?? '';
        $cooldown->lastUsedAt = isset($row['last_used_at'])
            ? new DateTimeImmutable($row['last_used_at'])
            : null;
        $cooldown->cooldownUntil = isset($row['cooldown_until'])
            ? new DateTimeImmutable($row['cooldown_until'])
            : null;
        $cooldown->createdAt = isset($row['created_at'])
            ? new DateTimeImmutable($row['created_at'])
            : null;

        return $cooldown;
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
            'email_local_part' => $this->emailLocalPart,
            'email_domain' => $this->emailDomain,
            'cooldown_until' => $this->cooldownUntil?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Check if this address is still in cooldown period.
     */
    public function isCoolingDown(): bool
    {
        if ($this->cooldownUntil === null) {
            return false;
        }

        return new DateTimeImmutable() < $this->cooldownUntil;
    }

    /**
     * Get the full email address.
     */
    public function getFullEmailAddress(): string
    {
        return $this->emailLocalPart . '@' . $this->emailDomain;
    }
}
