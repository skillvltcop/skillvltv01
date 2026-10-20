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
        $leftParts = self::parts((string) $left);
        $rightParts = self::parts((string) $right);

        if ($leftParts['sign'] !== $rightParts['sign']) {
            return $leftParts['sign'] <=> $rightParts['sign'];
        }

        if ($leftParts['sign'] === 0) {
            return 0;
        }

        $magnitude = $leftParts['order'] <=> $rightParts['order'];

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

    /**
     * @return array{sign: int, order: int, digits: string}
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
        $exponent = (int) ($matches[4] ?? '0');
        $digits = ltrim($digits, '0');

        if ($digits === '') {
            return ['sign' => 0, 'order' => 0, 'digits' => '0'];
        }

        $leadingZeroCount = strlen($integer.$fraction) - strlen(ltrim($integer.$fraction, '0'));
        $order = strlen($integer) + $exponent - $leadingZeroCount;

        return [
            'sign' => ($matches[1] ?? '') === '-' ? -1 : 1,
            'order' => $order,
            'digits' => $digits,
        ];
    }
}
