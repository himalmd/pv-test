# Snaply Database Schema

This directory contains the MySQL database schema and migration files for Snaply.

## Quick Start

### Run All Migrations

```bash
mysql -u <username> -p <database_name> < database/migrate.sql
```

### Rollback All Tables

```bash
mysql -u <username> -p <database_name> < database/rollback.sql
```

### Run Individual Migration

```bash
mysql -u <username> -p <database_name> < database/migrations/001_create_projects_table.sql
```

## Entity Relationship Diagram

```
┌─────────────┐
│  projects   │
├─────────────┤
│ id (PK)     │
│ name        │
│ description │
│ status      │
│ created_at  │
│ updated_at  │
│ deleted_at  │──── Soft Delete
└──────┬──────┘
       │ 1:N
       ▼
┌─────────────┐
│    pages    │
├─────────────┤
│ id (PK)     │
│ project_id  │──── FK → projects.id
│ url         │
│ title       │
│ description │
│ created_at  │
│ updated_at  │
│ deleted_at  │──── Soft Delete
└──────┬──────┘
       │ 1:N
       ▼
┌─────────────┐
│  snapshots  │
├─────────────┤
│ id (PK)     │
│ page_id     │──── FK → pages.id
│ version     │
│ created_at  │
│ updated_at  │
│ deleted_at  │──── Soft Delete
└──────┬──────┘
       │ 1:N
       ▼
┌─────────────┐
│  comments   │
├─────────────┤
│ id (PK)     │
│ snapshot_id │──── FK → snapshots.id
│ parent_id   │──── FK → comments.id (self-ref for replies)
│ author_name │
│ author_email│
│ content     │
│ created_at  │
│ updated_at  │
└─────────────┘
```

## Tables Overview

### projects

The root entity containing project metadata.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| name | VARCHAR(255) | Project name |
| description | TEXT | Optional description |
| status | ENUM | 'active', 'archived', 'draft' |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |
| deleted_at | TIMESTAMP | Soft delete marker (NULL = active) |

### pages

Web pages belonging to a project.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| project_id | INT UNSIGNED | Foreign key to projects |
| url | VARCHAR(2048) | The web page URL |
| title | VARCHAR(255) | Page title |
| description | TEXT | Optional description |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |
| deleted_at | TIMESTAMP | Soft delete marker (NULL = active) |

### snapshots

Captured images of web pages with dimension and media information.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| page_id | INT UNSIGNED | Foreign key to pages |
| version | INT UNSIGNED | Version number |
| width_px | INT UNSIGNED | Rendered width in pixels |
| height_px | INT UNSIGNED | Rendered height in pixels |
| media_reference | VARCHAR(512) | Reference to stored media file |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |
| deleted_at | TIMESTAMP | Soft delete marker (NULL = active) |

### comments

Comments attached to snapshots with normalised coordinate positioning.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| snapshot_id | INT UNSIGNED | Foreign key to snapshots |
| parent_id | INT UNSIGNED | Self-referencing FK for replies |
| author_name | VARCHAR(255) | Comment author name |
| author_email | VARCHAR(255) | Optional author email |
| content | TEXT | Comment text |
| x_norm | DECIMAL(10,9) | Normalised X coordinate (0.0 = left, 1.0 = right) |
| y_norm | DECIMAL(10,9) | Normalised Y coordinate (0.0 = top, 1.0 = bottom) |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |

## Indexes

All tables include indexes on:
- Primary keys
- Foreign key columns
- `deleted_at` columns (for soft delete filtering)
- `created_at` columns (for chronological queries)
- Composite indexes for common query patterns

Additional specialised indexes:
- `idx_snapshots_media_reference` - Lookup snapshots by media identifier
- `idx_snapshots_dimensions` - Query snapshots by size
- `idx_comments_coordinates` - Spatial queries on comment positions
- `idx_comments_snapshot_coords` - Find comments by snapshot and position

## Normalised Coordinate System

Comments use normalised coordinates (0.0 to 1.0) rather than pixel coordinates:

- `x_norm = 0.0` → Left edge of the snapshot
- `x_norm = 1.0` → Right edge of the snapshot
- `y_norm = 0.0` → Top edge of the snapshot
- `y_norm = 1.0` → Bottom edge of the snapshot

**Converting to pixel coordinates:**
```
pixel_x = x_norm * display_width
pixel_y = y_norm * display_height
```

**Converting from pixel coordinates:**
```
x_norm = pixel_x / snapshot_width_px
y_norm = pixel_y / snapshot_height_px
```

This approach ensures comment positions remain accurate regardless of how the snapshot is scaled or displayed.

## Media Reference

The `media_reference` field in snapshots stores a flexible identifier for the media file:

- Local filesystem: `snapshots/2024/01/abc123.png`
- UUID format: `550e8400-e29b-41d4-a716-446655440000`
- External storage: `s3://bucket/key` or `wp_attachment_123`

This abstraction allows the storage backend to be changed without schema modifications.

## Soft Delete Pattern

Projects, pages, and snapshots use soft delete via the `deleted_at` column:
- `NULL` = record is active
- Timestamp = record was soft-deleted at that time

Comments do NOT have soft delete - they remain intact but are excluded from queries when their parent snapshot/page/project is soft-deleted.

## Foreign Key Constraints

- `ON DELETE RESTRICT` - Prevents deletion of parent records with children
- `ON UPDATE CASCADE` - Propagates ID changes to child records
- Comment replies use `ON DELETE CASCADE` for parent_id

## Migrations Tracking

The `_migrations` table tracks which migrations have been executed:

```sql
SELECT * FROM _migrations;
```

## Character Set

All tables use `utf8mb4` character set with `utf8mb4_unicode_ci` collation for full Unicode support including emojis.
