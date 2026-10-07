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

        return $this->executeSteps(
            steps: $logic['steps'],
            input: $input,
            context: $context,
            state: $state,
        );

        throw new \DomainException(
            'Behavior execution did not produce a return output.'
        );
    }

    /**
     * @param array<int, array<string, mixed>> $steps
     * @param array<string, mixed> $input
     * @param array<string, mixed> $context
     * @param array<string, mixed> $state
     * @param array<string, mixed>|null $item
     * @return array<string, mixed>
     */
    private function executeSteps(
        array $steps,
        array $input,
        array $context,
        array &$state,
        ?array $item = null,
    ): array {
        foreach ($steps as $step) {
            $type = $step['type'];

            if ($type === 'map') {
                $source = $this->valueResolver->resolve(
                    path: $step['source'],
                    input: $input,
                    context: $context,
                    state: $state,
                    item: $item,
                );

                if (! is_array($source)) {
                    throw new \DomainException('Map source must be an array.');
                }

                $mapped = [];

                foreach ($source as $sourceItem) {
                    if (! is_array($sourceItem)) {
                        throw new \DomainException('Map items must be arrays.');
                    }

                    $nestedState = [];
                    $mapped[] = $this->executeSteps(
                        steps: $step['steps'],
                        input: $input,
                        context: $context,
                        state: $nestedState,
                        item: $sourceItem,
                    );
                }

                $state[$step['assign_to']] = $mapped;
                continue;
            }

            if ($type === 'evaluate_rule') {
                $this->executeEvaluateRule($step, $input, $context, $state, $item);
                continue;
            }

            if ($type === 'format_template') {
                $this->executeFormatTemplate($step, $input, $context, $state, $item);
                continue;
            }

            if ($type === 'calculate') {
                $this->executeCalculate($step, $input, $context, $state, $item);
                continue;
            }

            if ($type === 'return') {
                return $this->resolveData(
                    data: $step['data'],
                    input: $input,
                    context: $context,
                    state: $state,
                    item: $item,
                );
            }

            throw new \DomainException(
                sprintf('Unsupported behavior step type "%s".', $type)
            );
        }

        throw new \DomainException('Behavior execution did not produce a return output.');
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
        ?array $item = null,
    ): void {
        $condition = $step['condition'];

        $actual = $this->valueResolver->resolve(
            path: $condition['field'],
            input: $input,
            context: $context,
            state: $state,
            item: $item,
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
    private function executeCalculate(
        array $step,
        array $input,
        array $context,
        array &$state,
        ?array $item = null,
    ): void {
        $left = $this->resolveCalculateOperand(
            $step['left'],
            $input,
            $context,
            $state,
            $item,
        );
        $right = $this->resolveCalculateOperand(
            $step['right'],
            $input,
            $context,
            $state,
            $item,
        );

        if (
            (! is_int($left) && ! is_float($left))
            || (! is_int($right) && ! is_float($right))
        ) {
            throw new \DomainException(
                'Calculation requires numeric operands.'
            );
        }

        if ($step['operation'] === 'divide' && $right == 0) {
            throw new \DomainException(
                'Division by zero is not allowed.'
            );
        }

        $state[$step['assign_to']] = match ($step['operation']) {
            'add' => $left + $right,
            'subtract' => $left - $right,
            'multiply' => $left * $right,
            'divide' => $left / $right,
        };
    }

    private function resolveCalculateOperand(
        mixed $operand,
        array $input,
        array $context,
        array $state,
        ?array $item = null,
    ): mixed {
        if (is_string($operand)) {
            return $this->valueResolver->resolve(
                path: $operand,
                input: $input,
                context: $context,
                state: $state,
                item: $item,
            );
        }

        return $operand;
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
        ?array $item = null,
    ): void {
        $template = $step['template'];

        $state[$step['assign_to']] = preg_replace_callback(
            '/{([^{}\s]+)}/',
            function (array $matches) use (
                $input,
                $context,
                $state,
                $item,
            ): string {
                $value = $this->valueResolver->resolve(
                    path: $matches[1],
                    input: $input,
                    context: $context,
                    state: $state,
                    item: $item,
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
        ?array $item = null,
    ): array {
        $resolved = [];

        foreach ($data as $key => $value) {
            $resolved[$key] = $this->resolveDataValue(
                value: $value,
                input: $input,
                context: $context,
                state: $state,
                item: $item,
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
        ?array $item = null,
    ): mixed {
        if (is_string($value)) {
            return preg_replace_callback(
                '/{([^{}\s]+)}/',
                function (array $matches) use (
                    $input,
                    $context,
                    $state,
                    $item,
                ): string {
                    $resolved = $this->valueResolver->resolve(
                        path: $matches[1],
                        input: $input,
                        context: $context,
                        state: $state,
                        item: $item,
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
                item: $item,
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