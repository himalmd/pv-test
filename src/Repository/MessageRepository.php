<?php

declare(strict_types=1);

namespace Snaply\Repository;

use DateTimeImmutable;
use Snaply\Entity\Message;
use Snaply\Exception\ValidationException;

/**
 * Repository for Message entities.
 *
 * Manages email messages received by temporary inboxes.
 */
class MessageRepository extends AbstractRepository
{
    private InboxRepository $inboxRepository;

    public function __construct(
        \Snaply\Database\Connection $connection,
        InboxRepository $inboxRepository
    ) {
        parent::__construct($connection);
        $this->inboxRepository = $inboxRepository;
    }

    protected function getTableName(): string
    {
        return 'messages';
    }

    protected function getEntityClass(): string
    {
        return Message::class;
    }

    /**
     * Find a message by ID.
     */
    public function find(int $id): ?Message
    {
        /** @var Message|null */
        return parent::find($id);
    }

    /**
     * Find a message by ID, including soft-deleted.
     */
    public function findWithDeleted(int $id): ?Message
    {
        /** @var Message|null */
        return parent::findWithDeleted($id);
    }

    /**
     * Find a message by ID or throw.
     *
     * @throws \Snaply\Exception\EntityNotFoundException
     */
    public function findOrFail(int $id): Message
    {
        /** @var Message */
        return parent::findOrFail($id);
    }

    /**
     * Get all active messages.
     *
     * @return Message[]
     */
    public function findAll(): array
    {
        /** @var Message[] */
        return parent::findAll();
    }

    /**
     * Find messages by inbox ID with pagination.
     *
     * @param int $inboxId Inbox ID
     * @param int $limit Maximum number of messages to return
     * @param int $offset Number of messages to skip
     *
     * @return Message[]
     */
    public function findByInboxId(int $inboxId, int $limit = 50, int $offset = 0): array
    {
        $sql = 'SELECT * FROM `messages`
                WHERE `inbox_id` = ?
                  AND `deleted_at` IS NULL
                ORDER BY `received_at` DESC
                LIMIT ? OFFSET ?';

        $rows = $this->connection->fetchAll($sql, [$inboxId, $limit, $offset]);

        return array_map(fn($row) => Message::fromRow($row), $rows);
    }

    /**
     * Find recent messages by inbox ID.
     *
     * @param int $inboxId Inbox ID
     * @param int $limit Maximum number of messages to return
     *
     * @return Message[]
     */
    public function findRecentByInboxId(int $inboxId, int $limit = 10): array
    {
        $sql = 'SELECT * FROM `messages`
                WHERE `inbox_id` = ?
                  AND `deleted_at` IS NULL
                ORDER BY `received_at` DESC
                LIMIT ?';

        $rows = $this->connection->fetchAll($sql, [$inboxId, $limit]);

        return array_map(fn($row) => Message::fromRow($row), $rows);
    }

    /**
     * Count messages for an inbox.
     */
    public function countByInboxId(int $inboxId): int
    {
        $sql = 'SELECT COUNT(*) FROM `messages`
                WHERE `inbox_id` = ?
                  AND `deleted_at` IS NULL';

        return (int) $this->connection->fetchColumn($sql, [$inboxId]);
    }

    /**
     * Count active (non-deleted) messages for an inbox.
     */
    public function countActiveByInboxId(int $inboxId): int
    {
        return $this->countByInboxId($inboxId);
    }

    /**
     * Find message by Message-ID header.
     */
    public function findByMessageId(string $messageId): ?Message
    {
        $sql = 'SELECT * FROM `messages`
                WHERE `message_id` = ?
                  AND `deleted_at` IS NULL
                LIMIT 1';

        $row = $this->connection->fetchOne($sql, [$messageId]);

        return $row !== null ? Message::fromRow($row) : null;
    }

    /**
     * Check if a Message-ID already exists.
     */
    public function messageIdExists(string $messageId): bool
    {
        $sql = 'SELECT 1 FROM `messages`
                WHERE `message_id` = ?
                  AND `deleted_at` IS NULL
                LIMIT 1';

        return $this->connection->fetchColumn($sql, [$messageId]) !== null;
    }

    /**
     * Soft delete all messages for an inbox.
     *
     * @return int Number of messages deleted
     */
    public function deleteByInboxId(int $inboxId): int
    {
        $sql = 'UPDATE `messages`
                SET `deleted_at` = NOW(), `updated_at` = NOW()
                WHERE `inbox_id` = ? AND `deleted_at` IS NULL';

        return $this->connection->execute($sql, [$inboxId]);
    }

    /**
     * Hard delete all messages for an inbox.
     *
     * @return int Number of messages deleted
     */
    public function hardDeleteByInboxId(int $inboxId): int
    {
        $sql = 'DELETE FROM `messages` WHERE `inbox_id` = ?';

        return $this->connection->execute($sql, [$inboxId]);
    }

    /**
     * Hard delete messages older than a specific date.
     *
     * @param DateTimeImmutable $before Delete messages received before this date
     * @param int $limit Maximum number of messages to delete
     *
     * @return int Number of messages deleted
     */
    public function hardDeleteOlderThan(DateTimeImmutable $before, int $limit = 100): int
    {
        $sql = 'DELETE FROM `messages`
                WHERE `received_at` < ?
                LIMIT ?';

        return $this->connection->execute($sql, [
            $before->format('Y-m-d H:i:s'),
            $limit,
        ]);
    }

    /**
     * Find messages by sender address.
     *
     * @param string $fromAddress Sender email address
     * @param int $limit Maximum number of messages to return
     *
     * @return Message[]
     */
    public function findBySender(string $fromAddress, int $limit = 50): array
    {
        $sql = 'SELECT * FROM `messages`
                WHERE `from_address` = ?
                  AND `deleted_at` IS NULL
                ORDER BY `received_at` DESC
                LIMIT ?';

        $rows = $this->connection->fetchAll($sql, [$fromAddress, $limit]);

        return array_map(fn($row) => Message::fromRow($row), $rows);
    }

    /**
     * Search messages by subject pattern.
     *
     * @param int $inboxId Inbox ID
     * @param string $subjectPattern Subject search pattern (LIKE)
     * @param int $limit Maximum number of messages to return
     *
     * @return Message[]
     */
    public function searchBySubject(int $inboxId, string $subjectPattern, int $limit = 50): array
    {
        $sql = 'SELECT * FROM `messages`
                WHERE `inbox_id` = ?
                  AND `subject` LIKE ?
                  AND `deleted_at` IS NULL
                ORDER BY `received_at` DESC
                LIMIT ?';

        $rows = $this->connection->fetchAll($sql, [
            $inboxId,
            '%' . $subjectPattern . '%',
            $limit,
        ]);

        return array_map(fn($row) => Message::fromRow($row), $rows);
    }

    /**
     * Save a message (insert or update).
     *
     * @return Message The saved message with ID populated
     *
     * @throws ValidationException If validation fails
     */
    public function save(Message $message): Message
    {
        $this->validate($message);

        $data = [
            'inbox_id' => $message->inboxId,
            'message_id' => $message->messageId,
            'from_address' => $message->fromAddress,
            'from_name' => $message->fromName,
            'subject' => $message->subject,
            'body_text' => $message->bodyText,
            'body_html' => $message->bodyHtml,
            'received_at' => $message->receivedAt?->format('Y-m-d H:i:s'),
        ];

        if ($message->id === null) {
            // Set received_at to now if not specified
            if ($data['received_at'] === null) {
                $data['received_at'] = (new DateTimeImmutable())->format('Y-m-d H:i:s');
            }

            $message->id = $this->insert($data);
        } else {
            $this->update($message->id, $data);
        }

        // Reload to get timestamps
        return $this->findWithDeleted($message->id) ?? $message;
    }

    /**
     * Validate a message entity.
     *
     * @throws ValidationException If validation fails
     */
    private function validate(Message $message): void
    {
        $errors = [];

        // Validate inbox exists and is not soft-deleted
        if ($message->inboxId <= 0) {
            $errors['inbox_id'] = ['Inbox ID is required'];
        } elseif (!$this->inboxRepository->exists($message->inboxId)) {
            $errors['inbox_id'] = ['Inbox does not exist or has been deleted'];
        }

        // Validate from address
        if (empty(trim($message->fromAddress))) {
            $errors['from_address'] = ['From address is required'];
        } elseif (strlen($message->fromAddress) > 255) {
            $errors['from_address'] = ['From address must be 255 characters or less'];
        } elseif (!filter_var($message->fromAddress, FILTER_VALIDATE_EMAIL)) {
            $errors['from_address'] = ['From address must be a valid email address'];
        }

        // Validate from name
        if ($message->fromName !== null && strlen($message->fromName) > 255) {
            $errors['from_name'] = ['From name must be 255 characters or less'];
        }

        // Validate subject
        if ($message->subject !== null && strlen($message->subject) > 998) {
            $errors['subject'] = ['Subject must be 998 characters or less'];
        }

        // Validate message ID
        if ($message->messageId !== null && strlen($message->messageId) > 255) {
            $errors['message_id'] = ['Message ID must be 255 characters or less'];
        }

        // At least one body should be present
        if (!$message->hasTextBody() && !$message->hasHtmlBody()) {
            $errors['body'] = ['Message must have at least one body (text or HTML)'];
        }

        // Check for duplicate message ID (only on insert if message ID is provided)
        if ($message->id === null && $message->messageId !== null) {
            if ($this->messageIdExists($message->messageId)) {
                $errors['message_id'] = ['A message with this Message-ID already exists'];
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
