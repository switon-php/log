<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

class Response implements ResponseInterface
{
    protected int $statusCode = 200;
    protected array $body = [];

    public function setStatusCode(int $code): void
    {
        $this->statusCode = $code;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setBody(array $body): void
    {
        $this->body = $body;
    }

    public function getBody(): array
    {
        return $this->body;
    }
}
