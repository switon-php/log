<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

use Switon\Logging\LoggerInterface;

class EmailService implements EmailServiceInterface
{
    public function __construct(protected LoggerInterface $logger)
    {
    }

    public function sendWelcomeEmail(User $user): void
    {
        $this->logger->info('Sending welcome email', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);
    }

    public function sendOrderConfirmation(Order $order): void
    {
        $this->logger->info('Sending order confirmation', [
            'order_id' => $order->id,
            'user_id' => $order->userId,
        ]);
    }
}
