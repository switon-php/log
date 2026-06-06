<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

interface EmailServiceInterface
{
    public function sendWelcomeEmail(User $user): void;

    public function sendOrderConfirmation(Order $order): void;
}
