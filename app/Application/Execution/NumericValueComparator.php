<?php

declare(strict_types=1);

namespace App\Application\Execution;

final class NumericValueComparator
{
    /**
     * Compare finite numeric values without losing precision to binary floats.
     *
     * @return int -1 when $left < $right, 0 when equal, 1 when $left > $right
     */
    public static function compare(mixed $left, mixed $right): int
    {
        $leftParts = self::parts(self::toNumericString($left));
        $rightParts = self::parts(self::toNumericString($right));

        if ($leftParts['sign'] !== $rightParts['sign']) {
            return $leftParts['sign'] <=> $rightParts['sign'];
        }

        if ($leftParts['sign'] === 0) {
            return 0;
        }

        $magnitude = self::compareSignedIntegers(
            $leftParts['order'],
            $rightParts['order'],
        );

        if ($magnitude === 0) {
            $length = max(strlen($leftParts['digits']), strlen($rightParts['digits']));

            for ($index = 0; $index < $length; $index++) {
                $leftDigit = (int) ($leftParts['digits'][$index] ?? '0');
                $rightDigit = (int) ($rightParts['digits'][$index] ?? '0');

                if ($leftDigit !== $rightDigit) {
                    $magnitude = $leftDigit <=> $rightDigit;
                    break;
                }
            }
        }

        return $leftParts['sign'] * $magnitude;
    }


    private static function toNumericString(mixed $value): string
    {
        if (is_float($value)) {
            if (! is_finite($value)) {
                throw new \\InvalidArgumentException('Value must be finite.');
            }

            return json_encode(
                $value,
                JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR,
            );
        }

        if (! is_int($value) && ! is_string($value)) {
            throw new \\InvalidArgumentException('Value must be numeric.');
        }

        return (string) $value;
    }

    /**
     * @return array{sign: int, order: string, digits: string}
     */
    private static function parts(string $value): array
    {
        $value = trim($value);

        if (! preg_match('/^([+-]?)(\d*)(?:\.(\d*))?(?:[eE]([+-]?\d+))?$/D', $value, $matches)
            || (($matches[2] ?? '') === '' && ($matches[3] ?? '') === '')) {
            throw new \InvalidArgumentException('Value must be numeric.');
        }

        $integer = $matches[2] ?? '';
        $fraction = $matches[3] ?? '';
        $digits = $integer.$fraction;
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return ['sign' => 0, 'order' => '0', 'digits' => '0'];
        }

        $leadingZeroCount = strlen($integer.$fraction) - strlen(ltrim($integer.$fraction, '0'));
        $order = self::addIntegerOffset(
            $matches[4] ?? '0',
            strlen($integer) - $leadingZeroCount,
        );

        return [
            'sign' => ($matches[1] ?? '') === '-' ? -1 : 1,
            'order' => $order,
            'digits' => $digits,
        ];
    }

    private static function addIntegerOffset(string $value, int $offset): string
    {
        $value = self::normalizeSignedInteger($value);

        if ($offset === 0) {
            return $value;
        }

        $valueSign = $value[0] === '-' ? -1 : ($value === '0' ? 0 : 1);
        $valueDigits = ltrim($value, '-');
        $offsetSign = $offset < 0 ? -1 : 1;
        $offsetDigits = (string) abs($offset);

        if ($valueSign === 0) {
            return ($offsetSign < 0 ? '-' : '').$offsetDigits;
        }

        if ($valueSign === $offsetSign) {
            return ($valueSign < 0 ? '-' : '')
                .self::addMagnitudes($valueDigits, $offsetDigits);
        }

        $comparison = self::compareMagnitudes($valueDigits, $offsetDigits);

        if ($comparison === 0) {
            return '0';
        }

        if ($comparison > 0) {
            return ($valueSign < 0 ? '-' : '')
                .self::subtractMagnitudes($valueDigits, $offsetDigits);
        }

        return ($offsetSign < 0 ? '-' : '')
            .self::subtractMagnitudes($offsetDigits, $valueDigits);
    }

    private static function normalizeSignedInteger(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $digits = ltrim($value, '+-');
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return '0';
        }

        return ($negative ? '-' : '').$digits;
    }

    private static function compareSignedIntegers(string $left, string $right): int
    {
        $leftSign = $left[0] === '-' ? -1 : ($left === '0' ? 0 : 1);
        $rightSign = $right[0] === '-' ? -1 : ($right === '0' ? 0 : 1);

        if ($leftSign !== $rightSign) {
            return $leftSign <=> $rightSign;
        }

        if ($leftSign === 0) {
            return 0;
        }

        return $leftSign * self::compareMagnitudes(
            ltrim($left, '-'),
            ltrim($right, '-'),
        );
    }

    private static function compareMagnitudes(string $left, string $right): int
    {
        $lengthComparison = strlen($left) <=> strlen($right);

        return $lengthComparison !== 0
            ? $lengthComparison
            : (strcmp($left, $right) <=> 0);
    }

    private static function addMagnitudes(string $left, string $right): string
    {
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;
        $carry = 0;
        $result = '';

        while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
            $sum = ($leftIndex >= 0 ? (int) $left[$leftIndex--] : 0)
                + ($rightIndex >= 0 ? (int) $right[$rightIndex--] : 0)
                + $carry;

            $result = (string) ($sum % 10).$result;
            $carry = intdiv($sum, 10);
        }

        return $result;
    }

    /**
     * Subtract the smaller non-negative integer magnitude from the larger one.
     */
    private static function subtractMagnitudes(string $larger, string $smaller): string
    {
        $largerIndex = strlen($larger) - 1;
        $smallerIndex = strlen($smaller) - 1;
        $borrow = 0;
        $result = '';

        while ($largerIndex >= 0) {
            $digit = (int) $larger[$largerIndex--] - $borrow;
            $subtract = $smallerIndex >= 0 ? (int) $smaller[$smallerIndex--] : 0;

            if ($digit < $subtract) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result = (string) ($digit - $subtract).$result;
        }

        return ltrim($result, '0') ?: '0';
    }
}
