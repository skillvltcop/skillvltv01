<?php

declare(strict_types=1);

namespace App\Application\Execution\Runtime;

use App\Application\Execution\Runtime\Contracts\BehaviorRunner as BehaviorRunnerContract;

final class BehaviorRunner implements BehaviorRunnerContract
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
    ): array {
        return [
            'steps' => $logic['steps'] ?? [],
        ];
    }
}