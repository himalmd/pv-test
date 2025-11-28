<?php

declare(strict_types=1);

namespace Snaply\Repository;

use Snaply\Entity\Snapshot;
use Snaply\Exception\ValidationException;

/**
 * Repository for Snapshot entities.
 */
class SnapshotRepository extends AbstractRepository
{
    private PageRepository $pageRepository;

    public function __construct(
        \Snaply\Database\Connection $connection,
        PageRepository $pageRepository
    ) {
        parent::__construct($connection);
        $this->pageRepository = $pageRepository;
    }

    protected function getTableName(): string
    {
        return 'snapshots';
    }

    protected function getEntityClass(): string
    {
        return Snapshot::class;
    }

    /**
     * Find a snapshot by ID.
     */
    public function find(int $id): ?Snapshot
    {
        /** @var Snapshot|null */
        return parent::find($id);
    }

    /**
     * Find a snapshot by ID, including soft-deleted.
     */
    public function findWithDeleted(int $id): ?Snapshot
    {
        /** @var Snapshot|null */
        return parent::findWithDeleted($id);
    }

    /**
     * Find a snapshot by ID or throw.
     *
     * @throws \Snaply\Exception\EntityNotFoundException
     */
    public function findOrFail(int $id): Snapshot
    {
        /** @var Snapshot */
        return parent::findOrFail($id);
    }

    /**
     * Get all active snapshots.
     *
     * @return Snapshot[]
     */
    public function findAll(): array
    {
        /** @var Snapshot[] */
        return parent::findAll();
    }

    /**
     * Find snapshots by page ID (active only, with active parent chain).
     *
     * @return Snapshot[]
     */
    public function findByPageId(int $pageId): array
    {
        $sql = 'SELECT s.* FROM `snapshots` s
                INNER JOIN `pages` p ON s.page_id = p.id
                INNER JOIN `projects` pr ON p.project_id = pr.id
                WHERE s.page_id = ?
                  AND s.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND pr.deleted_at IS NULL
                ORDER BY s.version DESC';

        $rows = $this->connection->fetchAll($sql, [$pageId]);

        return array_map(fn($row) => Snapshot::fromRow($row), $rows);
    }

    /**
     * Find snapshots by page ID, including soft-deleted snapshots.
     *
     * @return Snapshot[]
     */
    public function findByPageIdWithDeleted(int $pageId): array
    {
        $sql = 'SELECT * FROM `snapshots` WHERE `page_id` = ? ORDER BY `version` DESC';
        $rows = $this->connection->fetchAll($sql, [$pageId]);

        return array_map(fn($row) => Snapshot::fromRow($row), $rows);
    }

    /**
     * Find the latest snapshot for a page (active only).
     */
    public function findLatestByPageId(int $pageId): ?Snapshot
    {
        $sql = 'SELECT s.* FROM `snapshots` s
                INNER JOIN `pages` p ON s.page_id = p.id
                INNER JOIN `projects` pr ON p.project_id = pr.id
                WHERE s.page_id = ?
                  AND s.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND pr.deleted_at IS NULL
                ORDER BY s.version DESC
                LIMIT 1';

        $row = $this->connection->fetchOne($sql, [$pageId]);

        return $row !== null ? Snapshot::fromRow($row) : null;
    }

    /**
     * Find a snapshot by media reference.
     */
    public function findByMediaReference(string $mediaReference): ?Snapshot
    {
        $sql = 'SELECT * FROM `snapshots`
                WHERE `media_reference` = ? AND `deleted_at` IS NULL
                LIMIT 1';

        $row = $this->connection->fetchOne($sql, [$mediaReference]);

        return $row !== null ? Snapshot::fromRow($row) : null;
    }

    /**
     * Get the next version number for a page.
     */
    public function getNextVersion(int $pageId): int
    {
        $sql = 'SELECT MAX(`version`) FROM `snapshots` WHERE `page_id` = ?';
        $maxVersion = $this->connection->fetchColumn($sql, [$pageId]);

        return $maxVersion !== null ? ((int) $maxVersion) + 1 : 1;
    }

    /**
     * Count snapshots for a page (active only).
     */
    public function countByPageId(int $pageId): int
    {
        $sql = 'SELECT COUNT(*) FROM `snapshots` s
                INNER JOIN `pages` p ON s.page_id = p.id
                INNER JOIN `projects` pr ON p.project_id = pr.id
                WHERE s.page_id = ?
                  AND s.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND pr.deleted_at IS NULL';

        return (int) $this->connection->fetchColumn($sql, [$pageId]);
    }

    /**
     * Save a snapshot (insert or update).
     *
     * @return Snapshot The saved snapshot with ID populated
     *
     * @throws ValidationException If validation fails
     */
    public function save(Snapshot $snapshot): Snapshot
    {
        $this->validate($snapshot);

        // Auto-assign version on insert
        if ($snapshot->id === null && $snapshot->version <= 0) {
            $snapshot->version = $this->getNextVersion($snapshot->pageId);
        }

        $data = [
            'page_id' => $snapshot->pageId,
            'version' => $snapshot->version,
            'width_px' => $snapshot->widthPx,
            'height_px' => $snapshot->heightPx,
            'media_reference' => $snapshot->mediaReference,
        ];

        if ($snapshot->id === null) {
            $snapshot->id = $this->insert($data);
        } else {
            $this->update($snapshot->id, $data);
        }

        // Reload to get timestamps
        return $this->findWithDeleted($snapshot->id) ?? $snapshot;
    }

    /**
     * Update snapshot dimensions.
     */
    public function updateDimensions(int $id, int $width, int $height): bool
    {
        $sql = 'UPDATE `snapshots` SET `width_px` = ?, `height_px` = ?
                WHERE `id` = ? AND `deleted_at` IS NULL';

        return $this->connection->execute($sql, [$width, $height, $id]) > 0;
    }

    /**
     * Update snapshot media reference.
     */
    public function updateMediaReference(int $id, string $mediaReference): bool
    {
        $sql = 'UPDATE `snapshots` SET `media_reference` = ?
                WHERE `id` = ? AND `deleted_at` IS NULL';

        return $this->connection->execute($sql, [$mediaReference, $id]) > 0;
    }

    /**
     * Validate a snapshot entity.
     *
     * @throws ValidationException If validation fails
     */
    private function validate(Snapshot $snapshot): void
    {
        $errors = [];

        // Validate page exists and is not soft-deleted
        if ($snapshot->pageId <= 0) {
            $errors['page_id'] = ['Page ID is required'];
        } elseif (!$this->pageRepository->exists($snapshot->pageId)) {
            $errors['page_id'] = ['Page does not exist or has been deleted'];
        }

        if ($snapshot->widthPx !== null && $snapshot->widthPx <= 0) {
            $errors['width_px'] = ['Width must be a positive integer'];
        }

        if ($snapshot->heightPx !== null && $snapshot->heightPx <= 0) {
            $errors['height_px'] = ['Height must be a positive integer'];
        }

        if ($snapshot->mediaReference !== null && strlen($snapshot->mediaReference) > 512) {
            $errors['media_reference'] = ['Media reference must be 512 characters or less'];
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
