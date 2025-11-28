-- ============================================================================
-- Migration: 003_create_snapshots_table
-- Description: Creates the snapshots table - each snapshot belongs to a page
-- Dependencies: 002_create_pages_table
-- Note: Width, height, and media reference fields will be added in a subsequent migration
-- ============================================================================

-- ----------------------------------------------------------------------------
-- UP: Create the snapshots table
-- ----------------------------------------------------------------------------

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

-- ----------------------------------------------------------------------------
-- DOWN: Drop the snapshots table
-- ----------------------------------------------------------------------------

-- DROP TABLE IF EXISTS `snapshots`;
