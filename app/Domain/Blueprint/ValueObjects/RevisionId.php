<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\ValueObjects;

use Illuminate\Support\Str;
use InvalidArgumentException;

final readonly class RevisionId
{
    public function __construct(
        public string $value,
    ) {
        if (! Str::isUlid($value)) {
            throw new InvalidArgumentException(
                'Invalid Revision ID. Expected a valid ULID.'
            );
        }
    }

    public static function generate(): self
    {
        return new self((string) Str::ulid());
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}