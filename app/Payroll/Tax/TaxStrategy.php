<?php

namespace App\Payroll\Tax;

use App\Payroll\PayslipInput;
use App\Payroll\RulePackSnapshot;

interface TaxStrategy
{
    /**
     * @param  int  $taxBruto  this period's taxable gross in minor units (earnings + taxable employer premiums)
     * @param  int  $deductible  this period's employee contributions that reduce yearly taxable income
     * @return array{tax: int, label: string, detail: array<string, mixed>}
     */
    public function compute(PayslipInput $in, RulePackSnapshot $pack, int $taxBruto, int $deductible): array;
}
