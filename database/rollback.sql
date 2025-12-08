-- ============================================================================
-- Snaply Database Rollback Script
-- ============================================================================
-- This file drops all tables in reverse order to properly handle foreign keys.
--
-- WARNING: This will permanently delete all data in these tables!
--
-- Usage:
--   mysql -u <username> -p <database_name> < database/rollback.sql
-- ============================================================================

-- Disable foreign key checks temporarily to allow dropping in any order
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- Drop tables in reverse dependency order
-- ============================================================================

-- Drop messages table (depends on inboxes)
DROP TABLE IF EXISTS `messages`;

-- Drop inbox_address_cooldowns table (independent)
DROP TABLE IF EXISTS `inbox_address_cooldowns`;

-- Drop inboxes table (root entity for temp inbox)
DROP TABLE IF EXISTS `inboxes`;

-- Drop comments table (depends on snapshots)
DROP TABLE IF EXISTS `comments`;

-- Drop snapshots table (depends on pages)
DROP TABLE IF EXISTS `snapshots`;

-- Drop pages table (depends on projects)
DROP TABLE IF EXISTS `pages`;

-- Drop projects table (root entity)
DROP TABLE IF EXISTS `projects`;

-- Drop migrations tracking table
DROP TABLE IF EXISTS `_migrations`;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- Rollback Complete
-- ============================================================================

SELECT 'All tables dropped successfully.' AS status;
