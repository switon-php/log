<?php

declare(strict_types=1);

namespace Switon\Logging\Appender;

use Switon\Core\Attribute\Autowired;
use Switon\Core\ContextAware;
use Switon\Core\ContextManagerInterface;
use Switon\Logging\AppenderInterface;
use Switon\Logging\LogEntry;

/**
 * In-memory appender for tests and diagnostics.
 *
 * Useful for assertions. Log entries are isolated per request/coroutine context.
 *
 * @see \Switon\Logging\AppenderInterface
 * @see \Switon\Logging\LogEntry
 */
class MemoryAppender implements AppenderInterface, ContextAware
{
    #[Autowired] protected ContextManagerInterface $contextManager;

    /**
     * {@inheritDoc}
     */
    public function getContext(): MemoryAppenderContext
    {
        return $this->contextManager->getContext($this);
    }

    /**
     * {@inheritDoc}
     */
    public function append(LogEntry $logEntry): void
    {
        $this->getContext()->logs[] = $logEntry;
    }

    /**
     * Gets all stored log entries for current request/coroutine.
     *
     * @return LogEntry[]
     */
    public function getLogs(): array
    {
        return $this->getContext()->logs;
    }

    /**
     * Clears all stored log entries for current request/coroutine.
     */
    public function clear(): void
    {
        $this->getContext()->logs = [];
    }
}
