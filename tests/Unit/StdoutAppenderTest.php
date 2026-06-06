<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Unit;

use Psr\Log\LogLevel;
use Switon\Logging\Appender\StdoutAppender;
use Switon\Logging\LogEntry;
use Switon\Logging\Tests\TestCase;

// Load bootstrap to ensure autoloader is loaded (required when using --no-configuration)

/**
 * Test cases for StdoutAppender class.
 *
 * Tests console output formatting and message routing to stdout.
 */
class StdoutAppenderTest extends TestCase
{
    protected StdoutAppender $appender;

    protected function setUp(): void
    {
        parent::setUp();

        // Create appender via make() to support property injection
        $this->appender = $this->make(StdoutAppender::class);
    }

    /**
     * Test that StdoutAppender outputs log entries to stdout.
     */
    public function testStdoutAppenderOutputsToStdout(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = 'Test log message';

        // Act - Capture output
        ob_start();
        $this->appender->append($entry);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('Test log message', $output, 'Output should contain log message');
        $this->assertStringContainsString('[info]', $output, 'Output should contain log level');
    }

    /**
     * Test that StdoutAppender formats log entries according to line format.
     */
    public function testStdoutAppenderFormatsLogEntries(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::ERROR, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = 'Error message';

        // Act
        ob_start();
        $this->appender->append($entry);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('[error]', $output, 'Output should contain log level');
        $this->assertStringContainsString('[Test.Category]', $output, 'Output should contain category');
        $this->assertStringContainsString('Error message', $output, 'Output should contain message');
    }

    /**
     * Test that StdoutAppender uses custom line format.
     */
    public function testStdoutAppenderUsesCustomLineFormat(): void
    {
        // Arrange
        $appender = $this->make(StdoutAppender::class, [
            'format' => '{level} - {message}',
        ]);

        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = 'Test message';

        // Act
        ob_start();
        $appender->append($entry);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('info - Test message', $output, 'Output should use custom format');
    }

    /**
     * Test that StdoutAppender handles missing log entry properties gracefully.
     */
    public function testStdoutAppenderHandlesMissingProperties(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->message = 'Test message';
        // Don't set category or location

        // Act
        ob_start();
        $this->appender->append($entry);
        $output = ob_get_clean();

        // Assert
        $this->assertStringContainsString('Test message', $output, 'Output should contain message');
        // Missing properties should be replaced with '-' in default format
        $this->assertStringContainsString('-', $output, 'Output should contain placeholder for missing properties');
    }

    /**
     * Test that StdoutAppender outputs all log levels.
     */
    public function testStdoutAppenderOutputsAllLevels(): void
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
            $entry = new LogEntry($level, 'test-host', 'Y-m-d H:i:s', microtime(true));
            $entry->category = 'Test.Category';
            $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
            $entry->message = "Message at $level";

            // Act
            ob_start();
            $this->appender->append($entry);
            $output = ob_get_clean();

            // Assert
            $this->assertStringContainsString("[$level]", $output, "Output should contain $level");
            $this->assertStringContainsString("Message at $level", $output, "Output should contain message for $level");
        }
    }

    /**
     * Test that StdoutAppender appends newline to each log entry.
     */
    public function testStdoutAppenderAppendsNewline(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = 'Test message';

        // Act
        ob_start();
        $this->appender->append($entry);
        $output = ob_get_clean();

        // Assert
        $this->assertStringEndsWith("\n", $output, 'Output should end with newline');
    }
}
