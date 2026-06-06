<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

use Switon\Logging\AppenderInterface;
use Switon\Logging\LogEntry;

class MockAppender implements AppenderInterface
{
    public array $entries = [];

    public function append(LogEntry $logEntry): void
    {
        $this->entries[] = $logEntry;
    }

    public function clear(): void
    {
        $this->entries = [];
    }

    public function getLastEntry(): ?LogEntry
    {
        return $this->entries[count($this->entries) - 1] ?? null;
    }
}
