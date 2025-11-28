<?php

declare(strict_types=1);

namespace Snaply\Service;

use Snaply\Database\Connection;
use Snaply\Entity\Project;
use Snaply\Exception\EntityNotFoundException;
use Snaply\Exception\ValidationException;
use Snaply\Repository\ProjectRepository;

/**
 * Service layer for Project operations.
 *
 * Orchestrates project creation, retrieval, and management with
 * support for soft delete semantics.
 */
class ProjectService
{
    private Connection $connection;
    private ProjectRepository $projectRepository;

    public function __construct(
        Connection $connection,
        ProjectRepository $projectRepository
    ) {
        $this->connection = $connection;
        $this->projectRepository = $projectRepository;
    }

    /**
     * Create a new project.
     *
     * @param string $name Project name
     * @param string|null $description Optional description
     * @param string $status Initial status (default: active)
     *
     * @return Project The created project
     *
     * @throws ValidationException If validation fails
     */
    public function createProject(
        string $name,
        ?string $description = null,
        string $status = Project::STATUS_ACTIVE
    ): Project {
        $project = new Project(
            id: null,
            name: $name,
            description: $description,
            status: $status
        );

        return $this->projectRepository->save($project);
    }

    /**
     * Update an existing project.
     *
     * @param int $id Project ID
     * @param array<string, mixed> $data Fields to update (name, description, status)
     *
     * @return Project The updated project
     *
     * @throws EntityNotFoundException If project not found
     * @throws ValidationException If validation fails
     */
    public function updateProject(int $id, array $data): Project
    {
        $project = $this->projectRepository->find($id);

        if ($project === null) {
            throw EntityNotFoundException::project($id);
        }

        if (isset($data['name'])) {
            $project->name = $data['name'];
        }

        if (array_key_exists('description', $data)) {
            $project->description = $data['description'];
        }

        if (isset($data['status'])) {
            $project->status = $data['status'];
        }

        return $this->projectRepository->save($project);
    }

    /**
     * Soft delete a project.
     *
     * @param int $id Project ID
     *
     * @return bool True if deleted
     *
     * @throws EntityNotFoundException If project not found
     */
    public function deleteProject(int $id): bool
    {
        if (!$this->projectRepository->exists($id)) {
            throw EntityNotFoundException::project($id);
        }

        return $this->projectRepository->delete($id);
    }

    /**
     * Restore a soft-deleted project.
     *
     * @param int $id Project ID
     *
     * @return bool True if restored
     *
     * @throws EntityNotFoundException If project not found
     */
    public function restoreProject(int $id): bool
    {
        if (!$this->projectRepository->existsWithDeleted($id)) {
            throw EntityNotFoundException::project($id);
        }

        return $this->projectRepository->restore($id);
    }

    /**
     * Permanently delete a project.
     *
     * Warning: This will fail if the project has associated pages
     * due to foreign key constraints.
     *
     * @param int $id Project ID
     *
     * @return bool True if deleted
     */
    public function hardDeleteProject(int $id): bool
    {
        return $this->projectRepository->hardDelete($id);
    }

    /**
     * Get a project by ID (active only).
     *
     * @param int $id Project ID
     *
     * @return Project|null The project or null if not found/deleted
     */
    public function getProject(int $id): ?Project
    {
        return $this->projectRepository->find($id);
    }

    /**
     * Get a project by ID or throw exception.
     *
     * @param int $id Project ID
     *
     * @return Project The project
     *
     * @throws EntityNotFoundException If project not found
     */
    public function getProjectOrFail(int $id): Project
    {
        return $this->projectRepository->findOrFail($id);
    }

    /**
     * Get a project by ID, including soft-deleted (admin/debug).
     *
     * @param int $id Project ID
     *
     * @return Project|null The project or null if not found
     */
    public function getProjectWithDeleted(int $id): ?Project
    {
        return $this->projectRepository->findWithDeleted($id);
    }

    /**
     * List all active projects.
     *
     * @return Project[]
     */
    public function listProjects(): array
    {
        return $this->projectRepository->findAll();
    }

    /**
     * List all projects including soft-deleted (admin/debug).
     *
     * @return Project[]
     */
    public function listProjectsWithDeleted(): array
    {
        return $this->projectRepository->findAllWithDeleted();
    }

    /**
     * List only soft-deleted projects (admin/debug).
     *
     * @return Project[]
     */
    public function listDeletedProjects(): array
    {
        return $this->projectRepository->findOnlyDeleted();
    }

    /**
     * List projects by status.
     *
     * @param string $status Status to filter by
     *
     * @return Project[]
     */
    public function listProjectsByStatus(string $status): array
    {
        return $this->projectRepository->findByStatus($status);
    }

    /**
     * List active projects (status = 'active', not soft-deleted).
     *
     * @return Project[]
     */
    public function listActiveProjects(): array
    {
        return $this->projectRepository->findActive();
    }

    /**
     * Update project status.
     *
     * @param int $id Project ID
     * @param string $status New status
     *
     * @return bool True if updated
     *
     * @throws EntityNotFoundException If project not found
     * @throws ValidationException If status is invalid
     */
    public function updateProjectStatus(int $id, string $status): bool
    {
        if (!$this->projectRepository->exists($id)) {
            throw EntityNotFoundException::project($id);
        }

        return $this->projectRepository->updateStatus($id, $status);
    }

    /**
     * Archive a project (set status to archived).
     *
     * @param int $id Project ID
     *
     * @return bool True if archived
     */
    public function archiveProject(int $id): bool
    {
        return $this->updateProjectStatus($id, Project::STATUS_ARCHIVED);
    }

    /**
     * Count all active projects.
     */
    public function countProjects(): int
    {
        return $this->projectRepository->count();
    }

    /**
     * Count all projects including soft-deleted.
     */
    public function countProjectsWithDeleted(): int
    {
        return $this->projectRepository->countWithDeleted();
    }

    /**
     * Check if a project exists (active only).
     */
    public function projectExists(int $id): bool
    {
        return $this->projectRepository->exists($id);
    }
}
