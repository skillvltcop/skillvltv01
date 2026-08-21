<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\ValueObjects;

use InvalidArgumentException;

final readonly class BlueprintNamespace
{
    private const PATTERN = '/^[a-z0-9]+(?:\.[a-z0-9]+)*$/';

    public function __construct(
        public string $value,
    ) {
        if (! preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException(
                'Invalid Blueprint namespace. Expected lowercase dot-separated segments.'
            );
        }
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