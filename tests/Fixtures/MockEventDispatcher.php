<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

use Psr\EventDispatcher\EventDispatcherInterface;

class MockEventDispatcher implements EventDispatcherInterface
{
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }

    public function clear(): void
    {
        $this->events = [];
    }

    public function getLastEvent(): ?object
    {
        return $this->events[count($this->events) - 1] ?? null;
    }
}
