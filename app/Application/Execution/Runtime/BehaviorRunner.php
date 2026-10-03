<?php

declare(strict_types=1);

namespace App\Application\Execution\Runtime;

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Behavior\ValueResolver;
use App\Application\Execution\Runtime\Contracts\BehaviorRunner as BehaviorRunnerContract;

final class BehaviorRunner implements BehaviorRunnerContract
{
    public function __construct(
        private ValueResolver $valueResolver,
        private ?BehaviorContractValidator $contractValidator = null,
    ) {
    }

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
        ($this->contractValidator ??= new BehaviorContractValidator())
            ->validate($logic);

        $state = [];

        foreach ($logic['steps'] as $step) {
            $type = $step['type'];

            if ($type === 'evaluate_rule') {
                $this->executeEvaluateRule(
                    step: $step,
                    input: $input,
                    context: $context,
                    state: $state,
                );

                continue;
            }

            if ($type === 'format_template') {
                $this->executeFormatTemplate(
                    step: $step,
                    input: $input,
                    context: $context,
                    state: $state,
                );

                continue;
            }

            if ($type === 'return') {
                return $this->resolveData(
                    data: $step['data'],
                    input: $input,
                    context: $context,
                    state: $state,
                );
            }

            throw new \DomainException(
                sprintf('Unsupported behavior step type "%s".', $type)
            );
        }

        throw new \DomainException(
            'Behavior execution did not produce a return output.'
        );
    }

    /**
     * @param array<string, mixed> $step
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     * @param array<string, mixed> $state
     */
    private function executeEvaluateRule(
        array $step,
        array $input,
        array $context,
        array &$state,
    ): void {
        $condition = $step['condition'];

        $actual = $this->valueResolver->resolve(
            path: $condition['field'],
            input: $input,
            context: $context,
            state: $state,
        );

        $expected = $condition['value'];

        $result = match ($condition['operator']) {
            'eq' => $actual === $expected,
            'neq' => $actual !== $expected,
            'gt' => $this->compareNumeric($actual, $expected, '>'),
            'gte' => $this->compareNumeric($actual, $expected, '>='),
            'lt' => $this->compareNumeric($actual, $expected, '<'),
            'lte' => $this->compareNumeric($actual, $expected, '<='),
        };

        $state[$step['assign_to']] = $result
            ? $step['true_value']
            : $step['false_value'];
    }

    /**
     * @param array<string, mixed> $step
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     * @param array<string, mixed> $state
     */
    private function executeFormatTemplate(
        array $step,
        array $input,
        array $context,
        array &$state,
    ): void {
        $template = $step['template'];

        $state[$step['assign_to']] = preg_replace_callback(
            '/{([^{}\s]+)}/',
            function (array $matches) use (
                $input,
                $context,
                $state,
            ): string {
                $value = $this->valueResolver->resolve(
                    path: $matches[1],
                    input: $input,
                    context: $context,
                    state: $state,
                );

                if (is_array($value) || is_object($value)) {
                    throw new \DomainException(
                        sprintf(
                            'Interpolation value for "%s" must be scalar.',
                            $matches[1],
                        )
                    );
                }

                return (string) $value;
            },
            $template,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     * @param array<string, mixed> $state
     * @return array<string, mixed>
     */
    private function resolveData(
        array $data,
        array $input,
        array $context,
        array $state,
    ): array {
        $resolved = [];

        foreach ($data as $key => $value) {
            $resolved[$key] = $this->resolveDataValue(
                value: $value,
                input: $input,
                context: $context,
                state: $state,
            );
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     * @param array<string, mixed> $state
     */
    private function resolveDataValue(
        mixed $value,
        array $input,
        array $context,
        array $state,
    ): mixed {
        if (is_string($value)) {
            return preg_replace_callback(
                '/{([^{}\s]+)}/',
                function (array $matches) use (
                    $input,
                    $context,
                    $state,
                ): string {
                    $resolved = $this->valueResolver->resolve(
                        path: $matches[1],
                        input: $input,
                        context: $context,
                        state: $state,
                    );

                    if (is_array($resolved) || is_object($resolved)) {
                        throw new \DomainException(
                            sprintf(
                                'Interpolation value for "%s" must be scalar.',
                                $matches[1],
                            )
                        );
                    }

                    return (string) $resolved;
                },
                $value,
            );
        }

        if (is_array($value)) {
            return $this->resolveData(
                data: $value,
                input: $input,
                context: $context,
                state: $state,
            );
        }

        return $value;
    }

    private function compareNumeric(
        mixed $actual,
        mixed $expected,
        string $operator,
    ): bool {
        if (
            (! is_int($actual) && ! is_float($actual))
            || (! is_int($expected) && ! is_float($expected))
        ) {
            throw new \DomainException(
                'Numeric comparison requires numeric values.'
            );
        }

        return match ($operator) {
            '>' => $actual > $expected,
            '>=' => $actual >= $expected,
            '<' => $actual < $expected,
            '<=' => $actual <= $expected,
        };
    }
}