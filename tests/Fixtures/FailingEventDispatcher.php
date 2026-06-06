<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

use Psr\EventDispatcher\EventDispatcherInterface;
use RuntimeException;

class FailingEventDispatcher implements EventDispatcherInterface
{
    public function dispatch(object $event): object
    {
        throw new RuntimeException('Event dispatcher failed');
    }
}
