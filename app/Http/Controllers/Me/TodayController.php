<?php

namespace App\Http\Controllers\Me;

use App\Exceptions\ClockException;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Location;
use App\Services\Time\ClockService;
use App\Services\Time\LeaveService;
use App\Services\Time\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** S05 — the employee's phone screen: clock in/out, today's shift, this week. */
class TodayController extends Controller
{
    public function __construct(private ClockService $clock, private WorkCalendar $calendar, private LeaveService $leave) {}

    public static function employeeOf(Request $request): Employee
    {
        $employee = $request->user()->employee;
        abort_if($employee === null, 403, 'Your account is not linked to an employee profile in this company.');

        return $employee;
    }

    public function show(Request $request): Response
    {
        $employee = self::employeeOf($request);
        $company = Company::findOrFail($employee->company_id);
        $pattern = $this->calendar->patternFor($employee);
        $now = $this->clock->now($company);
        $workDate = $this->clock->workDate($pattern, $now);
        $location = $employee->location_id ? Location::find($employee->location_id) : null;

        $record = AttendanceRecord::where('employee_id', $employee->id)->whereDate('work_date', $workDate)->first();
        $weekStart = $now->startOfWeek();
        $week = AttendanceRecord::where('employee_id', $employee->id)
            ->whereBetween('work_date', [$weekStart->toDateString(), $weekStart->addDays(6)->toDateString()])
            ->get()->keyBy(fn ($r) => $r->work_date->toDateString());
        $holidays = $this->calendar->holidays($company, $weekStart, $weekStart->addDays(6));

        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $d = $weekStart->addDays($i);
            $r = $week[$d->toDateString()] ?? null;
            $days[] = [
                'date' => $d->toDateString(),
                'label' => $d->format('D'),
                'working' => $pattern ? in_array($d->isoWeekday(), $pattern->working_days, true) : $d->isWeekday(),
                'holiday' => $holidays[$d->toDateString()] ?? null,
                'in' => $r?->clock_in_at->setTimezone($company->timezone)->format('H:i'),
                'out' => $r?->clock_out_at?->setTimezone($company->timezone)->format('H:i'),
                'status' => $r?->status,
                'late_min' => $r?->late_min,
                'future' => $d->greaterThan($now->startOfDay()),
            ];
        }

        $incomplete = AttendanceRecord::where('employee_id', $employee->id)->where('status', 'incomplete')
            ->whereDoesntHave('corrections', fn ($q) => $q->where('status', 'pending'))
            ->orderByDesc('work_date')->first();
        $annual = LeaveType::where('code', 'annual')->first();

        return Inertia::render('me/Today', [
            'now' => $now->toIso8601String(),
            'timezone' => $company->timezone,
            'employee' => ['first_name' => explode(' ', $employee->full_name)[0], 'job_title' => $employee->job_title],
            'shift' => $pattern ? [
                'label' => $pattern->label(),
                'start' => substr($pattern->start_time, 0, 5),
                'end' => substr($pattern->end_time, 0, 5),
                'grace' => $pattern->grace_min,
                'working_today' => in_array($workDate->isoWeekday(), $pattern->working_days, true),
            ] : null,
            'holiday' => $this->calendar->holidayOn($company, $workDate),
            'location' => $location ? [
                'name' => $location->name,
                'lat' => $location->lat === null ? null : (float) $location->lat,
                'lng' => $location->lng === null ? null : (float) $location->lng,
                'radius' => $location->radius_m,
                'remote' => $location->allow_remote || $location->lat === null,
            ] : null,
            'record' => $record ? [
                'in' => $record->clock_in_at->setTimezone($company->timezone)->format('H:i'),
                'out' => $record->clock_out_at?->setTimezone($company->timezone)->format('H:i'),
                'status' => $record->status,
                'late_min' => $record->late_min,
            ] : null,
            'week' => $days,
            'incomplete' => $incomplete ? ['id' => $incomplete->id, 'date' => $incomplete->work_date->toDateString()] : null,
            'annual' => $annual ? $this->leave->balance($employee, $annual, $now->year) : null,
        ]);
    }

    public function clock(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:in,out'],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $employee = self::employeeOf($request);

        try {
            $record = $data['action'] === 'in'
                ? $this->clock->clockIn($employee, $data['lat'] ?? null, $data['lng'] ?? null)
                : $this->clock->clockOut($employee, $data['lat'] ?? null, $data['lng'] ?? null);
        } catch (ClockException $e) {
            return back()->withErrors(['clock' => $e->getMessage()])->with('clockReason', $e->reason);
        }

        $tz = Company::findOrFail($employee->company_id)->timezone;
        $time = CarbonImmutable::parse($data['action'] === 'in' ? $record->clock_in_at : $record->clock_out_at)->setTimezone($tz)->format('H:i');
        $message = $data['action'] === 'in'
            ? ($record->late_min > 0 ? __('Clocked in at :time, :late after the grace period.', ['time' => $time, 'late' => ClockService::humanMinutes($record->late_min)]) : __('Clocked in at :time. Have a good day.', ['time' => $time]))
            : __('Clocked out at :time.', ['time' => $time]);

        return back()->with('success', $message);
    }
}
