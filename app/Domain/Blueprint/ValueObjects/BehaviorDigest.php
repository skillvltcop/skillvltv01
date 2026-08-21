<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\ValueObjects;

use InvalidArgumentException;

final readonly class BehaviorDigest
{
    private const PATTERN = '/^sha256:[a-f0-9]{64}$/';

    public function __construct(
        public string $value,
    ) {
        if (! preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException(
                'Invalid behavior digest. Expected sha256:<64 lowercase hexadecimal characters>.'
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