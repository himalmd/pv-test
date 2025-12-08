-- ============================================================================
-- Migration: 009_create_inbox_address_cooldowns_table
-- Description: Creates the inbox_address_cooldowns table to prevent address reuse
-- ============================================================================

-- ----------------------------------------------------------------------------
-- UP: Create the inbox_address_cooldowns table
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `inbox_address_cooldowns` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `email_local_part` VARCHAR(64) NOT NULL,
    `email_domain` VARCHAR(255) NOT NULL,
    `last_used_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `cooldown_until` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE INDEX `idx_cooldowns_email` (`email_local_part`, `email_domain`),
    INDEX `idx_cooldowns_cooldown_until` (`cooldown_until`),
    INDEX `idx_cooldowns_last_used` (`last_used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- DOWN: Drop the inbox_address_cooldowns table
-- ----------------------------------------------------------------------------

-- DROP TABLE IF EXISTS `inbox_address_cooldowns`;
