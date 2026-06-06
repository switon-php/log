<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

interface RequestInterface
{
    public function getMethod(): string;

    public function getPath(): string;

    public function getBody(): array;
}
