<?php

declare(strict_types=1);

namespace Snaply\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Snaply\Config\CleanupConfig;
use Snaply\Service\CleanupService;
use Snaply\Service\InboxService;
use Snaply\Value\CleanupStats;

/**
 * Unit tests for CleanupService.
 */
class CleanupServiceTest extends TestCase
{
    private InboxService $inboxService;
    private CleanupConfig $config;
    private CleanupService $service;

    protected function setUp(): void
    {
        $this->inboxService = $this->createMock(InboxService::class);
        $this->config = new CleanupConfig(
            inboxAgeMinutes: 60,
            batchSize: 1000,
            maxRuntimeSeconds: 300,
            verbose: false,
            dryRun: false
        );
        $this->service = new CleanupService($this->inboxService, $this->config);
    }

    public function testRunFullCleanupProcessesAllPhases(): void
    {
        $this->inboxService
            ->expects($this->once())
            ->method('processExpiredInboxes')
            ->with(1000)
            ->willReturn(10);

        $this->inboxService
            ->expects($this->once())
            ->method('cleanupOldInboxes')
            ->with(60, 1000)
            ->willReturn(5);

        $this->inboxService
            ->expects($this->once())
            ->method('cleanupExpiredCooldowns')
            ->with(1000)
            ->willReturn(3);

        $stats = $this->service->runFullCleanup();

        $this->assertInstanceOf(CleanupStats::class, $stats);
        $this->assertSame(10, $stats->inboxesExpired);
        $this->assertSame(5, $stats->inboxesDeleted);
        $this->assertSame(3, $stats->cooldownsDeleted);
        $this->assertTrue($stats->completed);
    }

    public function testRunFullCleanupStopsOnTimeout(): void
    {
        $config = new CleanupConfig(
            inboxAgeMinutes: 60,
            batchSize: 1000,
            maxRuntimeSeconds: 0, // Immediate timeout
            verbose: false,
            dryRun: false
        );
        $service = new CleanupService($this->inboxService, $config);

        $this->inboxService
            ->expects($this->never())
            ->method('processExpiredInboxes');

        $this->inboxService
            ->expects($this->never())
            ->method('cleanupOldInboxes');

        $this->inboxService
            ->expects($this->never())
            ->method('cleanupExpiredCooldowns');

        $stats = $service->runFullCleanup();

        $this->assertFalse($stats->completed);
    }

    public function testRunFullCleanupInDryRunMode(): void
    {
        $config = new CleanupConfig(
            inboxAgeMinutes: 60,
            batchSize: 1000,
            maxRuntimeSeconds: 300,
            verbose: false,
            dryRun: true
        );
        $service = new CleanupService($this->inboxService, $config);

        $this->inboxService
            ->expects($this->once())
            ->method('countExpiredInboxes')
            ->willReturn(25);

        $this->inboxService
            ->expects($this->never())
            ->method('processExpiredInboxes');

        $stats = $service->runFullCleanup();

        $this->assertSame(25, $stats->inboxesExpired);
        $this->assertSame(0, $stats->inboxesDeleted);
        $this->assertSame(0, $stats->cooldownsDeleted);
    }
}
