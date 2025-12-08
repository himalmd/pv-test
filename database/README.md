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

### Snaply Entities (Project Management)

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

### TempInbox Entities (Temporary Email)

```
┌──────────────────────┐
│       inboxes        │
├──────────────────────┤
│ id (PK)              │
│ session_token_hash   │
│ email_local_part     │
│ email_domain         │
│ status               │
│ ttl_minutes          │
│ last_accessed_at     │
│ expired_at           │
│ created_at           │
│ updated_at           │
│ deleted_at           │──── Soft Delete
└──────────┬───────────┘
           │ 1:N
           ▼
┌──────────────────────┐
│      messages        │
├──────────────────────┤
│ id (PK)              │
│ inbox_id             │──── FK → inboxes.id (CASCADE)
│ message_id           │
│ from_address         │
│ from_name            │
│ subject              │
│ body_text            │
│ body_html            │
│ received_at          │
│ created_at           │
│ updated_at           │
│ deleted_at           │──── Soft Delete
└──────────────────────┘

┌──────────────────────────┐
│ inbox_address_cooldowns  │
├──────────────────────────┤
│ id (PK)                  │
│ email_local_part         │
│ email_domain             │
│ last_used_at             │
│ cooldown_until           │
│ created_at               │
└──────────────────────────┘
(Independent - tracks address reuse prevention)
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

### inboxes

Temporary email inboxes with session-based lifecycle management.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| session_token_hash | VARCHAR(64) | SHA-256 hash of session token (privacy) |
| email_local_part | VARCHAR(64) | Local part of email address (before @) |
| email_domain | VARCHAR(255) | Domain part of email address |
| status | ENUM | 'active', 'abandoned', 'expired', 'deleted' |
| ttl_minutes | INT UNSIGNED | Time-to-live in minutes (default: 60) |
| last_accessed_at | TIMESTAMP | Last activity timestamp for TTL calculation |
| expired_at | TIMESTAMP | When inbox was marked as expired (NULL = not expired) |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |
| deleted_at | TIMESTAMP | Soft delete marker (NULL = active) |

### messages

Email messages received by temporary inboxes.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| inbox_id | INT UNSIGNED | Foreign key to inboxes (CASCADE delete) |
| message_id | VARCHAR(255) | Email Message-ID header |
| from_address | VARCHAR(255) | Sender email address |
| from_name | VARCHAR(255) | Sender display name |
| subject | VARCHAR(998) | Email subject line (RFC 2822 max length) |
| body_text | MEDIUMTEXT | Plain text email body |
| body_html | MEDIUMTEXT | HTML email body |
| received_at | TIMESTAMP | When message was received |
| created_at | TIMESTAMP | Creation timestamp |
| updated_at | TIMESTAMP | Last update timestamp |
| deleted_at | TIMESTAMP | Soft delete marker (NULL = active) |

### inbox_address_cooldowns

Tracks recently used email addresses to prevent immediate reuse.

| Column | Type | Description |
|--------|------|-------------|
| id | INT UNSIGNED | Primary key |
| email_local_part | VARCHAR(64) | Local part of email address |
| email_domain | VARCHAR(255) | Domain part of email address |
| last_used_at | TIMESTAMP | When address was last used |
| cooldown_until | TIMESTAMP | Address available for reuse after this time |
| created_at | TIMESTAMP | Creation timestamp |

## Indexes

All tables include indexes on:
- Primary keys
- Foreign key columns
- `deleted_at` columns (for soft delete filtering)
- `created_at` columns (for chronological queries)
- Composite indexes for common query patterns

### Snaply Specialized Indexes
- `idx_snapshots_media_reference` - Lookup snapshots by media identifier
- `idx_snapshots_dimensions` - Query snapshots by size
- `idx_comments_coordinates` - Spatial queries on comment positions
- `idx_comments_snapshot_coords` - Find comments by snapshot and position

### TempInbox Specialized Indexes

**inboxes table:**
- `idx_inboxes_session_token` (UNIQUE) - One active inbox per session
- `idx_inboxes_email` (UNIQUE) - Email address uniqueness
- `idx_inboxes_status` - Filter by lifecycle status
- `idx_inboxes_last_accessed` - TTL expiry calculations
- `idx_inboxes_expired_at` - Cleanup of expired inboxes
- `idx_inboxes_status_accessed` - Composite for TTL cleanup queries
- `idx_inboxes_status_expired` - Composite for expired inbox cleanup

**messages table:**
- `idx_messages_inbox_id` - Lookup messages by inbox
- `idx_messages_received_at` - Chronological ordering
- `idx_messages_inbox_received` - Inbox messages by date
- `idx_messages_inbox_deleted` - Active messages per inbox

**inbox_address_cooldowns table:**
- `idx_cooldowns_email` (UNIQUE) - Address uniqueness in cooldown tracking
- `idx_cooldowns_cooldown_until` - Cleanup of expired cooldowns
- `idx_cooldowns_last_used` - Historical tracking

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

**Snaply entities:** Projects, pages, and snapshots use soft delete via the `deleted_at` column:
- `NULL` = record is active
- Timestamp = record was soft-deleted at that time

Comments do NOT have soft delete - they remain intact but are excluded from queries when their parent snapshot/page/project is soft-deleted.

**TempInbox entities:** Inboxes and messages use soft delete via the `deleted_at` column:
- `NULL` = record is active
- Timestamp = record was soft-deleted at that time
- Soft-deleted records are eligible for hard deletion by cleanup processes

Address cooldowns do NOT have soft delete - they are hard-deleted once the cooldown period expires.

## Inbox Lifecycle States

Inboxes progress through distinct lifecycle states:

1. **active** - Currently in use, accepting messages
2. **abandoned** - User rotated to a new inbox
3. **expired** - TTL exceeded (last_accessed_at + ttl_minutes)
4. **deleted** - Soft-deleted, pending hard deletion

Status transitions:
- active → abandoned (user rotates to new address)
- active → expired (TTL exceeded)
- active → deleted (user deletes now)
- abandoned/expired → deleted (by cleanup process)
- deleted → hard-deleted (by cleanup process)

## Foreign Key Constraints

**Snaply constraints:**
- `ON DELETE RESTRICT` - Prevents deletion of parent records with children
- `ON UPDATE CASCADE` - Propagates ID changes to child records
- Comment replies use `ON DELETE CASCADE` for parent_id

**TempInbox constraints:**
- Messages use `ON DELETE CASCADE` for inbox_id - when an inbox is hard-deleted, all messages are automatically removed
- `ON UPDATE CASCADE` - Propagates ID changes to child records

## Migrations Tracking

The `_migrations` table tracks which migrations have been executed:

```sql
SELECT * FROM _migrations;
```

## Character Set

All tables use `utf8mb4` character set with `utf8mb4_unicode_ci` collation for full Unicode support including emojis.

## Address Cooldown Mechanism

The `inbox_address_cooldowns` table prevents immediate reuse of email addresses to enhance privacy and security:

**How it works:**
1. When an inbox is created, its address is recorded in the cooldown table
2. The `cooldown_until` timestamp is set to `last_used_at + cooldown_period` (default: 24 hours)
3. Address generation checks the cooldown table before assigning an address
4. Addresses with `cooldown_until` in the future are skipped
5. Expired cooldown records are periodically cleaned up

**Benefits:**
- Prevents address reuse within the cooldown window
- Reduces risk of receiving messages intended for previous inbox owner
- Maintains user privacy by avoiding predictable address patterns
- Configurable cooldown period via application settings

**Cleanup strategy:**
Cooldown records can be hard-deleted once `cooldown_until` has passed, as they serve no further purpose.
