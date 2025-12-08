<?php

declare(strict_types=1);

/**
 * Application bootstrap.
 *
 * Sets up autoloading, database connection, and dependency injection.
 */

// Load composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use Snaply\Database\Connection;
use Snaply\Repository\InboxAddressCooldownRepository;
use Snaply\Repository\InboxRepository;
use Snaply\Repository\MessageRepository;
use Snaply\Service\InboxService;

// Load database configuration
$dbConfig = require __DIR__ . '/database.php';

// Create database connection
$connection = Connection::fromConfig($dbConfig);

// Load inbox configuration from environment
$inboxConfig = [
    'domain' => getenv('INBOX_DOMAIN') ?: 'tempinbox.pro',
    'ttl_minutes' => (int) (getenv('INBOX_TTL_MINUTES') ?: 60),
    'cooldown_hours' => (int) (getenv('INBOX_COOLDOWN_HOURS') ?: 24),
    'address_length' => (int) (getenv('INBOX_ADDRESS_LENGTH') ?: 10),
    'max_retry_attempts' => (int) (getenv('INBOX_MAX_RETRY_ATTEMPTS') ?: 10),
];

// Build dependency chain for InboxService
$cooldownRepository = new InboxAddressCooldownRepository($connection);
$inboxRepository = new InboxRepository($connection, $cooldownRepository);
$messageRepository = new MessageRepository($connection, $inboxRepository);

// Create InboxService
$inboxService = new InboxService(
    $connection,
    $inboxRepository,
    $messageRepository,
    $cooldownRepository,
    $inboxConfig
);

// Return services for use in API endpoints
return [
    'connection' => $connection,
    'inboxService' => $inboxService,
    'inboxRepository' => $inboxRepository,
    'messageRepository' => $messageRepository,
    'cooldownRepository' => $cooldownRepository,
];
