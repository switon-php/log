<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

class Request implements RequestInterface
{
    public function __construct(
        protected string $method = 'GET',
        protected string $path = '/',
        protected array  $body = [],
    ) {
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getBody(): array
    {
        return $this->body;
    }
}
