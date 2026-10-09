<?php

declare(strict_types=1);

namespace App\Application\Execution;

final class InputContractValidator
{
    /**
     * Validate the required fields declared by a revision's input contract.
     *
     * Contracts without a required list remain backward compatible.
     *
     * @param array<string, mixed> $contracts
     * @param array<string, mixed> $input
     */
    public function validate(array $contracts, array $input): void
    {
        $required = $contracts['input']['required'] ?? [];

        if (! is_array($required) || $required === []) {
            return;
        }

        $missing = [];

        foreach ($required as $field) {
            if (is_string($field) && ! array_key_exists($field, $input)) {
                $missing[] = $field;
            }
        }

        if ($missing !== []) {
            throw new \DomainException(
                'Input is missing required field(s): ' . implode(', ', $missing) . '.'
            );
        }
    }
}
