<?php

declare(strict_types=1);

namespace Switon\Logging;

/**
 * Contract for log output destinations (file, Redis, stdout, syslog, etc.).
 *
 * Guidance:
 * - implement <code>append()</code> to write one prepared <code>LogEntry</code>
 * - do not throw from <code>append()</code>; report recoverable failures internally so other appenders can still run
 *
 * @see \Switon\Logging\Appender\FileAppender
 * @see \Switon\Logging\Appender\StdoutAppender
 * @see \Switon\Logging\Appender\SyslogAppender
 * @see \Switon\Logging\LogEntry
 * @see \Switon\Logging\FormatterInterface
 */
interface AppenderInterface
{
    /**
     * Writes one prepared log entry to the destination.
     *
     * Must not throw; for recoverable failures prefer <code>trigger_error()</code> or <code>error_log()</code>.
     *
     * @param LogEntry $logEntry
     */
    public function append(LogEntry $logEntry): void;
}
