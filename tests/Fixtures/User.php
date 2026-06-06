<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

class User
{
    public function __construct(
        public readonly int    $id,
        public readonly string $name,
        public readonly string $email,
    ) {
    }
}
