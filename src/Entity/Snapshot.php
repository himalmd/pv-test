<?php

declare(strict_types=1);

namespace Snaply\Entity;

use DateTimeImmutable;

/**
 * Snapshot entity representing a row in the snapshots table.
 */
class Snapshot
{
    public ?int $id = null;
    public int $pageId = 0;
    public int $version = 1;
    public ?int $widthPx = null;
    public ?int $heightPx = null;
    public ?string $mediaReference = null;
    public ?DateTimeImmutable $createdAt = null;
    public ?DateTimeImmutable $updatedAt = null;
    public ?DateTimeImmutable $deletedAt = null;

    /**
     * Create a new Snapshot instance.
     */
    public function __construct(
        ?int $id = null,
        int $pageId = 0,
        int $version = 1,
        ?int $widthPx = null,
        ?int $heightPx = null,
        ?string $mediaReference = null
    ) {
        $this->id = $id;
        $this->pageId = $pageId;
        $this->version = $version;
        $this->widthPx = $widthPx;
        $this->heightPx = $heightPx;
        $this->mediaReference = $mediaReference;
    }

    /**
     * Create a Snapshot from a database row.
     *
     * @param array<string, mixed> $row Database row
     */
    public static function fromRow(array $row): self
    {
        $snapshot = new self();
        $snapshot->id = isset($row['id']) ? (int) $row['id'] : null;
        $snapshot->pageId = isset($row['page_id']) ? (int) $row['page_id'] : 0;
        $snapshot->version = isset($row['version']) ? (int) $row['version'] : 1;
        $snapshot->widthPx = isset($row['width_px']) ? (int) $row['width_px'] : null;
        $snapshot->heightPx = isset($row['height_px']) ? (int) $row['height_px'] : null;
        $snapshot->mediaReference = $row['media_reference'] ?? null;
        $snapshot->createdAt = isset($row['created_at'])
            ? new DateTimeImmutable($row['created_at'])
            : null;
        $snapshot->updatedAt = isset($row['updated_at'])
            ? new DateTimeImmutable($row['updated_at'])
            : null;
        $snapshot->deletedAt = isset($row['deleted_at'])
            ? new DateTimeImmutable($row['deleted_at'])
            : null;

        return $snapshot;
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
            'page_id' => $this->pageId,
            'version' => $this->version,
            'width_px' => $this->widthPx,
            'height_px' => $this->heightPx,
            'media_reference' => $this->mediaReference,
        ];
    }

    /**
     * Check if this snapshot is soft-deleted.
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Check if this snapshot is active (not deleted).
     */
    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    /**
     * Check if dimensions are set.
     */
    public function hasDimensions(): bool
    {
        return $this->widthPx !== null && $this->heightPx !== null;
    }

    /**
     * Check if media reference is set.
     */
    public function hasMedia(): bool
    {
        return $this->mediaReference !== null && $this->mediaReference !== '';
    }
}
