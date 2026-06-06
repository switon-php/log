<?php

declare(strict_types=1);

namespace Switon\Logging\Event;

use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\EventSilent;
use Switon\Eventing\Severity;

/**
 * Event emitted when a log appender fails to write output.
 *
 * Log category: <code>switon.logging.appender.write.failed</code>
 *
 * @see \Switon\Logging\Appender\FileAppender
 */
#[EventLevel(Severity::WARNING)]
class AppenderWriteFailed implements EventSilent
{
    /**
     * @param string $appender Appender name or class that failed.
     * @param string $operation Write operation that failed.
     * @param string $target Output target involved in the failure.
     * @param string $reason Human-readable failure reason.
     */
    public function __construct(
        public string $appender,
        public string $operation,
        public string $target,
        public string $reason,
    ) {
    }
}
