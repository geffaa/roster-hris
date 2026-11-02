<?php

namespace App\Payroll\Tax;

use App\Payroll\Money;
use App\Payroll\PayslipInput;
use App\Payroll\RulePackSnapshot;

/** Generic pack: the month's taxable pay is annualised, taxed on progressive bands above an allowance, and divided by 12. */
final class ProgressiveMonthly implements TaxStrategy
{
    public function compute(PayslipInput $in, RulePackSnapshot $pack, int $taxBruto, int $deductible): array
    {
        $c = $pack->config;
        $taxable = max(0, $taxBruto * 12 - $pack->minor($c['allowance_annual']));
        $annualMicro = Progressive::micro($taxable, $c['bands_annual'], $pack->decimals());
        $tax = Money::frac($annualMicro, 1, 12 * 1_000_000);

        return [
            'tax' => $tax,
            'label' => 'Progressive bands on '.number_format($taxBruto * 12 / 10 ** $pack->decimals(), $pack->decimals()).' a year',
            'detail' => ['method' => 'progressive_monthly', 'annualised' => $taxBruto * 12, 'taxable_annual' => $taxable, 'annual_tax' => intdiv($annualMicro, 1_000_000)],
        ];
    }
}
