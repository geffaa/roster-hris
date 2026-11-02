<?php

namespace App\Payroll;

/**
 * Everything the calculator needs for one person and one period. Built by PayrollRunService from the database,
 * built by hand in golden tests. Amounts are in major units (e.g. 10000000 for Rp10 juta, 3000 for £3,000).
 */
final readonly class PayslipInput
{
    /**
     * @param  list<array{amount: float|int, days: int}>  $salarySegments  monthly base salary × days it applied while employed
     * @param  list<array{code: string, name: string, amount: float|int, taxable?: bool, fixed?: bool}>  $allowances  recurring monthly earnings
     * @param  list<array{code: string, name: string, amount: float|int, kind: 'earning'|'deduction', taxable?: bool}>  $oneOffs
     * @param  list<array{date: string, hours: float, rest_day: bool}>  $overtime
     * @param  array{tax_bruto: float|int, tax_withheld: float|int, employee_deductible: float|int, months: int}  $ytd
     */
    public function __construct(
        public string $periodLabel,
        public int $periodDays,
        public array $salarySegments,
        public float|int $currentSalary,
        public int $periodWorkingDays = 0,
        public float $unpaidLeaveDays = 0,
        public array $allowances = [],
        public array $oneOffs = [],
        public array $overtime = [],
        public string $taxStatus = 'TK/0',
        public bool $includeThr = false,
        public int $serviceMonths = 0,
        public array $ytd = ['tax_bruto' => 0, 'tax_withheld' => 0, 'employee_deductible' => 0, 'months' => 0],
        public bool $finalPeriod = false,
    ) {}

    public function employedDays(): int
    {
        return array_sum(array_column($this->salarySegments, 'days'));
    }
}
