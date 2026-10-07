<?php

declare(strict_types=1);

namespace App\Application\Behavior;

use App\Domain\Behavior\Exceptions\InvalidBehaviorContractException;

final class BehaviorContractValidator
{
    private const ALLOWED_OPERATORS = [
        'eq',
        'neq',
        'gt',
        'gte',
        'lt',
        'lte',
    ];

    private const ALLOWED_PATH_ROOTS = [
        'input',
        'context',
        'state',
    ];

    private const IDENTIFIER_REGEX = '/^[a-zA-Z_][a-zA-Z0-9_]*$/';

    private const PLACEHOLDER_REGEX = '/{([^{}]*)}/';

    /**
     * @throws InvalidBehaviorContractException
     */
    public function validate(array $behavior): void
    {
        $errors = [];

        if (($behavior['type'] ?? null) !== 'steps') {
            $errors['root.type'] =
                'The type attribute must equal "steps".';
        }

        if (($behavior['version'] ?? null) !== 1) {
            $errors['root.version'] =
                'The version attribute must equal 1.';
        }

        $steps = $behavior['steps'] ?? null;

        if (! is_array($steps) || $steps === []) {
            $errors['root.steps'] =
                'The steps attribute must be a non-empty array.';

            throw new InvalidBehaviorContractException($errors);
        }

        $hasReturned = false;
        $returnCount = 0;
        $totalSteps = count($steps);

        foreach ($steps as $index => $step) {
            $prefix = "steps.{$index}";

            if (! is_array($step)) {
                $errors["{$prefix}.structure"] =
                    'Each step must be an array.';

                continue;
            }

            $type = $step['type'] ?? null;

            if (! is_string($type)) {
                $errors["{$prefix}.type"] =
                    'Step type must be a string.';

                continue;
            }

        if ($type === 'return') {
            $returnCount++;

            if ($returnCount > 1) {
                $errors["{$prefix}.duplicate"] =
                    'Only one "return" step is allowed.';
            }

            if ($index !== $totalSteps - 1) {
                $errors["{$prefix}.position"] =
                    'The "return" step must be the last step in the sequence.';
            }

            $hasReturned = true;

            $this->validateReturn(
                $step,
                $prefix,
                $errors,
            );

            continue;
        }

        if ($hasReturned) {
            $errors["{$prefix}.unreachable"] =
                'No steps are allowed after the "return" step.';

            continue;
        }

            match ($type) {
                'evaluate_rule' => $this->validateEvaluateRule(
                    $step,
                    $prefix,
                    $errors,
                ),

                'format_template' => $this->validateFormatTemplate(
                    $step,
                    $prefix,
                    $errors,
                ),

                'calculate' => $this->validateCalculate(
                    $step,
                    $prefix,
                    $errors,
                ),

                'map' => $this->validateMap(
                    $step,
                    $prefix,
                    $errors,
                ),

                default => $errors["{$prefix}.type"] =
                    sprintf('Unknown step type "%s".', $type),
            };
        }

        if ($returnCount === 0) {
            $errors['root.sequence'] =
                'The behavior contract must end with a "return" step.';
        }

        if ($errors !== []) {
            throw new InvalidBehaviorContractException($errors);
        }
    }

    /**
     * @param array<string, mixed> $step
     * @param array<string, string> $errors
     */
    private function validateEvaluateRule(
        array $step,
        string $prefix,
        array &$errors,
    ): void {
        $condition = $step['condition'] ?? null;

        if (! is_array($condition)) {
            $errors["{$prefix}.condition"] =
                'condition must be an array.';

            return;
        }

        $field = $condition['field'] ?? null;

        if (
            ! is_string($field)
            || ! $this->isValidPath($field)
        ) {
            $errors["{$prefix}.condition.field"] =
                'Field must be a valid input., context., or state. path.';
        }

        $operator = $condition['operator'] ?? null;

        if (
            ! is_string($operator)
            || ! in_array($operator, self::ALLOWED_OPERATORS, true)
        ) {
            $errors["{$prefix}.condition.operator"] =
                'Operator must be one of: '
                . implode(', ', self::ALLOWED_OPERATORS)
                . '.';
        }

        if (! array_key_exists('value', $condition)) {
            $errors["{$prefix}.condition.value"] =
                'Condition must contain a static comparison value.';
        } elseif (
            in_array(
                $operator,
                ['gt', 'gte', 'lt', 'lte'],
                true,
            )
            && (! is_int($condition['value']) && ! is_float($condition['value']))
        ) {
            $errors["{$prefix}.condition.value"] =
                'Numeric comparison operators require a numeric comparison value.';
        }

        $assignTo = $step['assign_to'] ?? null;

        if (
            ! is_string($assignTo)
            || preg_match(self::IDENTIFIER_REGEX, $assignTo) !== 1
        ) {
            $errors["{$prefix}.assign_to"] =
                'assign_to must be a simple identifier matching '
                . '[a-zA-Z_][a-zA-Z0-9_]*.';
        }

        if (! array_key_exists('true_value', $step)) {
            $errors["{$prefix}.true_value"] =
                'evaluate_rule must define true_value.';
        }

        if (! array_key_exists('false_value', $step)) {
            $errors["{$prefix}.false_value"] =
                'evaluate_rule must define false_value.';
        }
    }

    /**
     * @param array<string, mixed> $step
     * @param array<string, string> $errors
     */
    private function validateFormatTemplate(
        array $step,
        string $prefix,
        array &$errors,
    ): void {
        $template = $step['template'] ?? null;

        if (! is_string($template)) {
            $errors["{$prefix}.template"] =
                'template must be a string.';
        } else {
            $this->validatePlaceholders(
                $template,
                "{$prefix}.template",
                $errors,
            );
        }

        $assignTo = $step['assign_to'] ?? null;

        if (
            ! is_string($assignTo)
            || preg_match(self::IDENTIFIER_REGEX, $assignTo) !== 1
        ) {
            $errors["{$prefix}.assign_to"] =
                'assign_to must be a simple identifier matching '
                . '[a-zA-Z_][a-zA-Z0-9_]*.';
        }
    }

    /**
     * @param array<string, mixed> $step
     * @param array<string, string> $errors
     */
    private function validateMap(
        array $step,
        string $prefix,
        array &$errors,
    ): void {
        $source = $step['source'] ?? null;

        if (! is_string($source) || ! $this->isValidPath($source)) {
            $errors["{$prefix}.source"] =
                'source must be a valid input., context., or state. path.';
        }

        $assignTo = $step['assign_to'] ?? null;

        if (
            ! is_string($assignTo)
            || preg_match(self::IDENTIFIER_REGEX, $assignTo) !== 1
        ) {
            $errors["{$prefix}.assign_to"] =
                'assign_to must be a simple identifier matching '
                . '[a-zA-Z_][a-zA-Z0-9_]*.';
        }

        $nestedSteps = $step['steps'] ?? null;

        if (! is_array($nestedSteps) || $nestedSteps === []) {
            $errors["{$prefix}.steps"] =
                'map must contain a non-empty steps array.';

            return;
        }

        $returnCount = 0;
        $totalSteps = count($nestedSteps);

        foreach ($nestedSteps as $index => $nestedStep) {
            $nestedPrefix = "{$prefix}.steps.{$index}";

            if (! is_array($nestedStep)) {
                $errors["{$nestedPrefix}.structure"] = 'Each map step must be an array.';
                continue;
            }

            $type = $nestedStep['type'] ?? null;

            if (! is_string($type)) {
                $errors["{$nestedPrefix}.type"] = 'Map step type must be a string.';
                continue;
            }

            if ($type === 'return') {
                $returnCount++;

                if ($index !== $totalSteps - 1) {
                    $errors["{$nestedPrefix}.position"] =
                        'The map "return" step must be the last step.';
                }

                $this->validateReturn(
                    $nestedStep,
                    $nestedPrefix,
                    $errors,
                    true,
                );

                continue;
            }

            match ($type) {
                'calculate' => $this->validateCalculate(
                    $nestedStep,
                    $nestedPrefix,
                    $errors,
                    true,
                ),
                default => $errors["{$nestedPrefix}.type"] =
                    sprintf('Unknown map step type "%s".', $type),
            };
        }

        if ($returnCount === 0) {
            $errors["{$prefix}.sequence"] =
                'The map steps must end with a "return" step.';
        }
    }

    /**
     * @param array<string, mixed> $step
     * @param array<string, string> $errors
     */
    private function validateCalculate(
        array $step,
        string $prefix,
        array &$errors,
        bool $allowItemPaths = false,
    ): void {
        $operation = $step['operation'] ?? null;

        if (
            ! is_string($operation)
            || ! in_array($operation, ['add', 'subtract', 'multiply', 'divide'], true)
        ) {
            $errors["{$prefix}.operation"] =
                'operation must be one of: add, subtract, multiply, divide.';
        }

        foreach (['left', 'right'] as $operand) {
            if (! array_key_exists($operand, $step)) {
                $errors["{$prefix}.{$operand}"] =
                    "calculate step must contain a {$operand} operand.";

                continue;
            }

            $value = $step[$operand];

            if (is_string($value) && ! $this->isValidPath($value, $allowItemPaths)) {
                $errors["{$prefix}.{$operand}"] =
                    "{$operand} must be a numeric value or a valid input., context., state., or item. path.";
                continue;
            }

            if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
                $errors["{$prefix}.{$operand}"] =
                    "{$operand} must be a numeric value or a valid input., context., or state. path.";
            }
        }

        $assignTo = $step['assign_to'] ?? null;

        if (
            ! is_string($assignTo)
            || preg_match(self::IDENTIFIER_REGEX, $assignTo) !== 1
        ) {
            $errors["{$prefix}.assign_to"] =
                'assign_to must be a simple identifier matching '
                . '[a-zA-Z_][a-zA-Z0-9_]*.';
        }
    }

    /**
     * @param array<string, mixed> $step
     * @param array<string, string> $errors
     */
    private function validateReturn(
        array $step,
        string $prefix,
        array &$errors,
        bool $allowItemPaths = false,
    ): void {
    if (! array_key_exists('data', $step)) {
        $errors["{$prefix}.data"] =
            'return step must contain a data payload.';

        return;
    }

    if (! is_array($step['data'])) {
        $errors["{$prefix}.data"] =
            'return data must be an array.';

        return;
    }

    $this->validateDataPlaceholdersRecursively(
        $step['data'],
        "{$prefix}.data",
        $errors,
    );
    }

    /**
     * @param array<string, string> $errors
     */
    private function validateDataPlaceholdersRecursively(
        mixed $data,
        string $prefix,
        array &$errors,
        bool $allowItemPaths = false,
    ): void {
        if (is_string($data)) {
            $this->validatePlaceholders(
                $data,
                $prefix,
                $errors,
            );

            return;
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                $this->validateDataPlaceholdersRecursively(
                    $value,
                    "{$prefix}.{$key}",
                    $errors,
                    $allowItemPaths,
                );
            }
        }
    }

    /**
     * @param array<string, string> $errors
     */
    private function validatePlaceholders(
        string $text,
        string $errorKey,
        array &$errors,
        bool $allowItemPaths = false,
    ): void {
        if (substr_count($text, '{') !== substr_count($text, '}')) {
            $errors["{$errorKey}.placeholder"] =
                'Placeholder braces must be balanced.';

            return;
        }

        preg_match_all(
            self::PLACEHOLDER_REGEX,
            $text,
            $matches,
        );

        foreach ($matches[1] as $path) {
            if (! $this->isValidPath($path, $allowItemPaths)) {
                $errors["{$errorKey}.placeholder"] =
                    sprintf(
                        'Invalid placeholder path "{%s}". '
                        . 'Must start with input., context., or state.',
                        $path,
                    );
            }
        }
    }

    private function isValidPath(
        string $path,
        bool $allowItemPaths = false,
    ): bool {
        $pattern = $allowItemPaths
            ? '/^(input|context|state|item)\.[a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*$/'
            : '/^(input|context|state)\.[a-zA-Z0-9_]+(?:\.[a-zA-Z0-9_]+)*$/';

        if (preg_match($pattern, $path) !== 1) {
            return false;
        }

        [$root] = explode('.', $path, 2);

        return in_array(
            $root,
            $allowItemPaths
                ? ['input', 'context', 'state', 'item']
                : self::ALLOWED_PATH_ROOTS,
            true,
        );
    }
}