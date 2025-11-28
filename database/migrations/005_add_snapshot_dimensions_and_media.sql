-- ============================================================================
-- Migration: 005_add_snapshot_dimensions_and_media
-- Description: Adds dimension and media reference fields to the snapshots table
-- Dependencies: 003_create_snapshots_table
-- ============================================================================

-- ----------------------------------------------------------------------------
-- UP: Add columns for rendered dimensions and media reference
-- ----------------------------------------------------------------------------

-- Add width_px: the rendered width of the snapshot image in pixels
ALTER TABLE `snapshots`
    ADD COLUMN `width_px` INT UNSIGNED NULL AFTER `version`;

-- Add height_px: the rendered height of the snapshot image in pixels
ALTER TABLE `snapshots`
    ADD COLUMN `height_px` INT UNSIGNED NULL AFTER `width_px`;

-- Add media_reference: identifier pointing to the stored media file
-- This is a flexible string that can hold:
--   - Local filesystem paths (e.g., "snapshots/2024/01/abc123.png")
--   - UUIDs (e.g., "550e8400-e29b-41d4-a716-446655440000")
--   - External storage IDs (e.g., "s3://bucket/key" or "wp_attachment_123")
ALTER TABLE `snapshots`
    ADD COLUMN `media_reference` VARCHAR(512) NULL AFTER `height_px`;

-- Add index on media_reference for lookups by media identifier
ALTER TABLE `snapshots`
    ADD INDEX `idx_snapshots_media_reference` (`media_reference`);

-- Add composite index for dimension-based queries (e.g., finding snapshots by size)
ALTER TABLE `snapshots`
    ADD INDEX `idx_snapshots_dimensions` (`width_px`, `height_px`);

-- ----------------------------------------------------------------------------
-- DOWN: Remove the added columns and indexes
-- ----------------------------------------------------------------------------

-- ALTER TABLE `snapshots` DROP INDEX `idx_snapshots_dimensions`;
-- ALTER TABLE `snapshots` DROP INDEX `idx_snapshots_media_reference`;
-- ALTER TABLE `snapshots` DROP COLUMN `media_reference`;
-- ALTER TABLE `snapshots` DROP COLUMN `height_px`;
-- ALTER TABLE `snapshots` DROP COLUMN `width_px`;
