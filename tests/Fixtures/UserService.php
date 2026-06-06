<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

use Switon\Logging\LoggerInterface;

class UserService
{
    public function __construct(
        protected UserRepositoryInterface $userRepository,
        protected EmailServiceInterface   $emailService,
        protected LoggerInterface         $logger,
    ) {
    }

    public function registerUser(string $name, string $email): User
    {
        $this->logger->info('Registering new user', [
            'name' => $name,
            'email' => $email,
        ]);

        $user = new User(random_int(1000, 9999), $name, $email);

        $this->userRepository->save($user);
        $this->emailService->sendWelcomeEmail($user);
        $this->logger->info('User registered successfully', ['user_id' => $user->id]);

        return $user;
    }

    public function getUser(int $id): ?User
    {
        $this->logger->debug('Getting user', ['user_id' => $id]);

        return $this->userRepository->findById($id);
    }
}
