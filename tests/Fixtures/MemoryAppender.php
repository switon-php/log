<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

use Switon\Logging\AppenderInterface;
use Switon\Logging\LogEntry;

class MemoryAppender implements AppenderInterface
{
    protected array $entries = [];

    public function append(LogEntry $logEntry): void
    {
        $this->entries[] = $logEntry;
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    public function clear(): void
    {
        $this->entries = [];
    }

    public function count(): int
    {
        return count($this->entries);
    }
}
