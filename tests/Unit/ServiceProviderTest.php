<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Unit;

use Psr\Log\LoggerInterface as PsrLoggerInterface;
use Psr\Log\LogLevel;
use Switon\Core\PathAliasInterface;
use Switon\Logging\Appender\FileAppender;
use Switon\Logging\Logger;
use Switon\Logging\ServiceProvider;
use Switon\Logging\Tests\Fixtures\MockEventDispatcher;
use Switon\Logging\Tests\TestCase;
use Switon\Testing\PackagePathAssert;

// Load bootstrap to ensure autoloader is loaded (required when using --no-configuration)

/**
 * Test cases for ServiceProvider class.
 *
 * Tests logger service registration and dependency injection setup.
 */
class ServiceProviderTest extends TestCase
{
    /**
     * Test that ServiceProvider registers Logger service.
     */
    public function testServiceProviderRegistersLogger(): void
    {
        // Arrange
        // Define env() function if not exists (ServiceProvider uses it)
        if (!function_exists('env')) {
            eval('function env($key, $default = null) { return $default; }');
        }

        $provider = new ServiceProvider();

        // Register required dependencies
        $this->container->set(\Psr\EventDispatcher\EventDispatcherInterface::class, new MockEventDispatcher());

        // Act
        $provider->register($this->container);

        // Assert
        $this->assertTrue(
            $this->container->has(PsrLoggerInterface::class),
            'ServiceProvider should register PsrLoggerInterface'
        );

        $logger = $this->container->get(PsrLoggerInterface::class);
        $this->assertInstanceOf(
            Logger::class,
            $logger,
            'Registered service should be Logger instance'
        );
    }

    /**
     * Test that ServiceProvider registers Logger with default configuration.
     */
    public function testServiceProviderRegistersLoggerWithDefaultConfig(): void
    {
        // Arrange
        // Define env() function if not exists (ServiceProvider uses it)
        if (!function_exists('env')) {
            eval('function env($key, $default = null) { return $default; }');
        }

        $provider = new ServiceProvider();

        // Register required dependencies
        $this->container->set(\Psr\EventDispatcher\EventDispatcherInterface::class, new MockEventDispatcher());

        // Act
        $provider->register($this->container);
        $logger = $this->container->get(PsrLoggerInterface::class);

        // Assert - test behavior: logger can log without exception
        $this->assertInstanceOf(Logger::class, $logger);

        // Logger should be functional (can log without throwing)
        $logger->info('Test message');
        $this->assertTrue(true, 'Logger should be able to log without exception');
    }

    /**
     * Test that ServiceProvider boot method does nothing.
     */
    public function testServiceProviderBoot(): void
    {
        // Arrange
        $provider = new ServiceProvider();
        $pathAlias = $this->container->get(PathAliasInterface::class);

        $this->injector->inject($provider);
        $resourceRoot = $pathAlias->get('@switon.log.resources');
        $this->assertIsString($resourceRoot);
        PackagePathAssert::assertSameAsPackagePath(ServiceProvider::class, $resourceRoot, 'resources');

        // Act - Should not throw exception
        $provider->boot();

        // Assert
        $this->assertSame($resourceRoot, $pathAlias->get('@switon.log.resources'));
    }

    /**
     * Test that partial config override (no 'class') after ServiceProvider succeeds.
     *
     * Simulates real flow: ServiceProvider registers PsrLoggerInterface; user config/logger.php
     * overrides with partial array [level=>..., appenders=>...]. Container preserves 'class'
     * from the provider when user omits it, so resolution succeeds.
     */
    public function testPartialConfigOverrideAfterServiceProviderSucceeds(): void
    {
        // Arrange - ServiceProvider registers full def
        if (!function_exists('env')) {
            eval('function env($key, $default = null) { return $default; }');
        }
        $this->container->set(\Psr\EventDispatcher\EventDispatcherInterface::class, new MockEventDispatcher());
        $provider = new ServiceProvider();
        $provider->register($this->container);

        // Act - Simulate user config/logger.php override (partial, no class)
        $this->container->set(PsrLoggerInterface::class, [
            'level' => LogLevel::INFO,
            'appenders' => ['file' => FileAppender::class],
        ]);

        $logger = $this->container->get(PsrLoggerInterface::class);

        // Assert - 'class' preserved from provider; Logger resolves and logs
        $this->assertInstanceOf(Logger::class, $logger);
        $logger->info('Partial override works');
    }
}
