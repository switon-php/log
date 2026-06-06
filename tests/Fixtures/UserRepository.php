<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

use Switon\Logging\LoggerInterface;

class UserRepository implements UserRepositoryInterface
{
    public function __construct(protected LoggerInterface $logger)
    {
    }

    public function findById(int $id): ?User
    {
        $this->logger->info('Finding user by ID', ['user_id' => $id]);

        if ($id === 1) {
            return new User(1, 'John Doe', 'john@example.com');
        }

        $this->logger->warning('User not found', ['user_id' => $id]);

        return null;
    }

    public function save(User $user): void
    {
        $this->logger->info('Saving user', [
            'user_id' => $user->id,
            'user_name' => $user->name,
        ]);
    }
}
