<?php

declare(strict_types=1);

namespace Snaply\Storage;

use Snaply\Exception\StorageException;

/**
 * Local filesystem implementation of MediaStorageInterface.
 *
 * Stores media files on the local filesystem under a configurable base directory.
 * Files are organized by entity type and date for easy management and to prevent
 * single-directory bloat.
 *
 * Directory structure:
 *   {basePath}/{entityType}/{year}/{month}/{day}/{uuid}.{extension}
 *
 * Example:
 *   /var/www/app/uploads/snapshots/2024/01/15/550e8400-e29b-41d4-a716-446655440000.png
 *
 * The media identifier format is:
 *   {entityType}/{year}/{month}/{day}/{uuid}.{extension}
 *
 * This format is:
 * - Backend-agnostic (works as S3 key, WordPress path, etc.)
 * - Self-documenting (includes entity type and date)
 * - Collision-free (uses UUIDs)
 * - Extension-preserving (keeps original file type)
 */
class LocalFilesystemStorage implements MediaStorageInterface
{
    private string $basePath;
    private string $baseUrl;
    private int $directoryPermissions;
    private int $filePermissions;

    /**
     * Allowed file extensions for upload (lowercase).
     *
     * @var array<string>
     */
    private array $allowedExtensions = [
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg',
        'pdf',
    ];

    /**
     * MIME type mapping for common extensions.
     *
     * @var array<string, string>
     */
    private const MIME_TYPES = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'pdf' => 'application/pdf',
    ];

    /**
     * Create a new LocalFilesystemStorage instance.
     *
     * @param string $basePath Base directory for storing files (must be writable)
     * @param string $baseUrl Base URL for generating public URLs
     * @param int $directoryPermissions Permissions for created directories (default: 0755)
     * @param int $filePermissions Permissions for stored files (default: 0644)
     *
     * @throws StorageException If the base path is not configured properly
     */
    public function __construct(
        string $basePath,
        string $baseUrl,
        int $directoryPermissions = 0755,
        int $filePermissions = 0644
    ) {
        $this->basePath = rtrim($basePath, '/');
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->directoryPermissions = $directoryPermissions;
        $this->filePermissions = $filePermissions;

        $this->validateConfiguration();
    }

    /**
     * @inheritDoc
     */
    public function store(
        string $sourcePath,
        string $originalFilename,
        string $entityType = 'snapshot',
        array $metadata = []
    ): string {
        // Validate source file exists
        if (!file_exists($sourcePath)) {
            throw StorageException::uploadFailed('Source file does not exist: ' . $sourcePath);
        }

        if (!is_readable($sourcePath)) {
            throw StorageException::permissionDenied($sourcePath);
        }

        // Extract and validate extension
        $extension = $this->extractExtension($originalFilename);
        $this->validateExtension($extension);

        // Generate unique identifier
        $identifier = $this->generateIdentifier($entityType, $extension);

        // Determine full path and ensure directory exists
        $fullPath = $this->getFullPath($identifier);
        $directory = dirname($fullPath);

        $this->ensureDirectoryExists($directory);

        // Copy file to destination
        if (!copy($sourcePath, $fullPath)) {
            throw StorageException::uploadFailed('Failed to copy file to destination');
        }

        // Set file permissions
        chmod($fullPath, $this->filePermissions);

        // Store metadata if provided
        if (!empty($metadata)) {
            $this->storeMetadata($identifier, $metadata);
        }

        return $identifier;
    }

    /**
     * @inheritDoc
     */
    public function getUrl(string $identifier): string
    {
        $this->validateIdentifier($identifier);

        if (!$this->exists($identifier)) {
            throw StorageException::fileNotFound($identifier);
        }

        return $this->baseUrl . '/' . $identifier;
    }

    /**
     * @inheritDoc
     */
    public function exists(string $identifier): bool
    {
        try {
            $this->validateIdentifier($identifier);
            $fullPath = $this->getFullPath($identifier);
            return file_exists($fullPath) && is_file($fullPath);
        } catch (StorageException) {
            return false;
        }
    }

    /**
     * @inheritDoc
     */
    public function delete(string $identifier): bool
    {
        $this->validateIdentifier($identifier);

        $fullPath = $this->getFullPath($identifier);

        if (!file_exists($fullPath)) {
            return false;
        }

        if (!unlink($fullPath)) {
            throw StorageException::deleteFailed($identifier);
        }

        // Also delete metadata file if it exists
        $metadataPath = $this->getMetadataPath($identifier);
        if (file_exists($metadataPath)) {
            unlink($metadataPath);
        }

        return true;
    }

    /**
     * @inheritDoc
     */
    public function getPath(string $identifier): ?string
    {
        $this->validateIdentifier($identifier);

        $fullPath = $this->getFullPath($identifier);

        if (!file_exists($fullPath)) {
            throw StorageException::fileNotFound($identifier);
        }

        return $fullPath;
    }

    /**
     * @inheritDoc
     */
    public function getMetadata(string $identifier): array
    {
        $this->validateIdentifier($identifier);

        $fullPath = $this->getFullPath($identifier);

        if (!file_exists($fullPath)) {
            throw StorageException::fileNotFound($identifier);
        }

        // Start with file system metadata
        $metadata = [
            'identifier' => $identifier,
            'size' => filesize($fullPath),
            'mime_type' => $this->getMimeType($identifier),
            'created_at' => date('Y-m-d H:i:s', filectime($fullPath)),
            'modified_at' => date('Y-m-d H:i:s', filemtime($fullPath)),
        ];

        // Add image dimensions if applicable
        $extension = $this->extractExtension($identifier);
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            $imageInfo = @getimagesize($fullPath);
            if ($imageInfo !== false) {
                $metadata['width'] = $imageInfo[0];
                $metadata['height'] = $imageInfo[1];
            }
        }

        // Merge with stored metadata if available
        $metadataPath = $this->getMetadataPath($identifier);
        if (file_exists($metadataPath)) {
            $storedMetadata = json_decode(file_get_contents($metadataPath), true);
            if (is_array($storedMetadata)) {
                $metadata = array_merge($storedMetadata, $metadata);
            }
        }

        return $metadata;
    }

    /**
     * Set allowed file extensions.
     *
     * @param array<string> $extensions List of allowed extensions (lowercase, without dots)
     */
    public function setAllowedExtensions(array $extensions): void
    {
        $this->allowedExtensions = array_map('strtolower', $extensions);
    }

    /**
     * Get allowed file extensions.
     *
     * @return array<string>
     */
    public function getAllowedExtensions(): array
    {
        return $this->allowedExtensions;
    }

    /**
     * Validate the storage configuration.
     *
     * @throws StorageException If configuration is invalid
     */
    private function validateConfiguration(): void
    {
        if (empty($this->basePath)) {
            throw StorageException::configurationError('Base path cannot be empty');
        }

        if (empty($this->baseUrl)) {
            throw StorageException::configurationError('Base URL cannot be empty');
        }

        // Create base directory if it doesn't exist
        if (!is_dir($this->basePath)) {
            $this->ensureDirectoryExists($this->basePath);
        }

        if (!is_writable($this->basePath)) {
            throw StorageException::configurationError(
                'Base path is not writable: ' . $this->basePath
            );
        }
    }

    /**
     * Generate a unique media identifier.
     *
     * Format: {entityType}/{year}/{month}/{day}/{uuid}.{extension}
     *
     * @param string $entityType Entity type (e.g., 'snapshot')
     * @param string $extension File extension (without dot)
     *
     * @return string The generated identifier
     */
    private function generateIdentifier(string $entityType, string $extension): string
    {
        $entityType = preg_replace('/[^a-z0-9_-]/i', '', $entityType) ?: 'media';
        $uuid = $this->generateUuid();
        $date = date('Y/m/d');

        return sprintf('%s/%s/%s.%s', $entityType, $date, $uuid, $extension);
    }

    /**
     * Generate a UUID v4.
     *
     * @return string UUID string
     */
    private function generateUuid(): string
    {
        // Generate 16 random bytes
        $data = random_bytes(16);

        // Set version to 0100 (UUID v4)
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);

        // Set bits 6-7 to 10 (UUID variant)
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);

        // Format as UUID string
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    /**
     * Get the full filesystem path for an identifier.
     *
     * @param string $identifier Media identifier
     *
     * @return string Full filesystem path
     */
    private function getFullPath(string $identifier): string
    {
        return $this->basePath . '/' . $identifier;
    }

    /**
     * Get the path for storing metadata JSON.
     *
     * @param string $identifier Media identifier
     *
     * @return string Metadata file path
     */
    private function getMetadataPath(string $identifier): string
    {
        return $this->getFullPath($identifier) . '.meta.json';
    }

    /**
     * Store metadata for a media file.
     *
     * @param string $identifier Media identifier
     * @param array<string, mixed> $metadata Metadata to store
     */
    private function storeMetadata(string $identifier, array $metadata): void
    {
        $metadataPath = $this->getMetadataPath($identifier);
        file_put_contents(
            $metadataPath,
            json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        chmod($metadataPath, $this->filePermissions);
    }

    /**
     * Extract file extension from a filename or path.
     *
     * @param string $filename Filename or path
     *
     * @return string Extension (lowercase, without dot)
     */
    private function extractExtension(string $filename): string
    {
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        return strtolower($extension);
    }

    /**
     * Validate that an extension is allowed.
     *
     * @param string $extension Extension to validate (lowercase, without dot)
     *
     * @throws StorageException If extension is not allowed
     */
    private function validateExtension(string $extension): void
    {
        if (empty($extension)) {
            throw StorageException::invalidFile('File has no extension');
        }

        if (!in_array($extension, $this->allowedExtensions, true)) {
            throw StorageException::invalidFile(
                sprintf(
                    'Extension "%s" is not allowed. Allowed: %s',
                    $extension,
                    implode(', ', $this->allowedExtensions)
                )
            );
        }
    }

    /**
     * Validate a media identifier format.
     *
     * @param string $identifier Identifier to validate
     *
     * @throws StorageException If identifier is invalid
     */
    private function validateIdentifier(string $identifier): void
    {
        if (empty($identifier)) {
            throw StorageException::invalidIdentifier($identifier, 'Identifier cannot be empty');
        }

        // Check for directory traversal attempts
        if (str_contains($identifier, '..')) {
            throw StorageException::invalidIdentifier($identifier, 'Directory traversal not allowed');
        }

        // Validate format: entityType/year/month/day/uuid.extension
        $pattern = '/^[a-z0-9_-]+\/\d{4}\/\d{2}\/\d{2}\/[a-f0-9-]+\.[a-z0-9]+$/i';
        if (!preg_match($pattern, $identifier)) {
            throw StorageException::invalidIdentifier(
                $identifier,
                'Invalid identifier format. Expected: entityType/YYYY/MM/DD/uuid.extension'
            );
        }
    }

    /**
     * Ensure a directory exists, creating it if necessary.
     *
     * @param string $directory Directory path
     *
     * @throws StorageException If directory cannot be created
     */
    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            return;
        }

        if (!mkdir($directory, $this->directoryPermissions, true)) {
            throw StorageException::directoryCreateFailed($directory);
        }
    }

    /**
     * Get MIME type for an identifier based on extension.
     *
     * @param string $identifier Media identifier
     *
     * @return string MIME type
     */
    private function getMimeType(string $identifier): string
    {
        $extension = $this->extractExtension($identifier);
        return self::MIME_TYPES[$extension] ?? 'application/octet-stream';
    }
}
