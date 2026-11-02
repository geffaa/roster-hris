<?php

namespace App\Services\Time;

use App\Enums\Permission;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/** Basic attendance and leave reports. Managers see their direct reports; HR sees the whole company. */
class TimeReports
{
    public function __construct(private WorkCalendar $calendar, private LeaveService $leave) {}

    /** @return Collection<int, Employee> */
    public function people(User $user, CarbonImmutable $asOf): Collection
    {
        return Employee::query()
            ->with('department:id,name')
            ->where('status', '!=', 'resigned')
            ->whereDate('hire_date', '<=', $asOf)
            ->when(! $user->can(Permission::EmployeesViewAll->value), fn ($q) => $q->where('manager_id', $user->employee()->value('id') ?? 0))
            ->orderBy('full_name')
            ->get(['id', 'employee_no', 'full_name', 'job_title', 'department_id', 'hire_date', 'company_id']);
    }

    /**
     * Per person for one month, counted up to yesterday (today is still in progress).
     *
     * @param  Collection<int, Employee>  $people
     * @return list<array{id: int, employee_no: string, name: string, department: ?string, expected: int, present: int, late: int, late_min: int, incomplete: int, leave: float, absent: int, rate: ?float}>
     */
    public function attendance(Collection $people, CarbonImmutable $month, CarbonImmutable $today): array
    {
        $from = $month->startOfMonth();
        $to = $month->endOfMonth()->min($today->subDay());
        if ($to->lessThan($from)) {
            return [];
        }

        $ids = $people->pluck('id');
        $records = AttendanceRecord::whereIn('employee_id', $ids)
            ->whereDate('work_date', '>=', $from)->whereDate('work_date', '<=', $to)
            ->get(['employee_id', 'work_date', 'status', 'late_min'])
            ->groupBy('employee_id');
        $leaves = LeaveRequest::whereIn('employee_id', $ids)->where('status', 'approved')
            ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)
            ->get(['employee_id', 'start_date', 'end_date', 'half_day'])
            ->groupBy('employee_id');

        $rows = [];
        foreach ($people as $e) {
            $start = $from->max(CarbonImmutable::parse($e->hire_date->toDateString()));
            $expected = $start->greaterThan($to) ? [] : $this->calendar->workingDates($e, $start, $to);
            $mine = $records->get($e->id, collect());
            $worked = $mine->map(fn (AttendanceRecord $r) => $r->work_date->toDateString())->all();

            $leaveDays = 0.0;
            $leaveDates = [];
            foreach ($leaves->get($e->id, collect()) as $l) {
                foreach ($expected as $d) {
                    if ($d >= $l->start_date->toDateString() && $d <= $l->end_date->toDateString()) {
                        $leaveDates[$d] = true;
                        $leaveDays += $l->half_day === 'none' ? 1 : 0.5;
                    }
                }
            }

            $absent = count(array_filter($expected, fn ($d) => ! isset($leaveDates[$d]) && ! in_array($d, $worked, true)));
            $due = count($expected) - $leaveDays;

            $rows[] = [
                'id' => $e->id,
                'employee_no' => $e->employee_no,
                'name' => $e->full_name,
                'department' => $e->department?->name,
                'expected' => count($expected),
                'present' => $mine->count(),
                'late' => $mine->where('status', 'late')->count(),
                'late_min' => (int) $mine->sum('late_min'),
                'incomplete' => $mine->where('status', 'incomplete')->count(),
                'leave' => $leaveDays,
                'absent' => $absent,
                'rate' => $due > 0 ? round(min($mine->count(), $due) / $due * 100, 1) : null,
            ];
        }

        return $rows;
    }

    /**
     * Balances for limited yearly types and days taken for open-ended ones (event-based types are granted per event, not listed).
     *
     * @param  Collection<int, Employee>  $people
     * @return array{types: list<array{id: int, name: string, color: string, limited: bool}>, rows: list<array{id: int, employee_no: string, name: string, department: ?string, balances: array<int, array{entitled: ?float, carried: float, adjustment: float, used: float, pending: float, available: ?float, eligible: bool}>}>}
     */
    public function leaveBalances(Collection $people, int $year): array
    {
        $types = LeaveType::orderBy('id')->get()->filter(fn (LeaveType $t) => ! $t->event_based)->values();
        $yearEnd = CarbonImmutable::create($year, 12, 31);

        return [
            'types' => $types->map(fn (LeaveType $t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color, 'limited' => $t->isLimited()])->all(),
            'rows' => $people->map(fn (Employee $e) => [
                'id' => $e->id,
                'employee_no' => $e->employee_no,
                'name' => $e->full_name,
                'department' => $e->department?->name,
                'balances' => $types->mapWithKeys(fn (LeaveType $t) => [$t->id => [
                    ...$this->leave->balance($e, $t, $year),
                    'eligible' => ! $t->min_service_months || $this->leave->eligibleFrom($e, $t)->lessThanOrEqualTo($yearEnd),
                ]])->all(),
            ])->values()->all(),
        ];
    }
}
