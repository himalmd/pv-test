-- ============================================================================
-- Migration: 007_create_inboxes_table
-- Description: Creates the inboxes table for temporary inbox lifecycle management
-- ============================================================================

-- ----------------------------------------------------------------------------
-- UP: Create the inboxes table
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `inboxes` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_token_hash` VARCHAR(64) NOT NULL,
    `email_local_part` VARCHAR(64) NOT NULL,
    `email_domain` VARCHAR(255) NOT NULL,
    `status` ENUM('active', 'abandoned', 'expired', 'deleted') NOT NULL DEFAULT 'active',
    `ttl_minutes` INT UNSIGNED NOT NULL DEFAULT 60,
    `last_accessed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `expired_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_inboxes_session_token` (`session_token_hash`),
    UNIQUE INDEX `idx_inboxes_email` (`email_local_part`, `email_domain`),
    INDEX `idx_inboxes_status` (`status`),
    INDEX `idx_inboxes_deleted_at` (`deleted_at`),
    INDEX `idx_inboxes_last_accessed` (`last_accessed_at`),
    INDEX `idx_inboxes_expired_at` (`expired_at`),
    INDEX `idx_inboxes_status_accessed` (`status`, `last_accessed_at`),
    INDEX `idx_inboxes_status_expired` (`status`, `expired_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- DOWN: Drop the inboxes table
-- ----------------------------------------------------------------------------

-- DROP TABLE IF EXISTS `inboxes`;
