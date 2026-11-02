<?php

namespace App\Payroll;

/** An immutable copy of one rule pack version's numbers, as used for a period. */
final readonly class RulePackSnapshot
{
    /** @param array<string, mixed> $config */
    public function __construct(public string $code, public array $config) {}

    public function decimals(): int
    {
        return (int) ($this->config['decimals'] ?? 0);
    }

    /** @return list<array{code: string, name: string, employer: float, employee: float, cap: ?float, exempt_below: float, taxable_employer: bool, deductible_employee: bool}> */
    public function contributions(): array
    {
        return $this->config['contributions'] ?? [];
    }

    public function minor(float|int|string $amount): int
    {
        return Money::toMinor($amount, $this->decimals());
    }
}
