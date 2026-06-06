<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

use Switon\Logging\LoggerInterface;

class OrderService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected PaymentServiceInterface $paymentService,
        protected LoggerInterface         $logger,
    ) {
    }

    public function createOrder(int $userId, float $amount): ?Order
    {
        $this->logger->info('Creating order', [
            'user_id' => $userId,
            'amount' => $amount,
        ]);

        $user = $this->userRepository->findById($userId);
        if (!$user) {
            $this->logger->warning('Cannot create order: user not found', ['user_id' => $userId]);

            return null;
        }

        $order = new Order(random_int(1000, 9999), $userId, $amount);

        if ($this->paymentService->processPayment($order)) {
            $this->logger->info('Order created successfully', ['order_id' => $order->id]);

            return $order;
        }

        $this->logger->error('Order creation failed: payment failed', ['order_id' => $order->id]);

        return null;
    }
}
