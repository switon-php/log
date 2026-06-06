<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Switon\Core\ContextIsolated;
use Switon\Logging\Appender\MemoryAppenderContext;
use Switon\Logging\LogEntry;

class MemoryAppenderContextTest extends TestCase
{
    public function testContextStartsWithEmptyLogBuffer(): void
    {
        $context = new MemoryAppenderContext();

        $this->assertSame([], $context->logs);
    }

    public function testContextImplementsIsolationMarkerAndKeepsEntries(): void
    {
        $context = new MemoryAppenderContext();
        $entry = new LogEntry('info', 'host', 'Y-m-d H:i:s', microtime(true));
        $entry->message = 'hello';

        $context->logs[] = $entry;

        $this->assertInstanceOf(ContextIsolated::class, $context);
        $this->assertCount(1, $context->logs);
        $this->assertSame('hello', $context->logs[0]->message);
    }
}
