<?php

declare(strict_types=1);

namespace Snaply\Service;

use Snaply\Database\Connection;
use Snaply\Entity\Page;
use Snaply\Exception\EntityNotFoundException;
use Snaply\Exception\ValidationException;
use Snaply\Repository\PageRepository;
use Snaply\Repository\ProjectRepository;

/**
 * Service layer for Page operations.
 *
 * Orchestrates page creation, retrieval, and management with
 * project relationship validation and soft delete semantics.
 */
class PageService
{
    private Connection $connection;
    private PageRepository $pageRepository;
    private ProjectRepository $projectRepository;

    public function __construct(
        Connection $connection,
        PageRepository $pageRepository,
        ProjectRepository $projectRepository
    ) {
        $this->connection = $connection;
        $this->pageRepository = $pageRepository;
        $this->projectRepository = $projectRepository;
    }

    /**
     * Create a new page in a project.
     *
     * @param int $projectId Project to add page to
     * @param string $url Page URL
     * @param string|null $title Optional page title
     * @param string|null $description Optional description
     *
     * @return Page The created page
     *
     * @throws EntityNotFoundException If project not found
     * @throws ValidationException If validation fails
     */
    public function createPage(
        int $projectId,
        string $url,
        ?string $title = null,
        ?string $description = null
    ): Page {
        // Validate project exists and is active
        if (!$this->projectRepository->exists($projectId)) {
            throw ValidationException::invalidParent('Project', $projectId);
        }

        $page = new Page(
            id: null,
            projectId: $projectId,
            url: $url,
            title: $title,
            description: $description
        );

        return $this->pageRepository->save($page);
    }

    /**
     * Update an existing page.
     *
     * @param int $id Page ID
     * @param array<string, mixed> $data Fields to update (url, title, description)
     *
     * @return Page The updated page
     *
     * @throws EntityNotFoundException If page not found
     * @throws ValidationException If validation fails
     */
    public function updatePage(int $id, array $data): Page
    {
        $page = $this->pageRepository->find($id);

        if ($page === null) {
            throw EntityNotFoundException::page($id);
        }

        if (isset($data['url'])) {
            $page->url = $data['url'];
        }

        if (array_key_exists('title', $data)) {
            $page->title = $data['title'];
        }

        if (array_key_exists('description', $data)) {
            $page->description = $data['description'];
        }

        return $this->pageRepository->save($page);
    }

    /**
     * Soft delete a page.
     *
     * @param int $id Page ID
     *
     * @return bool True if deleted
     *
     * @throws EntityNotFoundException If page not found
     */
    public function deletePage(int $id): bool
    {
        if (!$this->pageRepository->exists($id)) {
            throw EntityNotFoundException::page($id);
        }

        return $this->pageRepository->delete($id);
    }

    /**
     * Restore a soft-deleted page.
     *
     * @param int $id Page ID
     *
     * @return bool True if restored
     *
     * @throws EntityNotFoundException If page not found
     */
    public function restorePage(int $id): bool
    {
        if (!$this->pageRepository->existsWithDeleted($id)) {
            throw EntityNotFoundException::page($id);
        }

        return $this->pageRepository->restore($id);
    }

    /**
     * Permanently delete a page.
     *
     * Warning: This will fail if the page has associated snapshots
     * due to foreign key constraints.
     *
     * @param int $id Page ID
     *
     * @return bool True if deleted
     */
    public function hardDeletePage(int $id): bool
    {
        return $this->pageRepository->hardDelete($id);
    }

    /**
     * Get a page by ID (active only).
     *
     * @param int $id Page ID
     *
     * @return Page|null The page or null if not found/deleted
     */
    public function getPage(int $id): ?Page
    {
        return $this->pageRepository->find($id);
    }

    /**
     * Get a page by ID or throw exception.
     *
     * @param int $id Page ID
     *
     * @return Page The page
     *
     * @throws EntityNotFoundException If page not found
     */
    public function getPageOrFail(int $id): Page
    {
        return $this->pageRepository->findOrFail($id);
    }

    /**
     * Get a page by ID, including soft-deleted (admin/debug).
     *
     * @param int $id Page ID
     *
     * @return Page|null The page or null if not found
     */
    public function getPageWithDeleted(int $id): ?Page
    {
        return $this->pageRepository->findWithDeleted($id);
    }

    /**
     * List all active pages.
     *
     * @return Page[]
     */
    public function listPages(): array
    {
        return $this->pageRepository->findAll();
    }

    /**
     * List pages for a project (active pages, active project).
     *
     * @param int $projectId Project ID
     *
     * @return Page[]
     */
    public function listPagesByProject(int $projectId): array
    {
        return $this->pageRepository->findByProjectId($projectId);
    }

    /**
     * List pages for a project including soft-deleted (admin/debug).
     *
     * @param int $projectId Project ID
     *
     * @return Page[]
     */
    public function listPagesByProjectWithDeleted(int $projectId): array
    {
        return $this->pageRepository->findByProjectIdWithDeleted($projectId);
    }

    /**
     * List all pages including soft-deleted (admin/debug).
     *
     * @return Page[]
     */
    public function listPagesWithDeleted(): array
    {
        return $this->pageRepository->findAllWithDeleted();
    }

    /**
     * List only soft-deleted pages (admin/debug).
     *
     * @return Page[]
     */
    public function listDeletedPages(): array
    {
        return $this->pageRepository->findOnlyDeleted();
    }

    /**
     * Search pages by URL pattern.
     *
     * @param string $urlPattern URL pattern to search for
     *
     * @return Page[]
     */
    public function searchPagesByUrl(string $urlPattern): array
    {
        return $this->pageRepository->findByUrlLike($urlPattern);
    }

    /**
     * Count pages in a project (active only).
     */
    public function countPagesByProject(int $projectId): int
    {
        return $this->pageRepository->countByProjectId($projectId);
    }

    /**
     * Count all active pages.
     */
    public function countPages(): int
    {
        return $this->pageRepository->count();
    }

    /**
     * Count all pages including soft-deleted.
     */
    public function countPagesWithDeleted(): int
    {
        return $this->pageRepository->countWithDeleted();
    }

    /**
     * Check if a page exists (active only).
     */
    public function pageExists(int $id): bool
    {
        return $this->pageRepository->exists($id);
    }

    /**
     * Get the project that owns a page.
     *
     * @param int $pageId Page ID
     *
     * @return \Snaply\Entity\Project|null The owning project
     */
    public function getPageProject(int $pageId): ?\Snaply\Entity\Project
    {
        $page = $this->pageRepository->find($pageId);

        if ($page === null) {
            return null;
        }

        return $this->projectRepository->find($page->projectId);
    }
}
