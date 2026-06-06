<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

use Switon\Logging\LogEntry;

class LogEntryFactory
{
    public static function create(
        string $level = 'info',
        string $hostname = 'test-host',
        string $time_format = 'Y-m-d H:i:s',
        ?float $timestamp = null,
    ): LogEntry {
        return new LogEntry($level, $hostname, $time_format, $timestamp ?? microtime(true));
    }
}
