<?php

declare(strict_types=1);

namespace Switon\Logging;

use stdClass;

use function basename;
use function date;
use function sprintf;
use function str_contains;
use function str_replace;

/**
 * Structured payload passed from Logger to appenders and events.
 *
 * Guidance:
 * - use this object in custom appenders or <code>LoggerLogged</code> listeners
 * - <code>message</code> contains interpolated text, while non-interpolated context stays in <code>extra</code>
 *
 * Road-signs:
 * - message interpolated
 * - extra structured context
 * - category + level
 * - location <code>file:line</code>
 * - tokens <code>v</code>/<code>u</code>
 *
 * @see \Switon\Logging\Logger
 * @see \Switon\Logging\AppenderInterface
 * @see \Switon\Logging\Event\LoggerLogged
 */
class LogEntry extends stdClass
{
    /** Rendered timestamp string. */
    public string $time;

    /** Unix timestamp with microseconds. */
    public float $timestamp;

    /** Hostname for the current process. */
    public string $hostname;

    /** Dot-notation category used for level matching. */
    public string $category;

    /** Source filename (basename only). */
    public string $file;

    /** Source line number. */
    public int $line;

    /** Source location in <code>file:line</code> format. */
    public string $location;

    /** PSR-3 level string. */
    public string $level;

    /** Message text after placeholder interpolation. */
    public string $message;

    /** @var array<string, mixed> Non-interpolated context data for structured output. */
    public array $extra = [];

    /**
     * Creates a log entry and renders <code>time</code> from <code>timestamp</code>.
     * Supports <code>Logger::MILLISECONDS</code> (<code>v</code>) and
     * <code>Logger::MICROSECONDS</code> (<code>u</code>) tokens in <code>$time_format</code>.
     *
     * @param string $level PSR-3 log level
     * @param string $hostname Server hostname
     * @param string $time_format PHP date format
     * @param float $timestamp Unix timestamp with microseconds
     */
    public function __construct(string $level, string $hostname, string $time_format, float $timestamp)
    {
        $this->level = $level;
        $this->hostname = $hostname;

        $this->timestamp = $timestamp;
        if (str_contains($time_format, Logger::MILLISECONDS)) {
            $ms = sprintf('%03d', ($this->timestamp - (int)$timestamp) * 1000);
            $time_format = str_replace(Logger::MILLISECONDS, $ms, $time_format);
        } elseif (str_contains($time_format, Logger::MICROSECONDS)) {
            $us = sprintf('%06d', ($this->timestamp - (int)$timestamp) * 1000000);
            $time_format = str_replace(Logger::MICROSECONDS, $us, $time_format);
        }

        $this->time = date($time_format, (int)$timestamp);
    }

    /**
     * Populates <code>file</code>, <code>line</code>, and <code>location</code> from one trace frame.
     * Missing keys fallback to <code>-</code> and <code>0</code>.
     *
     * @param array<string, mixed> $trace Stack trace frame
     */
    public function setLocation(array $trace): void
    {
        $this->file = isset($trace['file']) ? basename($trace['file']) : '-';
        $this->line = $trace['line'] ?? 0;

        $this->location = $this->file . ':' . $this->line;
    }
}
