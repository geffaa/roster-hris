<?php

namespace App\Services\Payroll;

use App\Models\Claim;
use App\Models\Company;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\User;
use App\Payroll\Money;
use App\Payroll\PayrollCalculator;
use App\Services\Notify;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Draft → calculated → approved by two different people → locked → published.
 * Recalculating clears approvals and reviews; once locked, nothing in the run changes.
 */
class PayrollRuns
{
    /** Net pay moving more than this against the previous month needs a reviewer's tick. */
    public const CHANGE_THRESHOLD = 0.20;

    public function __construct(private PayslipInputBuilder $inputs, private PayrollCalculator $calculator, private RulePacks $packs, private Notify $notify) {}

    public function create(Company $company, CarbonImmutable $month, bool $includeThr, User $by): PayrollRun
    {
        $start = $month->startOfMonth();
        if (PayrollRun::whereDate('period_start', $start)->exists()) {
            throw ValidationException::withMessages(['period' => 'There is already a payroll run for '.$start->format('F Y').'.']);
        }
        $open = PayrollRun::whereNotIn('status', ['locked', 'published'])->value('reference');
        if ($open) {
            throw ValidationException::withMessages(['period' => "Finish {$open} first: year-to-date tax depends on earlier months being locked."]);
        }
        $last = PayrollRun::orderByDesc('period_start')->first();
        if ($last && $start->lessThan(CarbonImmutable::parse($last->period_start->toDateString()))) {
            throw ValidationException::withMessages(['period' => 'Runs go forward in time; use an adjustment in the next run for earlier months.']);
        }
        $payDate = $start->setDay(min($company->pay_day, $start->daysInMonth));

        return PayrollRun::create([
            'company_id' => $company->id,
            'reference' => 'RUN-'.$start->format('Y-m'),
            'period_start' => $start,
            'period_end' => $start->endOfMonth(),
            'pay_date' => $payDate->isWeekend() ? $payDate->previousWeekday() : $payDate,
            'include_thr' => $includeThr,
            'created_by' => $by->id,
        ]);
    }

    public function calculate(PayrollRun $run, ?User $by = null): PayrollRun
    {
        if ($run->isFinal()) {
            throw ValidationException::withMessages(['run' => 'This run is locked. Corrections go into the next run.']);
        }
        $company = Company::findOrFail($run->company_id);
        $pack = $company->rulePack ?? throw ValidationException::withMessages(['run' => 'This company has no rule pack.']);
        $version = $this->packs->versionFor($pack, $run->period_end);
        $snapshot = $version->snapshot();
        $people = $this->inputs->people($run);

        DB::transaction(function () use ($run, $by, $version, $people) {
            $run->items()->delete();
            $run->update([
                'status' => 'calculating', 'progress' => 0, 'headcount' => $people->count(), 'rule_pack_version_id' => $version->id,
                'approved_by_1' => null, 'approved_at_1' => null, 'approved_by_2' => null, 'approved_at_2' => null,
                'calculated_by' => $by->id ?? $run->calculated_by,
            ]);
        });

        $previous = PayrollRun::whereIn('status', ['locked', 'published'])->whereDate('period_start', '<', $run->period_start)
            ->orderByDesc('period_start')->first();
        $previousNet = $previous ? PayrollItem::where('payroll_run_id', $previous->id)->pluck('net', 'employee_id') : collect();
        $dec = $snapshot->decimals();

        foreach ($people->values() as $i => $e) {
            ['input' => $input, 'context' => $context] = $this->inputs->build($run, $e, $company);
            $r = $this->calculator->calculate($input, $snapshot);
            $prev = isset($previousNet[$e->id]) ? Money::toMinor($previousNet[$e->id], $dec) : null;

            PayrollItem::create([
                'company_id' => $run->company_id, 'payroll_run_id' => $run->id, 'employee_id' => $e->id, 'currency' => $company->base_currency,
                'gross' => Money::fromMinor($r->gross, $dec), 'tax_bruto' => Money::fromMinor($r->taxBruto, $dec), 'tax' => Money::fromMinor($r->tax, $dec),
                'employee_contributions' => Money::fromMinor($r->employeeContributions, $dec), 'employer_contributions' => Money::fromMinor($r->employerContributions, $dec),
                'employee_deductible' => Money::fromMinor($r->employeeDeductible, $dec), 'other_deductions' => Money::fromMinor($r->otherDeductions, $dec),
                'net' => Money::fromMinor($r->net, $dec), 'previous_net' => $prev === null ? null : Money::fromMinor($prev, $dec),
                'breakdown' => array_map(fn ($l) => $l->toArray(), $r->lines),
                'tax_detail' => $r->taxDetail + ['version' => $version->version],
                'flags' => $this->flags($r->net, $prev, $context, $previous?->period_start->format('F')),
                'context' => $context + ['previous_month' => $previous?->period_start->format('M')],
            ]);
            $run->update(['progress' => $i + 1]);
        }

        $run->update(['status' => 'calculated', 'calculated_at' => now(), 'totals' => $this->totals($run)]);

        return $run->refresh();
    }

    public function review(PayrollItem $item, User $by): void
    {
        if ($item->run()->value('status') !== 'calculated') {
            throw ValidationException::withMessages(['item' => 'Reviews happen while the run is calculated and not yet approved.']);
        }
        $item->update(['reviewed_by' => $by->id, 'reviewed_at' => now()]);
    }

    public function approve(PayrollRun $run, User $by): void
    {
        if ($run->status !== 'calculated') {
            throw ValidationException::withMessages(['run' => 'Only a calculated run can be approved.']);
        }
        $items = $run->items()->get();
        if ($items->contains(fn (PayrollItem $i) => $i->hasFlag('error'))) {
            throw ValidationException::withMessages(['run' => 'Fix the errors first: someone has negative net pay.']);
        }
        $open = $items->filter(fn (PayrollItem $i) => $i->needsReview())->count();
        if ($open > 0) {
            throw ValidationException::withMessages(['run' => "Review the {$open} flagged ".($open === 1 ? 'person' : 'people').' before approving.']);
        }
        if ($run->approved_by_1 === $by->id) {
            throw ValidationException::withMessages(['run' => 'You already approved. A second, different person must approve.']);
        }
        $run->approved_by_1
            ? $run->update(['approved_by_2' => $by->id, 'approved_at_2' => now(), 'status' => 'approved'])
            : $run->update(['approved_by_1' => $by->id, 'approved_at_1' => now()]);
    }

    public function lock(PayrollRun $run, User $by): void
    {
        if ($run->status !== 'approved' || $run->approvals() < 2) {
            throw ValidationException::withMessages(['run' => 'Two different people must approve before the run can be locked.']);
        }
        DB::transaction(function () use ($run, $by) {
            $run->update(['status' => 'locked', 'locked_by' => $by->id, 'locked_at' => now()]);
            // Claims included in this run are now paid; they will not appear in another run.
            $ids = $run->items()->get()->flatMap(fn (PayrollItem $i) => $i->context['claims'] ?? [])->all();
            Claim::whereIn('id', $ids)->update(['status' => 'paid', 'payroll_run_id' => $run->id]);
        });
    }

    public function publish(PayrollRun $run): void
    {
        if ($run->status !== 'locked') {
            throw ValidationException::withMessages(['run' => 'Lock the run before sending payslips.']);
        }
        $slips = DB::transaction(function () use ($run) {
            $slips = collect();
            foreach ($run->items()->get() as $item) {
                $slips->push(Payslip::firstOrCreate(['payroll_item_id' => $item->id], ['company_id' => $run->company_id, 'employee_id' => $item->employee_id, 'published_at' => now()]));
            }
            $run->update(['status' => 'published', 'published_at' => now()]);

            return $slips;
        });
        $this->notify->payslipsPublished($slips, $run->period_start->format('F Y'));
    }

    /** @return array<string, int|string> */
    public function totals(PayrollRun $run): array
    {
        $sum = PayrollItem::where('payroll_run_id', $run->id)->toBase()
            ->selectRaw('count(*) as headcount, coalesce(sum(gross),0) gross, coalesce(sum(tax),0) tax, coalesce(sum(employee_contributions),0) ee, coalesce(sum(employer_contributions),0) er, coalesce(sum(other_deductions),0) other, coalesce(sum(net),0) net')
            ->first();

        return [
            'headcount' => (int) $sum->headcount, 'gross' => (string) $sum->gross, 'tax' => (string) $sum->tax,
            'employee_contributions' => (string) $sum->ee, 'employer_contributions' => (string) $sum->er,
            'other_deductions' => (string) $sum->other, 'net' => (string) $sum->net,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{type: string, level: string, message: string}>
     */
    private function flags(int $net, ?int $previous, array $context, ?string $previousMonth): array
    {
        $flags = [];
        if ($net < 0) {
            $flags[] = ['type' => 'negative_net', 'level' => 'error', 'message' => 'Net pay is negative. Reduce one-off deductions or move them to a later month.'];
        }
        if ($context['bank'] === null) {
            $flags[] = ['type' => 'no_bank', 'level' => 'warning', 'message' => 'No bank account on file, so the transfer file will skip this person.'];
        }
        if ($previous !== null && $previous > 0 && abs($net - $previous) / $previous > self::CHANGE_THRESHOLD) {
            $pct = round(($net - $previous) / $previous * 100, 1);
            $why = $context['one_offs'] ? ' ('.implode(', ', $context['one_offs']).')' : ($context['unpaid_days'] ? " ({$context['unpaid_days']} unpaid days)" : '');
            $flags[] = ['type' => 'change', 'level' => 'check', 'message' => sprintf('Net pay is %s%% %s than %s%s. Confirm it is intended.', abs($pct), $pct > 0 ? 'higher' : 'lower', $previousMonth, $why)];
        }
        if ($context['joined']) {
            $flags[] = ['type' => 'joiner', 'level' => 'info', 'message' => 'Joined '.date('j M', (int) strtotime($context['joined'])).'; pay is prorated.'];
        }
        if ($context['leaving']) {
            $flags[] = ['type' => 'leaver', 'level' => 'info', 'message' => 'Leaves '.date('j M', (int) strtotime($context['leaving'])).'; this is the final pay with the year-end tax recalculation.'];
        }

        return $flags;
    }
}
