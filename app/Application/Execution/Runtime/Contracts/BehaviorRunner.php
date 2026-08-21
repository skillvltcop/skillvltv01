<?php

declare(strict_types=1);

namespace App\Application\Execution\Runtime\Contracts;

interface BehaviorRunner
{
    /**
     * @param array<string, mixed> $logic
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function run(
        array $logic,
        array $input,
        array $context = [],
    ): array;
}