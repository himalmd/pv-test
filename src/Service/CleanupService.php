<?php

declare(strict_types=1);

namespace Snaply\Service;

use Snaply\Config\CleanupConfig;
use Snaply\Value\CleanupStats;

/**
 * Service for orchestrating cleanup operations.
 *
 * Coordinates expiry marking, hard deletion, and cooldown cleanup
 * with batch processing, timeout protection, and statistics tracking.
 */
class CleanupService
{
    private InboxService $inboxService;
    private CleanupConfig $config;

    public function __construct(InboxService $inboxService, CleanupConfig $config)
    {
        $this->inboxService = $inboxService;
        $this->config = $config;
    }

    /**
     * Run complete cleanup cycle.
     *
     * Executes all cleanup phases in order:
     * 1. Mark expired inboxes
     * 2. Hard delete old inboxes (cascade deletes messages)
     * 3. Clean up expired cooldowns
     *
     * Respects batch size limits and timeout protection.
     *
     * @return CleanupStats Statistics from the cleanup run
     */
    public function runFullCleanup(): CleanupStats
    {
        $startTime = microtime(true);
        $stats = new CleanupStats();

        try {
            // Phase 1: Mark expired inboxes
            if ($this->config->verbose) {
                $this->log("Phase 1: Marking expired inboxes...");
            }
            $stats->inboxesExpired = $this->runExpiry($startTime);

            // Phase 2: Hard delete old inboxes (and cascade delete messages)
            if (!$this->isTimedOut($startTime)) {
                if ($this->config->verbose) {
                    $this->log("Phase 2: Hard deleting old inboxes...");
                }
                $stats->inboxesDeleted = $this->runHardDelete($startTime);
            }

            // Phase 3: Clean up expired cooldowns
            if (!$this->isTimedOut($startTime)) {
                if ($this->config->verbose) {
                    $this->log("Phase 3: Cleaning up expired cooldowns...");
                }
                $stats->cooldownsDeleted = $this->runCooldownCleanup($startTime);
            }

            $stats->completed = !$this->isTimedOut($startTime);
        } catch (\Throwable $e) {
            $this->logError("Cleanup failed: {$e->getMessage()}");
            $stats->completed = false;
            throw $e;
        }

        $stats->executionTimeSeconds = microtime(true) - $startTime;

        return $stats;
    }

    /**
     * Mark expired inboxes.
     *
     * Finds active inboxes that have exceeded their TTL and marks them as expired.
     * Processes in batches until timeout or no more work.
     *
     * @param float $startTime Start time for timeout calculation
     * @return int Number of inboxes marked as expired
     */
    private function runExpiry(float $startTime): int
    {
        $totalExpired = 0;
        $batchCount = 0;

        while (!$this->isTimedOut($startTime)) {
            if ($this->config->dryRun) {
                $count = $this->inboxService->countExpiredInboxes();
                if ($count > 0) {
                    $this->log("[DRY RUN] Would mark {$count} inboxes as expired");
                }
                return $count;
            }

            $expired = $this->inboxService->processExpiredInboxes($this->config->batchSize);

            if ($expired === 0) {
                break; // No more work
            }

            $totalExpired += $expired;
            $batchCount++;

            if ($this->config->verbose) {
                $this->log("  Batch {$batchCount}: Marked {$expired} inboxes as expired (total: {$totalExpired})");
            }
        }

        return $totalExpired;
    }

    /**
     * Hard delete old expired/abandoned inboxes.
     *
     * Permanently removes inboxes in expired/abandoned/deleted status
     * older than the configured age threshold. Messages are cascade-deleted
     * by the database foreign key constraint.
     *
     * @param float $startTime Start time for timeout calculation
     * @return int Number of inboxes deleted
     */
    private function runHardDelete(float $startTime): int
    {
        $totalDeleted = 0;
        $batchCount = 0;

        while (!$this->isTimedOut($startTime)) {
            if ($this->config->dryRun) {
                // In dry run, we can't easily count what would be deleted
                // without replicating the repository logic, so just report capability
                $this->log("[DRY RUN] Would hard-delete old inboxes (age >= {$this->config->inboxAgeMinutes} minutes)");
                return 0;
            }

            $deleted = $this->inboxService->cleanupOldInboxes(
                $this->config->inboxAgeMinutes,
                $this->config->batchSize
            );

            if ($deleted === 0) {
                break; // No more work
            }

            $totalDeleted += $deleted;
            $batchCount++;

            if ($this->config->verbose) {
                $this->log("  Batch {$batchCount}: Deleted {$deleted} inboxes (total: {$totalDeleted})");
            }
        }

        return $totalDeleted;
    }

    /**
     * Clean up expired address cooldowns.
     *
     * Removes cooldown records that have passed their cooldown period,
     * making those addresses available for reuse.
     *
     * @param float $startTime Start time for timeout calculation
     * @return int Number of cooldown records deleted
     */
    private function runCooldownCleanup(float $startTime): int
    {
        $totalDeleted = 0;
        $batchCount = 0;

        while (!$this->isTimedOut($startTime)) {
            if ($this->config->dryRun) {
                $this->log("[DRY RUN] Would delete expired cooldown records");
                return 0;
            }

            $deleted = $this->inboxService->cleanupExpiredCooldowns($this->config->batchSize);

            if ($deleted === 0) {
                break; // No more work
            }

            $totalDeleted += $deleted;
            $batchCount++;

            if ($this->config->verbose) {
                $this->log("  Batch {$batchCount}: Deleted {$deleted} cooldowns (total: {$totalDeleted})");
            }
        }

        return $totalDeleted;
    }

    /**
     * Check if operation has timed out.
     *
     * @param float $startTime Start time from microtime(true)
     * @return bool True if timeout exceeded
     */
    private function isTimedOut(float $startTime): bool
    {
        $elapsed = microtime(true) - $startTime;
        return $elapsed >= $this->config->maxRuntimeSeconds;
    }

    /**
     * Log informational message.
     *
     * @param string $message Message to log
     */
    private function log(string $message): void
    {
        echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
    }

    /**
     * Log error message.
     *
     * @param string $message Error message to log
     */
    private function logError(string $message): void
    {
        $timestamp = date('Y-m-d H:i:s');
        error_log("[{$timestamp}] ERROR: {$message}");
        echo "[{$timestamp}] ERROR: {$message}\n";
    }
}
