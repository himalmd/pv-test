<?php

declare(strict_types=1);

namespace Snaply\Entity;

use DateTimeImmutable;

/**
 * Page entity representing a row in the pages table.
 */
class Page
{
    public ?int $id = null;
    public int $projectId = 0;
    public string $url = '';
    public ?string $title = null;
    public ?string $description = null;
    public ?DateTimeImmutable $createdAt = null;
    public ?DateTimeImmutable $updatedAt = null;
    public ?DateTimeImmutable $deletedAt = null;

    /**
     * Create a new Page instance.
     */
    public function __construct(
        ?int $id = null,
        int $projectId = 0,
        string $url = '',
        ?string $title = null,
        ?string $description = null
    ) {
        $this->id = $id;
        $this->projectId = $projectId;
        $this->url = $url;
        $this->title = $title;
        $this->description = $description;
    }

    /**
     * Create a Page from a database row.
     *
     * @param array<string, mixed> $row Database row
     */
    public static function fromRow(array $row): self
    {
        $page = new self();
        $page->id = isset($row['id']) ? (int) $row['id'] : null;
        $page->projectId = isset($row['project_id']) ? (int) $row['project_id'] : 0;
        $page->url = $row['url'] ?? '';
        $page->title = $row['title'] ?? null;
        $page->description = $row['description'] ?? null;
        $page->createdAt = isset($row['created_at'])
            ? new DateTimeImmutable($row['created_at'])
            : null;
        $page->updatedAt = isset($row['updated_at'])
            ? new DateTimeImmutable($row['updated_at'])
            : null;
        $page->deletedAt = isset($row['deleted_at'])
            ? new DateTimeImmutable($row['deleted_at'])
            : null;

        return $page;
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
            'project_id' => $this->projectId,
            'url' => $this->url,
            'title' => $this->title,
            'description' => $this->description,
        ];
    }

    /**
     * Check if this page is soft-deleted.
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Check if this page is active (not deleted).
     */
    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }
}
