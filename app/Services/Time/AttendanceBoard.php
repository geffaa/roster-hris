<?php

namespace App\Services\Time;

use App\Enums\Permission;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Carbon\CarbonImmutable;

/** Who is in on a given day: on time, late, missing, on leave. Shared by the attendance board and the home page. */
class AttendanceBoard
{
    public function __construct(private WorkCalendar $calendar) {}

    /** @return array{rows: list<array<string, mixed>>, holiday: ?string} */
    public function day(User $user, Company $company, CarbonImmutable $date): array
    {
        $tz = $company->timezone;
        $people = Employee::query()
            ->with('department:id,name')
            ->where('status', '!=', 'resigned')
            ->whereDate('hire_date', '<=', $date)
            ->when(! $user->can(Permission::EmployeesViewAll->value), fn ($q) => $q->where('manager_id', $user->employee()->value('id') ?? 0))
            ->orderBy('full_name')
            ->get(['id', 'company_id', 'full_name', 'job_title', 'department_id', 'location_id']);

        $ids = $people->pluck('id');
        $records = AttendanceRecord::whereIn('employee_id', $ids)->whereDate('work_date', $date)->get()->keyBy('employee_id');
        $leave = LeaveRequest::with('type:id,name,color')->whereIn('employee_id', $ids)->where('status', 'approved')
            ->whereDate('start_date', '<=', $date)->whereDate('end_date', '>=', $date)->get()->keyBy('employee_id');
        $holiday = $this->calendar->holidayOn($company, $date);
        $now = CarbonImmutable::now($tz);
        $isPast = $date->lessThan($now->startOfDay());

        $rows = $people->map(function (Employee $e) use ($records, $leave, $holiday, $date, $tz, $isPast, $now) {
            $r = $records[$e->id] ?? null;
            $l = $leave[$e->id] ?? null;
            $pattern = $this->calendar->patternFor($e);
            $working = $pattern ? in_array($date->isoWeekday(), $pattern->working_days, true) : $date->isWeekday();

            $state = match (true) {
                $r !== null => $r->status === 'incomplete' ? 'incomplete' : ($r->status === 'late' ? 'late' : ($r->status === 'corrected' ? 'corrected' : 'present')),
                $l !== null => 'leave',
                $holiday !== null => 'holiday',
                ! $working => 'off',
                $isPast => 'absent',
                $pattern !== null && $now->lessThan($date->setTimeFromTimeString($pattern->start_time)) => 'later',
                default => 'not_in',
            };

            return [
                'id' => $e->id,
                'name' => $e->full_name,
                'title' => $e->job_title,
                'department' => $e->department?->name,
                'state' => $state,
                'in' => $r?->clock_in_at->setTimezone($tz)->format('H:i'),
                'out' => $r?->clock_out_at?->setTimezone($tz)->format('H:i'),
                'late_min' => $r?->late_min,
                'remote' => $r?->remote,
                'leave' => $l?->type->name,
                'shift' => $pattern ? substr($pattern->start_time, 0, 5).'–'.substr($pattern->end_time, 0, 5) : null,
            ];
        });

        return ['rows' => $rows->values()->all(), 'holiday' => $holiday];
    }
}
