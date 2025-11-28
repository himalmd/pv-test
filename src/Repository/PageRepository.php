<?php

declare(strict_types=1);

namespace Snaply\Repository;

use Snaply\Entity\Page;
use Snaply\Exception\ValidationException;

/**
 * Repository for Page entities.
 */
class PageRepository extends AbstractRepository
{
    private ProjectRepository $projectRepository;

    public function __construct(
        \Snaply\Database\Connection $connection,
        ProjectRepository $projectRepository
    ) {
        parent::__construct($connection);
        $this->projectRepository = $projectRepository;
    }

    protected function getTableName(): string
    {
        return 'pages';
    }

    protected function getEntityClass(): string
    {
        return Page::class;
    }

    /**
     * Find a page by ID.
     */
    public function find(int $id): ?Page
    {
        /** @var Page|null */
        return parent::find($id);
    }

    /**
     * Find a page by ID, including soft-deleted.
     */
    public function findWithDeleted(int $id): ?Page
    {
        /** @var Page|null */
        return parent::findWithDeleted($id);
    }

    /**
     * Find a page by ID or throw.
     *
     * @throws \Snaply\Exception\EntityNotFoundException
     */
    public function findOrFail(int $id): Page
    {
        /** @var Page */
        return parent::findOrFail($id);
    }

    /**
     * Get all active pages.
     *
     * @return Page[]
     */
    public function findAll(): array
    {
        /** @var Page[] */
        return parent::findAll();
    }

    /**
     * Find pages by project ID (active pages only, active project only).
     *
     * @return Page[]
     */
    public function findByProjectId(int $projectId): array
    {
        $sql = 'SELECT p.* FROM `pages` p
                INNER JOIN `projects` pr ON p.project_id = pr.id
                WHERE p.project_id = ?
                  AND p.deleted_at IS NULL
                  AND pr.deleted_at IS NULL
                ORDER BY p.id ASC';

        $rows = $this->connection->fetchAll($sql, [$projectId]);

        return array_map(fn($row) => Page::fromRow($row), $rows);
    }

    /**
     * Find pages by project ID, including soft-deleted pages.
     * Still requires the project to exist (but can be deleted).
     *
     * @return Page[]
     */
    public function findByProjectIdWithDeleted(int $projectId): array
    {
        $sql = 'SELECT * FROM `pages` WHERE `project_id` = ? ORDER BY `id` ASC';
        $rows = $this->connection->fetchAll($sql, [$projectId]);

        return array_map(fn($row) => Page::fromRow($row), $rows);
    }

    /**
     * Find pages by URL pattern (active pages only).
     *
     * @return Page[]
     */
    public function findByUrlLike(string $urlPattern): array
    {
        $sql = 'SELECT p.* FROM `pages` p
                INNER JOIN `projects` pr ON p.project_id = pr.id
                WHERE p.url LIKE ?
                  AND p.deleted_at IS NULL
                  AND pr.deleted_at IS NULL
                ORDER BY p.id ASC';

        $rows = $this->connection->fetchAll($sql, ['%' . $urlPattern . '%']);

        return array_map(fn($row) => Page::fromRow($row), $rows);
    }

    /**
     * Count pages in a project (active only).
     */
    public function countByProjectId(int $projectId): int
    {
        $sql = 'SELECT COUNT(*) FROM `pages` p
                INNER JOIN `projects` pr ON p.project_id = pr.id
                WHERE p.project_id = ?
                  AND p.deleted_at IS NULL
                  AND pr.deleted_at IS NULL';

        return (int) $this->connection->fetchColumn($sql, [$projectId]);
    }

    /**
     * Save a page (insert or update).
     *
     * @return Page The saved page with ID populated
     *
     * @throws ValidationException If validation fails
     */
    public function save(Page $page): Page
    {
        $this->validate($page);

        $data = [
            'project_id' => $page->projectId,
            'url' => $page->url,
            'title' => $page->title,
            'description' => $page->description,
        ];

        if ($page->id === null) {
            $page->id = $this->insert($data);
        } else {
            $this->update($page->id, $data);
        }

        // Reload to get timestamps
        return $this->findWithDeleted($page->id) ?? $page;
    }

    /**
     * Validate a page entity.
     *
     * @throws ValidationException If validation fails
     */
    private function validate(Page $page): void
    {
        $errors = [];

        // Validate project exists and is not soft-deleted
        if ($page->projectId <= 0) {
            $errors['project_id'] = ['Project ID is required'];
        } elseif (!$this->projectRepository->exists($page->projectId)) {
            $errors['project_id'] = ['Project does not exist or has been deleted'];
        }

        if (empty(trim($page->url))) {
            $errors['url'] = ['URL is required'];
        } elseif (strlen($page->url) > 2048) {
            $errors['url'] = ['URL must be 2048 characters or less'];
        } elseif (!filter_var($page->url, FILTER_VALIDATE_URL)) {
            $errors['url'] = ['URL must be a valid URL'];
        }

        if ($page->title !== null && strlen($page->title) > 255) {
            $errors['title'] = ['Title must be 255 characters or less'];
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }
}
