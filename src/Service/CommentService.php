<?php

declare(strict_types=1);

namespace Snaply\Service;

use Snaply\Database\Connection;
use Snaply\Entity\Comment;
use Snaply\Exception\EntityNotFoundException;
use Snaply\Exception\ValidationException;
use Snaply\Repository\CommentRepository;
use Snaply\Repository\SnapshotRepository;

/**
 * Service layer for Comment operations.
 *
 * Orchestrates comment creation with coordinate validation,
 * retrieval, and management. Comments do not have soft delete
 * but are filtered based on parent entity status for UI queries.
 */
class CommentService
{
    private Connection $connection;
    private CommentRepository $commentRepository;
    private SnapshotRepository $snapshotRepository;

    public function __construct(
        Connection $connection,
        CommentRepository $commentRepository,
        SnapshotRepository $snapshotRepository
    ) {
        $this->connection = $connection;
        $this->commentRepository = $commentRepository;
        $this->snapshotRepository = $snapshotRepository;
    }

    /**
     * Create a new comment on a snapshot.
     *
     * @param int $snapshotId Snapshot to attach comment to
     * @param string $authorName Comment author name
     * @param string|null $authorEmail Comment author email (optional)
     * @param string $content Comment text
     * @param float|null $x X coordinate (0.0 to 1.0, optional)
     * @param float|null $y Y coordinate (0.0 to 1.0, optional)
     *
     * @return Comment The created comment
     *
     * @throws ValidationException If validation fails
     */
    public function createComment(
        int $snapshotId,
        string $authorName,
        ?string $authorEmail,
        string $content,
        ?float $x = null,
        ?float $y = null
    ): Comment {
        // Validate snapshot exists (can be soft-deleted per requirements)
        if (!$this->snapshotRepository->existsWithDeleted($snapshotId)) {
            throw ValidationException::forField('snapshot_id', 'Snapshot does not exist');
        }

        // Validate coordinates if provided
        if ($x !== null || $y !== null) {
            $this->validateCoordinates($x, $y);
        }

        $comment = new Comment(
            id: null,
            snapshotId: $snapshotId,
            parentId: null,
            authorName: $authorName,
            authorEmail: $authorEmail,
            content: $content
        );

        // Set coordinates if provided
        if ($x !== null && $y !== null) {
            $comment->setCoordinates($x, $y);
        }

        return $this->commentRepository->save($comment);
    }

    /**
     * Create a reply to an existing comment.
     *
     * Replies inherit the snapshot from the parent comment and
     * do not have coordinates (they appear in the thread).
     *
     * @param int $parentId Parent comment ID
     * @param string $authorName Reply author name
     * @param string|null $authorEmail Reply author email (optional)
     * @param string $content Reply text
     *
     * @return Comment The created reply
     *
     * @throws EntityNotFoundException If parent comment not found
     * @throws ValidationException If validation fails
     */
    public function createReply(
        int $parentId,
        string $authorName,
        ?string $authorEmail,
        string $content
    ): Comment {
        // Find parent comment to get snapshot ID
        $parent = $this->commentRepository->find($parentId);

        if ($parent === null) {
            throw EntityNotFoundException::comment($parentId);
        }

        $reply = new Comment(
            id: null,
            snapshotId: $parent->snapshotId,
            parentId: $parentId,
            authorName: $authorName,
            authorEmail: $authorEmail,
            content: $content
        );

        // Replies do not have coordinates
        return $this->commentRepository->save($reply);
    }

    /**
     * Update comment content.
     *
     * @param int $id Comment ID
     * @param string $content New content
     *
     * @return bool True if updated
     *
     * @throws EntityNotFoundException If comment not found
     * @throws ValidationException If content is empty
     */
    public function updateCommentContent(int $id, string $content): bool
    {
        if (!$this->commentRepository->exists($id)) {
            throw EntityNotFoundException::comment($id);
        }

        return $this->commentRepository->updateContent($id, $content);
    }

    /**
     * Update comment position (coordinates).
     *
     * @param int $id Comment ID
     * @param float $x New X coordinate (0.0 to 1.0)
     * @param float $y New Y coordinate (0.0 to 1.0)
     *
     * @return bool True if updated
     *
     * @throws EntityNotFoundException If comment not found
     * @throws ValidationException If coordinates invalid
     */
    public function updateCommentPosition(int $id, float $x, float $y): bool
    {
        if (!$this->commentRepository->exists($id)) {
            throw EntityNotFoundException::comment($id);
        }

        $this->validateCoordinates($x, $y);

        return $this->commentRepository->updateCoordinates($id, $x, $y);
    }

    /**
     * Delete a comment and all its replies.
     *
     * Comments are hard deleted (no soft delete). Replies are
     * automatically deleted via ON DELETE CASCADE.
     *
     * @param int $id Comment ID
     *
     * @return bool True if deleted
     */
    public function deleteComment(int $id): bool
    {
        return $this->commentRepository->delete($id);
    }

    /**
     * Get a comment by ID.
     *
     * @param int $id Comment ID
     *
     * @return Comment|null The comment or null if not found
     */
    public function getComment(int $id): ?Comment
    {
        return $this->commentRepository->find($id);
    }

    /**
     * Get a comment by ID or throw exception.
     *
     * @param int $id Comment ID
     *
     * @return Comment The comment
     *
     * @throws EntityNotFoundException If comment not found
     */
    public function getCommentOrFail(int $id): Comment
    {
        return $this->commentRepository->findOrFail($id);
    }

    /**
     * List comments for a snapshot (UI use - active entities only).
     *
     * This method filters out comments when the parent snapshot, page,
     * or project is soft-deleted.
     *
     * @param int $snapshotId Snapshot ID
     *
     * @return Comment[]
     */
    public function listCommentsBySnapshot(int $snapshotId): array
    {
        return $this->commentRepository->findBySnapshotIdForActiveEntities($snapshotId);
    }

    /**
     * List comments for a snapshot (admin/debug - all comments).
     *
     * This method returns all comments regardless of parent entity status.
     *
     * @param int $snapshotId Snapshot ID
     *
     * @return Comment[]
     */
    public function listCommentsBySnapshotAdmin(int $snapshotId): array
    {
        return $this->commentRepository->findBySnapshotId($snapshotId);
    }

    /**
     * List top-level comments (not replies) for a snapshot.
     *
     * @param int $snapshotId Snapshot ID
     *
     * @return Comment[]
     */
    public function listTopLevelComments(int $snapshotId): array
    {
        return $this->commentRepository->findTopLevelBySnapshotId($snapshotId);
    }

    /**
     * List replies to a comment.
     *
     * @param int $parentId Parent comment ID
     *
     * @return Comment[]
     */
    public function listReplies(int $parentId): array
    {
        return $this->commentRepository->findReplies($parentId);
    }

    /**
     * Find comments within a coordinate region.
     *
     * Useful for finding comments near a click point or within
     * a selection area.
     *
     * @param int $snapshotId Snapshot ID
     * @param float $xMin Minimum X coordinate (0.0 to 1.0)
     * @param float $xMax Maximum X coordinate (0.0 to 1.0)
     * @param float $yMin Minimum Y coordinate (0.0 to 1.0)
     * @param float $yMax Maximum Y coordinate (0.0 to 1.0)
     *
     * @return Comment[]
     *
     * @throws ValidationException If coordinates are invalid
     */
    public function findCommentsInRegion(
        int $snapshotId,
        float $xMin,
        float $xMax,
        float $yMin,
        float $yMax
    ): array {
        // Validate region bounds
        $this->validateCoordinateBounds($xMin, $xMax, $yMin, $yMax);

        return $this->commentRepository->findInRegion($snapshotId, $xMin, $xMax, $yMin, $yMax);
    }

    /**
     * Find comments near a point (within a radius).
     *
     * @param int $snapshotId Snapshot ID
     * @param float $x Center X coordinate
     * @param float $y Center Y coordinate
     * @param float $radius Search radius (in normalised coordinates)
     *
     * @return Comment[]
     */
    public function findCommentsNearPoint(
        int $snapshotId,
        float $x,
        float $y,
        float $radius = 0.05
    ): array {
        $xMin = max(0.0, $x - $radius);
        $xMax = min(1.0, $x + $radius);
        $yMin = max(0.0, $y - $radius);
        $yMax = min(1.0, $y + $radius);

        return $this->findCommentsInRegion($snapshotId, $xMin, $xMax, $yMin, $yMax);
    }

    /**
     * Count comments for a snapshot (UI use - active entities only).
     */
    public function countCommentsBySnapshot(int $snapshotId): int
    {
        return $this->commentRepository->countBySnapshotIdForActiveEntities($snapshotId);
    }

    /**
     * Count all comments for a snapshot (admin/debug - all comments).
     */
    public function countCommentsBySnapshotAdmin(int $snapshotId): int
    {
        return $this->commentRepository->countBySnapshotId($snapshotId);
    }

    /**
     * Count replies to a comment.
     */
    public function countReplies(int $parentId): int
    {
        return $this->commentRepository->countReplies($parentId);
    }

    /**
     * Count all comments.
     */
    public function countComments(): int
    {
        return $this->commentRepository->count();
    }

    /**
     * Check if a comment exists.
     */
    public function commentExists(int $id): bool
    {
        return $this->commentRepository->exists($id);
    }

    /**
     * Get a threaded view of comments for a snapshot.
     *
     * Returns top-level comments with their replies nested.
     *
     * @param int $snapshotId Snapshot ID
     *
     * @return array<int, array{comment: Comment, replies: Comment[]}>
     */
    public function getThreadedComments(int $snapshotId): array
    {
        $topLevel = $this->listTopLevelComments($snapshotId);
        $threaded = [];

        foreach ($topLevel as $comment) {
            $threaded[] = [
                'comment' => $comment,
                'replies' => $this->listReplies($comment->id),
            ];
        }

        return $threaded;
    }

    /**
     * Validate that coordinates are within the 0-1 range.
     *
     * @param float|null $x X coordinate
     * @param float|null $y Y coordinate
     *
     * @throws ValidationException If coordinates are invalid
     */
    private function validateCoordinates(?float $x, ?float $y): void
    {
        $errors = [];

        if ($x === null && $y !== null) {
            $errors['x'] = ['X coordinate is required when Y is provided'];
        }

        if ($y === null && $x !== null) {
            $errors['y'] = ['Y coordinate is required when X is provided'];
        }

        if ($x !== null && ($x < 0.0 || $x > 1.0)) {
            $errors['x'] = ['X coordinate must be between 0 and 1'];
        }

        if ($y !== null && ($y < 0.0 || $y > 1.0)) {
            $errors['y'] = ['Y coordinate must be between 0 and 1'];
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Validate coordinate bounds for region search.
     *
     * @throws ValidationException If bounds are invalid
     */
    private function validateCoordinateBounds(
        float $xMin,
        float $xMax,
        float $yMin,
        float $yMax
    ): void {
        $errors = [];

        if ($xMin < 0.0 || $xMin > 1.0) {
            $errors['x_min'] = ['X minimum must be between 0 and 1'];
        }

        if ($xMax < 0.0 || $xMax > 1.0) {
            $errors['x_max'] = ['X maximum must be between 0 and 1'];
        }

        if ($yMin < 0.0 || $yMin > 1.0) {
            $errors['y_min'] = ['Y minimum must be between 0 and 1'];
        }

        if ($yMax < 0.0 || $yMax > 1.0) {
            $errors['y_max'] = ['Y maximum must be between 0 and 1'];
        }

        if ($xMin > $xMax) {
            $errors['x_range'] = ['X minimum cannot be greater than X maximum'];
        }

        if ($yMin > $yMax) {
            $errors['y_range'] = ['Y minimum cannot be greater than Y maximum'];
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
