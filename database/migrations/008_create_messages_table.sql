-- ============================================================================
-- Migration: 008_create_messages_table
-- Description: Creates the messages table for storing emails received by inboxes
-- Dependencies: 007_create_inboxes_table
-- ============================================================================

-- ----------------------------------------------------------------------------
-- UP: Create the messages table
-- ----------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS `messages` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `inbox_id` INT UNSIGNED NOT NULL,
    `message_id` VARCHAR(255) NULL,
    `from_address` VARCHAR(255) NOT NULL,
    `from_name` VARCHAR(255) NULL,
    `subject` VARCHAR(998) NULL,
    `body_text` MEDIUMTEXT NULL,
    `body_html` MEDIUMTEXT NULL,
    `received_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `deleted_at` TIMESTAMP NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    INDEX `idx_messages_inbox_id` (`inbox_id`),
    INDEX `idx_messages_deleted_at` (`deleted_at`),
    INDEX `idx_messages_received_at` (`received_at`),
    INDEX `idx_messages_inbox_received` (`inbox_id`, `received_at`),
    INDEX `idx_messages_inbox_deleted` (`inbox_id`, `deleted_at`),

    CONSTRAINT `fk_messages_inbox_id`
        FOREIGN KEY (`inbox_id`)
        REFERENCES `inboxes` (`id`)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- DOWN: Drop the messages table
-- ----------------------------------------------------------------------------

-- DROP TABLE IF EXISTS `messages`;
