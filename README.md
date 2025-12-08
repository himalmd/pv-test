# Snaply Backend

Backend API for Snaply - Web page annotation and commenting platform with temporary inbox functionality.

## Features

- **Project Management**: Create and manage annotation projects
- **Page Tracking**: Track web pages within projects
- **Snapshots**: Version snapshots of pages with media references
- **Comments**: Threaded comments with coordinate annotations
- **Temporary Inboxes**: Disposable email addresses with TTL and lifecycle management

## Architecture

This is a pure PHP 8.x LAMP stack application with the following layers:

- **Database Layer**: MySQL 8.x with migrations
- **Entity Layer**: Plain PHP objects representing domain models
- **Repository Layer**: Data access with parameterized queries and soft delete support
- **Service Layer**: Business logic orchestration with transaction management
- **API Layer**: RESTful JSON endpoints for frontend integration

## Setup

### Prerequisites

- PHP 8.1 or higher
- MySQL 8.x
- Apache with mod_rewrite enabled
- Composer

### Installation

1. Clone the repository
2. Install dependencies:
   ```bash
   composer install
   ```

3. Configure environment:
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

4. Run database migrations:
   ```bash
   mysql -u <username> -p <database_name> < database/migrate.sql
   ```

5. Configure web server to point to `public/` directory

## API Documentation

### Base URL

All API endpoints are relative to `/api/`

### Authentication

Session management uses HTTP-only cookies. The `session_token` cookie is automatically generated on first request and persists for 30 days (configurable).

### Inbox API

#### GET /api/inbox/current

Get or create the current active inbox for the session.

**Request:**
```bash
curl -X GET https://your-domain.com/api/inbox/current \
  -H "Cookie: session_token=<your-token>"
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "email": "abc123xyz@tempinbox.pro",
    "status": "active",
    "ttl_minutes": 60,
    "created_at": "2025-12-08T10:45:00+00:00",
    "last_accessed_at": "2025-12-08T10:45:00+00:00",
    "expires_at": "2025-12-08T11:45:00+00:00",
    "seconds_until_expiry": 3600
  }
}
```

**Behavior:**
- Automatically creates an inbox if none exists for the session
- Updates `last_accessed_at` timestamp to extend TTL
- Returns the same inbox across all browser tabs (shared session)

---

#### POST /api/inbox/rotate

Abandon current inbox and create a new one with a fresh email address.

**Request:**
```bash
curl -X POST https://your-domain.com/api/inbox/rotate \
  -H "Cookie: session_token=<your-token>" \
  -H "Content-Type: application/json"
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 124,
    "email": "xyz789abc@tempinbox.pro",
    "status": "active",
    "ttl_minutes": 60,
    "created_at": "2025-12-08T10:50:00+00:00",
    "last_accessed_at": "2025-12-08T10:50:00+00:00",
    "expires_at": "2025-12-08T11:50:00+00:00",
    "seconds_until_expiry": 3600
  },
  "message": "New inbox created successfully"
}
```

**Behavior:**
- Previous inbox is marked as "abandoned"
- New unique email address is generated (respects cooldown period)
- Operation is atomic (uses database transaction)

---

#### POST /api/inbox/delete-now

Immediately delete current inbox and all messages, then create a new empty inbox.

**Request:**
```bash
curl -X POST https://your-domain.com/api/inbox/delete-now \
  -H "Cookie: session_token=<your-token>" \
  -H "Content-Type: application/json"
```

**Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "id": 125,
    "email": "def456ghi@tempinbox.pro",
    "status": "active",
    "ttl_minutes": 60,
    "created_at": "2025-12-08T10:55:00+00:00",
    "last_accessed_at": "2025-12-08T10:55:00+00:00",
    "expires_at": "2025-12-08T11:55:00+00:00",
    "seconds_until_expiry": 3600
  },
  "message": "Inbox deleted and new inbox created successfully"
}
```

**Behavior:**
- Soft-deletes all messages in current inbox
- Marks inbox as "deleted"
- Creates new empty inbox with fresh address
- Operation is atomic (uses database transaction)

---

### Error Responses

All errors follow this format:

```json
{
  "success": false,
  "error": {
    "code": "ERROR_CODE",
    "message": "Human readable error message",
    "details": {
      "field_name": ["Validation error message"]
    }
  }
}
```

**Common Error Codes:**

- `MISSING_SESSION_TOKEN` (400): Session token not provided
- `METHOD_NOT_ALLOWED` (405): Wrong HTTP method used
- `VALIDATION_ERROR` (400): Input validation failed
- `NOT_FOUND` (404): Resource not found
- `INTERNAL_ERROR` (500): Unexpected server error

## Cleanup & Maintenance

### Scheduled Cleanup

The system includes automated cleanup mechanisms for expired inboxes and messages. The cleanup script should be run periodically via cron.

#### Running Cleanup Manually

```bash
# Standard cleanup
php bin/cleanup-inboxes.php

# Dry run (see what would be deleted without actually deleting)
php bin/cleanup-inboxes.php --dry-run

# Verbose output with detailed progress
php bin/cleanup-inboxes.php --verbose

# Combine options
php bin/cleanup-inboxes.php --dry-run --verbose
```

#### Cron Setup

Add to your crontab to run cleanup automatically:

```bash
# Run cleanup every 5 minutes
*/5 * * * * cd /var/www/snaply && php bin/cleanup-inboxes.php >> /var/log/snaply-cleanup.log 2>&1

# Run verbose cleanup every hour (for monitoring)
0 * * * * cd /var/www/snaply && php bin/cleanup-inboxes.php --verbose >> /var/log/snaply-cleanup.log 2>&1
```

#### Cleanup Process

The cleanup script performs three phases:

1. **Mark Expired Inboxes**: Finds active inboxes that have exceeded their TTL and marks them as expired
2. **Hard Delete Old Inboxes**: Permanently removes expired/abandoned inboxes older than the configured age threshold (messages are cascade-deleted by database)
3. **Clean Up Cooldowns**: Removes expired address cooldown records to make addresses available for reuse

All operations use batched processing with configurable limits to ensure efficiency under high load.

## Configuration

All configuration is via environment variables (see `.env.example`):

### Database
- `DB_HOST`: MySQL host (default: localhost)
- `DB_PORT`: MySQL port (default: 3306)
- `DB_NAME`: Database name (default: snaply)
- `DB_USER`: Database username
- `DB_PASS`: Database password

### Inbox
- `INBOX_DOMAIN`: Email domain for temporary addresses (default: tempinbox.pro)
- `INBOX_TTL_MINUTES`: Inbox lifetime in minutes (default: 60)
- `INBOX_COOLDOWN_HOURS`: Address reuse cooldown in hours (default: 24)
- `INBOX_ADDRESS_LENGTH`: Generated address length (default: 10)
- `INBOX_MAX_RETRY_ATTEMPTS`: Max retries for unique address (default: 10)

### Session
- `SESSION_COOKIE_LIFETIME`: Cookie lifetime in seconds (default: 2592000 = 30 days)
- `SESSION_COOKIE_SECURE`: HTTPS-only flag (default: true)
- `SESSION_COOKIE_DOMAIN`: Cookie domain (default: current domain)

### Cleanup
- `CLEANUP_INBOX_AGE_MINUTES`: Age threshold before hard deletion (default: 60)
- `CLEANUP_BATCH_SIZE`: Records to process per batch (default: 1000)
- `CLEANUP_MAX_RUNTIME_SECONDS`: Maximum cleanup execution time (default: 300)
- `CLEANUP_VERBOSE`: Enable verbose logging (default: false)
- `CLEANUP_DRY_RUN`: Dry run mode - no actual deletion (default: false)

## Security Features

- **Parameterized Queries**: 100% SQL injection protection
- **Soft Delete**: Safe data retention with recovery options
- **Session Hashing**: SHA-256 hashing of session tokens in database
- **HTTP-Only Cookies**: XSS protection for session tokens
- **Secure Cookies**: HTTPS-only transmission
- **SameSite Cookies**: CSRF protection
- **No PII Storage**: Privacy-first design

## Database Schema

See [database/README.md](database/README.md) for complete schema documentation including:
- Entity Relationship Diagrams
- Table structures
- Index strategies
- Migration guide

## Development

### Running Tests

```bash
composer test
```

### Code Standards

- PHP 8.x with strict typing (`declare(strict_types=1)`)
- PSR-4 autoloading
- Comprehensive PHPDoc comments
- Type hints on all parameters and returns
- Constructor dependency injection

## License

Proprietary
