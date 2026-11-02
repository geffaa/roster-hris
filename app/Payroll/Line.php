<?php

namespace App\Payroll;

/** One row of the breakdown shown on the review screen and the payslip. Amount is in minor units, signed as it affects net pay. */
final readonly class Line
{
    public const EARNING = 'earning';

    public const DEDUCTION = 'deduction';

    public const TAX = 'tax';

    public const EMPLOYEE_CONTRIBUTION = 'employee_contribution';

    public const EMPLOYER_CONTRIBUTION = 'employer_contribution';

    public function __construct(
        public string $code,
        public string $label,
        public string $section,
        public int $amount,
        public ?string $basis = null,
        public ?string $rule = null,
    ) {}

    /** @return array{code: string, label: string, section: string, amount: int, basis: ?string, rule: ?string} */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
