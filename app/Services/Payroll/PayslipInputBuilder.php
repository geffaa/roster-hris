<?php

namespace App\Services\Payroll;

use App\Models\Company;
use App\Models\Compensation;
use App\Models\Employee;
use App\Models\EmployeeComponent;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Payroll\PayslipInput;
use App\Services\Time\LeaveService;
use App\Services\Time\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Reads salaries, components, unpaid leave, approved overtime and year-to-date figures for one person and one period. */
class PayslipInputBuilder
{
    public function __construct(private WorkCalendar $calendar, private LeaveService $leave) {}

    /** @return Collection<int, Employee> people on payroll for the period: hired by its end and not gone before its start */
    public function people(PayrollRun $run): Collection
    {
        return Employee::query()->with(['department:id,name'])
            ->whereDate('hire_date', '<=', $run->period_end)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $run->period_start))
            ->orderBy('employee_no')->get();
    }

    /** @return array{input: PayslipInput, context: array<string, mixed>} */
    public function build(PayrollRun $run, Employee $e, Company $company): array
    {
        $start = CarbonImmutable::parse($run->period_start->toDateString());
        $end = CarbonImmutable::parse($run->period_end->toDateString());
        $from = $start->max(CarbonImmutable::parse($e->hire_date->toDateString()));
        $to = $e->end_date ? $end->min(CarbonImmutable::parse($e->end_date->toDateString())) : $end;
        $byWorkingDays = $company->proration_policy === 'working_days';
        $periodWorking = count($this->calendar->workingDates($e, $start, $end));
        $countDays = fn (CarbonImmutable $a, CarbonImmutable $b) => $b->lessThan($a) ? 0
            : ($byWorkingDays ? count($this->calendar->workingDates($e, $a, $b)) : (int) $a->diffInDays($b) + 1);

        // Salary segments: the rate in force on each day employed.
        $history = Compensation::where('employee_id', $e->id)->whereDate('effective_from', '<=', $to)->orderBy('effective_from')->orderBy('id')->get();
        $segments = [];
        $current = $history->last();
        foreach ($history as $i => $c) {
            $segStart = CarbonImmutable::parse($c->effective_from->toDateString())->max($from);
            $next = $history[$i + 1] ?? null;
            $segEnd = $next ? CarbonImmutable::parse($next->effective_from->toDateString())->subDay()->min($to) : $to;
            if ($segEnd->greaterThanOrEqualTo($segStart) && $segEnd->greaterThanOrEqualTo($from)) {
                $segments[] = ['amount' => (float) $c->base_salary, 'days' => $countDays($segStart, $segEnd)];
            }
        }
        $segments = array_values(array_filter($segments, fn ($s) => $s['days'] > 0));

        $components = EmployeeComponent::with('component')->where('employee_id', $e->id)
            ->whereDate('effective_from', '<=', $end)
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $start))->get();
        $allowances = $oneOffs = [];
        foreach ($components as $ec) {
            $c = $ec->component;
            if ($c->recurring && $c->kind === 'earning') {
                $allowances[] = ['code' => $c->code, 'name' => $c->name, 'amount' => (float) $ec->amount, 'taxable' => $c->taxable, 'fixed' => $c->fixed];
            } elseif (! $c->recurring && $ec->effective_from->betweenIncluded($start, $end)) {
                $oneOffs[] = ['code' => $c->code.'_'.$ec->id, 'name' => $ec->note ? "{$c->name} · {$ec->note}" : $c->name, 'amount' => (float) $ec->amount, 'kind' => $c->kind, 'taxable' => $c->taxable];
            }
        }

        $unpaid = 0.0;
        $unpaidDates = [];
        $leaves = LeaveRequest::with('type:id,paid')->where('employee_id', $e->id)->where('status', 'approved')
            ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)->get()->filter(fn ($l) => ! $l->type->paid);
        foreach ($leaves as $l) {
            $a = CarbonImmutable::parse($l->start_date->toDateString())->max($from);
            $b = CarbonImmutable::parse($l->end_date->toDateString())->min($to);
            $w = $this->leave->workingDays($e, $a, $b, $l->start_date->equalTo($l->end_date) ? $l->half_day : 'none');
            $unpaid += $w['days'];
            array_push($unpaidDates, ...$w['dates']);
        }

        $overtime = OvertimeRequest::where('employee_id', $e->id)->where('status', 'approved')
            ->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)->orderBy('work_date')->get()
            ->map(fn (OvertimeRequest $o) => [
                'date' => $o->work_date->toDateString(),
                'hours' => round($o->minutes / 60, 2),
                'rest_day' => $this->calendar->workingDates($e, $o->work_date, $o->work_date) === [],
            ])->all();

        $ytd = PayrollItem::query()->whereHas('run', fn ($q) => $q->whereIn('status', ['locked', 'published'])
            ->whereYear('period_start', $start->year)->whereDate('period_start', '<', $start))
            ->where('employee_id', $e->id)
            ->selectRaw('coalesce(sum(tax_bruto),0) as tax_bruto, coalesce(sum(tax),0) as tax, coalesce(sum(employee_deductible),0) as deductible, count(*) as months')
            ->first();

        $leavingOn = $e->end_date && $e->end_date->betweenIncluded($start, $end) ? $e->end_date->toDateString() : null;
        $leaving = $leavingOn !== null;
        $taxStatus = $current?->tax_status ?: ($company->country_code === 'ID' ? 'TK/0' : '');

        $input = new PayslipInput(
            periodLabel: $start->format('F Y'),
            periodDays: $byWorkingDays ? $periodWorking : $start->daysInMonth,
            salarySegments: $segments,
            currentSalary: (float) ($current->base_salary ?? 0),
            periodWorkingDays: $periodWorking,
            unpaidLeaveDays: $unpaid,
            allowances: $allowances,
            oneOffs: $oneOffs,
            overtime: $overtime,
            taxStatus: $taxStatus,
            includeThr: $run->include_thr,
            serviceMonths: (int) floor(CarbonImmutable::parse($e->hire_date->toDateString())->diffInMonths($end)),
            ytd: [
                'tax_bruto' => (float) ($ytd->tax_bruto ?? 0), 'tax_withheld' => (float) ($ytd->tax ?? 0),
                'employee_deductible' => (float) ($ytd->deductible ?? 0), 'months' => (int) ($ytd->months ?? 0),
            ],
            finalPeriod: $start->month === 12 || $leaving,
        );

        return ['input' => $input, 'context' => [
            'employee_no' => $e->employee_no,
            'name' => $e->full_name,
            'job_title' => $e->job_title,
            'department' => $e->department?->name,
            'tax_status' => $taxStatus,
            'joined' => $e->hire_date->betweenIncluded($start, $end) ? $e->hire_date->toDateString() : null,
            'leaving' => $leavingOn,
            'unpaid_days' => $unpaid,
            'unpaid_dates' => $unpaidDates,
            'overtime_hours' => array_sum(array_column($overtime, 'hours')),
            'one_offs' => array_column($oneOffs, 'name'),
            'bank' => $e->bank_account_last4 ? ['name' => $e->bank_name, 'last4' => $e->bank_account_last4] : null,
            'employed_days' => $input->employedDays(),
            'period_days' => $input->periodDays,
        ]];
    }
}
