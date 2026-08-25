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
        if (! array_key_exists('steps', $logic)) {
            throw new \DomainException(
                'Behavior logic must define steps.'
            );
        }

        if (! is_array($logic['steps'])) {
            throw new \DomainException(
                'Behavior logic steps must be an array.'
            );
        }

        foreach ($logic['steps'] as $step) {
            if (! is_string($step)) {
                throw new \DomainException(
                    'Behavior logic steps must contain only strings.'
                );
            }
        }

        return [
            'steps' => $logic['steps'],
        ];
    }   
}