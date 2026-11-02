<?php

namespace App\Services\Time;

use App\Models\ApprovalRequest;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LeaveService
{
    public function __construct(private WorkCalendar $calendar, private ApprovalService $approvals) {}

    /**
     * Working days in a range, split by calendar year so cross-year leave draws from each year's balance.
     *
     * @return array{days: float, by_year: array<int, float>, dates: list<string>}
     */
    public function workingDays(Employee $employee, CarbonImmutable $start, CarbonImmutable $end, string $halfDay = 'none'): array
    {
        $dates = $this->calendar->workingDates($employee, $start, $end);
        $byYear = [];
        foreach ($dates as $date) {
            $year = (int) substr($date, 0, 4);
            $byYear[$year] = ($byYear[$year] ?? 0) + 1;
        }
        if ($halfDay !== 'none' && count($dates) === 1) {
            $byYear[(int) substr($dates[0], 0, 4)] = 0.5;
        }

        return ['days' => (float) array_sum($byYear), 'by_year' => $byYear, 'dates' => $dates];
    }

    public function eligibleFrom(Employee $employee, LeaveType $type): CarbonImmutable
    {
        return CarbonImmutable::parse($employee->hire_date)->addMonthsNoOverflow($type->min_service_months);
    }

    /**
     * Entitlement for a year: full allowance once eligible (upfront), or prorated by months employed (monthly);
     * a mid-year joiner on an upfront type without a waiting period gets the remaining months only.
     */
    public function entitlement(Employee $employee, LeaveType $type, int $year): ?float
    {
        if (! $type->isLimited()) {
            return null;
        }
        $annual = (float) $type->annual_days;
        $hire = CarbonImmutable::parse($employee->hire_date);
        $yearStart = CarbonImmutable::create($year, 1, 1);
        $yearEnd = $yearStart->endOfYear();
        $eligible = $this->eligibleFrom($employee, $type);

        if ($eligible->greaterThan($yearEnd) || ($employee->end_date && CarbonImmutable::parse($employee->end_date)->lessThan($yearStart))) {
            return 0.0;
        }

        $from = $hire->greaterThan($yearStart) ? $hire : $yearStart;
        $to = $employee->end_date && CarbonImmutable::parse($employee->end_date)->lessThan($yearEnd) ? CarbonImmutable::parse($employee->end_date) : $yearEnd;
        $months = ($to->year - $from->year) * 12 + $to->month - $from->month + 1;

        $prorated = $type->accrual === 'monthly' || ($type->min_service_months === 0 && $hire->year === $year);

        return $prorated ? floor($annual * min(12, $months) / 12 * 2) / 2 : $annual;
    }

    /**
     * @return array{entitled: ?float, carried: float, adjustment: float, used: float, pending: float, available: ?float}
     */
    public function balance(Employee $employee, LeaveType $type, int $year): array
    {
        $row = LeaveBalance::where(['employee_id' => $employee->id, 'leave_type_id' => $type->id, 'year' => $year])->first();
        $used = $pending = 0.0;

        $requests = LeaveRequest::withoutGlobalScopes()
            ->where('employee_id', $employee->id)
            ->where('leave_type_id', $type->id)
            ->whereIn('status', ['approved', 'pending'])
            ->whereDate('start_date', '<=', "$year-12-31")
            ->whereDate('end_date', '>=', "$year-01-01")
            ->get();

        foreach ($requests as $r) {
            $portion = $this->workingDays($employee, CarbonImmutable::parse($r->start_date), CarbonImmutable::parse($r->end_date), $r->half_day)['by_year'][$year] ?? 0;
            $r->status === 'approved' ? $used += $portion : $pending += $portion;
        }

        $entitled = $this->entitlement($employee, $type, $year);
        $carried = (float) ($row->carried_over ?? 0);
        $adjustment = (float) ($row->adjustment ?? 0);

        return [
            'entitled' => $entitled,
            'carried' => $carried,
            'adjustment' => $adjustment,
            'used' => $used,
            'pending' => $pending,
            'available' => $entitled === null ? null : $entitled + $carried + $adjustment - $used - $pending,
        ];
    }

    public function request(Employee $employee, LeaveType $type, CarbonImmutable $start, CarbonImmutable $end, string $halfDay, ?string $reason): LeaveRequest
    {
        if ($end->lessThan($start)) {
            throw ValidationException::withMessages(['end_date' => 'The last day must be on or after the first day.']);
        }
        if ($halfDay !== 'none' && (! $start->isSameDay($end) || ! $type->half_day_allowed)) {
            throw ValidationException::withMessages(['half_day' => $type->half_day_allowed ? 'Half days are for single-day requests.' : "{$type->name} cannot be taken as half days."]);
        }

        $eligible = $this->eligibleFrom($employee, $type);
        if ($start->lessThan($eligible)) {
            throw ValidationException::withMessages(['start_date' => sprintf('%s becomes available on %s, %d months after you joined.', $type->name, $eligible->format('j M Y'), $type->min_service_months)]);
        }

        $count = $this->workingDays($employee, $start, $end, $halfDay);
        if ($count['days'] <= 0) {
            throw ValidationException::withMessages(['start_date' => 'Those dates are weekends or public holidays, so no leave is needed.']);
        }

        $overlap = LeaveRequest::withoutGlobalScopes()->where('employee_id', $employee->id)->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $end->toDateString())->whereDate('end_date', '>=', $start->toDateString())->exists();
        if ($overlap) {
            throw ValidationException::withMessages(['start_date' => 'You already have leave booked or pending on some of these days.']);
        }

        if ($type->isLimited() && ! $type->allow_negative) {
            foreach ($count['by_year'] as $year => $needed) {
                $available = $this->balance($employee, $type, $year)['available'];
                if ($needed > $available) {
                    throw ValidationException::withMessages(['end_date' => sprintf('This needs %s %s from your %d balance, but only %s %s left.', $this->fmt($needed), $needed == 1 ? 'day' : 'days', $year, $this->fmt(max(0, $available)), $available == 1 ? 'is' : 'are')]);
                }
            }
        }

        return DB::transaction(function () use ($employee, $type, $start, $end, $halfDay, $reason, $count) {
            $leave = LeaveRequest::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'leave_type_id' => $type->id,
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'half_day' => $halfDay,
                'days' => $count['days'],
                'reason' => $reason,
            ]);
            $this->approvals->submit('leave', $leave, $employee, $type->requires_second_approval);

            return $leave;
        });
    }

    /**
     * Pending or future leave is cancelled outright. Leave already under way is cut short at today,
     * so only the days that have not happened yet return to the balance.
     */
    public function cancel(LeaveRequest $leave): void
    {
        $company = Company::findOrFail($leave->company_id);
        $today = CarbonImmutable::now($company->timezone)->startOfDay();
        $start = CarbonImmutable::parse($leave->start_date);
        $end = CarbonImmutable::parse($leave->end_date);

        if ($leave->status === 'pending' || ($leave->status === 'approved' && $start->greaterThan($today))) {
            DB::transaction(function () use ($leave) {
                $leave->update(['status' => 'cancelled']);
                $request = ApprovalRequest::withoutGlobalScopes()->where('subject_type', LeaveRequest::class)->where('subject_id', $leave->id)->first();
                $request && $this->approvals->cancel($request);
            });

            return;
        }

        if ($leave->status !== 'approved' || $end->lessThanOrEqualTo($today)) {
            throw ValidationException::withMessages(['leave' => 'This leave has already been taken and cannot be cancelled.']);
        }

        $employee = Employee::withoutGlobalScopes()->findOrFail($leave->employee_id);
        $leave->update([
            'original_end_date' => $leave->original_end_date ?? $end->toDateString(),
            'end_date' => $today->toDateString(),
            'days' => $this->workingDays($employee, $start, $today, $leave->half_day)['days'],
        ]);
    }

    private function fmt(float $days): string
    {
        return rtrim(rtrim(number_format($days, 1), '0'), '.');
    }
}
