<?php

declare(strict_types=1);

namespace App\Application\Behavior;

use App\Domain\Blueprint\ValueObjects\BehaviorDigest;
use JsonException;

final class BehaviorDigestCalculator
{
    /**
     * Calculate a deterministic SHA-256 digest from behavior logic.
     *
     * Associative array keys are sorted recursively so their order does not
     * affect the digest. List element order is preserved because it is part
     * of the behavior sequence.
     *
     * @param array<string, mixed> $logic
     *
     * @throws JsonException
     */
    public function calculate(array $logic): BehaviorDigest
    {
        $canonical = $this->canonicalize($logic);

        $encoded = json_encode(
            $canonical,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRESERVE_ZERO_FRACTION
            | JSON_THROW_ON_ERROR,
        );

        return new BehaviorDigest(
            'sha256:' . hash('sha256', $encoded),
        );
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(
                fn (mixed $item): mixed => $this->canonicalize($item),
                $value,
            );
        }

        $canonical = [];

        foreach ($value as $key => $item) {
            $canonical[(string) $key] = $this->canonicalize($item);
        }

        ksort($canonical, SORT_STRING);

        return $canonical;
    }
}
