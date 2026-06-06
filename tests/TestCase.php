<?php

declare(strict_types=1);

namespace Switon\Logging\Tests;

use Switon\Core\PathAliasInterface;
use Switon\Testing\TestCase as BaseTestCase;

use function sys_get_temp_dir;

/**
 * Base test case for Log package tests.
 *
 * Provides common functionality for all Log tests, including Container initialization
 * and test isolation between runs.
 */
abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Set @runtime alias for FileAppender tests (PathAlias is already configured by Container)
        $pathAlias = $this->container->get(PathAliasInterface::class);
        $pathAlias->set('@runtime', sys_get_temp_dir());
    }
}
