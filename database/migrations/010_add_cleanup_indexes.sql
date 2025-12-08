-- ============================================================================
-- Migration: 010_add_cleanup_indexes
-- Description: Adds composite indexes to optimize cleanup queries
-- ============================================================================

-- ----------------------------------------------------------------------------
-- UP: Add cleanup optimization indexes
-- ----------------------------------------------------------------------------

-- Composite index for cleanup queries on status + updated_at
-- Optimizes: SELECT/DELETE WHERE status IN ('expired', 'abandoned') ORDER BY updated_at
CREATE INDEX `idx_inboxes_status_updated` ON `inboxes` (`status`, `updated_at`);

-- ----------------------------------------------------------------------------
-- DOWN: Drop cleanup indexes
-- ----------------------------------------------------------------------------

-- DROP INDEX `idx_inboxes_status_updated` ON `inboxes`;
