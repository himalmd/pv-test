<?php

declare(strict_types=1);

namespace Snaply\Storage;

use Snaply\Exception\StorageException;

/**
 * Interface for media storage operations.
 *
 * This interface defines the contract for storing and retrieving media files
 * in Snaply. Implementations can use various backends such as local filesystem,
 * Amazon S3, WordPress Media Library, or other storage services.
 *
 * Media identifiers are opaque strings that uniquely identify stored files.
 * The format is implementation-independent and can be stored directly in the
 * database (e.g., in the snapshots.media_reference column).
 *
 * Example identifier formats:
 * - Local filesystem: "snapshots/2024/01/15/abc123-def456.png"
 * - S3: "snapshots/2024/01/15/abc123-def456.png" (same format, different backend)
 * - WordPress: "wp_attachment_12345"
 */
interface MediaStorageInterface
{
    /**
     * Store a file and return a stable media identifier.
     *
     * The returned identifier is a string that can be stored in the database
     * and later used to retrieve the file URL or delete the file. The identifier
     * format is designed to be backend-agnostic.
     *
     * @param string $sourcePath Path to the source file to store (e.g., temp upload path)
     * @param string $originalFilename Original filename (used to preserve extension)
     * @param string $entityType Type of entity this media belongs to (e.g., 'snapshot')
     * @param array<string, mixed> $metadata Optional metadata to associate with the file
     *
     * @return string The media identifier for the stored file
     *
     * @throws StorageException If the file cannot be stored
     */
    public function store(
        string $sourcePath,
        string $originalFilename,
        string $entityType = 'snapshot',
        array $metadata = []
    ): string;

    /**
     * Get a URL for accessing the media file.
     *
     * Returns a URL that can be used in the frontend to display or download
     * the media file. Depending on the implementation, this may be:
     * - A public URL (for publicly accessible storage)
     * - A signed URL with expiration (for private storage like S3)
     * - A WordPress attachment URL
     *
     * @param string $identifier The media identifier returned by store()
     *
     * @return string The URL for accessing the media
     *
     * @throws StorageException If the identifier is invalid or file not found
     */
    public function getUrl(string $identifier): string;

    /**
     * Check if a media file exists.
     *
     * @param string $identifier The media identifier to check
     *
     * @return bool True if the file exists, false otherwise
     */
    public function exists(string $identifier): bool;

    /**
     * Delete a media file.
     *
     * Permanently removes the media file from storage. This operation cannot
     * be undone. For soft-delete semantics, handle this at the application
     * level by keeping the identifier but not calling delete().
     *
     * @param string $identifier The media identifier of the file to delete
     *
     * @return bool True if the file was deleted, false if it didn't exist
     *
     * @throws StorageException If the file exists but cannot be deleted
     */
    public function delete(string $identifier): bool;

    /**
     * Get the full filesystem path for a media file (if applicable).
     *
     * This method is optional and may not be supported by all implementations.
     * For example, S3 storage would not have a local filesystem path.
     *
     * @param string $identifier The media identifier
     *
     * @return string|null The filesystem path, or null if not applicable
     *
     * @throws StorageException If the identifier is invalid
     */
    public function getPath(string $identifier): ?string;

    /**
     * Get metadata associated with a media file.
     *
     * Returns any metadata that was stored with the file, plus any
     * additional metadata the storage backend provides (e.g., file size,
     * content type, dimensions for images).
     *
     * @param string $identifier The media identifier
     *
     * @return array<string, mixed> Metadata array
     *
     * @throws StorageException If the identifier is invalid or file not found
     */
    public function getMetadata(string $identifier): array;
}
