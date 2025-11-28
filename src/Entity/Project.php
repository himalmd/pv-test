<?php

declare(strict_types=1);

namespace Snaply\Entity;

use DateTimeImmutable;

/**
 * Project entity representing a row in the projects table.
 */
class Project
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_DRAFT = 'draft';

    public ?int $id = null;
    public string $name = '';
    public ?string $description = null;
    public string $status = self::STATUS_ACTIVE;
    public ?DateTimeImmutable $createdAt = null;
    public ?DateTimeImmutable $updatedAt = null;
    public ?DateTimeImmutable $deletedAt = null;

    /**
     * Create a new Project instance.
     */
    public function __construct(
        ?int $id = null,
        string $name = '',
        ?string $description = null,
        string $status = self::STATUS_ACTIVE
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->status = $status;
    }

    /**
     * Create a Project from a database row.
     *
     * @param array<string, mixed> $row Database row
     */
    public static function fromRow(array $row): self
    {
        $project = new self();
        $project->id = isset($row['id']) ? (int) $row['id'] : null;
        $project->name = $row['name'] ?? '';
        $project->description = $row['description'] ?? null;
        $project->status = $row['status'] ?? self::STATUS_ACTIVE;
        $project->createdAt = isset($row['created_at'])
            ? new DateTimeImmutable($row['created_at'])
            : null;
        $project->updatedAt = isset($row['updated_at'])
            ? new DateTimeImmutable($row['updated_at'])
            : null;
        $project->deletedAt = isset($row['deleted_at'])
            ? new DateTimeImmutable($row['deleted_at'])
            : null;

        return $project;
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
            'name' => $this->name,
            'description' => $this->description,
            'status' => $this->status,
        ];
    }

    /**
     * Check if this project is soft-deleted.
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Check if this project is active (not deleted).
     */
    public function isActive(): bool
    {
        return $this->deletedAt === null;
    }

    /**
     * Get valid status values.
     *
     * @return string[]
     */
    public static function getValidStatuses(): array
    {
        return [self::STATUS_ACTIVE, self::STATUS_ARCHIVED, self::STATUS_DRAFT];
    }
}
