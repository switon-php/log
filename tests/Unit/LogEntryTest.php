<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Unit;

use Psr\Log\LogLevel;
use Switon\Logging\LogEntry;
use Switon\Logging\Logger;
use Switon\Logging\Tests\TestCase;

// Load bootstrap to ensure autoloader is loaded (required when using --no-configuration)

/**
 * Test cases for LogEntry class.
 */
class LogEntryTest extends TestCase
{
    public function testLogEntryCreation(): void
    {
        // Act
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));

        // Assert
        $this->assertSame(LogLevel::INFO, $entry->level, 'Level should be set correctly');
        $this->assertSame('test-host', $entry->hostname, 'Hostname should be set correctly');
        $this->assertIsFloat($entry->timestamp, 'Timestamp should be a float');
        $this->assertIsString($entry->time, 'Time should be a string');
    }

    public function testLogEntryTimestampPrecision(): void
    {
        // Act
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s.u', microtime(true));

        // Assert
        $this->assertGreaterThan(0, $entry->timestamp, 'Timestamp should be positive');
        $this->assertIsFloat($entry->timestamp, 'Timestamp should be a float for microsecond precision');
        // Verify timestamp is close to current time (within 1 second)
        $this->assertLessThan(
            1,
            abs($entry->timestamp - microtime(true)),
            'Timestamp should be close to current time'
        );
    }

    public function testLogEntryTimeFormatWithMilliseconds(): void
    {
        // Act
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s.' . Logger::MILLISECONDS, microtime(true));

        // Assert
        $this->assertIsString($entry->time, 'Time should be formatted as string');
        // Verify milliseconds are included (3 digits)
        $this->assertMatchesRegularExpression(
            '/\d{3}$/',
            $entry->time,
            'Time format should include 3-digit milliseconds'
        );
    }

    public function testLogEntryTimeFormatWithMicroseconds(): void
    {
        // Act
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s.' . Logger::MICROSECONDS, microtime(true));

        // Assert
        $this->assertIsString($entry->time, 'Time should be formatted as string');
        // Verify microseconds are included (6 digits)
        $this->assertMatchesRegularExpression(
            '/\d{6}$/',
            $entry->time,
            'Time format should include 6-digit microseconds'
        );
    }

    public function testSetLocationExtractsFileAndLine(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $trace = [
            'file' => '/path/to/TestFile.php',
            'line' => 42,
        ];

        // Act
        $entry->setLocation($trace);

        // Assert
        $this->assertSame('TestFile.php', $entry->file, 'File should be basename only');
        $this->assertSame(42, $entry->line, 'Line should be extracted correctly');
        $this->assertSame('TestFile.php:42', $entry->location, 'Location should be formatted correctly');
    }

    public function testSetLocationHandlesMissingFile(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $trace = [
            'line' => 42,
        ];

        // Act
        $entry->setLocation($trace);

        // Assert
        $this->assertSame('-', $entry->file, 'File should be "-" when missing');
        $this->assertSame(42, $entry->line, 'Line should be extracted correctly');
        $this->assertSame('-:42', $entry->location, 'Location should handle missing file');
    }

    public function testSetLocationHandlesMissingLine(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $trace = [
            'file' => '/path/to/TestFile.php',
        ];

        // Act
        $entry->setLocation($trace);

        // Assert
        $this->assertSame('TestFile.php', $entry->file, 'File should be extracted correctly');
        $this->assertSame(0, $entry->line, 'Line should be 0 when missing');
        $this->assertSame('TestFile.php:0', $entry->location, 'Location should handle missing line');
    }

    public function testSetLocationHandlesEmptyTrace(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $trace = [];

        // Act
        $entry->setLocation($trace);

        // Assert
        $this->assertSame('-', $entry->file, 'File should be "-" when trace is empty');
        $this->assertSame(0, $entry->line, 'Line should be 0 when trace is empty');
        $this->assertSame('-:0', $entry->location, 'Location should handle empty trace');
    }

    /** LogEntry extends stdClass */
    public function testLogEntrySupportsDynamicProperties(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));

        // Act
        $entry->customProperty = 'custom-value';
        $entry->anotherProperty = 123;

        // Assert
        $this->assertSame('custom-value', $entry->customProperty, 'Should support dynamic properties');
        $this->assertSame(123, $entry->anotherProperty, 'Should support multiple dynamic properties');
    }

    public function testLogEntryCategoryCanBeSet(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));

        // Act
        $entry->category = 'App.Controllers.UserController.index';

        // Assert
        $this->assertSame(
            'App.Controllers.UserController.index',
            $entry->category,
            'Category should be settable'
        );
    }

    public function testLogEntryMessageCanBeSet(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));

        // Act
        $entry->message = 'Test log message';

        // Assert
        $this->assertSame('Test log message', $entry->message, 'Message should be settable');
    }
}
