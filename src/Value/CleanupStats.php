<?php

declare(strict_types=1);

namespace Snaply\Value;

/**
 * Statistics from cleanup operations.
 *
 * Immutable value object holding metrics from a cleanup run.
 */
class CleanupStats
{
    /**
     * Number of inboxes marked as expired.
     */
    public int $inboxesExpired = 0;

    /**
     * Number of inboxes hard-deleted.
     */
    public int $inboxesDeleted = 0;

    /**
     * Number of messages hard-deleted (informational, cascade handles this).
     */
    public int $messagesDeleted = 0;

    /**
     * Number of address cooldown records deleted.
     */
    public int $cooldownsDeleted = 0;

    /**
     * Total execution time in seconds.
     */
    public float $executionTimeSeconds = 0.0;

    /**
     * Whether the operation completed or timed out.
     */
    public bool $completed = true;

    /**
     * Create a new statistics object.
     *
     * @param int $inboxesExpired Number of inboxes marked as expired
     * @param int $inboxesDeleted Number of inboxes hard-deleted
     * @param int $messagesDeleted Number of messages hard-deleted
     * @param int $cooldownsDeleted Number of cooldowns deleted
     * @param float $executionTimeSeconds Execution time in seconds
     * @param bool $completed Whether the operation completed
     */
    public function __construct(
        int $inboxesExpired = 0,
        int $inboxesDeleted = 0,
        int $messagesDeleted = 0,
        int $cooldownsDeleted = 0,
        float $executionTimeSeconds = 0.0,
        bool $completed = true
    ) {
        $this->inboxesExpired = $inboxesExpired;
        $this->inboxesDeleted = $inboxesDeleted;
        $this->messagesDeleted = $messagesDeleted;
        $this->cooldownsDeleted = $cooldownsDeleted;
        $this->executionTimeSeconds = $executionTimeSeconds;
        $this->completed = $completed;
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'inboxes_expired' => $this->inboxesExpired,
            'inboxes_deleted' => $this->inboxesDeleted,
            'messages_deleted' => $this->messagesDeleted,
            'cooldowns_deleted' => $this->cooldownsDeleted,
            'execution_time_seconds' => round($this->executionTimeSeconds, 3),
            'completed' => $this->completed,
        ];
    }

    /**
     * Convert to JSON string.
     *
     * @return string JSON representation
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_UNESCAPED_SLASHES);
    }

    /**
     * Get human-readable summary.
     *
     * @return string Formatted summary
     */
    public function getSummary(): string
    {
        $lines = [];
        $lines[] = "Cleanup Statistics:";
        $lines[] = "- Inboxes expired: {$this->inboxesExpired}";
        $lines[] = "- Inboxes deleted: {$this->inboxesDeleted}";
        $lines[] = "- Messages deleted: {$this->messagesDeleted} (cascade)";
        $lines[] = "- Cooldowns deleted: {$this->cooldownsDeleted}";
        $lines[] = sprintf("- Execution time: %.3fs", $this->executionTimeSeconds);
        $lines[] = "- Status: " . ($this->completed ? "Completed" : "Timed out (partial)");

        return implode("\n", $lines);
    }

    /**
     * Check if any cleanup was performed.
     *
     * @return bool True if any records were processed
     */
    public function hasActivity(): bool
    {
        return $this->inboxesExpired > 0
            || $this->inboxesDeleted > 0
            || $this->messagesDeleted > 0
            || $this->cooldownsDeleted > 0;
    }
}
