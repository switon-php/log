<?php

declare(strict_types=1);

namespace Switon\Logging\Appender;

use Psr\EventDispatcher\EventDispatcherInterface;
use Switon\Core\AppInterface;
use Switon\Core\Attribute\Autowired;
use Switon\Core\Exception\CreateDirectoryFailedException;
use Switon\Core\FilesystemInterface;
use Switon\Core\PathAliasInterface;
use Switon\Core\SceneManagerInterface;
use Switon\Logging\AppenderInterface;
use Switon\Logging\Event\AppenderWriteFailed;
use Switon\Logging\FormatterInterface;
use Switon\Logging\LogEntry;
use Throwable;

use function dirname;
use function error_log;
use function file_put_contents;

/**
 * Writes formatted log lines to files resolved from path aliases.
 *
 * Road-signs:
 * - file: alias+{scene}/{app_id} placeholders
 * - format: text or JSON fields
 *
 * @see \Switon\Logging\AppenderInterface
 */
class FileAppender implements AppenderInterface
{
    #[Autowired] protected EventDispatcherInterface $eventDispatcher;
    #[Autowired] protected AppInterface $app;
    #[Autowired] protected SceneManagerInterface $sceneManager;
    #[Autowired] protected FilesystemInterface $filesystem;
    #[Autowired] protected PathAliasInterface $pathAlias;
    #[Autowired] protected FormatterInterface $formatter;

    /** Path with optional aliases (default: '@runtime/logger/{scene}.log'). */
    #[Autowired] protected string $file = '@runtime/logger/{scene}.log';
    /** Output format: text uses {key}; JSON uses no braces ('' or comma-separated fields). */
    #[Autowired] protected string $format = '[{time}][{level}][{category}][{location}] {message}';

    /**
     * Resolves path, creates dir if needed, and appends to file.
     *
     * Failures stay non-throwing: they emit <code>AppenderWriteFailed</code> and fall back to
     * <code>error_log()</code>.
     */
    protected function write(string $fileTemplate, string $str): void
    {
        $file = $this->pathAlias->resolve($fileTemplate, [
            'app_id' => $this->app->id(),
            'scene' => $this->sceneManager->getScene(),
        ]);
        $dir = dirname($file);
        try {
            $this->filesystem->mkdir($dir);
        } catch (CreateDirectoryFailedException $e) {
            $this->reportWriteFailure('mkdir', $dir, $e->getMessage(), $e);
            return;
        }

        // Note: LOCK_EX flag is not used because it conflicts with Swoole coroutines
        // Swoole's coroutine file operations don't work well with file locking
        if (@file_put_contents($file, $str, FILE_APPEND) === false) {
            $this->reportWriteFailure('write', $file, 'file_put_contents returned false');
        }
    }

    /**
     * Report appender write failure without re-entering logger pipeline.
     */
    protected function reportWriteFailure(string $operation, string $target, string $reason, ?Throwable $exception = null): void
    {
        try {
            $this->eventDispatcher->dispatch(new AppenderWriteFailed(
                appender: static::class,
                operation: $operation,
                target: $target,
                reason: $reason,
            ));
        } catch (Throwable) {
            // Ignore dispatch failure and continue with fallback output.
        }

        $error = "[switon.log] file appender $operation failed: $target; reason: $reason";
        if ($exception !== null) {
            $error .= '; exception: ' . $exception::class;
        }
        error_log($error);
    }

    /**
     * {@inheritDoc}
     */
    public function append(LogEntry $logEntry): void
    {
        $this->write($this->file, $this->formatter->format($logEntry, $this->format));
    }
}
