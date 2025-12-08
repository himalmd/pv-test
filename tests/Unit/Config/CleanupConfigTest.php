<?php

declare(strict_types=1);

namespace Snaply\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Snaply\Config\CleanupConfig;

/**
 * Unit tests for CleanupConfig.
 */
class CleanupConfigTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $config = new CleanupConfig(
            inboxAgeMinutes: 60,
            batchSize: 1000,
            maxRuntimeSeconds: 300,
            verbose: true,
            dryRun: false
        );

        $this->assertSame(60, $config->inboxAgeMinutes);
        $this->assertSame(1000, $config->batchSize);
        $this->assertSame(300, $config->maxRuntimeSeconds);
        $this->assertTrue($config->verbose);
        $this->assertFalse($config->dryRun);
    }

    public function testValidateAcceptsValidConfiguration(): void
    {
        $config = new CleanupConfig(
            inboxAgeMinutes: 60,
            batchSize: 1000,
            maxRuntimeSeconds: 300,
            verbose: false,
            dryRun: false
        );

        $this->expectNotToPerformAssertions();
        $config->validate();
    }

    public function testValidateThrowsExceptionForInvalidInboxAge(): void
    {
        $config = new CleanupConfig(
            inboxAgeMinutes: 0,
            batchSize: 1000,
            maxRuntimeSeconds: 300,
            verbose: false,
            dryRun: false
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('inboxAgeMinutes must be at least 1');
        $config->validate();
    }

    public function testValidateThrowsExceptionForInvalidBatchSize(): void
    {
        $config = new CleanupConfig(
            inboxAgeMinutes: 60,
            batchSize: 0,
            maxRuntimeSeconds: 300,
            verbose: false,
            dryRun: false
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('batchSize must be between 1 and 10000');
        $config->validate();
    }

    public function testValidateThrowsExceptionForBatchSizeTooLarge(): void
    {
        $config = new CleanupConfig(
            inboxAgeMinutes: 60,
            batchSize: 20000,
            maxRuntimeSeconds: 300,
            verbose: false,
            dryRun: false
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('batchSize must be between 1 and 10000');
        $config->validate();
    }

    public function testValidateThrowsExceptionForInvalidMaxRuntime(): void
    {
        $config = new CleanupConfig(
            inboxAgeMinutes: 60,
            batchSize: 1000,
            maxRuntimeSeconds: 0,
            verbose: false,
            dryRun: false
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('maxRuntimeSeconds must be at least 1');
        $config->validate();
    }
}
