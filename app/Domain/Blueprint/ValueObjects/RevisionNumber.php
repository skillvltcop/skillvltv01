<?php

declare(strict_types=1);

namespace App\Domain\Blueprint\ValueObjects;

use InvalidArgumentException;

final readonly class RevisionNumber
{
    private const PATTERN = '/^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$/';

    public function __construct(
        public string $value,
    ) {
        if (! preg_match(self::PATTERN, $value)) {
            throw new InvalidArgumentException(
                'Invalid revision number. Expected MAJOR.MINOR.PATCH semantic version.'
            );
        }
    }

    public function major(): int
    {
        return (int) explode('.', $this->value)[0];
    }

    public function minor(): int
    {
        return (int) explode('.', $this->value)[1];
    }

    public function patch(): int
    {
        return (int) explode('.', $this->value)[2];
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