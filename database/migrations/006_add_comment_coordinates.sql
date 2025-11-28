-- ============================================================================
-- Migration: 006_add_comment_coordinates
-- Description: Adds normalised coordinate fields to the comments table
-- Dependencies: 004_create_comments_table
-- ============================================================================

-- ----------------------------------------------------------------------------
-- UP: Add normalised coordinate columns for comment positioning
-- ----------------------------------------------------------------------------

-- Add x_norm: normalised X coordinate (0.0 = left edge, 1.0 = right edge)
-- Using DECIMAL(10,9) for exact precision - supports sub-pixel accuracy
-- on displays up to 1 billion pixels wide (more than sufficient for any use case)
ALTER TABLE `comments`
    ADD COLUMN `x_norm` DECIMAL(10,9) NULL AFTER `content`;

-- Add y_norm: normalised Y coordinate (0.0 = top edge, 1.0 = bottom edge)
ALTER TABLE `comments`
    ADD COLUMN `y_norm` DECIMAL(10,9) NULL AFTER `x_norm`;

-- Add index on coordinates for spatial queries and reporting
-- Supports queries like "find all comments in a region" or generating heatmaps
ALTER TABLE `comments`
    ADD INDEX `idx_comments_coordinates` (`x_norm`, `y_norm`);

-- Add composite index for snapshot + coordinates
-- Optimises the common query pattern: "get all comments for a snapshot ordered by position"
ALTER TABLE `comments`
    ADD INDEX `idx_comments_snapshot_coords` (`snapshot_id`, `x_norm`, `y_norm`);

-- ----------------------------------------------------------------------------
-- DOWN: Remove the added columns and indexes
-- ----------------------------------------------------------------------------

-- ALTER TABLE `comments` DROP INDEX `idx_comments_snapshot_coords`;
-- ALTER TABLE `comments` DROP INDEX `idx_comments_coordinates`;
-- ALTER TABLE `comments` DROP COLUMN `y_norm`;
-- ALTER TABLE `comments` DROP COLUMN `x_norm`;
