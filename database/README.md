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

Captured images of web pages.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| page_id | INT UNSIGNED | Foreign key to pages |
| version | INT UNSIGNED | Version number |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |
| deleted_at | TIMESTAMP | Soft delete marker (NULL = active) |

### comments

Comments attached to snapshots with coordinate positioning.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| snapshot_id | INT UNSIGNED | Foreign key to snapshots |
| parent_id | INT UNSIGNED | Self-referencing FK for replies |
| author_name | VARCHAR(255) | Comment author name |
| author_email | VARCHAR(255) | Optional author email |
| content | TEXT | Comment text |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |

## Indexes

All tables include indexes on:
- Primary keys
- Foreign key columns
- `deleted_at` columns (for soft delete filtering)
- `created_at` columns (for chronological queries)
- Composite indexes for common query patterns

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
