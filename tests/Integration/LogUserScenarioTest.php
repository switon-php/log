<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Integration;

// Load bootstrap to ensure autoloader is loaded (required when using --no-configuration)

use Switon\Logging\Logger;
use Switon\Logging\Tests\Fixtures\MemoryAppender;
use Switon\Logging\Tests\Fixtures\OrderService;
use Switon\Logging\Tests\Fixtures\Request;
use Switon\Logging\Tests\Fixtures\Response;
use Switon\Logging\Tests\Fixtures\UserController;
use Switon\Logging\Tests\Fixtures\UserService;
use Switon\Logging\Tests\TestCase;
use RuntimeException;

/**
 * Integration tests for real-world logging scenarios.
 */
class LogUserScenarioTest extends TestCase
{
    private MemoryAppender $appender;
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        // Create memory appender to capture all log entries
        $this->appender = new MemoryAppender();

        // Create mock event dispatcher
        $eventDispatcher = new \Switon\Logging\Tests\Fixtures\MockEventDispatcher();

        // Set up container with required dependencies
        $this->container->set(\Psr\EventDispatcher\EventDispatcherInterface::class, $eventDispatcher);

        // Register memory appender in container
        $this->container->set('memory', $this->appender);

        // Create logger using container with memory appender (remove default file appender)
        $this->logger = $this->container->make(\Switon\Logging\LoggerInterface::class, [
            'appenders' => ['file' => null, 'memory' => 'memory'],
        ]);
    }

    public function testUserRegistrationScenario(): void
    {
        // Arrange
        $userService = new \Switon\Logging\Tests\Fixtures\UserRepository($this->logger);
        $emailService = new \Switon\Logging\Tests\Fixtures\EmailService($this->logger);

        $service = new UserService($userService, $emailService, $this->logger);
        $request = new Request('POST', '/register', ['name' => 'John Doe', 'email' => 'john@example.com']);
        $response = new Response();

        $controller = new UserController($service, $request, $response, $this->logger);

        // Act
        $controller->register();

        // Assert
        $entries = $this->appender->getEntries();
        $this->assertGreaterThan(0, count($entries));

        // Should have logged user registration start
        $registrationLog = $this->findLogByMessage($entries, 'Registering new user');
        $this->assertNotNull($registrationLog);
        $this->assertEquals('info', $registrationLog->level);

        // Should have logged user save operation
        $saveLog = $this->findLogByMessage($entries, 'Saving user');
        $this->assertNotNull($saveLog);

        // Should have logged welcome email
        $emailLog = $this->findLogByMessage($entries, 'Sending welcome email');
        $this->assertNotNull($emailLog);

        // Should have logged successful registration
        $successLog = $this->findLogByMessage($entries, 'User registered successfully');
        $this->assertNotNull($successLog);

        // API response should be successful
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->getBody()['success']);
        $this->assertArrayHasKey('user', $response->getBody());
    }

    public function testOrderCreationScenario(): void
    {
        // Arrange - Create a user first
        $userRepo = new \Switon\Logging\Tests\Fixtures\UserRepository($this->logger);
        $emailService = new \Switon\Logging\Tests\Fixtures\EmailService($this->logger);
        $userService = new UserService($userRepo, $emailService, $this->logger);

        $userService->registerUser('Jane Doe', 'jane@example.com');

        // Clear previous logs
        $this->appender->clear();

        // Now test order creation - use user ID 1 which exists in the mock repository
        $paymentService = new \Switon\Logging\Tests\Fixtures\PaymentService($this->logger);
        $orderService = new OrderService($userRepo, $paymentService, $this->logger);

        // Act
        $orderService->createOrder(1, 99.99); // Use ID 1 which exists

        // Assert
        $entries = $this->appender->getEntries();

        // Debug: check if we have any entries
        $this->assertGreaterThan(0, count($entries), 'No log entries captured');

        // Debug: print all messages
        $messages = array_map(fn ($entry) => $entry->message, $entries);
        $hasCreatingOrder = array_filter($messages, fn ($msg) => str_contains($msg, 'Creating order'));
        $this->assertNotEmpty($hasCreatingOrder, 'Expected "Creating order" message not found. Available: ' . implode(', ', $messages));

        // Should have logged order creation start
        $creationLog = $this->findLogByMessage($entries, 'Creating order');
        $this->assertNotNull($creationLog);

        // Should have logged payment processing
        $paymentLog = $this->findLogByMessage($entries, 'Processing payment');
        $this->assertNotNull($paymentLog);

        // Since payment service has 80% success rate, we might get either result
        $successLog = $this->findLogByMessage($entries, 'Order created successfully');
        $failureLog = $this->findLogByMessage($entries, 'Payment processing failed');

        // Debug: print all log messages
        $messages = array_map(fn ($entry) => $entry->message, $entries);
        $this->assertNotEmpty($entries, 'No log entries found at all. Available messages: ' . implode(', ', $messages));

        // One of them should exist
        $this->assertTrue($successLog !== null || $failureLog !== null, 'No success or failure log found. Available messages: ' . implode(', ', $messages));
    }

    public function testErrorHandlingScenario(): void
    {
        // Arrange - Create a service that will throw an exception to test error handling
        new UserService(
            new \Switon\Logging\Tests\Fixtures\UserRepository($this->logger),
            new \Switon\Logging\Tests\Fixtures\EmailService($this->logger),
            $this->logger
        );

        // Create request with invalid data that will cause an error in the service layer
        // Use a mock repository that throws exception to simulate error scenario
        $request = new Request('POST', '/register', ['name' => 'Test', 'email' => 'test@example.com']);
        $response = new Response();

        // Create a repository that throws exception on save to simulate error
        $failingRepository = new class ($this->logger) extends \Switon\Logging\Tests\Fixtures\UserRepository {
            public function save(\Switon\Logging\Tests\Fixtures\User $user): void
            {
                throw new RuntimeException('Database connection failed');
            }
        };

        $failingUserService = new UserService($failingRepository, new \Switon\Logging\Tests\Fixtures\EmailService($this->logger), $this->logger);
        $controller = new UserController($failingUserService, $request, $response, $this->logger);

        // Act - This should handle the exception gracefully
        $controller->register();

        // Assert
        $entries = $this->appender->getEntries();

        // Should have logged the error
        $errorLogs = array_filter($entries, fn ($entry) => $entry->level === 'error');
        $this->assertGreaterThan(0, count($errorLogs), 'Should have logged error when exception occurs');

        // API response should indicate error
        $this->assertEquals(500, $response->getStatusCode(), 'Response should have 500 status code');
        $this->assertFalse($response->getBody()['success'], 'Response should indicate failure');
    }

    public function testGetUserScenario(): void
    {
        // Arrange
        $userService = new UserService(
            new \Switon\Logging\Tests\Fixtures\UserRepository($this->logger),
            new \Switon\Logging\Tests\Fixtures\EmailService($this->logger),
            $this->logger
        );

        $request = new Request('GET', '/user', ['id' => '1']);
        $response = new Response();

        $controller = new UserController($userService, $request, $response, $this->logger);

        // Act
        $controller->getUser();

        // Assert
        $entries = $this->appender->getEntries();

        // Should have logged user lookup
        $lookupLog = $this->findLogByMessage($entries, 'Getting user');
        $this->assertNotNull($lookupLog);
        $this->assertEquals('debug', $lookupLog->level);

        // Should have logged repository operation
        $repoLog = $this->findLogByMessage($entries, 'Finding user by ID');
        $this->assertNotNull($repoLog);

        // API response should be successful
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertTrue($response->getBody()['success']);
    }

    public function testGetNonexistentUserScenario(): void
    {
        // Arrange
        $userService = new UserService(
            new \Switon\Logging\Tests\Fixtures\UserRepository($this->logger),
            new \Switon\Logging\Tests\Fixtures\EmailService($this->logger),
            $this->logger
        );

        $request = new Request('GET', '/user', ['id' => '999']);
        $response = new Response();

        $controller = new UserController($userService, $request, $response, $this->logger);

        // Act
        $controller->getUser();

        // Assert
        $entries = $this->appender->getEntries();

        // Should have logged user not found warning
        $notFoundLog = $this->findLogByMessage($entries, 'User not found');
        $this->assertNotNull($notFoundLog);
        $this->assertEquals('warning', $notFoundLog->level);

        // API response should be 404
        $this->assertEquals(404, $response->getStatusCode());
        $this->assertFalse($response->getBody()['success']);
    }

    /**
     * Helper method to find a log entry by message content.
     */
    private function findLogByMessage(array $entries, string $message): ?\Switon\Logging\LogEntry
    {
        foreach ($entries as $entry) {
            if (str_contains($entry->message, $message)) {
                return $entry;
            }
        }
        return null;
    }
}
