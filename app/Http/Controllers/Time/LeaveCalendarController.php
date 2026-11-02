<?php

namespace App\Http\Controllers\Time;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Time\WorkCalendar;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** S07 team calendar — a month of who is off, with public holidays and weekends shaded. */
class LeaveCalendarController extends Controller
{
    public function __construct(private WorkCalendar $calendar, private CurrentCompany $company) {}

    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user->can('viewAny', Employee::class), 403);
        $company = $this->company->get();
        $month = CarbonImmutable::parse(($request->validate(['month' => ['nullable', 'date_format:Y-m']])['month'] ?? CarbonImmutable::now($company->timezone)->format('Y-m')).'-01');
        $from = $month->startOfMonth();
        $to = $month->endOfMonth();

        $people = Employee::query()->where('status', '!=', 'resigned')
            ->when(! $user->can(Permission::EmployeesViewAll->value), fn ($q) => $q->where(fn ($w) => $w->where('manager_id', $user->employee()->value('id') ?? 0)->orWhere('id', $user->employee()->value('id') ?? 0)))
            ->orderBy('full_name')->get(['id', 'full_name', 'job_title']);

        $leave = LeaveRequest::with('type:id,name,color')->whereIn('employee_id', $people->pluck('id'))->whereIn('status', ['approved', 'pending'])
            ->whereDate('start_date', '<=', $to)->whereDate('end_date', '>=', $from)->get();

        return Inertia::render('time/LeaveCalendar', [
            'month' => $month->format('Y-m'),
            'days' => collect(range(1, $month->daysInMonth))->map(fn ($d) => ['day' => $d, 'weekday' => $month->day($d)->isoWeekday()]),
            'holidays' => $this->calendar->holidays($company, $from, $to),
            'people' => $people->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->full_name,
                'title' => $p->job_title,
                'spans' => $leave->where('employee_id', $p->id)->map(fn (LeaveRequest $l) => [
                    'from' => max(1, $l->start_date->lessThan($from) ? 1 : $l->start_date->day),
                    'to' => $l->end_date->greaterThan($to) ? $month->daysInMonth : $l->end_date->day,
                    'type' => $l->type->name,
                    'color' => $l->type->color,
                    'pending' => $l->status === 'pending',
                    'half' => $l->half_day !== 'none',
                ])->values(),
            ]),
            'types' => LeaveType::orderBy('id')->get(['id', 'name', 'color']),
        ]);
    }
}
