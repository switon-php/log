<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

use Switon\Logging\AppenderInterface;
use Switon\Logging\LogEntry;
use RuntimeException;

class FailingAppender implements AppenderInterface
{
    public function append(LogEntry $logEntry): void
    {
        throw new RuntimeException('Appender failed');
    }
}
