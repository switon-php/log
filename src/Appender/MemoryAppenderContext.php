<?php

declare(strict_types=1);

namespace Switon\Logging\Appender;

use Switon\Core\ContextIsolated;
use Switon\Logging\LogEntry;

/**
 * Request/coroutine-local state for MemoryAppender.
 *
 * Stores captured log entries for one execution context.
 *
 * @see \Switon\Logging\Appender\MemoryAppender
 */
class MemoryAppenderContext implements ContextIsolated
{
    /** @var array<int, LogEntry> Captured entries for the current context. */
    public array $logs = [];
}
