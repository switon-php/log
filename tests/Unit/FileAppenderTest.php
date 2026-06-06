<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Unit;

use Psr\Log\LogLevel;
use Switon\Core\App;
use Switon\Core\Exception\CreateDirectoryFailedException;
use Switon\Core\FilesystemInterface;
use Switon\Core\SceneManagerInterface;
use Switon\Logging\Appender\FileAppender;
use Switon\Logging\Event\AppenderWriteFailed;
use Switon\Logging\LogEntry;
use Switon\Logging\Tests\Fixtures\MockEventDispatcher;
use Switon\Logging\Tests\TestCase;

use function file_get_contents;
use function sys_get_temp_dir;
use function unlink;

// Load bootstrap to ensure autoloader is loaded (required when using --no-configuration)

/**
 * Test cases for FileAppender class.
 *
 * Tests file-based log output with path alias support and line formatting.
 */
class FileAppenderTest extends TestCase
{
    protected FileAppender $appender;
    protected string $testFile;
    protected string $sceneFile;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testFile = sys_get_temp_dir() . '/switon_log_test_' . bin2hex(random_bytes(8)) . '.log';
        $this->sceneFile = sys_get_temp_dir() . '/switon_log_scene_test_' . bin2hex(random_bytes(8)) . '.log';

        $this->container->remove(App::class);
        $this->container->set(App::class, ['id' => 'test-app']);

        $this->appender = $this->make(FileAppender::class, [
            'file' => $this->testFile,
        ]);
    }

    protected function tearDown(): void
    {
        // Clean up test file
        if (file_exists($this->testFile)) {
            @unlink($this->testFile);
        }
        if (file_exists($this->sceneFile)) {
            @unlink($this->sceneFile);
        }

        parent::tearDown();
    }

    /**
     * Test that FileAppender writes log entries to file.
     */
    public function testFileAppenderWritesToFile(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = 'Test log message';

        // Act
        $this->appender->append($entry);

        // Assert
        $this->assertFileExists($this->testFile, 'Log file should be created');
        $content = file_get_contents($this->testFile);
        $this->assertStringContainsString('Test log message', $content, 'File should contain log message');
    }

    /**
     * Test that FileAppender formats log entries according to line format.
     */
    public function testFileAppenderFormatsLogEntries(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::ERROR, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = 'Error message';

        // Act
        $this->appender->append($entry);

        // Assert
        $content = file_get_contents($this->testFile);
        $this->assertStringContainsString('[error]', $content, 'File should contain log level');
        $this->assertStringContainsString('[Test.Category]', $content, 'File should contain category');
        $this->assertStringContainsString('Error message', $content, 'File should contain message');
    }

    /**
     * Test that FileAppender handles multi-line messages.
     */
    public function testFileAppenderHandlesMultiLineMessages(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = "Line 1\nLine 2\nLine 3";

        // Act
        $this->appender->append($entry);

        // Assert
        $content = file_get_contents($this->testFile);
        $lines = explode("\n", trim($content));
        $this->assertGreaterThan(1, count($lines), 'Multi-line message should create multiple lines');
        // Each line should have the log prefix
        foreach ($lines as $line) {
            if (!empty($line)) {
                $this->assertStringContainsString('[', $line, 'Each line should have log prefix');
            }
        }
    }

    /**
     * Test that FileAppender creates directory if it doesn't exist.
     */
    public function testFileAppenderCreatesDirectory(): void
    {
        // Arrange
        $testDir = sys_get_temp_dir() . '/switon_log_test_dir_' . bin2hex(random_bytes(8));
        $testFile = $testDir . '/test.log';

        $appender = $this->make(FileAppender::class, [
            'file' => $testFile,
        ]);

        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = 'Test message';

        // Act
        $appender->append($entry);

        // Assert
        $this->assertDirectoryExists($testDir, 'Directory should be created');
        $this->assertFileExists($testFile, 'File should be created in new directory');

        // Cleanup
        @unlink($testFile);
        @rmdir($testDir);
    }

    /**
     * Test that FileAppender appends to existing file.
     */
    public function testFileAppenderAppendsToExistingFile(): void
    {
        // Arrange
        $entry1 = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry1->category = 'Test.Category';
        $entry1->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry1->message = 'First message';

        $entry2 = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry2->category = 'Test.Category';
        $entry2->setLocation(['file' => '/path/to/Test.php', 'line' => 43]);
        $entry2->message = 'Second message';

        // Act
        $this->appender->append($entry1);
        $this->appender->append($entry2);

        // Assert
        $content = file_get_contents($this->testFile);
        $this->assertStringContainsString('First message', $content, 'File should contain first message');
        $this->assertStringContainsString('Second message', $content, 'File should contain second message');
        // Verify both messages are in the file
        $this->assertGreaterThan(
            strpos($content, 'First message'),
            strpos($content, 'Second message'),
            'Second message should come after first message'
        );
    }

    /**
     * Test that FileAppender uses custom line format.
     */
    public function testFileAppenderUsesCustomLineFormat(): void
    {
        // Arrange
        $appender = $this->make(FileAppender::class, [
            'file' => $this->testFile,
            'format' => '{level} - {message}',
        ]);

        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = 'Test message';

        // Act
        $appender->append($entry);

        // Assert
        $content = file_get_contents($this->testFile);
        $this->assertStringContainsString('info - Test message', $content, 'File should use custom format');
    }

    /**
     * Test that FileAppender handles missing log entry properties gracefully.
     */
    public function testFileAppenderHandlesMissingProperties(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->message = 'Test message';
        // Don't set category or location

        // Act
        $this->appender->append($entry);

        // Assert
        $content = file_get_contents($this->testFile);
        $this->assertStringContainsString('Test message', $content, 'File should contain message');
        // Missing properties should be replaced with '-' in default format
        $this->assertStringContainsString('-', $content, 'File should contain placeholder for missing properties');
    }

    /**
     * Test that FileAppender formats multi-line messages correctly.
     */
    public function testFileAppenderFormatsMultiLineMessages(): void
    {
        // Arrange
        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = "Line 1\nLine 2\nLine 3";

        // Act
        $this->appender->append($entry);

        // Assert
        $content = file_get_contents($this->testFile);
        $lines = explode("\n", trim($content));
        $this->assertGreaterThan(1, count($lines), 'Multi-line message should create multiple lines');
        // Each line should have the log prefix
        foreach ($lines as $line) {
            if (!empty($line)) {
                $this->assertStringContainsString('[', $line, 'Each line should have log prefix');
            }
        }
    }

    /**
     * Test that FileAppender handles write failures gracefully.
     */
    public function testFileAppenderHandlesWriteFailures(): void
    {
        // Arrange - Use an invalid path to simulate write failure
        $invalidFile = '/invalid/path/that/does/not/exist/test.log';
        $eventDispatcher = new MockEventDispatcher();

        $appender = $this->make(FileAppender::class, [
            'file' => $invalidFile,
            'eventDispatcher' => $eventDispatcher,
        ]);

        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = 'Test message';

        // Act - Should not throw exception
        $oldErrorLog = ini_get('error_log');
        $tmpErrorLog = sys_get_temp_dir() . '/switon_log_test_error_' . bin2hex(random_bytes(8)) . '.log';
        ini_set('error_log', $tmpErrorLog);
        $appender->append($entry);
        ini_set('error_log', $oldErrorLog ?: '');

        // Assert
        $this->assertTrue(true, 'Appender should not throw exception on write failure');
        $this->assertNotEmpty($eventDispatcher->events, 'Write failure should dispatch an event');
        $this->assertInstanceOf(AppenderWriteFailed::class, $eventDispatcher->events[0]);

        if (file_exists($tmpErrorLog)) {
            @unlink($tmpErrorLog);
        }
    }

    /**
     * Test that FileAppender handles all placeholder types.
     */
    public function testFileAppenderHandlesAllPlaceholders(): void
    {
        // Arrange
        $appender = $this->make(FileAppender::class, [
            'file' => $this->testFile,
            'format' => '{time} {level} {category} {location} {hostname} {message}',
        ]);

        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = 'Test message';

        // Act
        $appender->append($entry);

        // Assert
        $content = file_get_contents($this->testFile);
        $this->assertStringContainsString('info', $content, 'Should contain level');
        $this->assertStringContainsString('Test.Category', $content, 'Should contain category');
        $this->assertStringContainsString('test-host', $content, 'Should contain hostname');
        $this->assertStringContainsString('Test message', $content, 'Should contain message');
    }

    public function testFileAppenderDispatchesAppenderWriteFailedWhenMkdirFails(): void
    {
        $eventDispatcher = new MockEventDispatcher();
        $filesystem = $this->createMock(FilesystemInterface::class);
        $filesystem->expects($this->once())
            ->method('mkdir')
            ->willReturnCallback(static function (): never {
                CreateDirectoryFailedException::raise('permission denied', []);
            });

        $appender = $this->make(FileAppender::class, [
            'file' => $this->testFile,
            'eventDispatcher' => $eventDispatcher,
            'filesystem' => $filesystem,
        ]);

        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = 'After mkdir failure';

        $oldErrorLog = ini_get('error_log');
        $tmpErrorLog = sys_get_temp_dir() . '/switon_log_mkdir_err_' . bin2hex(random_bytes(8)) . '.log';
        ini_set('error_log', $tmpErrorLog);

        try {
            $appender->append($entry);
        } finally {
            ini_set('error_log', $oldErrorLog ?: '');
            if (file_exists($tmpErrorLog)) {
                @unlink($tmpErrorLog);
            }
        }

        $this->assertCount(1, $eventDispatcher->events);
        $event = $eventDispatcher->events[0];
        $this->assertInstanceOf(AppenderWriteFailed::class, $event);
        $this->assertSame('mkdir', $event->operation);
        $this->assertStringContainsString(dirname($this->testFile), $event->target);
        $this->assertStringContainsString('permission denied', $event->reason);
        $this->assertFileDoesNotExist($this->testFile);
    }

    public function testFileAppenderRoutesBySceneFileMapping(): void
    {
        $appender = $this->make(FileAppender::class, [
            'file' => $this->sceneFile,
        ]);
        $this->container->get(SceneManagerInterface::class)->setScene('schedule');

        $entry = new LogEntry(LogLevel::INFO, 'test-host', 'Y-m-d H:i:s', microtime(true));
        $entry->category = 'Test.Category';
        $entry->setLocation(['file' => '/path/to/Test.php', 'line' => 42]);
        $entry->message = 'Scene routed message';

        $appender->append($entry);

        $this->assertFileExists($this->sceneFile);
        $content = file_get_contents($this->sceneFile);
        $this->assertStringContainsString('Scene routed message', $content);
    }
}
