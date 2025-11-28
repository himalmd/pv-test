-- ============================================================================
-- Migration: 004_create_comments_table
-- Description: Creates the comments table - each comment references a snapshot
-- Dependencies: 003_create_snapshots_table
-- Note: Normalised coordinate fields (x_norm, y_norm) will be added in migration 006
-- ============================================================================

-- ----------------------------------------------------------------------------
-- UP: Create the comments table
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `comments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `snapshot_id` INT UNSIGNED NOT NULL,
    `parent_id` INT UNSIGNED NULL,
    `author_name` VARCHAR(255) NOT NULL,
    `author_email` VARCHAR(255) NULL,
    `content` TEXT NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    INDEX `idx_comments_snapshot_id` (`snapshot_id`),
    INDEX `idx_comments_parent_id` (`parent_id`),
    INDEX `idx_comments_created_at` (`created_at`),
    INDEX `idx_comments_snapshot_created` (`snapshot_id`, `created_at`),

    CONSTRAINT `fk_comments_snapshot_id`
        FOREIGN KEY (`snapshot_id`)
        REFERENCES `snapshots` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT `fk_comments_parent_id`
        FOREIGN KEY (`parent_id`)
        REFERENCES `comments` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- DOWN: Drop the comments table
-- ----------------------------------------------------------------------------

-- DROP TABLE IF EXISTS `comments`;
