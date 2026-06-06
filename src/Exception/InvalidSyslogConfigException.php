<?php

declare(strict_types=1);

namespace Switon\Logging\Exception;

use Switon\Logging\Exception as BaseException;

/**
 * Exception for invalid syslog appender configuration.
 *
 * Raised when the syslog URI, protocol, or socket setup is invalid.
 *
 * @see \Switon\Logging\Exception
 * @see \Switon\Logging\Appender\SyslogAppender
 */
class InvalidSyslogConfigException extends BaseException
{
}
