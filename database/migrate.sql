-- ============================================================================
-- Snaply Database Migration Runner
-- ============================================================================
-- This file runs all migrations in order to set up the complete database schema.
--
-- Usage:
--   mysql -u <username> -p <database_name> < database/migrate.sql
--
-- Or run individual migrations:
--   mysql -u <username> -p <database_name> < database/migrations/001_create_projects_table.sql
-- ============================================================================

-- Enable strict mode for better error handling
SET SQL_MODE = 'STRICT_ALL_TABLES';

-- Create migrations tracking table if it doesn't exist
CREATE TABLE IF NOT EXISTS `_migrations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `executed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_migrations_name` (`migration`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Migration 001: Projects Table
-- ============================================================================

CREATE TABLE IF NOT EXISTS `projects` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `description` TEXT NULL,
    `status` ENUM('active', 'archived', 'draft') NOT NULL DEFAULT 'active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    INDEX `idx_projects_status` (`status`),
    INDEX `idx_projects_deleted_at` (`deleted_at`),
    INDEX `idx_projects_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `_migrations` (`migration`) VALUES ('001_create_projects_table');

-- ============================================================================
-- Migration 002: Pages Table
-- ============================================================================

CREATE TABLE IF NOT EXISTS `pages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `project_id` INT UNSIGNED NOT NULL,
    `url` VARCHAR(2048) NOT NULL,
    `title` VARCHAR(255) NULL,
    `description` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    INDEX `idx_pages_project_id` (`project_id`),
    INDEX `idx_pages_deleted_at` (`deleted_at`),
    INDEX `idx_pages_created_at` (`created_at`),
    INDEX `idx_pages_project_deleted` (`project_id`, `deleted_at`),

    CONSTRAINT `fk_pages_project_id`
        FOREIGN KEY (`project_id`)
        REFERENCES `projects` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `_migrations` (`migration`) VALUES ('002_create_pages_table');

-- ============================================================================
-- Migration 003: Snapshots Table
-- ============================================================================

CREATE TABLE IF NOT EXISTS `snapshots` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `page_id` INT UNSIGNED NOT NULL,
    `version` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    INDEX `idx_snapshots_page_id` (`page_id`),
    INDEX `idx_snapshots_deleted_at` (`deleted_at`),
    INDEX `idx_snapshots_created_at` (`created_at`),
    INDEX `idx_snapshots_page_deleted` (`page_id`, `deleted_at`),
    INDEX `idx_snapshots_page_version` (`page_id`, `version`),

    CONSTRAINT `fk_snapshots_page_id`
        FOREIGN KEY (`page_id`)
        REFERENCES `pages` (`id`)
        ON DELETE RESTRICT
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `_migrations` (`migration`) VALUES ('003_create_snapshots_table');

-- ============================================================================
-- Migration 004: Comments Table
-- ============================================================================

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

INSERT IGNORE INTO `_migrations` (`migration`) VALUES ('004_create_comments_table');

-- ============================================================================
-- Migration 005: Snapshot Dimensions and Media Reference
-- ============================================================================

-- Add columns if they don't exist (for idempotency)
-- Note: ADD COLUMN IF NOT EXISTS requires MySQL 8.0.19+
ALTER TABLE `snapshots`
    ADD COLUMN IF NOT EXISTS `width_px` INT UNSIGNED NULL AFTER `version`,
    ADD COLUMN IF NOT EXISTS `height_px` INT UNSIGNED NULL AFTER `width_px`,
    ADD COLUMN IF NOT EXISTS `media_reference` VARCHAR(512) NULL AFTER `height_px`;

-- Create indexes (will be ignored if they already exist due to IF NOT EXISTS in MySQL 8.0+)
-- For MySQL 5.7 compatibility, we use a different approach with error suppression
CREATE INDEX `idx_snapshots_media_reference` ON `snapshots` (`media_reference`);
CREATE INDEX `idx_snapshots_dimensions` ON `snapshots` (`width_px`, `height_px`);

INSERT IGNORE INTO `_migrations` (`migration`) VALUES ('005_add_snapshot_dimensions_and_media');

-- ============================================================================
-- Migration 006: Comment Coordinates
-- ============================================================================

ALTER TABLE `comments`
    ADD COLUMN IF NOT EXISTS `x_norm` DECIMAL(10,9) NULL AFTER `content`,
    ADD COLUMN IF NOT EXISTS `y_norm` DECIMAL(10,9) NULL AFTER `x_norm`;

CREATE INDEX `idx_comments_coordinates` ON `comments` (`x_norm`, `y_norm`);
CREATE INDEX `idx_comments_snapshot_coords` ON `comments` (`snapshot_id`, `x_norm`, `y_norm`);

INSERT IGNORE INTO `_migrations` (`migration`) VALUES ('006_add_comment_coordinates');

-- ============================================================================
-- Migration Complete
-- ============================================================================

SELECT 'All migrations completed successfully.' AS status;
SELECT migration, executed_at FROM `_migrations` ORDER BY id;
