<?php

namespace App\Payroll;

/**
 * Integer money maths in minor units (rupiah, pence). Rates are percentages with up to 4 decimals.
 * Floats never touch an amount, so 10,454,000 × 2.5% is exactly 261,350.
 */
final class Money
{
    public static function toMinor(float|int|string $amount, int $decimals): int
    {
        return (int) round((float) $amount * 10 ** $decimals);
    }

    public static function fromMinor(int $minor, int $decimals): string
    {
        return number_format($minor / 10 ** $decimals, $decimals, '.', '');
    }

    /** $amount × $rate% ; $floor truncates (tax), otherwise rounds half up (contributions). */
    public static function pct(int $amount, float $rate, bool $floor = false): int
    {
        return self::frac($amount, (int) round($rate * 10_000), 1_000_000, $floor);
    }

    /** $amount × $num ÷ $den, rounded half up (or floored). */
    public static function frac(int $amount, int $num, int $den, bool $floor = false): int
    {
        if ($den === 0) {
            return 0;
        }
        $sign = ($amount < 0) !== ($num < 0) ? -1 : 1;
        $a = abs($amount) * abs($num);
        $d = abs($den);

        return $sign * ($floor ? intdiv($a, $d) : intdiv(2 * $a + $d, 2 * $d));
    }
}
