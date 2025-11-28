<?php

declare(strict_types=1);

namespace Snaply\Repository;

use Snaply\Entity\Comment;
use Snaply\Exception\ValidationException;

/**
 * Repository for Comment entities.
 *
 * Comments do not have soft delete. They remain in the database but are
 * filtered out from normal UI queries when their parent snapshot, page,
 * or project is soft-deleted.
 */
class CommentRepository extends AbstractRepository
{
    private SnapshotRepository $snapshotRepository;

    public function __construct(
        \Snaply\Database\Connection $connection,
        SnapshotRepository $snapshotRepository
    ) {
        parent::__construct($connection);
        $this->snapshotRepository = $snapshotRepository;
    }

    protected function getTableName(): string
    {
        return 'comments';
    }

    protected function getEntityClass(): string
    {
        return Comment::class;
    }

    /**
     * Comments do not support soft delete.
     */
    protected function supportsSoftDelete(): bool
    {
        return false;
    }

    /**
     * Find a comment by ID.
     */
    public function find(int $id): ?Comment
    {
        /** @var Comment|null */
        return parent::find($id);
    }

    /**
     * Find a comment by ID or throw.
     *
     * @throws \Snaply\Exception\EntityNotFoundException
     */
    public function findOrFail(int $id): Comment
    {
        /** @var Comment */
        return parent::findOrFail($id);
    }

    /**
     * Get all comments.
     *
     * @return Comment[]
     */
    public function findAll(): array
    {
        /** @var Comment[] */
        return parent::findAll();
    }

    /**
     * Find comments by snapshot ID for active entities only.
     *
     * This is the method for normal UI retrieval. It joins through the
     * entire entity hierarchy to ensure all parent entities are active.
     *
     * @return Comment[]
     */
    public function findBySnapshotIdForActiveEntities(int $snapshotId): array
    {
        $sql = 'SELECT c.* FROM `comments` c
                INNER JOIN `snapshots` s ON c.snapshot_id = s.id
                INNER JOIN `pages` p ON s.page_id = p.id
                INNER JOIN `projects` pr ON p.project_id = pr.id
                WHERE c.snapshot_id = ?
                  AND s.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND pr.deleted_at IS NULL
                ORDER BY c.created_at ASC';

        $rows = $this->connection->fetchAll($sql, [$snapshotId]);

        return array_map(fn($row) => Comment::fromRow($row), $rows);
    }

    /**
     * Find all comments by snapshot ID (admin/debug use).
     *
     * This method retrieves comments regardless of parent entity status.
     * Use this for admin panels or debugging.
     *
     * @return Comment[]
     */
    public function findBySnapshotId(int $snapshotId): array
    {
        $sql = 'SELECT * FROM `comments`
                WHERE `snapshot_id` = ?
                ORDER BY `created_at` ASC';

        $rows = $this->connection->fetchAll($sql, [$snapshotId]);

        return array_map(fn($row) => Comment::fromRow($row), $rows);
    }

    /**
     * Find top-level comments (not replies) for a snapshot.
     *
     * @return Comment[]
     */
    public function findTopLevelBySnapshotId(int $snapshotId): array
    {
        $sql = 'SELECT c.* FROM `comments` c
                INNER JOIN `snapshots` s ON c.snapshot_id = s.id
                INNER JOIN `pages` p ON s.page_id = p.id
                INNER JOIN `projects` pr ON p.project_id = pr.id
                WHERE c.snapshot_id = ?
                  AND c.parent_id IS NULL
                  AND s.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND pr.deleted_at IS NULL
                ORDER BY c.created_at ASC';

        $rows = $this->connection->fetchAll($sql, [$snapshotId]);

        return array_map(fn($row) => Comment::fromRow($row), $rows);
    }

    /**
     * Find replies to a comment.
     *
     * @return Comment[]
     */
    public function findReplies(int $parentId): array
    {
        $sql = 'SELECT c.* FROM `comments` c
                INNER JOIN `snapshots` s ON c.snapshot_id = s.id
                INNER JOIN `pages` p ON s.page_id = p.id
                INNER JOIN `projects` pr ON p.project_id = pr.id
                WHERE c.parent_id = ?
                  AND s.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND pr.deleted_at IS NULL
                ORDER BY c.created_at ASC';

        $rows = $this->connection->fetchAll($sql, [$parentId]);

        return array_map(fn($row) => Comment::fromRow($row), $rows);
    }

    /**
     * Find comments within a coordinate region.
     *
     * @param float $xMin Minimum X coordinate (0.0 to 1.0)
     * @param float $xMax Maximum X coordinate (0.0 to 1.0)
     * @param float $yMin Minimum Y coordinate (0.0 to 1.0)
     * @param float $yMax Maximum Y coordinate (0.0 to 1.0)
     *
     * @return Comment[]
     */
    public function findInRegion(
        int $snapshotId,
        float $xMin,
        float $xMax,
        float $yMin,
        float $yMax
    ): array {
        $sql = 'SELECT c.* FROM `comments` c
                INNER JOIN `snapshots` s ON c.snapshot_id = s.id
                INNER JOIN `pages` p ON s.page_id = p.id
                INNER JOIN `projects` pr ON p.project_id = pr.id
                WHERE c.snapshot_id = ?
                  AND c.x_norm >= ? AND c.x_norm <= ?
                  AND c.y_norm >= ? AND c.y_norm <= ?
                  AND s.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND pr.deleted_at IS NULL
                ORDER BY c.created_at ASC';

        $rows = $this->connection->fetchAll($sql, [
            $snapshotId,
            $xMin, $xMax,
            $yMin, $yMax,
        ]);

        return array_map(fn($row) => Comment::fromRow($row), $rows);
    }

    /**
     * Count comments for a snapshot (active entities only).
     */
    public function countBySnapshotIdForActiveEntities(int $snapshotId): int
    {
        $sql = 'SELECT COUNT(*) FROM `comments` c
                INNER JOIN `snapshots` s ON c.snapshot_id = s.id
                INNER JOIN `pages` p ON s.page_id = p.id
                INNER JOIN `projects` pr ON p.project_id = pr.id
                WHERE c.snapshot_id = ?
                  AND s.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND pr.deleted_at IS NULL';

        return (int) $this->connection->fetchColumn($sql, [$snapshotId]);
    }

    /**
     * Count all comments for a snapshot (admin/debug use).
     */
    public function countBySnapshotId(int $snapshotId): int
    {
        $sql = 'SELECT COUNT(*) FROM `comments` WHERE `snapshot_id` = ?';

        return (int) $this->connection->fetchColumn($sql, [$snapshotId]);
    }

    /**
     * Count replies to a comment.
     */
    public function countReplies(int $parentId): int
    {
        $sql = 'SELECT COUNT(*) FROM `comments` c
                INNER JOIN `snapshots` s ON c.snapshot_id = s.id
                INNER JOIN `pages` p ON s.page_id = p.id
                INNER JOIN `projects` pr ON p.project_id = pr.id
                WHERE c.parent_id = ?
                  AND s.deleted_at IS NULL
                  AND p.deleted_at IS NULL
                  AND pr.deleted_at IS NULL';

        return (int) $this->connection->fetchColumn($sql, [$parentId]);
    }

    /**
     * Save a comment (insert or update).
     *
     * @return Comment The saved comment with ID populated
     *
     * @throws ValidationException If validation fails
     */
    public function save(Comment $comment): Comment
    {
        $this->validate($comment);

        $data = [
            'snapshot_id' => $comment->snapshotId,
            'parent_id' => $comment->parentId,
            'author_name' => $comment->authorName,
            'author_email' => $comment->authorEmail,
            'content' => $comment->content,
            'x_norm' => $comment->xNorm,
            'y_norm' => $comment->yNorm,
        ];

        if ($comment->id === null) {
            $comment->id = $this->insert($data);
        } else {
            $this->update($comment->id, $data);
        }

        // Reload to get timestamps
        return $this->find($comment->id) ?? $comment;
    }

    /**
     * Update comment coordinates.
     */
    public function updateCoordinates(int $id, float $x, float $y): bool
    {
        $xNorm = number_format($x, 9, '.', '');
        $yNorm = number_format($y, 9, '.', '');

        $sql = 'UPDATE `comments` SET `x_norm` = ?, `y_norm` = ? WHERE `id` = ?';

        return $this->connection->execute($sql, [$xNorm, $yNorm, $id]) > 0;
    }

    /**
     * Update comment content.
     */
    public function updateContent(int $id, string $content): bool
    {
        if (empty(trim($content))) {
            throw ValidationException::forField('content', 'Content is required');
        }

        $sql = 'UPDATE `comments` SET `content` = ? WHERE `id` = ?';

        return $this->connection->execute($sql, [$content, $id]) > 0;
    }

    /**
     * Delete a comment and all its replies.
     *
     * Since comments don't support soft delete, this is a hard delete.
     * Replies are deleted via ON DELETE CASCADE in the database.
     */
    public function delete(int $id): bool
    {
        return $this->hardDelete($id);
    }

    /**
     * Validate a comment entity.
     *
     * @throws ValidationException If validation fails
     */
    private function validate(Comment $comment): void
    {
        $errors = [];

        // Validate snapshot exists (can be soft-deleted - comments persist)
        // We check existsWithDeleted because comments can be added to snapshots
        // that might later be soft-deleted, but we validate the snapshot exists at all
        if ($comment->snapshotId <= 0) {
            $errors['snapshot_id'] = ['Snapshot ID is required'];
        } elseif (!$this->snapshotRepository->existsWithDeleted($comment->snapshotId)) {
            $errors['snapshot_id'] = ['Snapshot does not exist'];
        }

        // Validate parent comment exists if specified
        if ($comment->parentId !== null) {
            if (!$this->existsWithDeleted($comment->parentId)) {
                $errors['parent_id'] = ['Parent comment does not exist'];
            }
        }

        if (empty(trim($comment->authorName))) {
            $errors['author_name'] = ['Author name is required'];
        } elseif (strlen($comment->authorName) > 255) {
            $errors['author_name'] = ['Author name must be 255 characters or less'];
        }

        if ($comment->authorEmail !== null && strlen($comment->authorEmail) > 255) {
            $errors['author_email'] = ['Author email must be 255 characters or less'];
        } elseif ($comment->authorEmail !== null && !filter_var($comment->authorEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['author_email'] = ['Author email must be a valid email address'];
        }

        if (empty(trim($comment->content))) {
            $errors['content'] = ['Content is required'];
        }

        // Validate coordinates if provided
        if ($comment->xNorm !== null || $comment->yNorm !== null) {
            if ($comment->xNorm === null || $comment->yNorm === null) {
                $errors['coordinates'] = ['Both X and Y coordinates must be provided'];
            } else {
                $x = (float) $comment->xNorm;
                $y = (float) $comment->yNorm;

                if ($x < 0 || $x > 1) {
                    $errors['x_norm'] = ['X coordinate must be between 0 and 1'];
                }

                if ($y < 0 || $y > 1) {
                    $errors['y_norm'] = ['Y coordinate must be between 0 and 1'];
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Check if a comment exists (alias for exists since comments don't soft delete).
     */
    public function existsWithDeleted(int $id): bool
    {
        return $this->exists($id);
    }
}
