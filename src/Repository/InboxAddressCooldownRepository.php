<?php

declare(strict_types=1);

namespace Snaply\Repository;

use DateTimeImmutable;
use Snaply\Entity\InboxAddressCooldown;
use Snaply\Exception\ValidationException;

/**
 * Repository for InboxAddressCooldown entities.
 *
 * Manages address cooldown tracking to prevent immediate reuse of email addresses.
 * Cooldowns do not use soft delete - they are hard-deleted when expired.
 */
class InboxAddressCooldownRepository extends AbstractRepository
{
    protected function getTableName(): string
    {
        return 'inbox_address_cooldowns';
    }

    protected function getEntityClass(): string
    {
        return InboxAddressCooldown::class;
    }

    /**
     * Cooldowns do not support soft delete.
     */
    protected function supportsSoftDelete(): bool
    {
        return false;
    }

    /**
     * Find a cooldown by ID.
     */
    public function find(int $id): ?InboxAddressCooldown
    {
        /** @var InboxAddressCooldown|null */
        return parent::find($id);
    }

    /**
     * Find a cooldown by ID or throw.
     *
     * @throws \Snaply\Exception\EntityNotFoundException
     */
    public function findOrFail(int $id): InboxAddressCooldown
    {
        /** @var InboxAddressCooldown */
        return parent::findOrFail($id);
    }

    /**
     * Find cooldown record by email address.
     */
    public function findByEmailAddress(string $localPart, string $domain): ?InboxAddressCooldown
    {
        $sql = 'SELECT * FROM `inbox_address_cooldowns`
                WHERE `email_local_part` = ? AND `email_domain` = ?
                LIMIT 1';

        $row = $this->connection->fetchOne($sql, [$localPart, $domain]);

        return $row !== null ? InboxAddressCooldown::fromRow($row) : null;
    }

    /**
     * Check if an email address is currently in cooldown.
     */
    public function isAddressInCooldown(string $localPart, string $domain): bool
    {
        $sql = 'SELECT 1 FROM `inbox_address_cooldowns`
                WHERE `email_local_part` = ?
                  AND `email_domain` = ?
                  AND `cooldown_until` > NOW()
                LIMIT 1';

        return $this->connection->fetchColumn($sql, [$localPart, $domain]) !== null;
    }

    /**
     * Get all addresses currently in cooldown period.
     *
     * @return InboxAddressCooldown[]
     */
    public function findActiveCooldowns(): array
    {
        $sql = 'SELECT * FROM `inbox_address_cooldowns`
                WHERE `cooldown_until` > NOW()
                ORDER BY `cooldown_until` ASC';

        $rows = $this->connection->fetchAll($sql);

        return array_map(fn($row) => InboxAddressCooldown::fromRow($row), $rows);
    }

    /**
     * Record address usage with cooldown period.
     *
     * @param string $localPart Email local part
     * @param string $domain Email domain
     * @param int $cooldownHours Cooldown period in hours (default: 24)
     *
     * @return InboxAddressCooldown The saved cooldown record
     *
     * @throws ValidationException If validation fails
     */
    public function recordAddressUsage(
        string $localPart,
        string $domain,
        int $cooldownHours = 24
    ): InboxAddressCooldown {
        $cooldown = new InboxAddressCooldown();
        $cooldown->emailLocalPart = $localPart;
        $cooldown->emailDomain = $domain;
        $cooldown->lastUsedAt = new DateTimeImmutable();
        $cooldown->cooldownUntil = $cooldown->lastUsedAt->modify("+{$cooldownHours} hours");

        return $this->save($cooldown);
    }

    /**
     * Delete expired cooldowns (past their cooldown_until timestamp).
     *
     * @return int Number of cooldowns deleted
     */
    public function deleteExpiredCooldowns(): int
    {
        $sql = 'DELETE FROM `inbox_address_cooldowns`
                WHERE `cooldown_until` <= NOW()';

        return $this->connection->execute($sql);
    }

    /**
     * Batch delete expired cooldowns with limit.
     *
     * @param int $limit Maximum number of records to delete
     *
     * @return int Number of cooldowns deleted
     */
    public function hardDeleteExpired(int $limit = 100): int
    {
        $sql = 'DELETE FROM `inbox_address_cooldowns`
                WHERE `cooldown_until` <= NOW()
                LIMIT ?';

        return $this->connection->execute($sql, [$limit]);
    }

    /**
     * Count addresses currently in cooldown.
     */
    public function countActiveCooldowns(): int
    {
        $sql = 'SELECT COUNT(*) FROM `inbox_address_cooldowns`
                WHERE `cooldown_until` > NOW()';

        return (int) $this->connection->fetchColumn($sql);
    }

    /**
     * Save a cooldown record (insert or update).
     *
     * @return InboxAddressCooldown The saved cooldown with ID populated
     *
     * @throws ValidationException If validation fails
     */
    public function save(InboxAddressCooldown $cooldown): InboxAddressCooldown
    {
        $this->validate($cooldown);

        $data = [
            'email_local_part' => $cooldown->emailLocalPart,
            'email_domain' => $cooldown->emailDomain,
            'last_used_at' => $cooldown->lastUsedAt?->format('Y-m-d H:i:s'),
            'cooldown_until' => $cooldown->cooldownUntil?->format('Y-m-d H:i:s'),
        ];

        if ($cooldown->id === null) {
            $cooldown->id = $this->insert($data);
        } else {
            $this->update($cooldown->id, $data);
        }

        // Reload to get timestamps
        return $this->find($cooldown->id) ?? $cooldown;
    }

    /**
     * Validate a cooldown entity.
     *
     * @throws ValidationException If validation fails
     */
    private function validate(InboxAddressCooldown $cooldown): void
    {
        $errors = [];

        if (empty(trim($cooldown->emailLocalPart))) {
            $errors['email_local_part'] = ['Email local part is required'];
        } elseif (strlen($cooldown->emailLocalPart) > 64) {
            $errors['email_local_part'] = ['Email local part must be 64 characters or less'];
        }

        if (empty(trim($cooldown->emailDomain))) {
            $errors['email_domain'] = ['Email domain is required'];
        } elseif (strlen($cooldown->emailDomain) > 255) {
            $errors['email_domain'] = ['Email domain must be 255 characters or less'];
        }

        if ($cooldown->cooldownUntil === null) {
            $errors['cooldown_until'] = ['Cooldown until timestamp is required'];
        }

        // Check for duplicate address (only on insert)
        if ($cooldown->id === null) {
            $existing = $this->findByEmailAddress(
                $cooldown->emailLocalPart,
                $cooldown->emailDomain
            );

            if ($existing !== null) {
                $errors['email_address'] = ['This email address already has a cooldown record'];
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
