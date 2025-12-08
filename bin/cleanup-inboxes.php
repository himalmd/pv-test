#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Inbox Cleanup Script
 *
 * Scheduled cleanup utility for expired inboxes, messages, and address cooldowns.
 * Designed to be run via cron for periodic maintenance.
 *
 * Usage:
 *   php bin/cleanup-inboxes.php [options]
 *
 * Options:
 *   --dry-run    Don't actually delete, just report what would be deleted
 *   --verbose    Enable verbose output with detailed progress
 *   --help       Display this help message
 *
 * Environment Variables:
 *   CLEANUP_INBOX_AGE_MINUTES      Age threshold for deletion (default: 60)
 *   CLEANUP_BATCH_SIZE             Records per batch (default: 1000)
 *   CLEANUP_MAX_RUNTIME_SECONDS    Max execution time (default: 300)
 *   CLEANUP_VERBOSE                Enable verbose mode (default: false)
 *   CLEANUP_DRY_RUN                Enable dry-run mode (default: false)
 *
 * Exit Codes:
 *   0 - Success
 *   1 - Error occurred
 *   2 - Timeout (partial completion)
 *
 * Example Crontab:
 *   */5 * * * * php /var/www/snaply/bin/cleanup-inboxes.php >> /var/log/snaply-cleanup.log 2>&1
 */

// Bootstrap application
$services = require __DIR__ . '/../config/bootstrap.php';

use Snaply\Config\CleanupConfig;
use Snaply\Service\CleanupService;

/**
 * Parse command line arguments.
 *
 * @param array<int, string> $argv Command line arguments
 * @return array<string, bool> Parsed options
 */
function parseArguments(array $argv): array
{
    $options = [];

    foreach ($argv as $arg) {
        if ($arg === '--dry-run') {
            $options['dry-run'] = true;
        } elseif ($arg === '--verbose') {
            $options['verbose'] = true;
        } elseif ($arg === '--help' || $arg === '-h') {
            $options['help'] = true;
        }
    }

    return $options;
}

/**
 * Display help message.
 */
function showHelp(): void
{
    echo <<<'HELP'
Inbox Cleanup Script

Scheduled cleanup utility for expired inboxes, messages, and address cooldowns.

Usage:
  php bin/cleanup-inboxes.php [options]

Options:
  --dry-run    Don't actually delete, just report what would be deleted
  --verbose    Enable verbose output with detailed progress
  --help       Display this help message

Environment Variables:
  CLEANUP_INBOX_AGE_MINUTES      Age threshold for deletion (default: 60)
  CLEANUP_BATCH_SIZE             Records per batch (default: 1000)
  CLEANUP_MAX_RUNTIME_SECONDS    Max execution time (default: 300)
  CLEANUP_VERBOSE                Enable verbose mode (default: false)
  CLEANUP_DRY_RUN                Enable dry-run mode (default: false)

Exit Codes:
  0 - Success
  1 - Error occurred
  2 - Timeout (partial completion)

Example Crontab:
  # Run cleanup every 5 minutes
  */5 * * * * php /var/www/snaply/bin/cleanup-inboxes.php >> /var/log/snaply-cleanup.log 2>&1

  # Run verbose cleanup every hour
  0 * * * * php /var/www/snaply/bin/cleanup-inboxes.php --verbose >> /var/log/snaply-cleanup.log 2>&1

HELP;
}

// Main execution
try {
    // Parse command line arguments
    $options = parseArguments($argv);

    // Show help if requested
    if (isset($options['help'])) {
        showHelp();
        exit(0);
    }

    // Load configuration from environment
    $config = CleanupConfig::fromEnvironment();

    // Override with command line options
    if (isset($options['dry-run'])) {
        $config->dryRun = true;
    }
    if (isset($options['verbose'])) {
        $config->verbose = true;
    }

    // Validate configuration
    $config->validate();

    // Display startup information
    if ($config->verbose) {
        echo "========================================\n";
        echo "Inbox Cleanup Starting\n";
        echo "========================================\n";
        echo "Configuration:\n";
        echo "  Inbox age threshold: {$config->inboxAgeMinutes} minutes\n";
        echo "  Batch size: {$config->batchSize}\n";
        echo "  Max runtime: {$config->maxRuntimeSeconds} seconds\n";
        echo "  Dry run: " . ($config->dryRun ? "Yes" : "No") . "\n";
        echo "========================================\n\n";
    }

    // Create cleanup service
    $cleanupService = new CleanupService($services['inboxService'], $config);

    // Run cleanup
    $stats = $cleanupService->runFullCleanup();

    // Display results
    echo "\n";
    echo "========================================\n";
    echo "Cleanup Completed\n";
    echo "========================================\n";
    echo $stats->getSummary() . "\n";
    echo "========================================\n";

    // Output machine-readable JSON
    if ($config->verbose) {
        echo "\nJSON Output:\n";
        echo $stats->toJson() . "\n";
    }

    // Determine exit code
    if (!$stats->completed) {
        // Timeout occurred
        echo "\nWARNING: Cleanup timed out - partial completion\n";
        exit(2);
    }

    exit(0);
} catch (\InvalidArgumentException $e) {
    echo "Configuration Error: {$e->getMessage()}\n";
    echo "Use --help for usage information\n";
    exit(1);
} catch (\Throwable $e) {
    echo "Fatal Error: {$e->getMessage()}\n";
    echo "  File: {$e->getFile()}\n";
    echo "  Line: {$e->getLine()}\n";

    if (getenv('CLEANUP_VERBOSE') === 'true') {
        echo "\nStack Trace:\n";
        echo $e->getTraceAsString() . "\n";
    }

    exit(1);
}
