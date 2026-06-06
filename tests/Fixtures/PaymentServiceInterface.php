<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

interface PaymentServiceInterface
{
    public function processPayment(Order $order): bool;
}
