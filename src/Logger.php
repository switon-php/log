<?php

declare(strict_types=1);

namespace Switon\Logging;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Backtrace;
use Switon\Core\Categorizable;
use Switon\Core\ClassName;
use Switon\Core\ClockInterface;
use Switon\Core\Exception;
use Switon\Core\Json;
use Switon\Core\Strings;
use Switon\Logging\Appender\FileAppender;
use Switon\Logging\Event\LoggerLogged;
use Throwable;

use function array_shift;
use function gethostname;
use function str_contains;
use function str_ends_with;
use function strlen;
use function strrpos;
use function substr;

/**
 * Implements PSR-3 logging with category-aware filtering, structured context handling, and pluggable appenders.
 *
 * Use when you need per-module log levels or structured exception logging.
 *
 * Road-signs:
 * - category → level filter
 * - placeholders <code>{key}</code>
 * - exception <code>context['exception']</code>
 * - extra keys → structured extra
 * - emits <code>LoggerLogged</code>
 *
 * Processing (accepted entries): fast global threshold → category resolution and per-category threshold → format into LogEntry → dispatch <code>LoggerLogged</code> → <code>AppenderInterface::append()</code> for each appender.
 *
 * @see \Switon\Logging\LoggerInterface
 * @see \Switon\Logging\Level
 * @see \Switon\Logging\LogEntry
 * @see \Switon\Logging\Event\LoggerLogged
 * @see \Switon\Logging\AppenderInterface
 * @see \Switon\Core\Exception
 */
class Logger extends AbstractLogger implements LoggerInterface
{
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected ClockInterface $clock;

    /** Global fallback level when a category has no override. */
    #[Autowired] protected string $level = LogLevel::DEBUG;

    /** @var array<string, string> Per-category overrides (specific key → dotted prefix → global level). */
    #[Autowired] protected array $levels = [
        'switon.di.singleton.created' => LogLevel::ERROR,
        'switon.eventing.observability.registered' => LogLevel::ERROR,
    ];

    /** Hostname override; auto-detected when null. */
    #[Autowired] protected ?string $hostname;

    /** PHP date format with optional v/u placeholders. */
    #[Autowired] protected string $time_format = 'Y-m-d\TH:i:s.uP';

    /** @var array<string, AppenderInterface> Enabled appenders keyed by name. */
    #[Autowired(instances: true)] protected array $appenders = ['file' => FileAppender::class];

    /** Cached minimum threshold used by fast pre-filtering. */
    protected ?string $minLevel = null;

    /**
     * Milliseconds token for <code>$time_format</code> (PHP date(), 3 digits).
     *
     * @see \Switon\Logging\Logger::$time_format
     */
    public const string MILLISECONDS = 'v';

    /**
     * Microseconds token for <code>$time_format</code> (PHP date(), 6 digits).
     */
    public const string MICROSECONDS = 'u';

    /**
     * Resolves the category used for per-category level filtering.
     *
     * Override this method to customize category naming for your application.
     * Default priority: exception throw frame → first non-closure caller class → <code>unknown</code>.
     *
     * @param array<string, mixed> $context Log context (may contain exception)
     * @param array<array<string, mixed>> $traces Stack trace from Backtrace::get()
     *
     * @return string Dot-notation category identifier
     */
    protected function getCategory(array $context, array $traces): string
    {
        // Use exception trace if present (categorize by throw location, not log location)
        if (($exception = $context['exception'] ?? null) !== null && $exception instanceof Throwable) {
            $trace = $exception->getTrace()[0] ?? null;
        } elseif (isset($traces[1])) {
            // Skip closures and standalone functions: use caller until we find a class frame (and not a closure method)
            $idx = 1;
            while (isset($traces[$idx])) {
                $frame = $traces[$idx];
                $isClosure = str_ends_with($frame['function'] ?? '', '{closure}');
                if (isset($frame['class']) && !$isClosure) {
                    $trace = $frame;
                    break;
                }
                $idx++;
                $trace = $traces[$idx] ?? null;
            }
        } else {
            $trace = $traces[0] ?? null;
        }

        if (isset($trace['class'])) {
            // Strip 'Action' suffix from method names (e.g., "currentAction" → "current")
            $method = $trace['function'] ?? '';
            if ($method !== '' && str_ends_with($method, 'Action') && strlen($method) > 6) {
                $method = substr($method, 0, -6);
            }

            // Convert "Class\method" to dot notation (e.g., "User\UserService\getUser" → "user.service.get")
            $fullName = $trace['class'] . '\\' . $method;
            return ClassName::dotId($fullName);
        }

        return 'unknown';
    }

    /**
     * Returns the most verbose configured threshold for fast pre-filtering.
     *
     * Computed once and cached in <code>$minLevel</code>.
     */
    protected function resolveMinLevel(): string
    {
        $min = $this->level;
        foreach ($this->levels as $l) {
            if (Level::gt($l, $min)) {
                $min = $l;
            }
        }
        return $min;
    }

    /**
     * Returns the effective log level for one category using hierarchical prefix matching.
     *
     * Searches from specific to general: "A.B.C.D" → "A.B.C" → "A.B" → "A" → global level
     *
     * @param string $category Category to get level for
     *
     * @return string Effective log level for the category
     */
    protected function getCategoryLevel(string $category): string
    {
        if (($level = $this->levels[$category] ?? null) !== null) {
            return $level;
        }

        $prev = 0;
        $len = strlen($category);
        // Traverse up hierarchy: "A.B.C.D" → "A.B.C" → "A.B" → "A"
        while (($next = strrpos($category, '.', $prev)) !== false && $next > 0) {
            $prefix = substr($category, 0, $next);
            if (($level = $this->levels[$prefix] ?? null) !== null) {
                return $level;
            }
            // Convert position to negative offset for next search
            $prev = $next - $len - 1;
        }

        return $this->level;
    }

    /**
     * Formats the message and populates <code>LogEntry</code> with rendered text and structured extra data.
     *
     * Extra (non-placeholder context) goes to logEntry->extra, not serialized into message,
     * so Formatter can output structured data without double-encoding.
     *
     * @param string|Stringable $message Log message (may be Throwable)
     * @param array<string, mixed> $context Context data for interpolation and extra
     * @param LogEntry $logEntry Entry to fill with message and extra
     */
    protected function formatAndFill(string|Stringable $message, array $context, LogEntry $logEntry): void
    {
        // Handle Throwable messages (PHP 8.0+ Throwable implements Stringable)
        if ($message instanceof Throwable) {
            $extra = $context;
            $str = $context === [] ? '' : Json::stringify($context);
            $logEntry->message = $str . PHP_EOL . Strings::renderException($message);
            $logEntry->extra = $extra;
            return;
        }

        // Extract exception from context (PSR-3 convention)
        if (($exception = $context['exception'] ?? null) !== null && $exception instanceof Throwable) {
            unset($context['exception']);
            // Auto-merge Switon exception context into log extra
            if ($exception instanceof Exception && ($exContext = $exception->getContext()) !== []) {
                $context += $exContext;
            }
        } else {
            $exception = null;
        }

        $message = (string)$message;

        // Separate placeholders from extra data
        $extra = [];
        foreach ($context as $key => $value) {
            if (!str_contains($message, "{{$key}}")) {
                $extra[$key] = $value;
                unset($context[$key]);
            }
        }

        // Interpolate placeholders
        if ($context !== []) {
            $message = Strings::interpolate($message, $context);
        }

        $messageStr = $message;

        // Append exception trace (extra stays in logEntry->extra, not in message)
        if ($exception !== null) {
            $messageStr .= PHP_EOL . Strings::renderException($exception);
        }

        $logEntry->message = $messageStr;
        $logEntry->extra = $extra;
    }

    /**
     * Logs one message with an arbitrary PSR-3 level.
     *
     * Supports <code>{key}</code> placeholders and <code>context['exception']</code>.
     * Extra context keys are stored as structured data in <code>LogEntry::extra</code>.
     *
     * Call flow when not rejected early: capture backtrace → resolve category → filter by category level → fill LogEntry → dispatch <code>LoggerLogged</code> → appenders.
     *
     * @param string $level PSR-3 level string (prefer <code>LogLevel::*</code>)
     * @param string|Stringable|Throwable|Categorizable $message
     * @param array<string, mixed> $context Context data (placeholders, extra data, exceptions)
     */
    public function log($level, mixed $message, array $context = []): void
    {
        $this->minLevel ??= $this->resolveMinLevel();

        // Fast path: reject if more verbose than any configured level
        if (Level::gt($level, $this->minLevel)) {
            return;
        }

        // Limit to 7 frames: balances nested call depth (filters/events/closures) with memory overhead
        $traces = Backtrace::get(DEBUG_BACKTRACE_PROVIDE_OBJECT | DEBUG_BACKTRACE_IGNORE_ARGS, 7);
        array_shift($traces);

        if ($message instanceof Categorizable) {
            $category = $message->getCategory();
            $message = (string)$message;
        } else {
            $category = $this->getCategory($context, $traces);
        }

        if ($this->levels !== [] && Level::gt($level, $this->getCategoryLevel($category))) {
            return;
        }

        $logEntry = new LogEntry($level, $this->hostname ?? gethostname(), $this->time_format, $this->clock->microtime());
        $logEntry->category = $category;
        $logEntry->setLocation($traces[0] ?? []);
        $this->formatAndFill($message, $context, $logEntry);

        $this->eventDispatcher->dispatch(new LoggerLogged($this, $level, $message, $context, $logEntry));

        foreach ($this->appenders as $appender) {
            $appender->append($logEntry);
        }
    }
}
