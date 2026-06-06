<?php

declare(strict_types=1);

namespace Switon\Logging\Event;

use JsonSerializable;
use Psr\Log\LoggerInterface;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\EventSilent;
use Switon\Eventing\Severity;
use Switon\Logging\LogEntry;

/**
 * Event emitted after a log entry is prepared and before appenders write it.
 *
 * Log category: <code>switon.logging.logger.logged</code>
 *
 * Road-signs:
 * - enrich <code>$logEntry->extra</code> (correlation IDs, request scope) before appenders run
 *
 * @see \Switon\Logging\Logger
 * @see \Switon\Logging\LogEntry
 */
#[EventLevel(Severity::DEBUG)]
class LoggerLogged implements EventSilent, JsonSerializable
{
    /**
     * @param LoggerInterface $logger Logger that processed the entry.
     * @param string $level PSR-3 log level.
     * @param mixed $message Original message before formatting and interpolation.
     * @param array<string, mixed> $context Original logging context.
     * @param LogEntry $logEntry Prepared log entry that appenders will receive next.
     */
    public function __construct(
        public LoggerInterface $logger,
        public string          $level,
        public mixed           $message,
        public array           $context,
        public LogEntry        $logEntry,
    ) {
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return [
            'level' => $this->level,
            'message' => (string)$this->message,
            'category' => $this->logEntry->category,
            'location' => $this->logEntry->location,
        ];
    }
}
