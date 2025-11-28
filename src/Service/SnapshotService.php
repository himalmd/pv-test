<?php

declare(strict_types=1);

namespace Snaply\Service;

use Snaply\Database\Connection;
use Snaply\Entity\Snapshot;
use Snaply\Exception\EntityNotFoundException;
use Snaply\Exception\StorageException;
use Snaply\Exception\ValidationException;
use Snaply\Repository\PageRepository;
use Snaply\Repository\SnapshotRepository;
use Snaply\Storage\MediaStorageInterface;

/**
 * Service layer for Snapshot operations.
 *
 * Orchestrates snapshot creation with media storage, retrieval,
 * and management with soft delete semantics.
 */
class SnapshotService
{
    private Connection $connection;
    private SnapshotRepository $snapshotRepository;
    private PageRepository $pageRepository;
    private MediaStorageInterface $mediaStorage;

    public function __construct(
        Connection $connection,
        SnapshotRepository $snapshotRepository,
        PageRepository $pageRepository,
        MediaStorageInterface $mediaStorage
    ) {
        $this->connection = $connection;
        $this->snapshotRepository = $snapshotRepository;
        $this->pageRepository = $pageRepository;
        $this->mediaStorage = $mediaStorage;
    }

    /**
     * Create a snapshot from an uploaded file.
     *
     * This method:
     * 1. Validates the page exists and is active
     * 2. Stores the image via media storage abstraction
     * 3. Creates and persists the snapshot record
     *
     * @param int $pageId Page to attach snapshot to
     * @param string $uploadedFilePath Path to uploaded file (temp path)
     * @param string $originalFilename Original filename from upload
     * @param int|null $width Rendered width in pixels (optional, can be extracted)
     * @param int|null $height Rendered height in pixels (optional, can be extracted)
     * @param array<string, mixed> $metadata Optional metadata to store with media
     *
     * @return Snapshot The created snapshot
     *
     * @throws ValidationException If page not found or validation fails
     * @throws StorageException If media storage fails
     */
    public function createSnapshot(
        int $pageId,
        string $uploadedFilePath,
        string $originalFilename,
        ?int $width = null,
        ?int $height = null,
        array $metadata = []
    ): Snapshot {
        // Validate page exists and is active
        if (!$this->pageRepository->exists($pageId)) {
            throw ValidationException::invalidParent('Page', $pageId);
        }

        // Auto-detect dimensions from image if not provided
        if ($width === null || $height === null) {
            $dimensions = $this->extractImageDimensions($uploadedFilePath);
            $width = $width ?? $dimensions['width'];
            $height = $height ?? $dimensions['height'];
        }

        // Use transaction to ensure atomicity
        return $this->connection->transaction(function () use (
            $pageId,
            $uploadedFilePath,
            $originalFilename,
            $width,
            $height,
            $metadata
        ) {
            // Store image via media abstraction
            $mediaReference = $this->mediaStorage->store(
                $uploadedFilePath,
                $originalFilename,
                'snapshot',
                $metadata
            );

            // Create snapshot entity
            $snapshot = new Snapshot(
                id: null,
                pageId: $pageId,
                version: 0, // Will be auto-assigned by repository
                widthPx: $width,
                heightPx: $height,
                mediaReference: $mediaReference
            );

            // Persist and return
            return $this->snapshotRepository->save($snapshot);
        });
    }

    /**
     * Create a snapshot from an existing file path.
     *
     * Similar to createSnapshot but for files already on the filesystem
     * (not from an HTTP upload).
     *
     * @param int $pageId Page to attach snapshot to
     * @param string $filePath Path to existing file
     * @param string $originalName Name to use for the file
     * @param int|null $width Rendered width in pixels
     * @param int|null $height Rendered height in pixels
     *
     * @return Snapshot The created snapshot
     */
    public function createSnapshotFromPath(
        int $pageId,
        string $filePath,
        string $originalName,
        ?int $width = null,
        ?int $height = null
    ): Snapshot {
        return $this->createSnapshot($pageId, $filePath, $originalName, $width, $height);
    }

    /**
     * Update snapshot dimensions.
     *
     * @param int $id Snapshot ID
     * @param int $width New width in pixels
     * @param int $height New height in pixels
     *
     * @return bool True if updated
     *
     * @throws EntityNotFoundException If snapshot not found
     * @throws ValidationException If dimensions invalid
     */
    public function updateSnapshotDimensions(int $id, int $width, int $height): bool
    {
        if (!$this->snapshotRepository->exists($id)) {
            throw EntityNotFoundException::snapshot($id);
        }

        $this->validateDimensions($width, $height);

        return $this->snapshotRepository->updateDimensions($id, $width, $height);
    }

    /**
     * Soft delete a snapshot.
     *
     * Note: The media file is NOT deleted. Use hardDeleteSnapshot to also
     * remove the media file.
     *
     * @param int $id Snapshot ID
     *
     * @return bool True if deleted
     *
     * @throws EntityNotFoundException If snapshot not found
     */
    public function deleteSnapshot(int $id): bool
    {
        if (!$this->snapshotRepository->exists($id)) {
            throw EntityNotFoundException::snapshot($id);
        }

        return $this->snapshotRepository->delete($id);
    }

    /**
     * Restore a soft-deleted snapshot.
     *
     * @param int $id Snapshot ID
     *
     * @return bool True if restored
     *
     * @throws EntityNotFoundException If snapshot not found
     */
    public function restoreSnapshot(int $id): bool
    {
        if (!$this->snapshotRepository->existsWithDeleted($id)) {
            throw EntityNotFoundException::snapshot($id);
        }

        return $this->snapshotRepository->restore($id);
    }

    /**
     * Permanently delete a snapshot and its media file.
     *
     * Warning: This will fail if the snapshot has associated comments
     * due to foreign key constraints.
     *
     * @param int $id Snapshot ID
     * @param bool $deleteMedia Whether to also delete the media file
     *
     * @return bool True if deleted
     */
    public function hardDeleteSnapshot(int $id, bool $deleteMedia = true): bool
    {
        $snapshot = $this->snapshotRepository->findWithDeleted($id);

        if ($snapshot === null) {
            return false;
        }

        // Delete media file if requested
        if ($deleteMedia && $snapshot->mediaReference !== null) {
            try {
                $this->mediaStorage->delete($snapshot->mediaReference);
            } catch (StorageException $e) {
                // Log error but continue with database deletion
                // Media file may already be deleted or missing
            }
        }

        return $this->snapshotRepository->hardDelete($id);
    }

    /**
     * Get a snapshot by ID (active only).
     *
     * @param int $id Snapshot ID
     *
     * @return Snapshot|null The snapshot or null if not found/deleted
     */
    public function getSnapshot(int $id): ?Snapshot
    {
        return $this->snapshotRepository->find($id);
    }

    /**
     * Get a snapshot by ID or throw exception.
     *
     * @param int $id Snapshot ID
     *
     * @return Snapshot The snapshot
     *
     * @throws EntityNotFoundException If snapshot not found
     */
    public function getSnapshotOrFail(int $id): Snapshot
    {
        return $this->snapshotRepository->findOrFail($id);
    }

    /**
     * Get a snapshot by ID, including soft-deleted (admin/debug).
     *
     * @param int $id Snapshot ID
     *
     * @return Snapshot|null The snapshot or null if not found
     */
    public function getSnapshotWithDeleted(int $id): ?Snapshot
    {
        return $this->snapshotRepository->findWithDeleted($id);
    }

    /**
     * Get the public URL for a snapshot's media.
     *
     * @param int $id Snapshot ID
     *
     * @return string|null The media URL or null if no media
     *
     * @throws EntityNotFoundException If snapshot not found
     * @throws StorageException If URL cannot be generated
     */
    public function getSnapshotUrl(int $id): ?string
    {
        $snapshot = $this->snapshotRepository->find($id);

        if ($snapshot === null) {
            throw EntityNotFoundException::snapshot($id);
        }

        if ($snapshot->mediaReference === null) {
            return null;
        }

        return $this->mediaStorage->getUrl($snapshot->mediaReference);
    }

    /**
     * Get the public URL for a snapshot's media (including soft-deleted).
     *
     * @param int $id Snapshot ID
     *
     * @return string|null The media URL or null if no media
     */
    public function getSnapshotUrlWithDeleted(int $id): ?string
    {
        $snapshot = $this->snapshotRepository->findWithDeleted($id);

        if ($snapshot === null || $snapshot->mediaReference === null) {
            return null;
        }

        try {
            return $this->mediaStorage->getUrl($snapshot->mediaReference);
        } catch (StorageException $e) {
            return null;
        }
    }

    /**
     * List all active snapshots.
     *
     * @return Snapshot[]
     */
    public function listSnapshots(): array
    {
        return $this->snapshotRepository->findAll();
    }

    /**
     * List snapshots for a page (active only, with active parent chain).
     *
     * @param int $pageId Page ID
     *
     * @return Snapshot[]
     */
    public function listSnapshotsByPage(int $pageId): array
    {
        return $this->snapshotRepository->findByPageId($pageId);
    }

    /**
     * List snapshots for a page including soft-deleted (admin/debug).
     *
     * @param int $pageId Page ID
     *
     * @return Snapshot[]
     */
    public function listSnapshotsByPageWithDeleted(int $pageId): array
    {
        return $this->snapshotRepository->findByPageIdWithDeleted($pageId);
    }

    /**
     * List all snapshots including soft-deleted (admin/debug).
     *
     * @return Snapshot[]
     */
    public function listSnapshotsWithDeleted(): array
    {
        return $this->snapshotRepository->findAllWithDeleted();
    }

    /**
     * List only soft-deleted snapshots (admin/debug).
     *
     * @return Snapshot[]
     */
    public function listDeletedSnapshots(): array
    {
        return $this->snapshotRepository->findOnlyDeleted();
    }

    /**
     * Get the latest snapshot for a page.
     *
     * @param int $pageId Page ID
     *
     * @return Snapshot|null The latest snapshot or null
     */
    public function getLatestSnapshot(int $pageId): ?Snapshot
    {
        return $this->snapshotRepository->findLatestByPageId($pageId);
    }

    /**
     * Find a snapshot by its media reference.
     *
     * @param string $mediaReference Media identifier
     *
     * @return Snapshot|null The snapshot or null
     */
    public function findSnapshotByMediaReference(string $mediaReference): ?Snapshot
    {
        return $this->snapshotRepository->findByMediaReference($mediaReference);
    }

    /**
     * Count snapshots for a page (active only).
     */
    public function countSnapshotsByPage(int $pageId): int
    {
        return $this->snapshotRepository->countByPageId($pageId);
    }

    /**
     * Count all active snapshots.
     */
    public function countSnapshots(): int
    {
        return $this->snapshotRepository->count();
    }

    /**
     * Count all snapshots including soft-deleted.
     */
    public function countSnapshotsWithDeleted(): int
    {
        return $this->snapshotRepository->countWithDeleted();
    }

    /**
     * Check if a snapshot exists (active only).
     */
    public function snapshotExists(int $id): bool
    {
        return $this->snapshotRepository->exists($id);
    }

    /**
     * Get snapshot metadata from media storage.
     *
     * @param int $id Snapshot ID
     *
     * @return array<string, mixed>|null Metadata or null if no media
     */
    public function getSnapshotMediaMetadata(int $id): ?array
    {
        $snapshot = $this->snapshotRepository->find($id);

        if ($snapshot === null || $snapshot->mediaReference === null) {
            return null;
        }

        try {
            return $this->mediaStorage->getMetadata($snapshot->mediaReference);
        } catch (StorageException $e) {
            return null;
        }
    }

    /**
     * Extract image dimensions from a file.
     *
     * @param string $filePath Path to image file
     *
     * @return array{width: int|null, height: int|null}
     */
    private function extractImageDimensions(string $filePath): array
    {
        $imageInfo = @getimagesize($filePath);

        if ($imageInfo === false) {
            return ['width' => null, 'height' => null];
        }

        return [
            'width' => $imageInfo[0],
            'height' => $imageInfo[1],
        ];
    }

    /**
     * Validate dimensions are positive integers.
     *
     * @throws ValidationException If dimensions are invalid
     */
    private function validateDimensions(int $width, int $height): void
    {
        $errors = [];

        if ($width <= 0) {
            $errors['width'] = ['Width must be a positive integer'];
        }

        if ($height <= 0) {
            $errors['height'] = ['Height must be a positive integer'];
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
