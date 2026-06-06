<?php

declare(strict_types=1);

namespace Switon\Logging;

use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Stringable;

/**
 * Defines the PSR-3 logger contract with category-based filtering and structured context behavior.
 *
 * Guidance:
 * - use this when callers need PSR-3 logging plus Switon category filtering and structured context
 * - before adding business or lifecycle logging, first ask whether this should be modeled as an event
 *
 * Use when you need:
 * - <code>{key}</code> placeholder interpolation from context
 * - exception rendering via <code>context['exception']</code>
 * - category-based level filtering
 * - structured extra context plus appender output and <code>LoggerLogged</code> event dispatch
 *
 * @see \Switon\Logging\Logger
 * @see \Switon\Logging\LogEntry
 * @see \Switon\Logging\Event\LoggerLogged
 * @see \Switon\Logging\Level
 * @see \Switon\Logging\AppenderInterface
 * @see \Switon\Core\Categorized
 * @see \Switon\Inspector\Collector\LoggerCollector Typical consumer
 * @see \Switon\Logging\Appender\FileAppender Typical consumer
 */
interface LoggerInterface extends PsrLoggerInterface
{
    /**
     * System is unusable. Use for complete service outages, critical infrastructure failures.
     *
     * Message: string or Stringable; <code>{key}</code> placeholders interpolated from context.
     * Context: <code>'exception' => $e</code> formats stack trace; non-placeholder keys are stored in
     * <code>LogEntry::extra</code> for formatter/appender-specific structured output.
     *
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     */
    public function emergency(string|Stringable $message, array $context = []);

    /**
     * Action must be taken immediately (e.g. website down, critical security breach).
     *
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     */
    public function alert(string|Stringable $message, array $context = []);

    /**
     * Critical conditions (e.g. payment gateway unavailable, critical path exceptions).
     *
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     */
    public function critical(string|Stringable $message, array $context = []);

    /**
     * Runtime errors to log and monitor (e.g. failed operations, API failures).
     *
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     */
    public function error(string|Stringable $message, array $context = []);

    /**
     * Exceptional occurrences that are not errors (e.g. deprecated API usage, performance warnings).
     *
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     */
    public function warning(string|Stringable $message, array $context = []);

    /**
     * Normal but significant events (e.g. user profile updates, configuration changes).
     *
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     */
    public function notice(string|Stringable $message, array $context = []);

    /**
     * Interesting events (e.g. user actions, SQL logs, API calls).
     *
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     */
    public function info(string|Stringable $message, array $context = []);

    /**
     * Detailed debug information (typically filtered in production).
     *
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     */
    public function debug(string|Stringable $message, array $context = []);

    /**
     * Logs one message with an arbitrary PSR-3 level.
     *
     * Pass one PSR-3 level string.
     *
     * @param string $level PSR-3 level: emergency, alert, critical, error, warning, notice, info, debug
     * @param string|Stringable $message
     * @param array<string, mixed> $context
     */
    public function log($level, string|Stringable $message, array $context = []);
}
