<?php

declare(strict_types=1);

namespace Snaply\Entity;

use DateTimeImmutable;

/**
 * Comment entity representing a row in the comments table.
 *
 * Comments do not support soft delete. They remain in the database
 * but are filtered out from normal UI queries when their parent
 * snapshot, page, or project is soft-deleted.
 */
class Comment
{
    public ?int $id = null;
    public int $snapshotId = 0;
    public ?int $parentId = null;
    public string $authorName = '';
    public ?string $authorEmail = null;
    public string $content = '';
    public ?string $xNorm = null;
    public ?string $yNorm = null;
    public ?DateTimeImmutable $createdAt = null;
    public ?DateTimeImmutable $updatedAt = null;

    /**
     * Create a new Comment instance.
     */
    public function __construct(
        ?int $id = null,
        int $snapshotId = 0,
        ?int $parentId = null,
        string $authorName = '',
        ?string $authorEmail = null,
        string $content = '',
        ?string $xNorm = null,
        ?string $yNorm = null
    ) {
        $this->id = $id;
        $this->snapshotId = $snapshotId;
        $this->parentId = $parentId;
        $this->authorName = $authorName;
        $this->authorEmail = $authorEmail;
        $this->content = $content;
        $this->xNorm = $xNorm;
        $this->yNorm = $yNorm;
    }

    /**
     * Create a Comment from a database row.
     *
     * @param array<string, mixed> $row Database row
     */
    public static function fromRow(array $row): self
    {
        $comment = new self();
        $comment->id = isset($row['id']) ? (int) $row['id'] : null;
        $comment->snapshotId = isset($row['snapshot_id']) ? (int) $row['snapshot_id'] : 0;
        $comment->parentId = isset($row['parent_id']) ? (int) $row['parent_id'] : null;
        $comment->authorName = $row['author_name'] ?? '';
        $comment->authorEmail = $row['author_email'] ?? null;
        $comment->content = $row['content'] ?? '';
        $comment->xNorm = $row['x_norm'] ?? null;
        $comment->yNorm = $row['y_norm'] ?? null;
        $comment->createdAt = isset($row['created_at'])
            ? new DateTimeImmutable($row['created_at'])
            : null;
        $comment->updatedAt = isset($row['updated_at'])
            ? new DateTimeImmutable($row['updated_at'])
            : null;

        return $comment;
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
            'snapshot_id' => $this->snapshotId,
            'parent_id' => $this->parentId,
            'author_name' => $this->authorName,
            'author_email' => $this->authorEmail,
            'content' => $this->content,
            'x_norm' => $this->xNorm,
            'y_norm' => $this->yNorm,
        ];
    }

    /**
     * Check if this comment has coordinates.
     */
    public function hasCoordinates(): bool
    {
        return $this->xNorm !== null && $this->yNorm !== null;
    }

    /**
     * Check if this comment is a reply to another comment.
     */
    public function isReply(): bool
    {
        return $this->parentId !== null;
    }

    /**
     * Get the X coordinate as a float.
     */
    public function getXNormFloat(): ?float
    {
        return $this->xNorm !== null ? (float) $this->xNorm : null;
    }

    /**
     * Get the Y coordinate as a float.
     */
    public function getYNormFloat(): ?float
    {
        return $this->yNorm !== null ? (float) $this->yNorm : null;
    }

    /**
     * Set coordinates from float values.
     *
     * @param float $x X coordinate (0.0 to 1.0)
     * @param float $y Y coordinate (0.0 to 1.0)
     */
    public function setCoordinates(float $x, float $y): void
    {
        $this->xNorm = number_format($x, 9, '.', '');
        $this->yNorm = number_format($y, 9, '.', '');
    }
}
