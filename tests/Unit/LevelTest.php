<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Unit;

use Psr\Log\LogLevel;
use Switon\Logging\Level;
use Switon\Logging\Tests\TestCase;

// Load bootstrap to ensure autoloader is loaded (required when using --no-configuration)

class LevelTest extends TestCase
{
    public function testMapReturnsAllLevels(): void
    {
        // Act
        $map = Level::map();

        // Assert
        $this->assertIsArray($map, 'map() should return an array');
        $this->assertArrayHasKey(LogLevel::EMERGENCY, $map, 'Map should contain EMERGENCY level');
        $this->assertArrayHasKey(LogLevel::ALERT, $map, 'Map should contain ALERT level');
        $this->assertArrayHasKey(LogLevel::CRITICAL, $map, 'Map should contain CRITICAL level');
        $this->assertArrayHasKey(LogLevel::ERROR, $map, 'Map should contain ERROR level');
        $this->assertArrayHasKey(LogLevel::WARNING, $map, 'Map should contain WARNING level');
        $this->assertArrayHasKey(LogLevel::NOTICE, $map, 'Map should contain NOTICE level');
        $this->assertArrayHasKey(LogLevel::INFO, $map, 'Map should contain INFO level');
        $this->assertArrayHasKey(LogLevel::DEBUG, $map, 'Map should contain DEBUG level');
    }

    public function testMapReturnsCorrectNumericValues(): void
    {
        // Act
        $map = Level::map();

        // Assert
        $this->assertSame(0, $map[LogLevel::EMERGENCY], 'EMERGENCY should be 0');
        $this->assertSame(1, $map[LogLevel::ALERT], 'ALERT should be 1');
        $this->assertSame(2, $map[LogLevel::CRITICAL], 'CRITICAL should be 2');
        $this->assertSame(3, $map[LogLevel::ERROR], 'ERROR should be 3');
        $this->assertSame(4, $map[LogLevel::WARNING], 'WARNING should be 4');
        $this->assertSame(5, $map[LogLevel::NOTICE], 'NOTICE should be 5');
        $this->assertSame(6, $map[LogLevel::INFO], 'INFO should be 6');
        $this->assertSame(7, $map[LogLevel::DEBUG], 'DEBUG should be 7');
    }

    public function testGtReturnsTrueWhenFirstIsMoreVerbose(): void
    {
        // Act & Assert
        $this->assertTrue(
            Level::gt(LogLevel::DEBUG, LogLevel::INFO),
            'DEBUG should be more verbose than INFO'
        );
        $this->assertTrue(
            Level::gt(LogLevel::INFO, LogLevel::WARNING),
            'INFO should be more verbose than WARNING'
        );
        $this->assertTrue(
            Level::gt(LogLevel::WARNING, LogLevel::ERROR),
            'WARNING should be more verbose than ERROR'
        );
        $this->assertTrue(
            Level::gt(LogLevel::ERROR, LogLevel::CRITICAL),
            'ERROR should be more verbose than CRITICAL'
        );
    }

    public function testGtReturnsFalseWhenFirstIsLessVerbose(): void
    {
        // Act & Assert
        $this->assertFalse(
            Level::gt(LogLevel::INFO, LogLevel::DEBUG),
            'INFO should not be more verbose than DEBUG'
        );
        $this->assertFalse(
            Level::gt(LogLevel::WARNING, LogLevel::INFO),
            'WARNING should not be more verbose than INFO'
        );
        $this->assertFalse(
            Level::gt(LogLevel::ERROR, LogLevel::WARNING),
            'ERROR should not be more verbose than WARNING'
        );
        $this->assertFalse(
            Level::gt(LogLevel::CRITICAL, LogLevel::ERROR),
            'CRITICAL should not be more verbose than ERROR'
        );
    }

    public function testGtReturnsFalseWhenLevelsAreEqual(): void
    {
        // Act & Assert
        $this->assertFalse(
            Level::gt(LogLevel::DEBUG, LogLevel::DEBUG),
            'DEBUG should not be more verbose than itself'
        );
        $this->assertFalse(
            Level::gt(LogLevel::INFO, LogLevel::INFO),
            'INFO should not be more verbose than itself'
        );
        $this->assertFalse(
            Level::gt(LogLevel::ERROR, LogLevel::ERROR),
            'ERROR should not be more verbose than itself'
        );
    }

    /** Unknown levels default to DEBUG (7) */
    public function testGtHandlesUnknownLevels(): void
    {
        // Act & Assert
        // Unknown level should default to DEBUG (7), so it should be more verbose than INFO (6)
        $this->assertTrue(
            Level::gt('unknown_level', LogLevel::INFO),
            'Unknown level should default to DEBUG and be more verbose than INFO'
        );

        // Unknown level compared to another unknown level should both default to DEBUG (7)
        $this->assertFalse(
            Level::gt('unknown1', 'unknown2'),
            'Two unknown levels should both default to DEBUG and be equal'
        );
    }

    public function testGtComparesAllLevelPairs(): void
    {
        $levels = [
            LogLevel::EMERGENCY => 0,
            LogLevel::ALERT => 1,
            LogLevel::CRITICAL => 2,
            LogLevel::ERROR => 3,
            LogLevel::WARNING => 4,
            LogLevel::NOTICE => 5,
            LogLevel::INFO => 6,
            LogLevel::DEBUG => 7,
        ];

        foreach ($levels as $level1 => $value1) {
            foreach ($levels as $level2 => $value2) {
                $expected = $value1 > $value2;
                $actual = Level::gt($level1, $level2);
                $this->assertSame(
                    $expected,
                    $actual,
                    "gt('$level1', '$level2') should return " . ($expected ? 'true' : 'false')
                );
            }
        }
    }
}
