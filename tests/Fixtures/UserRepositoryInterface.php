<?php

declare(strict_types=1);

namespace Switon\Logging\Tests\Fixtures;

interface UserRepositoryInterface
{
    public function findById(int $id): ?User;

    public function save(User $user): void;
}
