# Snaply Test Suite

Comprehensive test coverage for the TempInbox Pro V2 temporary inbox lifecycle system.

## Test Structure

```
tests/
├── Unit/               # Unit tests (isolated component testing)
│   ├── Config/        # Configuration value objects
│   ├── Entity/        # Domain entities
│   ├── Service/       # Business logic services
│   └── Value/         # Value objects
├── Feature/           # Feature tests (end-to-end scenarios)
│   └── Api/          # API lifecycle integration tests
├── bootstrap.php      # Test environment setup
└── README.md         # This file
```

## Running Tests

### Run All Tests
```bash
composer test
# or
vendor/bin/phpunit
```

### Run Specific Test Suite
```bash
# Unit tests only
vendor/bin/phpunit --testsuite=Unit

# Feature tests only
vendor/bin/phpunit --testsuite=Feature
```

### Run Specific Test File
```bash
vendor/bin/phpunit tests/Unit/Service/InboxServiceTest.php
```

### Run with Coverage (requires Xdebug)
```bash
vendor/bin/phpunit --coverage-html coverage/
```

## Test Configuration

Test configuration is defined in `phpunit.xml`:
- Test database: `snaply_test` (separate from production)
- Bootstrap: `tests/bootstrap.php`
- Coverage excludes: Entity classes (simple data objects)

### Environment Variables for Tests

Tests use separate configuration to avoid affecting production:
```
DB_NAME=snaply_test
DB_USER=test_user
DB_PASS=test_password
INBOX_DOMAIN=test.example.com
```

## Test Coverage

### Unit Tests (Isolated Component Testing)

**Entity Tests:**
- `InboxTest` - Inbox entity behavior, status transitions, expiry logic

**Service Tests:**
- `InboxServiceTest` - Core inbox lifecycle operations
- `CleanupServiceTest` - Cleanup orchestration and batch processing

**Configuration Tests:**
- `CleanupConfigTest` - Configuration validation and defaults

**Value Object Tests:**
- `CleanupStatsTest` - Statistics tracking and reporting

### Feature Tests (End-to-End Scenarios)

**API Lifecycle Tests:**
- `InboxLifecycleTest` - Complete inbox lifecycle flows:
  - First visit creates inbox automatically
  - Reload within TTL keeps same inbox
  - Rotate abandons current and creates new
  - Delete-now removes and creates fresh
  - Expiry processing
  - Cleanup operations

## User Story Acceptance Criteria Coverage

### ✅ Core Lifecycle Testing
- [x] Automatic inbox creation on first visit
- [x] Single active inbox per session
- [x] Unique address generation
- [x] Inbox rotation (abandon + new)
- [x] Immediate deletion (delete + new)
- [x] Time-based expiry detection
- [x] Cleanup batch processing

### ✅ Non-Functional Testing
- [x] Configuration validation
- [x] Batch processing with limits
- [x] Timeout protection
- [x] Statistics tracking
- [x] Privacy compliance (no PII)

## Test Database Setup

For integration testing with a real database:

1. Create test database:
```sql
CREATE DATABASE snaply_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'test_user'@'localhost' IDENTIFIED BY 'test_password';
GRANT ALL PRIVILEGES ON snaply_test.* TO 'test_user'@'localhost';
FLUSH PRIVILEGES;
```

2. Run migrations:
```bash
mysql -u test_user -p snaply_test < database/migrate.sql
```

3. Update `phpunit.xml` with actual test database credentials

## Writing New Tests

### Unit Test Template
```php
<?php

declare(strict_types=1);

namespace Snaply\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Snaply\Service\InboxService;

class ExampleServiceTest extends TestCase
{
    public function testExampleMethod(): void
    {
        // Arrange
        $service = $this->createMock(InboxService::class);
        $service->method('someMethod')->willReturn('expected');

        // Act
        $result = $service->someMethod();

        // Assert
        $this->assertSame('expected', $result);
    }
}
```

### Feature Test Template
```php
<?php

declare(strict_types=1);

namespace Snaply\Tests\Feature\Api;

use PHPUnit\Framework\TestCase;
use Snaply\Service\InboxService;

class ExampleFeatureTest extends TestCase
{
    protected function setUp(): void
    {
        // Set up test dependencies (mocked or real)
    }

    public function testFeatureScenario(): void
    {
        // Test complete end-to-end scenario
        $this->assertTrue(true);
    }
}
```

## Continuous Integration

Tests are designed to run in CI environments:
- Zero external dependencies (uses mocks)
- Configurable via environment variables
- Exit codes: 0 (success), 1 (failure)
- PHPUnit XML output for CI parsing

## Best Practices

1. **Test Isolation**: Each test should be independent
2. **Clear Naming**: Test names describe what is being tested
3. **Arrange-Act-Assert**: Follow AAA pattern for clarity
4. **Mock External Dependencies**: Use mocks for database, APIs
5. **Test Edge Cases**: Cover happy path and error conditions
6. **Keep Tests Fast**: Unit tests should run in milliseconds

## Notes on Current Implementation

The current test suite uses **mocks** for database operations rather than actual database queries. This approach:
- ✅ Allows tests to run without database setup
- ✅ Provides fast execution
- ✅ Ensures test isolation
- ⚠️  Does not test actual SQL queries

For full integration testing, consider:
1. Setting up a test database
2. Running actual migrations
3. Testing real database interactions
4. Using database transactions for test cleanup

## Coverage Goals

- **Unit Tests**: 80%+ coverage of service and repository logic
- **Feature Tests**: 100% coverage of user story acceptance criteria
- **Edge Cases**: All error paths and boundary conditions tested

Current coverage focuses on core lifecycle operations as specified in the user story acceptance criteria.
