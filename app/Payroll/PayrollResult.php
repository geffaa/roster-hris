<?php

namespace App\Payroll;

final readonly class PayrollResult
{
    /**
     * @param  list<Line>  $lines
     * @param  array<string, mixed>  $taxDetail  how the tax was found (method, category, rate, annual figures)
     */
    public function __construct(
        public array $lines,
        public int $gross,
        public int $taxBruto,
        public int $tax,
        public int $employeeContributions,
        public int $employerContributions,
        public int $employeeDeductible,
        public int $otherDeductions,
        public int $net,
        public array $taxDetail,
    ) {}

    public function line(string $code): ?Line
    {
        foreach ($this->lines as $line) {
            if ($line->code === $code) {
                return $line;
            }
        }

        return null;
    }
}
