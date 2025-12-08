<?php

declare(strict_types=1);

namespace Snaply\Entity;

use DateTimeImmutable;

/**
 * Message entity representing an email received by an inbox.
 */
class Message
{
    public ?int $id = null;
    public int $inboxId = 0;
    public ?string $messageId = null;
    public string $fromAddress = '';
    public ?string $fromName = null;
    public ?string $subject = null;
    public ?string $bodyText = null;
    public ?string $bodyHtml = null;
    public ?DateTimeImmutable $receivedAt = null;
    public ?DateTimeImmutable $createdAt = null;
    public ?DateTimeImmutable $updatedAt = null;
    public ?DateTimeImmutable $deletedAt = null;

    /**
     * Create a new Message instance.
     */
    public function __construct(
        ?int $id = null,
        int $inboxId = 0,
        string $fromAddress = '',
        ?string $subject = null
    ) {
        $this->id = $id;
        $this->inboxId = $inboxId;
        $this->fromAddress = $fromAddress;
        $this->subject = $subject;
    }

    /**
     * Create a Message from a database row.
     *
     * @param array<string, mixed> $row Database row
     */
    public static function fromRow(array $row): self
    {
        $message = new self();
        $message->id = isset($row['id']) ? (int) $row['id'] : null;
        $message->inboxId = isset($row['inbox_id']) ? (int) $row['inbox_id'] : 0;
        $message->messageId = $row['message_id'] ?? null;
        $message->fromAddress = $row['from_address'] ?? '';
        $message->fromName = $row['from_name'] ?? null;
        $message->subject = $row['subject'] ?? null;
        $message->bodyText = $row['body_text'] ?? null;
        $message->bodyHtml = $row['body_html'] ?? null;
        $message->receivedAt = isset($row['received_at'])
            ? new DateTimeImmutable($row['received_at'])
            : null;
        $message->createdAt = isset($row['created_at'])
            ? new DateTimeImmutable($row['created_at'])
            : null;
        $message->updatedAt = isset($row['updated_at'])
            ? new DateTimeImmutable($row['updated_at'])
            : null;
        $message->deletedAt = isset($row['deleted_at'])
            ? new DateTimeImmutable($row['deleted_at'])
            : null;

        return $message;
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
            'inbox_id' => $this->inboxId,
            'message_id' => $this->messageId,
            'from_address' => $this->fromAddress,
            'from_name' => $this->fromName,
            'subject' => $this->subject,
            'body_text' => $this->bodyText,
            'body_html' => $this->bodyHtml,
        ];
    }

    /**
     * Check if this message is soft-deleted.
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Check if this message is active (not soft-deleted).
     */
    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    /**
     * Check if message has HTML body.
     */
    public function hasHtmlBody(): bool
    {
        return $this->bodyHtml !== null && $this->bodyHtml !== '';
    }

    /**
     * Check if message has text body.
     */
    public function hasTextBody(): bool
    {
        return $this->bodyText !== null && $this->bodyText !== '';
    }
}
