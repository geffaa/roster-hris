<?php

namespace App\Http\Controllers\Time;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Time\WorkCalendar;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** S06 — who is in today: on time, late, missing, on leave; for HR (company) or managers (team). */
class TeamAttendanceController extends Controller
{
    public function __construct(private WorkCalendar $calendar, private CurrentCompany $company) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('viewAny', Employee::class), 403);
        $company = $this->company->get();
        $tz = $company->timezone;
        $date = CarbonImmutable::parse($request->validate(['date' => ['nullable', 'date']])['date'] ?? CarbonImmutable::now($tz)->toDateString(), $tz)->startOfDay();

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
        $isPast = $date->lessThan(CarbonImmutable::now($tz)->startOfDay());

        $rows = $people->map(function (Employee $e) use ($records, $leave, $holiday, $date, $tz, $isPast) {
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

        return Inertia::render('time/Attendance', [
            'date' => $date->toDateString(),
            'today' => CarbonImmutable::now($tz)->toDateString(),
            'holiday' => $holiday,
            'rows' => $rows,
            'summary' => $rows->countBy('state'),
            'scope' => $user->can(Permission::EmployeesViewAll->value) ? 'company' : 'team',
        ]);
    }
}
