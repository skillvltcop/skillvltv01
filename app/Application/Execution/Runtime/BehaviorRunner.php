<?php

declare(strict_types=1);

namespace App\Application\Execution\Runtime;

use App\Application\Behavior\BehaviorContractValidator;
use App\Application\Behavior\ValueResolver;
use App\Application\Execution\NumericValueComparator;
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

            if ($type === 'aggregate') {
                $source = $this->valueResolver->resolve(
                    path: $step['source'],
                    input: $input,
                    context: $context,
                    state: $state,
                    item: $item,
                );

                if (! is_array($source)) {
                    throw new \DomainException('Aggregate source must be an array.');
                }

                $operation = $step['operation'];

                if ($operation === 'count') {
                    $state[$step['assign_to']] = count($source);
                    continue;
                }

                $values = [];

                foreach ($source as $entry) {
                    if (! is_array($entry)) {
                        throw new \DomainException('Aggregate items must be arrays.');
                    }

                    $missing = new \stdClass();
                    $value = data_get($entry, $step['field'], $missing);

                    if ($value === $missing) {
                        throw new \DomainException(
                            sprintf('Aggregate field "%s" is missing.', $step['field'])
                        );
                    }

                    if ($operation === 'count_matching') {
                        if (in_array($step['operator'], ['gt', 'gte', 'lt', 'lte'], true)) {
                            if (! is_numeric($value)) {
                                throw new \DomainException(
                                    'Aggregate comparison value must be numeric.'
                                );
                            }

                            $numericValue = (float) $value;

                            if (! is_finite($numericValue)) {
                                throw new \DomainException(
                                    'Aggregate comparison value must be finite.'
                                );
                            }

                            if (
                                $numericValue === 0.0
                                && NumericValueComparator::compare($value, '0') !== 0
                            ) {
                                throw new \DomainException(
                                    'Aggregate comparison value must be finite and representable.'
                                );
                            }
                        }

                        $expected = $step['value'];
                        $matches = match ($step['operator']) {
                            'eq' => $value === $expected,
                            'neq' => $value !== $expected,
                            'gt' => is_numeric($value) && is_numeric($expected) && NumericValueComparator::compare($value, $expected) > 0,
                            'gte' => is_numeric($value) && is_numeric($expected) && NumericValueComparator::compare($value, $expected) >= 0,
                            'lt' => is_numeric($value) && is_numeric($expected) && NumericValueComparator::compare($value, $expected) < 0,
                            'lte' => is_numeric($value) && is_numeric($expected) && NumericValueComparator::compare($value, $expected) <= 0,
                        };

                        if ($matches) {
                            $values[] = $value;
                        }

                        continue;
                    }

                    if (! is_int($value) && ! is_float($value)) {
                        throw new \DomainException(
                            sprintf('Aggregate field "%s" must be numeric.', $step['field'])
                        );
                    }

                    $values[] = $value;
                }

                $aggregateResult = match ($operation) {
                    'sum' => array_sum($values),
                    'average' => $values === []
                        ? throw new \DomainException('Cannot average an empty collection.')
                        : array_sum($values) / count($values),
                    'count_matching' => count($values),
                };

                if (is_float($aggregateResult) && ! is_finite($aggregateResult)) {
                    throw new \DomainException(
                        'Aggregate result must be finite.'
                    );
                }

                if (
                    $operation === 'average'
                    && $aggregateResult === 0.0
                    && array_sum($values) != 0
                ) {
                    throw new \DomainException(
                        'Aggregate result must be representable as a finite number.'
                    );
                }

                $state[$step['assign_to']] = $aggregateResult;

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

        // Input validation accepts numeric strings, so normalize them before arithmetic.
        if (is_string($left) && is_numeric($left)) {
            $normalizedLeft = (float) $left;

            if ($normalizedLeft === 0.0 && NumericValueComparator::compare($left, '0') !== 0) {
                throw new \DomainException(
                    'Calculation operands must be representable as finite numbers.'
                );
            }

            $left = $normalizedLeft;
        }

        if (is_string($right) && is_numeric($right)) {
            $normalizedRight = (float) $right;

            if ($normalizedRight === 0.0 && NumericValueComparator::compare($right, '0') !== 0) {
                throw new \DomainException(
                    'Calculation operands must be representable as finite numbers.'
                );
            }

            $right = $normalizedRight;
        }

        if (
            (! is_int($left) && ! is_float($left))
            || (! is_int($right) && ! is_float($right))
        ) {
            throw new \DomainException(
                'Calculation requires numeric operands.'
            );
        }

        if (
            (is_float($left) && ! is_finite($left))
            || (is_float($right) && ! is_finite($right))
        ) {
            throw new \DomainException(
                'Calculation operands must be finite.'
            );
        }

        if ($step['operation'] === 'divide' && $right == 0) {
            throw new \DomainException(
                'Division by zero is not allowed.'
            );
        }

        $result = match ($step['operation']) {
            'add' => $left + $right,
            'subtract' => $left - $right,
            'multiply' => $left * $right,
            'divide' => $left / $right,
        };

        if (is_float($result) && ! is_finite($result)) {
            throw new \DomainException(
                'Calculation result must be finite.'
            );
        }

        if (
            in_array($step['operation'], ['multiply', 'divide'], true)
            && $left != 0
            && $right != 0
            && $result === 0.0
        ) {
            throw new \DomainException(
                'Calculation result must be representable as a finite number.'
            );
        }

        $state[$step['assign_to']] = $result;
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

                if (! is_scalar($value) && $value !== null) {
                    throw new \DomainException(
                        sprintf(
                            'Interpolation value for "%s" must be scalar.',
                            $matches[1],
                        )
                    );
                }

                if (is_float($value) && ! is_finite($value)) {
                    throw new \DomainException(
                        sprintf(
                            'Interpolation value for "%s" must be finite.',
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
        if (is_float($value) && ! is_finite($value)) {
            throw new \DomainException(
                'Return values must be finite.'
            );
        }

        if (is_string($value)) {
            if (preg_match('/^\\{([^{}\\s]+)\\}$/', $value, $matches) === 1) {
                $resolved = $this->valueResolver->resolve(
                    path: $matches[1],
                    input: $input,
                    context: $context,
                    state: $state,
                    item: $item,
                );

                if (is_float($resolved) && ! is_finite($resolved)) {
                    throw new \DomainException(
                        sprintf(
                            'Return value for "%s" must be finite.',
                            $matches[1],
                        )
                    );
                }

                return $resolved;
            }

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

                    if (! is_scalar($resolved) && $resolved !== null) {
                        throw new \DomainException(
                            sprintf(
                                'Interpolation value for "%s" must be scalar.',
                                $matches[1],
                            )
                        );
                    }

                    if (is_float($resolved) && ! is_finite($resolved)) {
                        throw new \DomainException(
                            sprintf(
                                'Interpolation value for "%s" must be finite.',
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

        if (
            (is_float($actual) && ! is_finite($actual))
            || (is_float($expected) && ! is_finite($expected))
        ) {
            throw new \DomainException(
                'Numeric comparison values must be finite.'
            );
        }

        $actualValue = is_float($actual)
            ? json_encode($actual, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)
            : (string) $actual;
        $expectedValue = is_float($expected)
            ? json_encode($expected, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR)
            : (string) $expected;

        $comparison = NumericValueComparator::compare($actualValue, $expectedValue);

        return match ($operator) {
            '>' => $comparison > 0,
            '>=' => $comparison >= 0,
            '<' => $comparison < 0,
            '<=' => $comparison <= 0,
        };
    }
}