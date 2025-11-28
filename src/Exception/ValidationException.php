<?php

declare(strict_types=1);

namespace Snaply\Exception;

use Exception;
use Throwable;

/**
 * Exception thrown when validation fails.
 */
class ValidationException extends Exception
{
    /**
     * @var array<string, string[]>
     */
    private array $errors;

    /**
     * @param array<string, string[]> $errors Field-specific error messages
     */
    public function __construct(
        array $errors,
        string $message = 'Validation failed',
        ?Throwable $previous = null
    ) {
        $this->errors = $errors;

        parent::__construct($message, 0, $previous);
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field.
     *
     * @return string[]
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if a specific field has errors.
     */
    public function hasFieldError(string $field): bool
    {
        return isset($this->errors[$field]) && count($this->errors[$field]) > 0;
    }

    /**
     * Create an exception for a single field error.
     */
    public static function forField(string $field, string $message): self
    {
        return new self([$field => [$message]]);
    }

    /**
     * Create an exception for a required field.
     */
    public static function required(string $field): self
    {
        return self::forField($field, sprintf('%s is required', $field));
    }

    /**
     * Create an exception for an invalid parent entity.
     */
    public static function invalidParent(string $parentType, int $parentId): self
    {
        return self::forField(
            strtolower($parentType) . '_id',
            sprintf('%s with ID %d does not exist or has been deleted', $parentType, $parentId)
        );
    }
}
