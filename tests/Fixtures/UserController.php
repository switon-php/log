<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

use Switon\Logging\LoggerInterface;
use Throwable;

class UserController
{
    public function __construct(
        protected UserService       $userService,
        protected RequestInterface  $request,
        protected ResponseInterface $response,
        protected LoggerInterface   $logger,
    ) {
    }

    public function register(): void
    {
        try {
            $body = $this->request->getBody();
            $name = $body['name'] ?? '';
            $email = $body['email'] ?? '';
            $user = $this->userService->registerUser($name, $email);

            $this->response->setBody([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ]);

            $this->logger->info('User registration API call completed', ['user_id' => $user->id]);
        } catch (Throwable $e) {
            $this->logger->error('User registration API call failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $this->response->setStatusCode(500);
            $this->response->setBody(['success' => false, 'error' => 'Internal server error']);
        }
    }

    public function getUser(): void
    {
        try {
            $userId = (int)($this->request->getBody()['id'] ?? 0);
            $user = $this->userService->getUser($userId);

            if ($user) {
                $this->response->setBody([
                    'success' => true,
                    'user' => [
                        'id' => $user->id,
                        'name' => $user->name,
                        'email' => $user->email,
                    ],
                ]);

                return;
            }

            $this->response->setStatusCode(404);
            $this->response->setBody(['success' => false, 'error' => 'User not found']);
        } catch (Throwable $e) {
            $this->logger->error('Get user API call failed', [
                'error' => $e->getMessage(),
            ]);

            $this->response->setStatusCode(500);
            $this->response->setBody(['success' => false, 'error' => 'Internal server error']);
        }
    }
}
