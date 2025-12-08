<?php

declare(strict_types=1);

namespace Snaply\Config;

/**
 * Configuration for cleanup operations.
 *
 * Holds all configurable parameters for the inbox/message cleanup system.
 * Values are loaded from environment variables with sensible defaults.
 */
class CleanupConfig
{
    /**
     * Minimum age in minutes before hard-deleting expired/abandoned inboxes.
     */
    public int $inboxAgeMinutes;

    /**
     * Maximum number of records to process in a single batch.
     */
    public int $batchSize;

    /**
     * Maximum runtime for cleanup operations in seconds.
     */
    public int $maxRuntimeSeconds;

    /**
     * Enable verbose logging output.
     */
    public bool $verbose;

    /**
     * Dry run mode - report what would be deleted without actually deleting.
     */
    public bool $dryRun;

    /**
     * Create a new cleanup configuration.
     *
     * @param int $inboxAgeMinutes Minimum age before hard deletion (default: 60)
     * @param int $batchSize Batch size for processing (default: 1000)
     * @param int $maxRuntimeSeconds Max runtime in seconds (default: 300)
     * @param bool $verbose Enable verbose logging (default: false)
     * @param bool $dryRun Enable dry run mode (default: false)
     */
    public function __construct(
        int $inboxAgeMinutes = 60,
        int $batchSize = 1000,
        int $maxRuntimeSeconds = 300,
        bool $verbose = false,
        bool $dryRun = false
    ) {
        $this->inboxAgeMinutes = $inboxAgeMinutes;
        $this->batchSize = $batchSize;
        $this->maxRuntimeSeconds = $maxRuntimeSeconds;
        $this->verbose = $verbose;
        $this->dryRun = $dryRun;
    }

    /**
     * Create configuration from environment variables.
     *
     * Reads configuration from environment with fallback defaults:
     * - CLEANUP_INBOX_AGE_MINUTES (default: 60)
     * - CLEANUP_BATCH_SIZE (default: 1000)
     * - CLEANUP_MAX_RUNTIME_SECONDS (default: 300)
     * - CLEANUP_VERBOSE (default: false)
     * - CLEANUP_DRY_RUN (default: false)
     *
     * @return self Configuration loaded from environment
     */
    public static function fromEnvironment(): self
    {
        $inboxAge = (int) (getenv('CLEANUP_INBOX_AGE_MINUTES') ?: 60);
        $batchSize = (int) (getenv('CLEANUP_BATCH_SIZE') ?: 1000);
        $maxRuntime = (int) (getenv('CLEANUP_MAX_RUNTIME_SECONDS') ?: 300);
        $verbose = filter_var(
            getenv('CLEANUP_VERBOSE') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        );
        $dryRun = filter_var(
            getenv('CLEANUP_DRY_RUN') ?: 'false',
            FILTER_VALIDATE_BOOLEAN
        );

        return new self($inboxAge, $batchSize, $maxRuntime, $verbose, $dryRun);
    }

    /**
     * Validate configuration values.
     *
     * @throws \InvalidArgumentException If configuration is invalid
     */
    public function validate(): void
    {
        if ($this->inboxAgeMinutes < 1) {
            throw new \InvalidArgumentException('inboxAgeMinutes must be at least 1');
        }

        if ($this->batchSize < 1 || $this->batchSize > 10000) {
            throw new \InvalidArgumentException('batchSize must be between 1 and 10000');
        }

        if ($this->maxRuntimeSeconds < 1) {
            throw new \InvalidArgumentException('maxRuntimeSeconds must be at least 1');
        }
    }
}
