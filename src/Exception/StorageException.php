<?php

declare(strict_types=1);

namespace Snaply\Exception;

use Exception;
use Throwable;

/**
 * Exception thrown when a storage operation fails.
 *
 * This exception covers various storage-related failures including:
 * - File not found
 * - Permission denied
 * - Upload failures
 * - Invalid file types
 * - Configuration errors
 */
class StorageException extends Exception
{
    public const CODE_FILE_NOT_FOUND = 1001;
    public const CODE_PERMISSION_DENIED = 1002;
    public const CODE_UPLOAD_FAILED = 1003;
    public const CODE_INVALID_FILE = 1004;
    public const CODE_DIRECTORY_CREATE_FAILED = 1005;
    public const CODE_DELETE_FAILED = 1006;
    public const CODE_INVALID_IDENTIFIER = 1007;
    public const CODE_CONFIGURATION_ERROR = 1008;

    private ?string $identifier;
    private ?string $filePath;

    public function __construct(
        string $message,
        int $code = 0,
        ?Throwable $previous = null,
        ?string $identifier = null,
        ?string $filePath = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->identifier = $identifier;
        $this->filePath = $filePath;
    }

    /**
     * Get the media identifier associated with this exception, if any.
     */
    public function getIdentifier(): ?string
    {
        return $this->identifier;
    }

    /**
     * Get the file path associated with this exception, if any.
     */
    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    /**
     * Create an exception for a file not found error.
     */
    public static function fileNotFound(string $identifier): self
    {
        return new self(
            sprintf('Media file not found: %s', $identifier),
            self::CODE_FILE_NOT_FOUND,
            null,
            $identifier
        );
    }

    /**
     * Create an exception for a permission denied error.
     */
    public static function permissionDenied(string $path, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Permission denied for path: %s', $path),
            self::CODE_PERMISSION_DENIED,
            $previous,
            null,
            $path
        );
    }

    /**
     * Create an exception for an upload failure.
     */
    public static function uploadFailed(string $reason, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Upload failed: %s', $reason),
            self::CODE_UPLOAD_FAILED,
            $previous
        );
    }

    /**
     * Create an exception for an invalid file.
     */
    public static function invalidFile(string $reason): self
    {
        return new self(
            sprintf('Invalid file: %s', $reason),
            self::CODE_INVALID_FILE
        );
    }

    /**
     * Create an exception for a directory creation failure.
     */
    public static function directoryCreateFailed(string $path, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Failed to create directory: %s', $path),
            self::CODE_DIRECTORY_CREATE_FAILED,
            $previous,
            null,
            $path
        );
    }

    /**
     * Create an exception for a delete failure.
     */
    public static function deleteFailed(string $identifier, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Failed to delete media: %s', $identifier),
            self::CODE_DELETE_FAILED,
            $previous,
            $identifier
        );
    }

    /**
     * Create an exception for an invalid identifier.
     */
    public static function invalidIdentifier(string $identifier, string $reason): self
    {
        return new self(
            sprintf('Invalid media identifier "%s": %s', $identifier, $reason),
            self::CODE_INVALID_IDENTIFIER,
            null,
            $identifier
        );
    }

    /**
     * Create an exception for a configuration error.
     */
    public static function configurationError(string $message): self
    {
        return new self(
            sprintf('Storage configuration error: %s', $message),
            self::CODE_CONFIGURATION_ERROR
        );
    }
}
