<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

class TestCategoryLogger
{
    protected $logger;

    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function logMessage(string $level, string $message): void
    {
        $this->logger->log($level, $message);
    }

    public static function getExpectedCategory(): string
    {
        return 'switon.logging.tests.fixtures.category.logger.log.message';
    }

    public static function getExpectedParentCategory(): string
    {
        return 'switon.logging.tests.fixtures.category.logger';
    }
}
