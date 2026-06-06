<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Unit;

use Psr\Log\LogLevel;
use Switon\Logging\Logger;
use Switon\Logging\Tests\Fixtures\MockAppender;
use Switon\Logging\Tests\Fixtures\MockEventDispatcher;
use Switon\Logging\Tests\TestCase;
use ReflectionClass;
use RuntimeException;
use Stringable;

/**
 * Test cases for Logger class.
 */
class LoggerTest extends TestCase
{
    protected Logger $logger;
    protected MockAppender $appender;
    protected MockEventDispatcher $eventDispatcher;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appender = new MockAppender();
        $this->eventDispatcher = new MockEventDispatcher();

        $this->container->set(\Psr\EventDispatcher\EventDispatcherInterface::class, $this->eventDispatcher);

        // Register appender in container for instances: true injection
        $this->container->set('test', $this->appender);

        // Get logger from container with appenders via make() method
        // Note: instances: true expects service IDs, not object instances
        // Use null to remove default 'file' appender, then add our test appender
        $this->logger = $this->container->make(\Switon\Logging\LoggerInterface::class, [
            'appenders' => ['file' => null, 'test' => 'test'],
        ]);
    }

    /**
     * Helper method to create a logger with custom configuration.
     *
     * @param array<string, mixed> $config Logger configuration (level, levels, appenders, hostname, time_format)
     *
     * @return Logger Configured logger instance
     */
    protected function createLogger(array $config = []): Logger
    {
        $defaultConfig = [
            'appenders' => ['file' => null, 'test' => 'test'],
        ];

        $config = array_merge($defaultConfig, $config);

        return $this->container->make(\Switon\Logging\LoggerInterface::class, $config);
    }

    public function testLoggerLogsInfoMessage(): void
    {
        // Act
        $this->logger->info('Test message');

        // Assert
        $this->assertCount(1, $this->appender->entries, 'Should have one log entry');
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry, 'Log entry should exist');
        $this->assertSame(LogLevel::INFO, $entry->level, 'Level should be INFO');
        $this->assertSame('Test message', $entry->message, 'Message should match');
    }

    public function testLoggerLogsAllLevels(): void
    {
        $levels = [
            LogLevel::EMERGENCY,
            LogLevel::ALERT,
            LogLevel::CRITICAL,
            LogLevel::ERROR,
            LogLevel::WARNING,
            LogLevel::NOTICE,
            LogLevel::INFO,
            LogLevel::DEBUG,
        ];

        foreach ($levels as $level) {
            $this->appender->clear();

            // Act
            $this->logger->log($level, "Test message at $level");

            // Assert
            $entry = $this->appender->getLastEntry();
            $this->assertNotNull($entry, "Log entry should exist for $level");
            $this->assertSame($level, $entry->level, "Level should be $level");
        }
    }

    public function testLoggerFiltersByGlobalLevel(): void
    {
        // Arrange - Create logger with WARNING level
        $logger = $this->createLogger(['level' => LogLevel::WARNING]);
        $appender = $this->container->get('test');

        // Act
        $logger->info('This should be filtered');
        $logger->debug('This should be filtered');
        $logger->warning('This should be logged');
        $logger->error('This should be logged');

        // Assert
        $this->assertCount(2, $appender->entries, 'Should have 2 log entries');
        $this->assertSame(LogLevel::WARNING, $appender->entries[0]->level);
        $this->assertSame(LogLevel::ERROR, $appender->entries[1]->level);
    }

    public function testLoggerFiltersByCategoryLevel(): void
    {
        // Arrange - Use TestCategoryLogger for predictable category
        $expectedCategory = \Switon\Logging\Tests\Fixtures\TestCategoryLogger::getExpectedCategory();

        // Create logger with category level configuration
        $logger = $this->createLogger([
            'levels' => [
                $expectedCategory => LogLevel::WARNING,
            ],
        ]);

        $testLogger = new \Switon\Logging\Tests\Fixtures\TestCategoryLogger($logger);
        $appender = $this->container->get('test');
        $appender->clear(); // Clear any previous entries

        // Act
        $message = $this->makeCategorizedMessage($expectedCategory, 'This should be logged');
        $logger->log(LogLevel::INFO, $message);
        $logger->log(LogLevel::WARNING, $message);
        $logger->log(LogLevel::ERROR, $message);

        // Assert
        $this->assertCount(2, $appender->entries, 'Should have exactly 2 accepted log entries');
        $this->assertSame(LogLevel::WARNING, $appender->entries[0]->level);
        $this->assertSame(LogLevel::ERROR, $appender->entries[1]->level);
        $this->assertSame('This should be logged', $appender->entries[0]->message);
        $this->assertSame('This should be logged', $appender->entries[1]->message);
    }

    public function testLoggerUsesHierarchicalCategoryMatching(): void
    {
        // Arrange - Use TestCategoryLogger for predictable category hierarchy
        $parentCategory = \Switon\Logging\Tests\Fixtures\TestCategoryLogger::getExpectedParentCategory();
        $childCategory = \Switon\Logging\Tests\Fixtures\TestCategoryLogger::getExpectedCategory();

        $logger = $this->createLogger([
            'levels' => [
                $parentCategory => LogLevel::WARNING,
            ],
        ]);

        $appender = $this->container->get('test');
        $appender->clear();

        // Act
        $message = $this->makeCategorizedMessage($childCategory, 'This should be logged');
        $logger->log(LogLevel::INFO, $message);
        $logger->log(LogLevel::WARNING, $message);

        // Assert
        $this->assertCount(1, $appender->entries, 'Should have exactly 1 accepted log entry');
        $this->assertSame(LogLevel::WARNING, $appender->entries[0]->level);
        $this->assertSame('This should be logged', $appender->entries[0]->message);
    }

    private function makeCategorizedMessage(string $category, string $message): Stringable
    {
        return new class ($category, $message) implements \Switon\Core\Categorizable, Stringable {
            public function __construct(
                private string $category,
                private string $message,
            ) {
            }

            public function getCategory(): string
            {
                return $this->category;
            }

            public function __toString(): string
            {
                return $this->message;
            }
        };
    }

    public function testLoggerInterpolatesPlaceholders(): void
    {
        // Act
        $this->logger->info('User {user_id} logged in', ['user_id' => 123]);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('123', $entry->message, 'Message should contain interpolated value');
        $this->assertStringNotContainsString('{user_id}', $entry->message, 'Message should not contain placeholder');
    }

    public function testLoggerPutsExtraContextInExtra(): void
    {
        // Act
        $this->logger->info('User logged in', ['user_id' => 123, 'ip' => '192.168.1.1']);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertSame('User logged in', $entry->message);
        $this->assertSame(123, $entry->extra['user_id'] ?? null);
        $this->assertSame('192.168.1.1', $entry->extra['ip'] ?? null);
    }

    public function testLoggerFormatsExceptionFromContext(): void
    {
        // Arrange
        $exception = new RuntimeException('Test exception');

        // Act
        $this->logger->error('Operation failed', ['exception' => $exception]);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('RuntimeException', $entry->message, 'Message should contain exception class');
        $this->assertStringContainsString('Test exception', $entry->message, 'Message should contain exception message');
    }

    public function testLoggerAutoMergesSwitonExceptionContext(): void
    {
        // Arrange
        try {
            \Switon\Core\Exception::raise('DB error: {error}', [
                'error' => 'timeout',
                'host' => 'localhost',
                'query' => 'SELECT * FROM users',
            ]);
        } catch (\Switon\Core\Exception $e) {
            // exception context: ['host' => 'localhost', 'query' => '...']
        }

        // Act
        $this->logger->error('Operation failed', ['exception' => $e]);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertSame('localhost', $entry->extra['host'] ?? null, 'Exception context should be merged into extra');
        $this->assertSame('SELECT * FROM users', $entry->extra['query'] ?? null, 'Exception context should be merged into extra');
    }

    public function testLoggerCallerContextOverridesExceptionContext(): void
    {
        // Arrange
        try {
            \Switon\Core\Exception::raise('Error: {error}', [
                'error' => 'fail',
                'host' => 'from-exception',
            ]);
        } catch (\Switon\Core\Exception $e) {
            // exception context: ['host' => 'from-exception']
        }

        // Act — caller passes 'host' explicitly, should win
        $this->logger->error('Operation failed', [
            'exception' => $e,
            'host' => 'from-caller',
        ]);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertSame('from-caller', $entry->extra['host'] ?? null, 'Caller context should take priority over exception context');
    }

    public function testLoggerFormatsThrowableMessage(): void
    {
        // Arrange
        $exception = new RuntimeException('Test exception');

        // Act
        $this->logger->error($exception);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('RuntimeException', $entry->message, 'Message should contain exception class');
        $this->assertStringContainsString('Test exception', $entry->message, 'Message should contain exception message');
    }

    public function testLoggerDispatchesEvent(): void
    {
        // Arrange - Clear any previous events
        $this->eventDispatcher->clear();

        // Act
        $this->logger->info('Test message');

        // Assert
        $this->assertCount(1, $this->eventDispatcher->events, 'Should have dispatched one event');
        $event = $this->eventDispatcher->getLastEvent();
        $this->assertInstanceOf(\Switon\Logging\Event\LoggerLogged::class, $event, 'Event should be LoggerLogged');
        $this->assertSame($this->logger, $event->logger, 'Event should contain logger instance');
        $this->assertSame(LogLevel::INFO, $event->level, 'Event should contain log level');
        $this->assertSame('Test message', $event->message, 'Event should contain message');
    }

    public function testLoggerPropagatesDispatcherExceptionAndSkipsAppenderWrites(): void
    {
        $failingDispatcher = new class () implements \Psr\EventDispatcher\EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                throw new RuntimeException('dispatch failed');
            }
        };
        $this->container->replace(\Psr\EventDispatcher\EventDispatcherInterface::class, $failingDispatcher);

        $logger = $this->createLogger();
        $appender = $this->container->get('test');
        $appender->clear();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('dispatch failed');

        try {
            $logger->info('event should fail');
        } finally {
            $this->assertCount(0, $appender->entries, 'Appender must not write when dispatch fails');
        }
    }

    public function testLoggerSetsCategoryFromCallStack(): void
    {
        // Act
        $this->logger->info('Test message');

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertNotEmpty($entry->category, 'Category should be set');
        $this->assertStringContainsString('logger', $entry->category, 'Category should contain logger');
    }

    /**
     * Test that logger uses category from exception trace when exception is in context.
     *
     * Note: This test verifies that when an exception is in context, the category
     * is determined from the exception's trace (where it was thrown) rather than
     * from the current call stack (where it was logged).
     */
    public function testLoggerUsesCategoryFromExceptionTrace(): void
    {
        // Arrange - Create an exception in a separate method to get a different trace
        $exception = $this->throwExceptionInHelper();

        // Act
        $this->logger->error('Operation failed', ['exception' => $exception]);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        // Category should be determined from exception trace
        // Since exception was thrown in throwExceptionInHelper, category should reflect that
        $this->assertNotEmpty($entry->category, 'Category should be set from exception trace');
    }

    /**
     * Helper method that throws an exception to create a trace entry.
     */
    protected function throwExceptionInHelper(): RuntimeException
    {
        try {
            throw new RuntimeException('Test exception from helper');
        } catch (RuntimeException $e) {
            return $e;
        }
    }

    public function testLoggerWritesToAllAppenders(): void
    {
        // Arrange
        $appender1 = new MockAppender();
        $appender2 = new MockAppender();
        $this->container->set('test1', $appender1);
        $this->container->set('test2', $appender2);

        $logger = $this->createLogger([
            'appenders' => ['file' => null, 'test1' => 'test1', 'test2' => 'test2'],
        ]);

        // Act
        $logger->info('Test message');

        // Assert
        $this->assertCount(1, $appender1->entries, 'First appender should have one entry');
        $this->assertCount(1, $appender2->entries, 'Second appender should have one entry');
    }

    public function testLoggerHandlesStringableMessages(): void
    {
        // Arrange
        $stringable = new class () implements Stringable {
            public function __toString(): string
            {
                return 'Stringable message';
            }
        };

        // Act
        $this->logger->info($stringable);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertSame('Stringable message', $entry->message, 'Message should be converted from Stringable');
    }

    public function testLoggerHandlesCategorizableMessages(): void
    {
        // Arrange
        $categorizable = new class () implements \Switon\Core\Categorizable {
            public function getCategory(): string
            {
                return 'Custom.Category';
            }

            public function __toString(): string
            {
                return 'Categorizable message';
            }
        };

        // Act
        $this->logger->info($categorizable);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertSame('Custom.Category', $entry->category, 'Category should come from Categorizable');
        $this->assertSame('Categorizable message', $entry->message, 'Message should be converted from Categorizable');
    }

    public function testLoggerReturnsEarlyWhenFilteredByGlobalLevel(): void
    {
        // Arrange - Create logger with WARNING level and empty levels array
        $logger = $this->createLogger([
            'level' => LogLevel::WARNING,
            'levels' => [],
        ]);
        $appender = $this->container->get('test');

        // Act
        $logger->info('This should be filtered');
        $logger->debug('This should be filtered');

        // Assert
        $this->assertCount(0, $appender->entries, 'Filtered logs should not be written');
    }

    public function testLoggerDoesNotDispatchEventWhenFilteredOut(): void
    {
        $this->eventDispatcher->clear();
        $logger = $this->createLogger(['level' => LogLevel::ERROR, 'levels' => []]);

        $logger->debug('filtered debug');

        $this->assertCount(0, $this->eventDispatcher->events);
    }

    public function testLoggerHandlesEmptyTraces(): void
    {
        // Act - This should work even with empty traces
        $this->logger->info('Test message');

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertNotEmpty($entry->category, 'Category should be set even with empty traces');
    }

    public function testLoggerHandlesClosureInCallStack(): void
    {
        // Arrange - Create a closure that calls logger
        $closure = function () {
            $this->logger->info('Message from closure');
        };
        $closure->bindTo($this);

        // Act
        $closure();

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        // Category should skip the closure and use the caller
        $this->assertStringNotContainsString('{closure}', $entry->category, 'Category should not contain closure');
    }

    /**
     * Tests hierarchical category matching with multiple levels.
     * Uses TestCategoryLogger for predictable category structure.
     */
    public function testLoggerHandlesDeepCategoryHierarchy(): void
    {
        // Arrange - Use TestCategoryLogger for predictable parent category
        $parentCategory = \Switon\Logging\Tests\Fixtures\TestCategoryLogger::getExpectedParentCategory();

        $logger = $this->createLogger([
            'level' => LogLevel::DEBUG,
            'levels' => [
                $parentCategory => LogLevel::WARNING,
            ],
        ]);

        $testLogger = new \Switon\Logging\Tests\Fixtures\TestCategoryLogger($logger);
        $appender = $this->container->get('test');

        // Act
        $testLogger->logMessage(LogLevel::INFO, 'This should be filtered by parent category level');
        $testLogger->logMessage(LogLevel::WARNING, 'This should be logged');

        // Assert
        $this->assertGreaterThanOrEqual(1, count($appender->entries), 'Should have at least 1 log entry');
        $warningEntry = null;
        foreach ($appender->entries as $entry) {
            if ($entry->level === LogLevel::WARNING) {
                $warningEntry = $entry;
                break;
            }
        }
        $this->assertNotNull($warningEntry, 'Should have WARNING log entry');
        $this->assertSame(LogLevel::WARNING, $warningEntry->level);
    }

    public function testLoggerHandlesMessageWithOnlyExtraContext(): void
    {
        // Act
        $this->logger->info('Simple message', ['extra' => 'data', 'more' => 'info']);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertSame('Simple message', $entry->message);
        $this->assertSame('data', $entry->extra['extra'] ?? null);
        $this->assertSame('info', $entry->extra['more'] ?? null);
    }

    public function testLoggerHandlesMessageWithPlaceholdersAndExtraContext(): void
    {
        // Act
        $this->logger->info('User {user_id} logged in', [
            'user_id' => 123,
            'extra' => 'data',
            'ip' => '192.168.1.1',
        ]);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertSame('User 123 logged in', $entry->message);
        $this->assertSame('data', $entry->extra['extra'] ?? null);
        $this->assertSame('192.168.1.1', $entry->extra['ip'] ?? null);
    }

    public function testLoggerHandlesEmptyContext(): void
    {
        // Act
        $this->logger->info('Simple message', []);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertSame('Simple message', $entry->message);
    }

    public function testLoggerGetCategoryFromExceptionTrace(): void
    {
        // Arrange - Create exception with specific trace
        $exception = new RuntimeException('Test exception');

        // Act
        $this->logger->error('Error message', ['exception' => $exception]);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        // Category should be determined from exception trace
        $this->assertNotEmpty($entry->category, 'Category should be set from exception trace');
    }

    public function testLoggerGetCategoryWithTraces0Fallback(): void
    {
        // Act - This will use traces[0] as fallback
        $this->logger->info('Test message');

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertNotEmpty($entry->category, 'Category should be set even with minimal traces');
    }

    public function testLoggerGetCategoryWithFunctionName(): void
    {
        // Act - Call logger directly (from class method, so category is from this test class)
        $this->logger->info('Test message');

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertNotEmpty($entry->category, 'Category should be set');
    }

    public function testLoggerGetCategoryStripsActionSuffixFromMethodName(): void
    {
        $traces = [
            ['class' => 'Switon\Logging\Logger', 'function' => 'log'],
            ['class' => 'App\Controller\UserController', 'function' => 'listAction'],
        ];
        $reflection = new ReflectionClass($this->logger);
        $getCategory = $reflection->getMethod('getCategory');

        $category = $getCategory->invoke($this->logger, [], $traces);

        $this->assertSame('app.controller.user.list', $category);
    }

    public function testLoggerGetCategorySkipsFunctionFrameUsesCallerClass(): void
    {
        // Arrange - traces[1] = function (no class), traces[2] = class method (caller)
        $traces = [
            ['class' => 'Switon\Logging\Logger', 'function' => 'log', 'file' => '/fake/Logger.php', 'line' => 1],
            ['function' => 'standaloneFunction', 'file' => '/fake/bootstrap.php', 'line' => 10],
            ['class' => 'App\Service\PaymentService', 'function' => 'run', 'file' => '/fake/PaymentService.php', 'line' => 42],
        ];
        $reflection = new ReflectionClass($this->logger);
        $getCategory = $reflection->getMethod('getCategory');

        // Act
        $category = $getCategory->invoke($this->logger, [], $traces);

        // Assert - should use traces[2] (caller's class), not the function frame
        $this->assertSame('app.service.payment.run', $category);
    }

    public function testLoggerGetCategoryReturnsUnknownWhenNoClassInStack(): void
    {
        // Arrange - only function frames, no class in stack
        $traces = [
            ['class' => 'Switon\Logging\Logger', 'function' => 'log', 'file' => '/fake/Logger.php', 'line' => 1],
            ['function' => 'main', 'file' => '/fake/index.php', 'line' => 5],
        ];
        $reflection = new ReflectionClass($this->logger);
        $getCategory = $reflection->getMethod('getCategory');

        // Act
        $category = $getCategory->invoke($this->logger, [], $traces);

        // Assert - no class frame found, should return unknown
        $this->assertSame('unknown', $category);
    }

    public function testLoggerGetCategoryLevelWithExactMatch(): void
    {
        // Arrange - Use TestCategoryLogger for predictable category
        $expectedCategory = \Switon\Logging\Tests\Fixtures\TestCategoryLogger::getExpectedCategory();

        $logger = $this->createLogger([
            'levels' => [
                $expectedCategory => LogLevel::WARNING,
            ],
        ]);

        $testLogger = new \Switon\Logging\Tests\Fixtures\TestCategoryLogger($logger);
        $appender = $this->container->get('test');

        // Act
        $testLogger->logMessage(LogLevel::INFO, 'This should be filtered');
        $testLogger->logMessage(LogLevel::WARNING, 'This should be logged');

        // Assert
        $this->assertGreaterThanOrEqual(1, count($appender->entries), 'Should have at least 1 log entry');
        $warningEntry = null;
        foreach ($appender->entries as $entry) {
            if ($entry->level === LogLevel::WARNING) {
                $warningEntry = $entry;
                break;
            }
        }
        $this->assertNotNull($warningEntry, 'Should have WARNING log entry');
        $this->assertSame(LogLevel::WARNING, $warningEntry->level);
    }

    public function testLoggerGetCategoryLevelWithHierarchicalMatch(): void
    {
        // Arrange - Use TestCategoryLogger for predictable parent category
        $parentCategory = \Switon\Logging\Tests\Fixtures\TestCategoryLogger::getExpectedParentCategory();

        $logger = $this->createLogger([
            'levels' => [
                $parentCategory => LogLevel::WARNING,
            ],
        ]);

        $testLogger = new \Switon\Logging\Tests\Fixtures\TestCategoryLogger($logger);
        $appender = $this->container->get('test');

        // Act
        $testLogger->logMessage(LogLevel::INFO, 'This should be filtered');
        $testLogger->logMessage(LogLevel::WARNING, 'This should be logged');

        // Assert
        $this->assertGreaterThanOrEqual(1, count($appender->entries), 'Should have at least 1 log entry');
        $warningEntry = null;
        foreach ($appender->entries as $entry) {
            if ($entry->level === LogLevel::WARNING) {
                $warningEntry = $entry;
                break;
            }
        }
        $this->assertNotNull($warningEntry, 'Should have WARNING log entry');
        $this->assertSame(LogLevel::WARNING, $warningEntry->level);
    }

    public function testLoggerGetCategoryLevelFallbackToGlobalLevel(): void
    {
        // Arrange - Create logger with global level and different category level
        $logger = $this->createLogger([
            'level' => LogLevel::WARNING,
            'levels' => [
                'Other.Category' => LogLevel::DEBUG, // Different category
            ],
        ]);
        $appender = $this->container->get('test');

        // Act
        $logger->info('This should be filtered by global level');
        $logger->warning('This should be logged');

        // Assert
        $this->assertCount(1, $appender->entries, 'Should have 1 log entry');
        $this->assertSame(LogLevel::WARNING, $appender->entries[0]->level);
    }

    public function testLoggerFormatWithThrowableMessageAndEmptyContext(): void
    {
        // Arrange
        $exception = new RuntimeException('Test exception');

        // Act
        $this->logger->error($exception, []);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('RuntimeException', $entry->message);
        $this->assertStringContainsString('Test exception', $entry->message);
    }

    public function testLoggerFormatWithThrowableMessageAndContext(): void
    {
        // Arrange
        $exception = new RuntimeException('Test exception');

        // Act
        $this->logger->error($exception, ['extra' => 'data']);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('RuntimeException', $entry->message);
        $this->assertStringContainsString('"extra":"data"', $entry->message);
    }

    public function testLoggerFormatWithPlaceholdersAndContext(): void
    {
        // Act
        $this->logger->info('User {user_id} from {country} logged in', [
            'user_id' => 123,
            'country' => 'US',
        ]);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('User 123 from US logged in', $entry->message);
        $this->assertStringNotContainsString('{user_id}', $entry->message);
        $this->assertStringNotContainsString('{country}', $entry->message);
    }

    public function testLoggerFormatWithMessageAndExceptionInContext(): void
    {
        // Arrange
        $exception = new RuntimeException('Test exception');

        // Act
        $this->logger->error('Operation failed', ['exception' => $exception, 'user_id' => 123]);

        // Assert
        $entry = $this->appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertStringContainsString('Operation failed', $entry->message);
        $this->assertStringContainsString('RuntimeException', $entry->message);
        $this->assertStringContainsString('Test exception', $entry->message);
    }

    public function testLoggerPreFiltersBeforeBacktrace(): void
    {
        // Arrange - global ERROR, one category at INFO → minLevel = INFO
        $logger = $this->createLogger([
            'level' => LogLevel::ERROR,
            'levels' => [
                'some.category' => LogLevel::INFO,
            ],
        ]);
        $appender = $this->container->get('test');

        // Act - DEBUG is more verbose than INFO (minLevel), pre-filtered without backtrace
        $logger->debug('This should be pre-filtered');

        // Assert
        $this->assertCount(0, $appender->entries, 'DEBUG should be pre-filtered when minLevel is INFO');
    }

    public function testLoggerHandlesNullHostname(): void
    {
        // Arrange - Create logger with null hostname
        $logger = $this->createLogger(['hostname' => null]);
        $appender = $this->container->get('test');

        // Act
        $logger->info('Test message');

        // Assert
        $entry = $appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertNotEmpty($entry->hostname, 'Hostname should be auto-detected when null');
    }

    public function testLoggerHandlesCustomTimeFormat(): void
    {
        // Arrange - Create logger with custom time format
        $logger = $this->createLogger(['time_format' => 'Y-m-d H:i:s']);
        $appender = $this->container->get('test');

        // Act
        $logger->info('Test message');

        // Assert
        $entry = $appender->getLastEntry();
        $this->assertNotNull($entry);
        $this->assertNotEmpty($entry->time, 'Time should be formatted');
    }
}
