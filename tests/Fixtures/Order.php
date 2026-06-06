<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

class Order
{
    public function __construct(
        public readonly int    $id,
        public readonly int    $userId,
        public readonly float  $amount,
        public readonly string $status = 'pending',
    ) {
    }
}
