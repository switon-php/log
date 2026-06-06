<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Unit;

use Psr\Log\LogLevel;
use Switon\Logging\Formatter;
use Switon\Logging\LogEntry;
use Switon\Logging\Tests\TestCase;

/**
 * Test cases for Formatter class.
 *
 * Tests text and JSON formatting with various configurations.
 */
class FormatterTest extends TestCase
{
    protected Formatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->formatter = new Formatter();
    }

    /**
     * Test text format with default placeholders.
     */
    public function testTextFormatWithDefaultPlaceholders(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', 1705315845.123456);
        $entry->category = 'app.test';
        $entry->setLocation(['file' => 'Test.php', 'line' => 42]);
        $entry->message = 'Test message';

        // Act
        $result = $this->formatter->format($entry, '[{time}][{level}][{category}][{location}] {message}');

        // Assert
        $this->assertStringContainsString('[info]', $result);
        $this->assertStringContainsString('[app.test]', $result);
        $this->assertStringContainsString('[Test.php:42]', $result);
        $this->assertStringContainsString('Test message', $result);
        $this->assertStringEndsWith(PHP_EOL, $result);
    }

    /**
     * Test JSON format with empty braces (all fields).
     */
    public function testJsonFormatWithAllFields(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', 1705315845.123456);
        $entry->category = 'app.test';
        $entry->setLocation(['file' => 'Test.php', 'line' => 42]);
        $entry->message = 'Test message';

        // Act
        $result = $this->formatter->format($entry, '');
        $data = json_decode(trim($result), true);

        // Assert
        $this->assertIsArray($data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('level', $data);
        $this->assertArrayHasKey('category', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('location', $data);
        $this->assertArrayHasKey('hostname', $data);
        $this->assertSame('info', $data['level']);
        $this->assertSame('app.test', $data['category']);
        $this->assertSame('Test message', $data['message']);
        $this->assertMatchesRegularExpression('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z/', $data['timestamp']);
    }

    /**
     * Test JSON format with custom field selection.
     */
    public function testJsonFormatWithCustomFields(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::ERROR, 'test-host', 'Y-m-d H:i:s', 1705315845.123456);
        $entry->category = 'app.test';
        $entry->setLocation(['file' => 'Test.php', 'line' => 42]);
        $entry->message = 'Error message';

        // Act
        $result = $this->formatter->format($entry, 'timestamp,level,message');
        $data = json_decode(trim($result), true);

        // Assert
        $this->assertIsArray($data);
        $this->assertArrayHasKey('timestamp', $data);
        $this->assertArrayHasKey('level', $data);
        $this->assertArrayHasKey('message', $data);
        $this->assertArrayNotHasKey('category', $data);
        $this->assertArrayNotHasKey('location', $data);
        $this->assertArrayNotHasKey('hostname', $data);
        $this->assertSame('error', $data['level']);
        $this->assertSame('Error message', $data['message']);
    }

    /**
     * Test JSON format with extra field.
     */
    public function testJsonFormatWithExtraField(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', 1705315845.123456);
        $entry->category = 'app.test';
        $entry->setLocation(['file' => 'Test.php', 'line' => 42]);
        $entry->message = 'Test message';
        $entry->extra = ['user_id' => 123, 'ip' => '192.168.1.1'];

        // Act
        $result = $this->formatter->format($entry, '');
        $data = json_decode(trim($result), true);

        // Assert
        $this->assertArrayHasKey('extra', $data);
        $this->assertIsArray($data['extra']);
        $this->assertSame(123, $data['extra']['user_id']);
        $this->assertSame('192.168.1.1', $data['extra']['ip']);
    }

    /**
     * Test JSON format with explicit extra placement.
     */
    public function testJsonFormatWithExplicitExtraPlacement(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', 1705315845.123456);
        $entry->category = 'app.test';
        $entry->setLocation(['file' => 'Test.php', 'line' => 42]);
        $entry->message = 'Test message';
        $entry->extra = ['request_id' => 'abc-123'];

        // Act
        $result = $this->formatter->format($entry, 'timestamp,level,extra,message');
        $data = json_decode(trim($result), true);

        // Assert
        $keys = array_keys($data);
        $this->assertSame(['timestamp', 'level', 'extra', 'message'], $keys);
        $this->assertSame(['request_id' => 'abc-123'], $data['extra']);
    }

    /**
     * Test text format with extra field appended as JSON.
     */
    public function testTextFormatWithExtraField(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', 1705315845.123456);
        $entry->category = 'app.test';
        $entry->setLocation(['file' => 'Test.php', 'line' => 42]);
        $entry->message = 'Test message';
        $entry->extra = ['user_id' => 456];

        // Act
        $result = $this->formatter->format($entry, '{level} {message}');

        // Assert
        $this->assertStringContainsString('info Test message', $result);
        $this->assertStringContainsString('{"user_id":456}', $result);
    }

    /**
     * Test text format with multi-line message.
     */
    public function testTextFormatWithMultiLineMessage(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::ERROR, 'test-host', 'Y-m-d H:i:s', 1705315845.123456);
        $entry->category = 'app.test';
        $entry->setLocation(['file' => 'Test.php', 'line' => 42]);
        $entry->message = "Line 1\nLine 2\nLine 3";

        // Act
        $result = $this->formatter->format($entry, '[{level}] {message}');

        // Assert
        $lines = explode("\n", trim($result));
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('[error] Line 1', $lines[0]);
        $this->assertStringContainsString('[error] Line 2', $lines[1]);
        $this->assertStringContainsString('[error] Line 3', $lines[2]);
    }

    /**
     * Test JSON format with timestamp_unix field.
     */
    public function testJsonFormatWithTimestampUnix(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', 1705315845.123456);
        $entry->category = 'app.test';
        $entry->setLocation(['file' => 'Test.php', 'line' => 42]);
        $entry->message = 'Test message';

        // Act
        $result = $this->formatter->format($entry, 'timestamp_unix,level,message');
        $data = json_decode(trim($result), true);

        // Assert
        $this->assertArrayHasKey('timestamp_unix', $data);
        $this->assertSame(1705315845.123456, $data['timestamp_unix']);
        $this->assertArrayNotHasKey('timestamp', $data);
    }

    /**
     * Test text format with missing properties.
     */
    public function testTextFormatWithMissingProperties(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', 1705315845.123456);
        $entry->message = 'Test message';
        // Don't set category or location

        // Act
        $result = $this->formatter->format($entry, '[{category}][{location}] {message}');

        // Assert
        $this->assertStringContainsString('[-][-]', $result);
        $this->assertStringContainsString('Test message', $result);
    }

    /**
     * Test JSON format when message is empty and extra has structured data.
     */
    public function testJsonFormatOutputsExtraWhenMessageEmpty(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::DEBUG, 'test-host', 'Y-m-d H:i:s', 1705315845.123456);
        $entry->category = 'switon.http.server.close';
        $entry->setLocation(['file' => 'EventLogger.php', 'line' => 128]);
        $entry->message = '';
        $entry->extra = ['fd' => 1, 'reactor_id' => 3];

        // Act
        $result = $this->formatter->format($entry, '');
        $data = json_decode(trim($result), true);

        // Assert
        $this->assertSame('', $data['message']);
        $this->assertArrayHasKey('extra', $data);
        $this->assertSame(1, $data['extra']['fd'] ?? null);
        $this->assertSame(3, $data['extra']['reactor_id'] ?? null);
    }

    /**
     * Test JSON format without extra when extra is empty.
     */
    public function testJsonFormatWithoutExtraWhenEmpty(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', 1705315845.123456);
        $entry->category = 'app.test';
        $entry->setLocation(['file' => 'Test.php', 'line' => 42]);
        $entry->message = 'Test message';
        $entry->extra = [];

        // Act
        $result = $this->formatter->format($entry, '');
        $data = json_decode(trim($result), true);

        // Assert
        $this->assertArrayNotHasKey('extra', $data);
    }
}
