<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

use Switon\Logging\AppenderInterface;
use Switon\Logging\LogEntry;

class FilteringAppender implements AppenderInterface
{
    protected array $entries = [];
    protected string $filterLevel;

    public function __construct(string $filterLevel = 'INFO')
    {
        $this->filterLevel = $filterLevel;
    }

    public function append(LogEntry $logEntry): void
    {
        $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
        if (($levels[$logEntry->level] ?? 0) >= ($levels[$this->filterLevel] ?? 1)) {
            $this->entries[] = $logEntry;
        }
    }

    public function getEntries(): array
    {
        return $this->entries;
    }
}
