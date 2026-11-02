<?php

namespace App\Payroll\Tax;

/** Shared progressive-band maths. Bands: [[upper bound in major units or null, rate %], ...]; bounds are cumulative. */
final class Progressive
{
    /**
     * Exact tax in micro-minor units (minor × 1,000,000) so band rounding never accumulates.
     *
     * @param  list<array{0: int|float|null, 1: float|int}>  $bands
     */
    public static function micro(int $income, array $bands, int $decimals): int
    {
        $total = 0;
        $lower = 0;
        foreach ($bands as [$upper, $rate]) {
            $top = $upper === null ? PHP_INT_MAX : (int) round($upper * 10 ** $decimals);
            if ($income <= $lower) {
                break;
            }
            $slice = min($income, $top) - $lower;
            $total += $slice * (int) round($rate * 10_000);
            $lower = $top;
        }

        return $total;
    }
}
