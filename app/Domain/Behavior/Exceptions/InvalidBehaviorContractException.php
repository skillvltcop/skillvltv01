<?php

declare(strict_types=1);

namespace App\Domain\Behavior\Exceptions;

use Exception;

final class InvalidBehaviorContractException extends Exception
{
    /**
     * @param array<string, string> $errors
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Behavior contract validation failed.',
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}