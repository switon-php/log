<?php

declare(strict_types=1);

namespace Switon\Logging\Appender;

use Switon\Core\Attribute\Autowired;
use Switon\Logging\AppenderInterface;
use Switon\Logging\FormatterInterface;
use Switon\Logging\LogEntry;

/**
 * Writes formatted log lines to stdout for terminal and container log streams.
 *
 * Configure <code>$format</code> to switch between text placeholders and JSON field output.
 *
 * @see \Switon\Logging\AppenderInterface
 * @see \Switon\Logging\LogEntry
 */
class StdoutAppender implements AppenderInterface
{
    #[Autowired] protected FormatterInterface $formatter;

    /** Output format: text uses {key}; JSON uses no braces ('' or comma-separated fields). */
    #[Autowired] protected string $format = '[{time}][{level}][{category}][{location}] {message}';

    /**
     * {@inheritDoc}
     */
    public function append(LogEntry $logEntry): void
    {
        echo $this->formatter->format($logEntry, $this->format);
    }
}
