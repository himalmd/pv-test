<?php

declare(strict_types=1);

namespace Snaply\Exception;

use Exception;
use Throwable;

/**
 * Exception thrown when an entity cannot be found.
 */
class EntityNotFoundException extends Exception
{
    private string $entityType;
    private int|string $entityId;

    public function __construct(
        string $entityType,
        int|string $entityId,
        ?Throwable $previous = null
    ) {
        $this->entityType = $entityType;
        $this->entityId = $entityId;

        parent::__construct(
            sprintf('%s with ID "%s" not found', $entityType, $entityId),
            0,
            $previous
        );
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function getEntityId(): int|string
    {
        return $this->entityId;
    }

    public static function project(int $id): self
    {
        return new self('Project', $id);
    }

    public static function page(int $id): self
    {
        return new self('Page', $id);
    }

    public static function snapshot(int $id): self
    {
        return new self('Snapshot', $id);
    }

    public static function comment(int $id): self
    {
        return new self('Comment', $id);
    }

    public static function inbox(int|string $id): self
    {
        return new self('Inbox', $id);
    }

    public static function message(int $id): self
    {
        return new self('Message', $id);
    }
}
