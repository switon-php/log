<?php

declare(strict_types=1);

namespace Switon\Logging;

use Psr\Log\LogLevel;

/**
 * PSR-3 level mapping and comparison helper.
 *
 * Lower number means higher severity. Unknown levels fallback to DEBUG.
 *
 * @see \Psr\Log\LogLevel
 * @see \Switon\Logging\Logger
 */
class Level
{
    /** @var array<string, int> PSR-3 level to numeric severity map (lower = more severe). */
    protected static array $map = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7,
    ];

    /**
     * Returns the full level-to-number map.
     *
     * @return array<string, int>
     */
    public static function map(): array
    {
        return self::$map;
    }

    /**
     * Returns true when $level is more verbose than $threshold.
     * Example: gt('debug', 'info') is true.
     *
     * @param string $level
     * @param string $threshold
     */
    public static function gt(string $level, string $threshold): bool
    {
        return (self::$map[$level] ?? 7) > (self::$map[$threshold] ?? 7);
    }
}
