<?php

declare(strict_types=1);

namespace Snaply\Repository;

use Snaply\Entity\Project;
use Snaply\Exception\ValidationException;

/**
 * Repository for Project entities.
 */
class ProjectRepository extends AbstractRepository
{
    protected function getTableName(): string
    {
        return 'projects';
    }

    protected function getEntityClass(): string
    {
        return Project::class;
    }

    /**
     * Find a project by ID.
     */
    public function find(int $id): ?Project
    {
        /** @var Project|null */
        return parent::find($id);
    }

    /**
     * Find a project by ID, including soft-deleted.
     */
    public function findWithDeleted(int $id): ?Project
    {
        /** @var Project|null */
        return parent::findWithDeleted($id);
    }

    /**
     * Find a project by ID or throw.
     *
     * @throws \Snaply\Exception\EntityNotFoundException
     */
    public function findOrFail(int $id): Project
    {
        /** @var Project */
        return parent::findOrFail($id);
    }

    /**
     * Get all active projects.
     *
     * @return Project[]
     */
    public function findAll(): array
    {
        /** @var Project[] */
        return parent::findAll();
    }

    /**
     * Get all projects including soft-deleted.
     *
     * @return Project[]
     */
    public function findAllWithDeleted(): array
    {
        /** @var Project[] */
        return parent::findAllWithDeleted();
    }

    /**
     * Find projects by status.
     *
     * @return Project[]
     */
    public function findByStatus(string $status): array
    {
        $sql = 'SELECT * FROM `projects` WHERE `status` = ? AND `deleted_at` IS NULL ORDER BY `id` ASC';
        $rows = $this->connection->fetchAll($sql, [$status]);

        return array_map(fn($row) => Project::fromRow($row), $rows);
    }

    /**
     * Find active projects (status = 'active', not soft-deleted).
     *
     * @return Project[]
     */
    public function findActive(): array
    {
        return $this->findByStatus(Project::STATUS_ACTIVE);
    }

    /**
     * Save a project (insert or update).
     *
     * @return Project The saved project with ID populated
     *
     * @throws ValidationException If validation fails
     */
    public function save(Project $project): Project
    {
        $this->validate($project);

        $data = [
            'name' => $project->name,
            'description' => $project->description,
            'status' => $project->status,
        ];

        if ($project->id === null) {
            $project->id = $this->insert($data);
        } else {
            $this->update($project->id, $data);
        }

        // Reload to get timestamps
        return $this->findWithDeleted($project->id) ?? $project;
    }

    /**
     * Update project status.
     */
    public function updateStatus(int $id, string $status): bool
    {
        if (!in_array($status, Project::getValidStatuses(), true)) {
            throw ValidationException::forField('status', 'Invalid status value');
        }

        $sql = 'UPDATE `projects` SET `status` = ? WHERE `id` = ? AND `deleted_at` IS NULL';

        return $this->connection->execute($sql, [$status, $id]) > 0;
    }

    /**
     * Validate a project entity.
     *
     * @throws ValidationException If validation fails
     */
    private function validate(Project $project): void
    {
        $errors = [];

        if (empty(trim($project->name))) {
            $errors['name'] = ['Name is required'];
        } elseif (strlen($project->name) > 255) {
            $errors['name'] = ['Name must be 255 characters or less'];
        }

        if (!in_array($project->status, Project::getValidStatuses(), true)) {
            $errors['status'] = ['Invalid status value'];
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
