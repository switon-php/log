<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Unit;

use Psr\Log\LogLevel;
use Switon\Logging\Appender\MemoryAppender;
use Switon\Logging\LogEntry;
use Switon\Logging\Tests\TestCase;

/**
 * Test cases for MemoryAppender class.
 */
class MemoryAppenderTest extends TestCase
{
    protected MemoryAppender $appender;

    protected function setUp(): void
    {
        parent::setUp();
        $this->appender = $this->container->get(MemoryAppender::class);
        $this->appender->clear(); // Clear any previous test data
    }

    /**
     * Test that MemoryAppender stores log entries.
     */
    public function testMemoryAppenderStoresLogEntries(): void
    {
        // Arrange
        $entry1 = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry1->message = 'First message';

        $entry2 = new LogEntry(LogLevel::ERROR, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry2->message = 'Second message';

        // Act
        $this->appender->append($entry1);
        $this->appender->append($entry2);

        // Assert
        $logs = $this->appender->getLogs();
        $this->assertCount(2, $logs);
        $this->assertSame('First message', $logs[0]->message);
        $this->assertSame('Second message', $logs[1]->message);
    }

    /**
     * Test that MemoryAppender can be cleared.
     */
    public function testMemoryAppenderCanBeCleared(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->message = 'Test message';
        $this->appender->append($entry);

        // Act
        $this->appender->clear();

        // Assert
        $this->assertCount(0, $this->appender->getLogs());
    }

    /**
     * Test that MemoryAppender filters by level.
     */
    public function testMemoryAppenderFiltersByLevel(): void
    {
        // Arrange
        $info = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $info->message = 'Info message';

        $error = new LogEntry(LogLevel::ERROR, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $error->message = 'Error message';

        // Act
        $this->appender->append($info);
        $this->appender->append($error);

        // Assert
        $logs = $this->appender->getLogs();
        $errors = array_values(array_filter($logs, fn ($e) => $e->level === LogLevel::ERROR));
        $this->assertCount(1, $errors);
        $this->assertSame('Error message', $errors[0]->message);
    }
}
