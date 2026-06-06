<?php

declare(strict_types=1);

namespace Switon\Logging;

/**
 * Converts <code>LogEntry</code> into one output line.
 *
 * Guidance:
 * - use <code>$line_format</code> with <code>{key}</code> placeholders for text mode
 * - use a format without braces for JSON mode
 *
 * @see \Switon\Logging\Formatter
 * @see \Switon\Logging\LogEntry
 */
interface FormatterInterface
{
    /**
     * Formats a log entry into a string.
     *
     * @param LogEntry $logEntry
     * @param string $line_format Text mode: includes <code>{key}</code> placeholders.
     *                            JSON mode: no braces.
     *                            Use <code>''</code> for all fields, or a comma-separated field list.
     *
     * @return string Formatted log string (including newline if needed)
     */
    public function format(LogEntry $logEntry, string $line_format): string;
}
