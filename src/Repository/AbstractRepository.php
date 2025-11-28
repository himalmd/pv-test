<?php

declare(strict_types=1);

namespace Snaply\Repository;

use Snaply\Database\Connection;
use Snaply\Exception\EntityNotFoundException;

/**
 * Abstract base repository providing common CRUD operations.
 *
 * This class provides:
 * - Soft delete support with transparent filtering
 * - Common query methods (find, findAll, save, delete)
 * - Methods to include or target soft-deleted entities
 */
abstract class AbstractRepository
{
    protected Connection $connection;

    /**
     * Get the table name for this repository.
     */
    abstract protected function getTableName(): string;

    /**
     * Get the entity class name.
     */
    abstract protected function getEntityClass(): string;

    /**
     * Get the primary key column name.
     */
    protected function getPrimaryKey(): string
    {
        return 'id';
    }

    /**
     * Check if this entity supports soft delete.
     */
    protected function supportsSoftDelete(): bool
    {
        return true;
    }

    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Find an entity by ID (excludes soft-deleted by default).
     *
     * @return object|null The entity or null if not found
     */
    public function find(int $id): ?object
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `%s` = ?%s LIMIT 1',
            $this->getTableName(),
            $this->getPrimaryKey(),
            $this->getSoftDeleteCondition()
        );

        $row = $this->connection->fetchOne($sql, [$id]);

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Find an entity by ID, including soft-deleted entities.
     *
     * @return object|null The entity or null if not found
     */
    public function findWithDeleted(int $id): ?object
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `%s` = ? LIMIT 1',
            $this->getTableName(),
            $this->getPrimaryKey()
        );

        $row = $this->connection->fetchOne($sql, [$id]);

        if ($row === null) {
            return null;
        }

        return $this->hydrate($row);
    }

    /**
     * Find an entity by ID or throw an exception.
     *
     * @throws EntityNotFoundException If not found
     */
    public function findOrFail(int $id): object
    {
        $entity = $this->find($id);

        if ($entity === null) {
            throw new EntityNotFoundException($this->getEntityClass(), $id);
        }

        return $entity;
    }

    /**
     * Get all entities (excludes soft-deleted by default).
     *
     * @return object[] Array of entities
     */
    public function findAll(): array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` WHERE 1=1%s ORDER BY `%s` ASC',
            $this->getTableName(),
            $this->getSoftDeleteCondition(),
            $this->getPrimaryKey()
        );

        $rows = $this->connection->fetchAll($sql);

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    /**
     * Get all entities, including soft-deleted ones.
     *
     * @return object[] Array of entities
     */
    public function findAllWithDeleted(): array
    {
        $sql = sprintf(
            'SELECT * FROM `%s` ORDER BY `%s` ASC',
            $this->getTableName(),
            $this->getPrimaryKey()
        );

        $rows = $this->connection->fetchAll($sql);

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    /**
     * Get only soft-deleted entities.
     *
     * @return object[] Array of soft-deleted entities
     */
    public function findOnlyDeleted(): array
    {
        if (!$this->supportsSoftDelete()) {
            return [];
        }

        $sql = sprintf(
            'SELECT * FROM `%s` WHERE `deleted_at` IS NOT NULL ORDER BY `%s` ASC',
            $this->getTableName(),
            $this->getPrimaryKey()
        );

        $rows = $this->connection->fetchAll($sql);

        return array_map(fn($row) => $this->hydrate($row), $rows);
    }

    /**
     * Count all active entities.
     */
    public function count(): int
    {
        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s` WHERE 1=1%s',
            $this->getTableName(),
            $this->getSoftDeleteCondition()
        );

        return (int) $this->connection->fetchColumn($sql);
    }

    /**
     * Count all entities including soft-deleted.
     */
    public function countWithDeleted(): int
    {
        $sql = sprintf(
            'SELECT COUNT(*) FROM `%s`',
            $this->getTableName()
        );

        return (int) $this->connection->fetchColumn($sql);
    }

    /**
     * Soft delete an entity by ID.
     *
     * @return bool True if deleted, false if not found
     */
    public function delete(int $id): bool
    {
        if (!$this->supportsSoftDelete()) {
            return $this->hardDelete($id);
        }

        $sql = sprintf(
            'UPDATE `%s` SET `deleted_at` = NOW() WHERE `%s` = ? AND `deleted_at` IS NULL',
            $this->getTableName(),
            $this->getPrimaryKey()
        );

        return $this->connection->execute($sql, [$id]) > 0;
    }

    /**
     * Permanently delete an entity by ID.
     *
     * @return bool True if deleted, false if not found
     */
    public function hardDelete(int $id): bool
    {
        $sql = sprintf(
            'DELETE FROM `%s` WHERE `%s` = ?',
            $this->getTableName(),
            $this->getPrimaryKey()
        );

        return $this->connection->execute($sql, [$id]) > 0;
    }

    /**
     * Restore a soft-deleted entity.
     *
     * @return bool True if restored, false if not found or not deleted
     */
    public function restore(int $id): bool
    {
        if (!$this->supportsSoftDelete()) {
            return false;
        }

        $sql = sprintf(
            'UPDATE `%s` SET `deleted_at` = NULL WHERE `%s` = ? AND `deleted_at` IS NOT NULL',
            $this->getTableName(),
            $this->getPrimaryKey()
        );

        return $this->connection->execute($sql, [$id]) > 0;
    }

    /**
     * Check if an entity exists (active only).
     */
    public function exists(int $id): bool
    {
        $sql = sprintf(
            'SELECT 1 FROM `%s` WHERE `%s` = ?%s LIMIT 1',
            $this->getTableName(),
            $this->getPrimaryKey(),
            $this->getSoftDeleteCondition()
        );

        return $this->connection->fetchColumn($sql, [$id]) !== null;
    }

    /**
     * Check if an entity exists (including soft-deleted).
     */
    public function existsWithDeleted(int $id): bool
    {
        $sql = sprintf(
            'SELECT 1 FROM `%s` WHERE `%s` = ? LIMIT 1',
            $this->getTableName(),
            $this->getPrimaryKey()
        );

        return $this->connection->fetchColumn($sql, [$id]) !== null;
    }

    /**
     * Get the SQL condition for filtering out soft-deleted entities.
     *
     * @return string SQL condition string (with leading AND) or empty string
     */
    protected function getSoftDeleteCondition(): string
    {
        if (!$this->supportsSoftDelete()) {
            return '';
        }

        return ' AND `deleted_at` IS NULL';
    }

    /**
     * Hydrate a database row into an entity object.
     *
     * @param array<string, mixed> $row Database row
     *
     * @return object The hydrated entity
     */
    protected function hydrate(array $row): object
    {
        $class = $this->getEntityClass();
        return $class::fromRow($row);
    }

    /**
     * Insert a new entity.
     *
     * @param array<string, mixed> $data Column => value pairs
     *
     * @return int The new entity ID
     */
    protected function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_fill(0, count($columns), '?');

        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $this->getTableName(),
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );

        $this->connection->execute($sql, array_values($data));

        return $this->connection->lastInsertId();
    }

    /**
     * Update an existing entity.
     *
     * @param int $id Entity ID
     * @param array<string, mixed> $data Column => value pairs
     *
     * @return bool True if updated
     */
    protected function update(int $id, array $data): bool
    {
        $sets = array_map(fn($col) => "`{$col}` = ?", array_keys($data));

        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE `%s` = ?',
            $this->getTableName(),
            implode(', ', $sets),
            $this->getPrimaryKey()
        );

        $params = array_values($data);
        $params[] = $id;

        return $this->connection->execute($sql, $params) > 0;
    }
}
