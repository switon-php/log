<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

interface ResponseInterface
{
    public function setStatusCode(int $code): void;

    public function getStatusCode(): int;

    public function setBody(array $body): void;

    public function getBody(): array;
}
