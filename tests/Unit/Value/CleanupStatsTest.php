<?php

declare(strict_types=1);

namespace Snaply\Tests\Unit\Value;

use PHPUnit\Framework\TestCase;
use Snaply\Value\CleanupStats;

/**
 * Unit tests for CleanupStats.
 */
class CleanupStatsTest extends TestCase
{
    public function testConstructorSetsDefaultValues(): void
    {
        $stats = new CleanupStats();

        $this->assertSame(0, $stats->inboxesExpired);
        $this->assertSame(0, $stats->inboxesDeleted);
        $this->assertSame(0, $stats->messagesDeleted);
        $this->assertSame(0, $stats->cooldownsDeleted);
        $this->assertSame(0.0, $stats->executionTimeSeconds);
        $this->assertTrue($stats->completed);
    }

    public function testConstructorSetsProvidedValues(): void
    {
        $stats = new CleanupStats(
            inboxesExpired: 10,
            inboxesDeleted: 5,
            messagesDeleted: 20,
            cooldownsDeleted: 3,
            executionTimeSeconds: 45.5,
            completed: false
        );

        $this->assertSame(10, $stats->inboxesExpired);
        $this->assertSame(5, $stats->inboxesDeleted);
        $this->assertSame(20, $stats->messagesDeleted);
        $this->assertSame(3, $stats->cooldownsDeleted);
        $this->assertSame(45.5, $stats->executionTimeSeconds);
        $this->assertFalse($stats->completed);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $stats = new CleanupStats(
            inboxesExpired: 10,
            inboxesDeleted: 5,
            messagesDeleted: 20,
            cooldownsDeleted: 3,
            executionTimeSeconds: 45.5,
            completed: true
        );

        $array = $stats->toArray();

        $this->assertSame(10, $array['inboxes_expired']);
        $this->assertSame(5, $array['inboxes_deleted']);
        $this->assertSame(20, $array['messages_deleted']);
        $this->assertSame(3, $array['cooldowns_deleted']);
        $this->assertSame(45.5, $array['execution_time_seconds']);
        $this->assertTrue($array['completed']);
    }

    public function testToJsonReturnsValidJson(): void
    {
        $stats = new CleanupStats(
            inboxesExpired: 10,
            inboxesDeleted: 5
        );

        $json = $stats->toJson();

        $this->assertJson($json);
        $decoded = json_decode($json, true);
        $this->assertSame(10, $decoded['inboxes_expired']);
        $this->assertSame(5, $decoded['inboxes_deleted']);
    }

    public function testGetSummaryReturnsFormattedString(): void
    {
        $stats = new CleanupStats(
            inboxesExpired: 10,
            inboxesDeleted: 5,
            messagesDeleted: 20,
            cooldownsDeleted: 3,
            executionTimeSeconds: 45.5,
            completed: true
        );

        $summary = $stats->getSummary();

        $this->assertStringContainsString('10', $summary);
        $this->assertStringContainsString('5', $summary);
        $this->assertStringContainsString('20', $summary);
        $this->assertStringContainsString('3', $summary);
        $this->assertStringContainsString('45.5', $summary);
    }

    public function testHasActivityReturnsTrueWhenActivityExists(): void
    {
        $stats = new CleanupStats(inboxesExpired: 1);
        $this->assertTrue($stats->hasActivity());

        $stats = new CleanupStats(inboxesDeleted: 1);
        $this->assertTrue($stats->hasActivity());

        $stats = new CleanupStats(cooldownsDeleted: 1);
        $this->assertTrue($stats->hasActivity());
    }

    public function testHasActivityReturnsFalseWhenNoActivity(): void
    {
        $stats = new CleanupStats();
        $this->assertFalse($stats->hasActivity());
    }
}
