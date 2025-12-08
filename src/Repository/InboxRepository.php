<?php

declare(strict_types=1);

namespace Snaply\Repository;

use Snaply\Entity\Inbox;
use Snaply\Exception\ValidationException;

/**
 * Repository for Inbox entities.
 *
 * Manages temporary inbox lifecycle, session linkage, and address management.
 */
class InboxRepository extends AbstractRepository
{
    private InboxAddressCooldownRepository $cooldownRepository;

    public function __construct(
        \Snaply\Database\Connection $connection,
        InboxAddressCooldownRepository $cooldownRepository
    ) {
        parent::__construct($connection);
        $this->cooldownRepository = $cooldownRepository;
    }

    protected function getTableName(): string
    {
        return 'inboxes';
    }

    protected function getEntityClass(): string
    {
        return Inbox::class;
    }

    /**
     * Find an inbox by ID.
     */
    public function find(int $id): ?Inbox
    {
        /** @var Inbox|null */
        return parent::find($id);
    }

    /**
     * Find an inbox by ID, including soft-deleted.
     */
    public function findWithDeleted(int $id): ?Inbox
    {
        /** @var Inbox|null */
        return parent::findWithDeleted($id);
    }

    /**
     * Find an inbox by ID or throw.
     *
     * @throws \Snaply\Exception\EntityNotFoundException
     */
    public function findOrFail(int $id): Inbox
    {
        /** @var Inbox */
        return parent::findOrFail($id);
    }

    /**
     * Get all active inboxes.
     *
     * @return Inbox[]
     */
    public function findAll(): array
    {
        /** @var Inbox[] */
        return parent::findAll();
    }

    /**
     * Find inbox by session token hash.
     *
     * Returns any inbox (regardless of status) for the session.
     */
    public function findBySessionTokenHash(string $hash): ?Inbox
    {
        $sql = 'SELECT * FROM `inboxes`
                WHERE `session_token_hash` = ?
                  AND `deleted_at` IS NULL
                LIMIT 1';

        $row = $this->connection->fetchOne($sql, [$hash]);

        return $row !== null ? Inbox::fromRow($row) : null;
    }

    /**
     * Find active inbox by session token hash.
     *
     * Only returns inboxes with status = 'active'.
     */
    public function findActiveBySessionTokenHash(string $hash): ?Inbox
    {
        $sql = 'SELECT * FROM `inboxes`
                WHERE `session_token_hash` = ?
                  AND `status` = ?
                  AND `deleted_at` IS NULL
                LIMIT 1';

        $row = $this->connection->fetchOne($sql, [$hash, Inbox::STATUS_ACTIVE]);

        return $row !== null ? Inbox::fromRow($row) : null;
    }

    /**
     * Find inboxes by lifecycle status.
     *
     * @return Inbox[]
     */
    public function findByStatus(string $status): array
    {
        $sql = 'SELECT * FROM `inboxes`
                WHERE `status` = ?
                  AND `deleted_at` IS NULL
                ORDER BY `id` ASC';

        $rows = $this->connection->fetchAll($sql, [$status]);

        return array_map(fn($row) => Inbox::fromRow($row), $rows);
    }

    /**
     * Find expired inboxes (TTL exceeded).
     *
     * @param int $limit Maximum number of inboxes to return
     *
     * @return Inbox[]
     */
    public function findExpired(int $limit = 100): array
    {
        $sql = 'SELECT * FROM `inboxes`
                WHERE `status` = ?
                  AND `deleted_at` IS NULL
                  AND TIMESTAMPDIFF(MINUTE, `last_accessed_at`, NOW()) >= `ttl_minutes`
                ORDER BY `last_accessed_at` ASC
                LIMIT ?';

        $rows = $this->connection->fetchAll($sql, [Inbox::STATUS_ACTIVE, $limit]);

        return array_map(fn($row) => Inbox::fromRow($row), $rows);
    }

    /**
     * Find inboxes ready for hard deletion cleanup.
     *
     * Returns soft-deleted or expired/abandoned inboxes older than specified age.
     *
     * @param int $ageMinutes Minimum age in minutes since status change
     * @param int $limit Maximum number of inboxes to return
     *
     * @return Inbox[]
     */
    public function findReadyForCleanup(int $ageMinutes, int $limit = 100): array
    {
        $sql = 'SELECT * FROM `inboxes`
                WHERE (
                    (`status` IN (?, ?) AND TIMESTAMPDIFF(MINUTE, `updated_at`, NOW()) >= ?)
                    OR `deleted_at` IS NOT NULL
                )
                ORDER BY `updated_at` ASC
                LIMIT ?';

        $rows = $this->connection->fetchAll($sql, [
            Inbox::STATUS_EXPIRED,
            Inbox::STATUS_ABANDONED,
            $ageMinutes,
            $limit,
        ]);

        return array_map(fn($row) => Inbox::fromRow($row), $rows);
    }

    /**
     * Find inbox by email address.
     */
    public function findByEmailAddress(string $localPart, string $domain): ?Inbox
    {
        $sql = 'SELECT * FROM `inboxes`
                WHERE `email_local_part` = ?
                  AND `email_domain` = ?
                  AND `deleted_at` IS NULL
                LIMIT 1';

        $row = $this->connection->fetchOne($sql, [$localPart, $domain]);

        return $row !== null ? Inbox::fromRow($row) : null;
    }

    /**
     * Check if email address is currently in use.
     */
    public function emailAddressExists(string $localPart, string $domain): bool
    {
        $sql = 'SELECT 1 FROM `inboxes`
                WHERE `email_local_part` = ?
                  AND `email_domain` = ?
                  AND `deleted_at` IS NULL
                LIMIT 1';

        return $this->connection->fetchColumn($sql, [$localPart, $domain]) !== null;
    }

    /**
     * Update inbox status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, Inbox::getValidStatuses(), true)) {
            throw ValidationException::forField('status', 'Invalid status value');
        }

        $sql = 'UPDATE `inboxes`
                SET `status` = ?, `updated_at` = NOW()
                WHERE `id` = ? AND `deleted_at` IS NULL';

        return $this->connection->execute($sql, [$status, $id]) > 0;
    }

    /**
     * Update last accessed timestamp (touch).
     */
    public function updateLastAccessed(int $id): bool
    {
        $sql = 'UPDATE `inboxes`
                SET `last_accessed_at` = NOW(), `updated_at` = NOW()
                WHERE `id` = ? AND `deleted_at` IS NULL';

        return $this->connection->execute($sql, [$id]) > 0;
    }

    /**
     * Mark inbox as expired with timestamp.
     */
    public function markAsExpired(int $id): bool
    {
        $sql = 'UPDATE `inboxes`
                SET `status` = ?, `expired_at` = NOW(), `updated_at` = NOW()
                WHERE `id` = ? AND `deleted_at` IS NULL';

        return $this->connection->execute($sql, [Inbox::STATUS_EXPIRED, $id]) > 0;
    }

    /**
     * Mark inbox as abandoned.
     */
    public function markAsAbandoned(int $id): bool
    {
        $sql = 'UPDATE `inboxes`
                SET `status` = ?, `updated_at` = NOW()
                WHERE `id` = ? AND `deleted_at` IS NULL';

        return $this->connection->execute($sql, [Inbox::STATUS_ABANDONED, $id]) > 0;
    }

    /**
     * Hard delete inbox by ID.
     *
     * This permanently removes the inbox and cascades to messages.
     */
    public function hardDeleteById(int $id): bool
    {
        return $this->hardDelete($id);
    }

    /**
     * Hard delete expired inboxes in batch.
     *
     * @param int $ageMinutes Minimum age in minutes since expiration
     * @param int $limit Maximum number of inboxes to delete
     *
     * @return int Number of inboxes deleted
     */
    public function hardDeleteExpired(int $ageMinutes, int $limit = 100): int
    {
        // First, get IDs to delete
        $sql = 'SELECT `id` FROM `inboxes`
                WHERE (`status` IN (?, ?) AND TIMESTAMPDIFF(MINUTE, `updated_at`, NOW()) >= ?)
                   OR `deleted_at` IS NOT NULL
                ORDER BY `updated_at` ASC
                LIMIT ?';

        $rows = $this->connection->fetchAll($sql, [
            Inbox::STATUS_EXPIRED,
            Inbox::STATUS_ABANDONED,
            $ageMinutes,
            $limit,
        ]);

        if (empty($rows)) {
            return 0;
        }

        $ids = array_map(fn($row) => $row['id'], $rows);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        $deleteSql = "DELETE FROM `inboxes` WHERE `id` IN ({$placeholders})";

        return $this->connection->execute($deleteSql, $ids);
    }

    /**
     * Count inboxes by status.
     */
    public function countByStatus(string $status): int
    {
        $sql = 'SELECT COUNT(*) FROM `inboxes`
                WHERE `status` = ?
                  AND `deleted_at` IS NULL';

        return (int) $this->connection->fetchColumn($sql, [$status]);
    }

    /**
     * Count expired inboxes.
     */
    public function countExpired(): int
    {
        $sql = 'SELECT COUNT(*) FROM `inboxes`
                WHERE `status` = ?
                  AND `deleted_at` IS NULL
                  AND TIMESTAMPDIFF(MINUTE, `last_accessed_at`, NOW()) >= `ttl_minutes`';

        return (int) $this->connection->fetchColumn($sql, [Inbox::STATUS_ACTIVE]);
    }

    /**
     * Save an inbox (insert or update).
     *
     * @return Inbox The saved inbox with ID populated
     *
     * @throws ValidationException If validation fails
     */
    public function save(Inbox $inbox): Inbox
    {
        $this->validate($inbox);

        $data = [
            'session_token_hash' => $inbox->sessionTokenHash,
            'email_local_part' => $inbox->emailLocalPart,
            'email_domain' => $inbox->emailDomain,
            'status' => $inbox->status,
            'ttl_minutes' => $inbox->ttlMinutes,
        ];

        if ($inbox->id === null) {
            $inbox->id = $this->insert($data);
        } else {
            $this->update($inbox->id, $data);
        }

        // Reload to get timestamps
        return $this->findWithDeleted($inbox->id) ?? $inbox;
    }

    /**
     * Validate an inbox entity.
     *
     * @throws ValidationException If validation fails
     */
    private function validate(Inbox $inbox): void
    {
        $errors = [];

        // Validate session token hash
        if (empty(trim($inbox->sessionTokenHash))) {
            $errors['session_token_hash'] = ['Session token hash is required'];
        } elseif (strlen($inbox->sessionTokenHash) !== 64) {
            $errors['session_token_hash'] = ['Session token hash must be exactly 64 characters (SHA-256)'];
        } elseif (!ctype_xdigit($inbox->sessionTokenHash)) {
            $errors['session_token_hash'] = ['Session token hash must be hexadecimal'];
        }

        // Validate email local part
        if (empty(trim($inbox->emailLocalPart))) {
            $errors['email_local_part'] = ['Email local part is required'];
        } elseif (strlen($inbox->emailLocalPart) > 64) {
            $errors['email_local_part'] = ['Email local part must be 64 characters or less'];
        }

        // Validate email domain
        if (empty(trim($inbox->emailDomain))) {
            $errors['email_domain'] = ['Email domain is required'];
        } elseif (strlen($inbox->emailDomain) > 255) {
            $errors['email_domain'] = ['Email domain must be 255 characters or less'];
        }

        // Validate status
        if (!in_array($inbox->status, Inbox::getValidStatuses(), true)) {
            $errors['status'] = ['Invalid status value'];
        }

        // Validate TTL
        if ($inbox->ttlMinutes <= 0) {
            $errors['ttl_minutes'] = ['TTL must be a positive integer'];
        }

        // Check for duplicate email address (only on insert)
        if ($inbox->id === null) {
            if ($this->emailAddressExists($inbox->emailLocalPart, $inbox->emailDomain)) {
                $errors['email_address'] = ['This email address is already in use'];
            }

            // Check if address is in cooldown
            if ($this->cooldownRepository->isAddressInCooldown(
                $inbox->emailLocalPart,
                $inbox->emailDomain
            )) {
                $errors['email_address'] = ['This email address is in cooldown period'];
            }
        }

        // Check for duplicate session token hash (only on insert)
        if ($inbox->id === null) {
            $existing = $this->findBySessionTokenHash($inbox->sessionTokenHash);
            if ($existing !== null) {
                $errors['session_token_hash'] = ['This session already has an inbox'];
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
