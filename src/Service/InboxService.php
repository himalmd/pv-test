<?php

declare(strict_types=1);

namespace Snaply\Service;

use DateTimeImmutable;
use Snaply\Database\Connection;
use Snaply\Entity\Inbox;
use Snaply\Exception\EntityNotFoundException;
use Snaply\Exception\ValidationException;
use Snaply\Repository\InboxAddressCooldownRepository;
use Snaply\Repository\InboxRepository;
use Snaply\Repository\MessageRepository;

/**
 * Service layer for temporary inbox lifecycle operations.
 *
 * Orchestrates inbox creation, rotation, deletion, and address generation
 * with session linkage and privacy-first design (no PII storage).
 */
class InboxService
{
    private Connection $connection;
    private InboxRepository $inboxRepository;
    private MessageRepository $messageRepository;
    private InboxAddressCooldownRepository $cooldownRepository;

    private string $defaultDomain;
    private int $defaultTtlMinutes;
    private int $addressCooldownHours;
    private int $addressLength;
    private int $maxRetryAttempts;

    /**
     * @param array<string, mixed> $config Configuration options:
     *   - domain: Default email domain (default: 'tempinbox.pro')
     *   - ttl_minutes: Default inbox TTL (default: 60)
     *   - cooldown_hours: Address cooldown period (default: 24)
     *   - address_length: Generated address length (default: 10)
     *   - max_retry_attempts: Max retries for address generation (default: 10)
     */
    public function __construct(
        Connection $connection,
        InboxRepository $inboxRepository,
        MessageRepository $messageRepository,
        InboxAddressCooldownRepository $cooldownRepository,
        array $config = []
    ) {
        $this->connection = $connection;
        $this->inboxRepository = $inboxRepository;
        $this->messageRepository = $messageRepository;
        $this->cooldownRepository = $cooldownRepository;

        $this->defaultDomain = $config['domain'] ?? 'tempinbox.pro';
        $this->defaultTtlMinutes = $config['ttl_minutes'] ?? 60;
        $this->addressCooldownHours = $config['cooldown_hours'] ?? 24;
        $this->addressLength = $config['address_length'] ?? 10;
        $this->maxRetryAttempts = $config['max_retry_attempts'] ?? 10;
    }

    /**
     * Get or create active inbox for session.
     *
     * Returns existing active inbox if found, otherwise atomically creates new one.
     * Enforces invariant: each session has at most one active inbox.
     *
     * @param string $sessionToken Opaque session identifier
     *
     * @return Inbox The active inbox for this session
     *
     * @throws ValidationException If inbox creation fails
     */
    public function getOrCreateActiveInboxForSession(string $sessionToken): Inbox
    {
        $sessionHash = $this->hashSessionToken($sessionToken);

        // Try to find existing active inbox
        $existingInbox = $this->inboxRepository->findActiveBySessionTokenHash($sessionHash);

        if ($existingInbox !== null) {
            // Touch last accessed timestamp to extend TTL
            $this->inboxRepository->updateLastAccessed($existingInbox->id);

            // Reload to get updated timestamp
            return $this->inboxRepository->findOrFail($existingInbox->id);
        }

        // No active inbox found - create new one atomically
        return $this->connection->transaction(function () use ($sessionHash) {
            // Generate unique address
            $address = $this->generateUniqueAddress($this->defaultDomain);

            // Create new inbox
            $inbox = new Inbox(
                id: null,
                sessionTokenHash: $sessionHash,
                emailLocalPart: $address['local_part'],
                emailDomain: $address['domain'],
                status: Inbox::STATUS_ACTIVE,
                ttlMinutes: $this->defaultTtlMinutes
            );

            $inbox = $this->inboxRepository->save($inbox);

            // Record address in cooldown
            $this->cooldownRepository->recordAddressUsage(
                $address['local_part'],
                $address['domain'],
                $this->addressCooldownHours
            );

            return $inbox;
        });
    }

    /**
     * Rotate inbox for session (abandon current, create new).
     *
     * Marks current inbox as abandoned and creates fresh inbox with new address.
     *
     * @param string $sessionToken Opaque session identifier
     *
     * @return Inbox The new active inbox
     *
     * @throws EntityNotFoundException If no inbox found for session
     * @throws ValidationException If inbox creation fails
     */
    public function rotateInboxForSession(string $sessionToken): Inbox
    {
        $sessionHash = $this->hashSessionToken($sessionToken);

        // Find current inbox (any status)
        $currentInbox = $this->inboxRepository->findBySessionTokenHash($sessionHash);

        if ($currentInbox === null) {
            throw new EntityNotFoundException('Inbox', $sessionHash);
        }

        return $this->connection->transaction(function () use ($currentInbox, $sessionHash) {
            // Mark current inbox as abandoned (if not already deleted)
            if ($currentInbox->status !== Inbox::STATUS_DELETED) {
                $this->inboxRepository->markAsAbandoned($currentInbox->id);
            }

            // Generate new unique address
            $address = $this->generateUniqueAddress($this->defaultDomain);

            // Create new inbox with same session
            $newInbox = new Inbox(
                id: null,
                sessionTokenHash: $sessionHash,
                emailLocalPart: $address['local_part'],
                emailDomain: $address['domain'],
                status: Inbox::STATUS_ACTIVE,
                ttlMinutes: $this->defaultTtlMinutes
            );

            $newInbox = $this->inboxRepository->save($newInbox);

            // Record new address in cooldown
            $this->cooldownRepository->recordAddressUsage(
                $address['local_part'],
                $address['domain'],
                $this->addressCooldownHours
            );

            return $newInbox;
        });
    }

    /**
     * Delete inbox immediately for session (mark deleted, create new empty).
     *
     * Marks current inbox as deleted, soft-deletes all messages, and creates
     * new empty inbox.
     *
     * @param string $sessionToken Opaque session identifier
     *
     * @return Inbox The new empty inbox
     *
     * @throws EntityNotFoundException If no inbox found for session
     * @throws ValidationException If inbox creation fails
     */
    public function deleteInboxNowForSession(string $sessionToken): Inbox
    {
        $sessionHash = $this->hashSessionToken($sessionToken);

        // Find current inbox (any status)
        $currentInbox = $this->inboxRepository->findBySessionTokenHash($sessionHash);

        if ($currentInbox === null) {
            throw new EntityNotFoundException('Inbox', $sessionHash);
        }

        return $this->connection->transaction(function () use ($currentInbox, $sessionHash) {
            // Soft-delete all messages for current inbox
            $this->messageRepository->deleteByInboxId($currentInbox->id);

            // Mark inbox as deleted (soft delete)
            $this->inboxRepository->delete($currentInbox->id);

            // Generate new unique address
            $address = $this->generateUniqueAddress($this->defaultDomain);

            // Create new empty inbox
            $newInbox = new Inbox(
                id: null,
                sessionTokenHash: $sessionHash,
                emailLocalPart: $address['local_part'],
                emailDomain: $address['domain'],
                status: Inbox::STATUS_ACTIVE,
                ttlMinutes: $this->defaultTtlMinutes
            );

            $newInbox = $this->inboxRepository->save($newInbox);

            // Record new address in cooldown
            $this->cooldownRepository->recordAddressUsage(
                $address['local_part'],
                $address['domain'],
                $this->addressCooldownHours
            );

            return $newInbox;
        });
    }

    /**
     * Generate unique email address.
     *
     * Produces random local-part that is not in use and not in cooldown period.
     *
     * @param string $domain Email domain
     *
     * @return array{local_part: string, domain: string} Generated address components
     *
     * @throws \RuntimeException If unable to generate unique address after max retries
     */
    public function generateUniqueAddress(string $domain): array
    {
        for ($attempt = 0; $attempt < $this->maxRetryAttempts; $attempt++) {
            $localPart = $this->generateRandomLocalPart($this->addressLength);

            if ($this->isAddressAvailable($localPart, $domain)) {
                return [
                    'local_part' => $localPart,
                    'domain' => $domain,
                ];
            }
        }

        throw new \RuntimeException(
            "Unable to generate unique address after {$this->maxRetryAttempts} attempts"
        );
    }

    /**
     * Touch inbox access timestamp to extend TTL.
     *
     * @param int $inboxId Inbox ID
     *
     * @return bool True if updated
     */
    public function touchInboxAccess(int $inboxId): bool
    {
        return $this->inboxRepository->updateLastAccessed($inboxId);
    }

    /**
     * Mark inbox as expired.
     *
     * Transitions inbox to expired status with timestamp.
     *
     * @param int $inboxId Inbox ID
     *
     * @return bool True if marked as expired
     */
    public function markInboxAsExpired(int $inboxId): bool
    {
        return $this->inboxRepository->markAsExpired($inboxId);
    }

    /**
     * Get inbox for session (any status).
     *
     * @param string $sessionToken Opaque session identifier
     *
     * @return Inbox|null The inbox or null if not found
     */
    public function getInboxForSession(string $sessionToken): ?Inbox
    {
        $sessionHash = $this->hashSessionToken($sessionToken);
        return $this->inboxRepository->findBySessionTokenHash($sessionHash);
    }

    /**
     * Get active inbox for session.
     *
     * @param string $sessionToken Opaque session identifier
     *
     * @return Inbox|null The active inbox or null if not found
     */
    public function getActiveInboxForSession(string $sessionToken): ?Inbox
    {
        $sessionHash = $this->hashSessionToken($sessionToken);
        return $this->inboxRepository->findActiveBySessionTokenHash($sessionHash);
    }

    /**
     * Process expired inboxes.
     *
     * Finds inboxes that have exceeded their TTL and marks them as expired.
     *
     * @param int $limit Maximum number of inboxes to process
     *
     * @return int Number of inboxes marked as expired
     */
    public function processExpiredInboxes(int $limit = 100): int
    {
        $expiredInboxes = $this->inboxRepository->findExpired($limit);
        $count = 0;

        foreach ($expiredInboxes as $inbox) {
            if ($this->markInboxAsExpired($inbox->id)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Clean up old inboxes.
     *
     * Hard deletes expired/abandoned inboxes older than specified age.
     * Messages cascade delete via foreign key constraint.
     *
     * @param int $ageMinutes Minimum age in minutes since status change
     * @param int $limit Maximum number of inboxes to delete
     *
     * @return int Number of inboxes deleted
     */
    public function cleanupOldInboxes(int $ageMinutes = 60, int $limit = 100): int
    {
        return $this->inboxRepository->hardDeleteExpired($ageMinutes, $limit);
    }

    /**
     * Clean up expired address cooldowns.
     *
     * Removes cooldown records that have passed their cooldown period.
     *
     * @param int $limit Maximum number of records to delete
     *
     * @return int Number of cooldown records deleted
     */
    public function cleanupExpiredCooldowns(int $limit = 100): int
    {
        return $this->cooldownRepository->hardDeleteExpired($limit);
    }

    /**
     * Get inbox by ID.
     *
     * @param int $id Inbox ID
     *
     * @return Inbox|null The inbox or null if not found
     */
    public function getInbox(int $id): ?Inbox
    {
        return $this->inboxRepository->find($id);
    }

    /**
     * Get inbox by ID or throw exception.
     *
     * @param int $id Inbox ID
     *
     * @return Inbox The inbox
     *
     * @throws EntityNotFoundException If inbox not found
     */
    public function getInboxOrFail(int $id): Inbox
    {
        return $this->inboxRepository->findOrFail($id);
    }

    /**
     * Get inbox by email address.
     *
     * @param string $localPart Email local part
     * @param string $domain Email domain
     *
     * @return Inbox|null The inbox or null if not found
     */
    public function getInboxByAddress(string $localPart, string $domain): ?Inbox
    {
        return $this->inboxRepository->findByEmailAddress($localPart, $domain);
    }

    /**
     * Count inboxes by status.
     *
     * @param string $status Inbox status
     *
     * @return int Number of inboxes with given status
     */
    public function countInboxesByStatus(string $status): int
    {
        return $this->inboxRepository->countByStatus($status);
    }

    /**
     * Count expired inboxes.
     *
     * @return int Number of inboxes past their TTL
     */
    public function countExpiredInboxes(): int
    {
        return $this->inboxRepository->countExpired();
    }

    /**
     * Check if address is available for use.
     *
     * @param string $localPart Email local part
     * @param string $domain Email domain
     *
     * @return bool True if address can be used
     */
    private function isAddressAvailable(string $localPart, string $domain): bool
    {
        // Check if address exists in active inboxes
        if ($this->inboxRepository->emailAddressExists($localPart, $domain)) {
            return false;
        }

        // Check if address is in cooldown period
        if ($this->cooldownRepository->isAddressInCooldown($localPart, $domain)) {
            return false;
        }

        return true;
    }

    /**
     * Generate random email local part.
     *
     * Uses cryptographically secure random bytes for address generation.
     *
     * @param int $length Desired length of local part
     *
     * @return string Random alphanumeric string
     */
    private function generateRandomLocalPart(int $length): string
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $charactersLength = strlen($characters);
        $localPart = '';

        for ($i = 0; $i < $length; $i++) {
            $localPart .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $localPart;
    }

    /**
     * Hash session token for privacy.
     *
     * Uses SHA-256 to create opaque, non-reversible session identifier.
     * Ensures no PII is stored in database.
     *
     * @param string $sessionToken Original session token
     *
     * @return string 64-character hexadecimal hash
     */
    private function hashSessionToken(string $sessionToken): string
    {
        return hash('sha256', $sessionToken);
    }
}
