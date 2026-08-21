<?php

declare(strict_types=1);

namespace App\Domain\Execution\ValueObjects;

use Illuminate\Support\Str;

final readonly class ExecutionId
{
    public function __construct(
        private string $value,
    ) {
        if (! Str::isUlid($value)) {
            throw new \InvalidArgumentException(
                'Invalid execution ID.'
            );
        }
    }

    public static function generate(): self
    {
        return new self((string) Str::ulid());
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }
}