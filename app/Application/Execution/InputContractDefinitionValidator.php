<?php

declare(strict_types=1);

namespace App\Application\Execution;

final class InputContractDefinitionValidator
{
    /**
     * Validate the shape of the optional input.required declaration.
     *
     * @param array<string, mixed> $contracts
     */
    public function validate(array $contracts): void
    {
        if (! array_key_exists('input', $contracts)) {
            return;
        }

        $inputContract = $contracts['input'];

        if (! is_array($inputContract) || ! array_key_exists('required', $inputContract)) {
            return;
        }

        $required = $inputContract['required'];

        if (! is_array($required) || ! array_is_list($required)) {
            throw new \\DomainException(
                'Input contract required must be a list of non-empty strings.'
            );
        }

        $seen = [];

        foreach ($required as $field) {
            if (! is_string($field) || trim($field) === '') {
                throw new \\DomainException(
                    'Input contract required must contain only non-empty strings.'
                );
            }

            if (in_array($field, $seen, true)) {
                throw new \\DomainException(
                    sprintf('Input contract required contains duplicate field "%s".', $field)
                );
            }

            $seen[] = $field;
        }
    }
}
