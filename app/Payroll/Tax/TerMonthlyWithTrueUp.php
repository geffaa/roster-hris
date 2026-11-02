<?php

namespace App\Payroll\Tax;

use App\Payroll\Money;
use App\Payroll\PayslipInput;
use App\Payroll\RulePackSnapshot;
use InvalidArgumentException;

/**
 * Indonesia PPh 21 for permanent employees (PP 58/2023, PMK 168/2023):
 * every month except the last of the year (or of employment) uses the monthly TER rate on gross;
 * the last month recalculates the year with Article 17 rates and withholds the difference (negative = refund).
 */
final class TerMonthlyWithTrueUp implements TaxStrategy
{
    public function compute(PayslipInput $in, RulePackSnapshot $pack, int $taxBruto, int $deductible): array
    {
        $c = $pack->config;
        $category = self::category($in->taxStatus, $c['ter_categories']);

        if (! $in->finalPeriod) {
            $rate = self::terRate($taxBruto, $c['ter'][$category], $pack->decimals());
            $tax = Money::pct($taxBruto, $rate, floor: true);

            return [
                'tax' => $tax,
                'label' => sprintf('TER %s %s%% of %s', $category, self::pct($rate), number_format($taxBruto / 10 ** $pack->decimals())),
                'detail' => ['method' => 'ter', 'status' => $in->taxStatus, 'category' => $category, 'rate' => $rate, 'bruto' => $taxBruto],
            ];
        }

        $m = fn ($v) => $pack->minor($v);
        $months = $in->ytd['months'] + 1;
        $annualBruto = $m($in->ytd['tax_bruto']) + $taxBruto;
        $positionCost = min(Money::pct($annualBruto, $c['position_cost']['rate']), $m($c['position_cost']['monthly_cap']) * $months);
        $deductibleYear = $m($in->ytd['employee_deductible']) + $deductible;
        $ptkp = $m(self::ptkp($in->taxStatus, $c['ptkp']));
        $neto = $annualBruto - $positionCost - $deductibleYear;
        $thousand = 1000 * 10 ** $pack->decimals();
        $pkp = max(0, intdiv($neto - $ptkp, $thousand) * $thousand);
        $annualTax = intdiv(Progressive::micro($pkp, $c['art17'], $pack->decimals()), 1_000_000);
        $withheld = $m($in->ytd['tax_withheld']);
        $tax = $annualTax - $withheld;

        return [
            'tax' => $tax,
            'label' => $tax < 0 ? 'Year-end recalculation: refund of tax withheld earlier' : 'Year-end recalculation (Article 17)',
            'detail' => [
                'method' => 'true_up', 'status' => $in->taxStatus, 'category' => $category, 'months' => $months,
                'annual_bruto' => $annualBruto, 'position_cost' => $positionCost, 'deductible' => $deductibleYear,
                'neto' => $neto, 'ptkp' => $ptkp, 'pkp' => $pkp, 'annual_tax' => $annualTax, 'withheld' => $withheld,
            ],
        ];
    }

    /** @param array<string, list<string>> $categories */
    public static function category(string $status, array $categories): string
    {
        foreach ($categories as $category => $statuses) {
            if (in_array($status, $statuses, true)) {
                return $category;
            }
        }
        throw new InvalidArgumentException("Unknown PTKP status {$status}.");
    }

    /** @param list<array{0: int|null, 1: float|int}> $rows */
    public static function terRate(int $bruto, array $rows, int $decimals): float
    {
        foreach ($rows as [$upper, $rate]) {
            if ($upper === null || $bruto <= $upper * 10 ** $decimals) {
                return (float) $rate;
            }
        }

        return (float) end($rows)[1];
    }

    /** @param array{base: int, married: int, per_dependant: int, max_dependants: int} $ptkp */
    public static function ptkp(string $status, array $ptkp): int
    {
        if (! preg_match('#^(TK|K)/([0-3])$#', $status, $m)) {
            throw new InvalidArgumentException("Unknown PTKP status {$status}.");
        }

        return $ptkp['base'] + ($m[1] === 'K' ? $ptkp['married'] : 0) + min((int) $m[2], $ptkp['max_dependants']) * $ptkp['per_dependant'];
    }

    private static function pct(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 2), '0'), '.');
    }
}
