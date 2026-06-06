<?php

declare(strict_types=1);

namespace Switon\Logging\Appender;

use Socket;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Logging\AppenderInterface;
use Switon\Logging\Exception\InvalidSyslogConfigException;
use Switon\Logging\LogEntry;

use function date;
use function is_array;
use function parse_url;
use function preg_match_all;
use function preg_split;
use function socket_close;
use function socket_create;
use function socket_last_error;
use function socket_sendto;
use function socket_strerror;
use function str_contains;
use function strlen;
use function strtr;

use const AF_INET;
use const LOG_ALERT;
use const LOG_CRIT;
use const LOG_DEBUG;
use const LOG_EMERG;
use const LOG_ERR;
use const LOG_INFO;
use const LOG_NOTICE;
use const LOG_WARNING;
use const SOCK_DGRAM;
use const SOL_UDP;

/**
 * Syslog appender over socket transports (UDP by default, RFC 3164 style packets).
 *
 * Configure with:
 * - <code>$uri</code>: syslog endpoint (<code>{scheme}://host[:port]</code>)
 * - <code>$sockets</code>: scheme to socket settings map (family/type/protocol/port)
 * - <code>$facility</code>: syslog facility code
 * - <code>$format</code>: text placeholder format
 *
 * @see \Switon\Logging\AppenderInterface
 * @see \Switon\Logging\LogEntry
 * @see \Switon\Logging\Exception\InvalidSyslogConfigException
 */
class SyslogAppender implements AppenderInterface
{
    #[Autowired] protected AppInterface $app;

    /** URI in format 'udp://hostname:port' (e.g., 'udp://syslog.example.com:514'). */
    #[Autowired] protected string $uri;

    /** Facility code (0-23, default: 1 = user-level messages). */
    #[Autowired] protected int $facility = 1;

    /** Format string with {key} placeholder syntax (same as message interpolation). */
    #[Autowired] protected string $format = '[{time}][{level}][{category}][{location}] {message}';

    /**
     * Socket transport map keyed by URI scheme.
     *
     * @var array<string, array{family:int, type:int, protocol:int, port:int}>
     */
    #[Autowired] protected array $sockets = [
        'udp' => ['family' => AF_INET, 'type' => SOCK_DGRAM, 'protocol' => SOL_UDP, 'port' => 514],
    ];

    protected string $scheme = 'udp';

    protected string $host = '';

    protected int $port = 0;

    /** @var Socket|false Socket object created from <code>$uri</code>. */
    protected Socket|false $socket = false;

    /**
     * Parses URI and creates socket by configured transport scheme.
     *
     * @throws InvalidSyslogConfigException If URI invalid, scheme unsupported, or socket creation fails
     */
    public function __construct()
    {
        $parts = parse_url($this->uri);
        if ($parts === false || !isset($parts['host'])) {
            InvalidSyslogConfigException::raise('Invalid syslog URI format: "{uri}"', ['uri' => $this->uri]);
        }
        $this->host = $parts['host'];
        $this->scheme = $parts['scheme'] ?? 'udp';
        $socket = $this->sockets[$this->scheme] ?? null;

        if (!is_array($socket)) {
            InvalidSyslogConfigException::raise('Unsupported syslog protocol: {scheme}', ['scheme' => $this->scheme]);
        }

        $this->port = isset($parts['port']) ? (int)$parts['port'] : (int)$socket['port'];

        $resource = $this->createSocket($socket);
        if ($resource === false) {
            InvalidSyslogConfigException::raise('Socket creation failed: {error}', ['error' => socket_strerror(socket_last_error())]);
        }
        $this->socket = $resource;
    }

    /**
     * Create socket; failures are <code>false</code> plus <code>socket_last_error()</code> (warnings suppressed for this call).
     *
     * @param array{family:int, type:int, protocol:int, port:int} $socket
     *
     * @return Socket|false
     */
    protected function createSocket(array $socket): Socket|false
    {
        return @socket_create((int)$socket['family'], (int)$socket['type'], (int)$socket['protocol']);
    }

    /**
     * Closes the socket when the appender is destroyed.
     */
    public function __destruct()
    {
        if ($this->socket !== false) {
            socket_close($this->socket);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function append(LogEntry $logEntry): void
    {
        if ($this->socket === false) {
            return;
        }

        $socket = $this->socket;

        // Map log levels to syslog severity constants
        // RFC 3164 defines severity levels: 0=emergency, 1=alert, 2=critical, 3=error, 4=warning, 5=notice, 6=info, 7=debug
        $severity = [
            'emergency' => LOG_EMERG,
            'alert' => LOG_ALERT,
            'critical' => LOG_CRIT,
            'error' => LOG_ERR,
            'warning' => LOG_WARNING,
            'notice' => LOG_NOTICE,
            'info' => LOG_INFO,
            'debug' => LOG_DEBUG,
        ][$logEntry->level] ?? LOG_INFO;

        $host = $this->host;
        $port = $this->port;
        $tag = $this->app->id();

        // Calculate priority: facility * 8 + severity
        // Facility is typically 1 (user-level messages), range 0-23
        // Severity range is 0-7, so priority range is 0-191
        $priority = $this->facility * 8 + $severity;
        // Format timestamp in RFC 3164 format: "Mmm dd HH:mm:ss" (e.g., "Jan 15 14:30:00")
        $timestamp = date('M d H:i:s', (int)$logEntry->timestamp);

        $replaced = [];
        // Extract all placeholder keys from the line format (e.g., {time}, {level}, {message})
        preg_match_all('#\{(\w+)\}#', $this->format, $matches);
        foreach ($matches[1] as $key) {
            // Prepare replacements for all placeholders except message
            // Message will be handled separately for exception multi-line handling
            if ($key !== 'message') {
                $replaced['{' . $key . '}'] = $logEntry->$key ?? '-';
            }
        }

        // Special handling for multi-line messages (e.g., exception stack traces):
        // send each line as a separate syslog packet
        $lines = preg_split('#[\\r\\n]+#', $logEntry->message);
        if ($lines !== false && str_contains($logEntry->message, "\n")) {
            foreach ($lines as $line) {
                $replaced['{message}'] = $line;
                $content = strtr($this->format, $replaced);
                // RFC 3164 syslog format: <PRI>TIMESTAMP HOST TAG:CONTENT
                // PRI is the priority value in angle brackets
                // TAG is the program identifier (app_id)
                $packet = "<$priority>$timestamp $logEntry->hostname $tag:$content";
                socket_sendto($socket, $packet, strlen($packet), 0, $host, $port);
            }
            return;
        }

        // Regular messages: send as a single syslog packet
        $replaced['{message}'] = $logEntry->message;
        $content = strtr($this->format, $replaced);
        // RFC 3164 syslog format: <PRI>TIMESTAMP HOST TAG:CONTENT
        $packet = "<$priority>$timestamp $logEntry->hostname $tag:$content";
        socket_sendto($socket, $packet, strlen($packet), 0, $host, $port);
    }
}
