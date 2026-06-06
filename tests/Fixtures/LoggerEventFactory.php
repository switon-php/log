<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

use Switon\Logging\Event\LoggerLogged;
use Switon\Logging\LogEntry;
use Switon\Logging\LoggerInterface;
use Stringable;

class LoggerEventFactory
{
    public static function createLoggerLogged(
        LogEntry         $entry,
        ?LoggerInterface $logger = null,
        string           $level = 'info',
        string           $message = 'Test message',
        array            $context = [],
    ): LoggerLogged {
        return new LoggerLogged(
            $logger ?? new class () implements LoggerInterface {
                public function emergency(string|Stringable $message, array $context = []): void
                {
                }

                public function alert(string|Stringable $message, array $context = []): void
                {
                }

                public function critical(string|Stringable $message, array $context = []): void
                {
                }

                public function error(string|Stringable $message, array $context = []): void
                {
                }

                public function warning(string|Stringable $message, array $context = []): void
                {
                }

                public function notice(string|Stringable $message, array $context = []): void
                {
                }

                public function info(string|Stringable $message, array $context = []): void
                {
                }

                public function debug(string|Stringable $message, array $context = []): void
                {
                }

                public function log($level, string|Stringable $message, array $context = []): void
                {
                }
            },
            $level,
            $message,
            $context,
            $entry,
        );
    }
}
