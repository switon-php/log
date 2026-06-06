<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

use Switon\Logging\LoggerInterface;

class PaymentService implements PaymentServiceInterface
{
    public function __construct(protected LoggerInterface $logger)
    {
    }

    public function processPayment(Order $order): bool
    {
        $this->logger->info('Processing payment', [
            'order_id' => $order->id,
            'amount' => $order->amount,
        ]);

        $success = rand(0, 10) > 2;

        if ($success) {
            $this->logger->info('Payment processed successfully', ['order_id' => $order->id]);
        } else {
            $this->logger->error('Payment processing failed', ['order_id' => $order->id]);
        }

        return $success;
    }
}
