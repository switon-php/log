<?php

declare(strict_types=1);

namespace Switon\Logging;

use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Switon\Core\Attribute\ResourceAlias;
use Switon\Core\ContainerInterface;
use Switon\Core\ServiceProviderInterface;
use Switon\Logging\Appender\FileAppender;

use function env;

/**
 * Integrates the logging component during application startup.
 *
 * Guidance:
 * - registers the baseline PSR logger binding
 * - keeps the default output path simple with a file appender and env-driven level
 *
 * Road-signs:
 * - PSR Logger→Switon Logger; FileAppender baseline
 *
 * @see \Switon\Core\ServiceProviderInterface
 */
#[ResourceAlias]
class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers the default PSR logger binding with file output and env-based level selection.
     */
    public function register(ContainerInterface $container): void
    {
        $container->set(PsrLoggerInterface::class, [
            'class' => Logger::class,
            'level' => env('LOG_LEVEL', 'debug'),
            'appenders' => ['file' => FileAppender::class],
        ]);
    }

    /**
     * No-op hook kept for service provider lifecycle parity.
     */
    public function boot(): void
    {
    }
}
