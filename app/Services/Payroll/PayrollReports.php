<?php

namespace App\Services\Payroll;

use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Illuminate\Support\Collection;

/** Reports read the locked items, so their totals always equal the payslips people received. */
class PayrollReports
{
    /** @return Collection<int, PayrollRun> */
    public function finalRuns(): Collection
    {
        return PayrollRun::whereIn('status', ['locked', 'published'])->orderByDesc('period_start')->get();
    }

    /** @return array{rows: list<array<string, mixed>>, total: array<string, mixed>} */
    public function summary(PayrollRun $run): array
    {
        $items = PayrollItem::where('payroll_run_id', $run->id)->get();
        $sum = fn (Collection $c, string $label) => [
            'department' => $label,
            'headcount' => $c->count(),
            'gross' => round($c->sum(fn ($i) => (float) $i->gross), 2),
            'tax' => round($c->sum(fn ($i) => (float) $i->tax), 2),
            'employee_contributions' => round($c->sum(fn ($i) => (float) $i->employee_contributions), 2),
            'other_deductions' => round($c->sum(fn ($i) => (float) $i->other_deductions), 2),
            'net' => round($c->sum(fn ($i) => (float) $i->net), 2),
            'employer_contributions' => round($c->sum(fn ($i) => (float) $i->employer_contributions), 2),
            'employer_cost' => round($c->sum(fn ($i) => (float) $i->gross + (float) $i->employer_contributions), 2),
        ];

        return [
            'rows' => $items->groupBy(fn ($i) => $i->context['department'] ?? 'No department')->sortKeys()->map($sum)->values()->all(),
            'total' => $sum($items, 'Total'),
        ];
    }

    /**
     * Totals per contribution programme, read from each locked breakdown line.
     *
     * @return array{rows: list<array<string, mixed>>, people: list<array<string, mixed>>, codes: list<array{code: string, name: string}>}
     */
    public function contributions(PayrollRun $run): array
    {
        $items = PayrollItem::where('payroll_run_id', $run->id)->orderBy('id')->get();
        $dec = $items->first()?->currency === 'IDR' ? 0 : 2;
        $programmes = [];
        $people = [];
        foreach ($items as $i) {
            $row = ['employee_no' => $i->context['employee_no'], 'name' => $i->context['name']];
            foreach ($i->breakdown as $l) {
                if (! str_contains($l['section'], 'contribution')) {
                    continue;
                }
                $code = preg_replace('/_(ee|er)$/', '', $l['code']);
                $side = $l['section'] === 'employee_contribution' ? 'employee' : 'employer';
                $amount = abs($l['amount']) / 10 ** $dec;
                $programmes[$code] ??= ['code' => $code, 'name' => $l['label'], 'people' => [], 'employee' => 0.0, 'employer' => 0.0];
                $programmes[$code][$side] += $amount;
                $programmes[$code]['people'][$i->employee_id] = true;
                $row["{$code}_{$side}"] = $amount;
            }
            $people[] = $row;
        }
        $rows = array_values(array_map(fn ($p) => [
            'code' => $p['code'], 'name' => $p['name'], 'people' => count($p['people']),
            'employee' => round($p['employee'], 2), 'employer' => round($p['employer'], 2), 'total' => round($p['employee'] + $p['employer'], 2),
        ], $programmes));

        return ['rows' => $rows, 'people' => $people, 'codes' => array_map(fn ($r) => ['code' => $r['code'], 'name' => $r['name']], $rows)];
    }
}
