<?php

namespace App\Payroll;

use App\Payroll\Tax\ProgressiveMonthly;
use App\Payroll\Tax\TaxStrategy;
use App\Payroll\Tax\TerMonthlyWithTrueUp;
use InvalidArgumentException;

/**
 * Pure and deterministic: input + rule pack snapshot in, breakdown out. No database, no clock.
 * Order: earnings → contributions (on base + fixed allowances, capped) → tax → other deductions → net.
 */
final class PayrollCalculator
{
    public function calculate(PayslipInput $in, RulePackSnapshot $pack): PayrollResult
    {
        $dec = $pack->decimals();
        $m = fn ($v) => $pack->minor($v);
        $fmt = fn (int $minor) => number_format($minor / 10 ** $dec, $dec);
        $lines = [];
        $taxableEarnings = 0;
        $gross = 0;
        $earn = function (Line $line, bool $taxable = true) use (&$lines, &$gross, &$taxableEarnings) {
            if ($line->amount === 0) {
                return;
            }
            $lines[] = $line;
            $gross += $line->amount;
            if ($taxable) {
                $taxableEarnings += $line->amount;
            }
        };

        // Base salary, prorated over the days employed (and split where the salary changed mid-period).
        $employed = $in->employedDays();
        $base = 0;
        foreach ($in->salarySegments as $segment) {
            $base += $segment['days'] === $in->periodDays ? $m($segment['amount']) : Money::frac($m($segment['amount']), $segment['days'], $in->periodDays);
        }
        $earn(new Line('base', 'Base salary', Line::EARNING, $base, $employed < $in->periodDays ? "{$employed} of {$in->periodDays} days" : null));

        // Recurring allowances follow the same proration. Fixed ones also count as wage for contributions, overtime and THR.
        $fixedMonthly = 0;
        $fixedPaid = 0;
        foreach ($in->allowances as $a) {
            $amount = $employed === $in->periodDays ? $m($a['amount']) : Money::frac($m($a['amount']), $employed, $in->periodDays);
            if ($a['fixed'] ?? true) {
                $fixedMonthly += $m($a['amount']);
                $fixedPaid += $amount;
            }
            $earn(new Line($a['code'], $a['name'], Line::EARNING, $amount, ($a['fixed'] ?? true) ? 'Recurring, fixed' : 'Recurring'), $a['taxable'] ?? true);
        }

        if ($in->unpaidLeaveDays > 0 && $in->periodWorkingDays > 0) {
            $days = rtrim(rtrim(number_format($in->unpaidLeaveDays, 1), '0'), '.');
            $cut = Money::frac($m($in->currentSalary), (int) round($in->unpaidLeaveDays * 2), $in->periodWorkingDays * 2);
            $earn(new Line('unpaid_leave', "Unpaid leave · {$days} ".($in->unpaidLeaveDays == 1 ? 'day' : 'days'), Line::EARNING, -$cut, "Base ÷ {$in->periodWorkingDays} working days × {$days}"));
        }

        $wage = $m($in->currentSalary) + $fixedMonthly;
        if ($in->overtime !== [] && isset($pack->config['overtime'])) {
            $earn($this->overtime($in->overtime, $pack->config['overtime'], $wage, $fmt));
        }

        if ($in->includeThr && ! empty($pack->config['thr']) && $in->serviceMonths >= $pack->config['thr']['min_service_months']) {
            $full = $pack->config['thr']['full_after_months'];
            $months = min($in->serviceMonths, $full);
            $earn(new Line('thr', 'THR (religious holiday allowance)', Line::EARNING, Money::frac($wage, $months, $full),
                $months >= $full ? '1 month of wage' : "{$months}/{$full} of monthly wage"), true);
        }

        foreach ($in->oneOffs as $o) {
            if ($o['kind'] === 'earning') {
                $earn(new Line($o['code'], $o['name'], Line::EARNING, $m($o['amount']), 'One-off'), $o['taxable'] ?? true);
            }
        }

        // Contributions on the contractual wage for the days employed, within the floor and cap of each programme.
        $contributionWage = $base + $fixedPaid;
        $ee = $er = $deductible = $taxablePremiums = 0;
        foreach ($pack->contributions() as $c) {
            $cap = $c['cap'] === null ? null : $m($c['cap']);
            $basis = max(0, ($cap === null ? $contributionWage : min($contributionWage, $cap)) - $m($c['exempt_below']));
            $capped = $cap !== null && $contributionWage > $cap;
            $describe = fn (float $rate) => self::rate($rate).'% of '.$fmt($basis).($capped ? ' (cap)' : '').($c['exempt_below'] > 0 ? ' above '.$fmt($m($c['exempt_below'])) : '');
            $rule = "contributions.{$c['code']}";

            if ($c['employee'] > 0 && ($amount = Money::pct($basis, $c['employee'])) > 0) {
                $lines[] = new Line("{$c['code']}_ee", $c['name'], Line::EMPLOYEE_CONTRIBUTION, -$amount, $describe($c['employee']), $rule);
                $ee += $amount;
                $deductible += $c['deductible_employee'] ? $amount : 0;
            }
            if ($c['employer'] > 0 && ($amount = Money::pct($basis, $c['employer'])) > 0) {
                $lines[] = new Line("{$c['code']}_er", $c['name'], Line::EMPLOYER_CONTRIBUTION, $amount, $describe($c['employer']), $rule);
                $er += $amount;
                $taxablePremiums += $c['taxable_employer'] ? $amount : 0;
            }
        }

        $taxBruto = $taxableEarnings + $taxablePremiums;
        $result = $this->strategy($pack)->compute($in, $pack, $taxBruto, $deductible);
        if ($result['tax'] !== 0) {
            $lines[] = new Line('tax', $pack->config['method'] === 'progressive_monthly' ? 'Income tax' : 'PPh 21', Line::TAX, -$result['tax'], $result['label'], 'tax');
        }

        $other = 0;
        foreach ($in->oneOffs as $o) {
            if ($o['kind'] === 'deduction') {
                $lines[] = new Line($o['code'], $o['name'], Line::DEDUCTION, -$m($o['amount']), 'One-off');
                $other += $m($o['amount']);
            }
        }

        return new PayrollResult(
            lines: $lines,
            gross: $gross,
            taxBruto: $taxBruto,
            tax: $result['tax'],
            employeeContributions: $ee,
            employerContributions: $er,
            employeeDeductible: $deductible,
            otherDeductions: $other,
            net: $gross - $ee - $result['tax'] - $other,
            taxDetail: $result['detail'],
        );
    }

    private function strategy(RulePackSnapshot $pack): TaxStrategy
    {
        return match ($pack->config['method']) {
            'ter_monthly_with_december_true_up' => new TerMonthlyWithTrueUp,
            'progressive_monthly' => new ProgressiveMonthly,
            default => throw new InvalidArgumentException("Unsupported tax method {$pack->config['method']}."),
        };
    }

    /**
     * @param  list<array{date: string, hours: float, rest_day: bool}>  $entries
     * @param  array{hourly_divisor: float|int, workday: list<array{0: ?int, 1: float}>, restday: list<array{0: ?int, 1: float}>}  $rules
     */
    private function overtime(array $entries, array $rules, int $wage, callable $fmt): Line
    {
        $total = 0;
        $hours = ['workday' => 0.0, 'restday' => 0.0];
        foreach ($entries as $e) {
            $tiers = $e['rest_day'] ? $rules['restday'] : $rules['workday'];
            $units = 0.0;
            $done = 0.0;
            foreach ($tiers as [$upTo, $multiplier]) {
                $slice = $upTo === null ? $e['hours'] - $done : max(0, min($e['hours'], $upTo) - $done);
                $units += $slice * $multiplier;
                $done += $slice;
                if ($done >= $e['hours']) {
                    break;
                }
            }
            $total += Money::frac($wage, (int) round($units * 1000), (int) round($rules['hourly_divisor'] * 1000));
            $hours[$e['rest_day'] ? 'restday' : 'workday'] += $e['hours'];
        }
        $parts = array_filter([
            $hours['workday'] ? self::num($hours['workday']).' h on workdays' : null,
            $hours['restday'] ? self::num($hours['restday']).' h on rest days' : null,
        ]);

        return new Line('overtime', 'Overtime', Line::EARNING, $total, implode(', ', $parts).' · hourly '.$fmt(Money::frac($wage, 1000, (int) round($rules['hourly_divisor'] * 1000))), 'overtime');
    }

    private static function rate(float $rate): string
    {
        return rtrim(rtrim(number_format($rate, 2), '0'), '.');
    }

    private static function num(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1), '0'), '.');
    }
}
