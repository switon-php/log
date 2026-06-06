<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Unit;

use Psr\Log\LogLevel;
use ReflectionClass;
use Switon\Eventing\Attribute\EventLevel;
use Switon\Eventing\EventSilent;
use Switon\Eventing\Severity;
use Switon\Logging\Event\LoggerLogged;
use Switon\Logging\LogEntry;
use Switon\Logging\LoggerInterface;
use Switon\Logging\Tests\TestCase;
use Stringable;

// Load bootstrap to ensure autoloader is loaded (required when using --no-configuration)

/**
 * Test cases for LoggerLogged event class.
 *
 * Tests event serialization and event dispatching functionality.
 */
class LoggerLoggedTest extends TestCase
{
    /**
     * Test that LoggerLogged jsonSerialize returns correct data.
     */
    public function testLoggerLoggedJsonSerialize(): void
    {
        // Arrange
        $logger = $this->createStub(LoggerInterface::class);
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = 'Test message';

        $event = new LoggerLogged($logger, Severity::INFO->value, 'Test message', [], $entry);

        // Act
        $data = $event->jsonSerialize();

        // Assert
        $this->assertIsArray($data, 'jsonSerialize should return array');
        $this->assertArrayHasKey('level', $data, 'Should contain level');
        $this->assertArrayHasKey('message', $data, 'Should contain message');
        $this->assertArrayHasKey('category', $data, 'Should contain category');
        $this->assertArrayHasKey('location', $data, 'Should contain location');
        $this->assertSame(Severity::INFO->value, $data['level'], 'Level should match');
        $this->assertSame('Test message', $data['message'], 'Message should match');
        $this->assertSame('Test.Category', $data['category'], 'Category should match');
        $this->assertSame('Test.php:42', $data['location'], 'Location should match');
    }

    /**
     * Test that LoggerLogged jsonSerialize converts Stringable message to string.
     */
    public function testLoggerLoggedJsonSerializeWithStringableMessage(): void
    {
        // Arrange
        $logger = $this->createStub(LoggerInterface::class);
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = 'Test message';

        $stringable = new class () implements Stringable {
            public function __toString(): string
            {
                return 'Stringable message';
            }
        };

        $event = new LoggerLogged($logger, Severity::INFO->value, $stringable, [], $entry);

        // Act
        $data = $event->jsonSerialize();

        // Assert
        $this->assertSame('Stringable message', $data['message'], 'Stringable should be converted to string');
    }

    public function testLoggerLoggedImplementsSilentMarkerAndDeclaresDebugLevel(): void
    {
        $rClass = new ReflectionClass(LoggerLogged::class);
        $attributes = $rClass->getAttributes(EventLevel::class);

        $this->assertCount(1, $attributes);
        $this->assertSame(Severity::DEBUG, $attributes[0]->newInstance()->severity);
        $this->assertTrue($rClass->implementsInterface(EventSilent::class));
    }
}
