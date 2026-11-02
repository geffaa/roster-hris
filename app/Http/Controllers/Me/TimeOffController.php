<?php

namespace App\Http\Controllers\Me;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Time\LeaveService;
use App\Services\Time\WorkCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** balances, request leave, history, cancel. */
class TimeOffController extends Controller
{
    public function __construct(private LeaveService $leave, private WorkCalendar $calendar) {}

    public function index(Request $request): Response
    {
        $employee = TodayController::employeeOf($request);
        $company = Company::findOrFail($employee->company_id);
        $year = (int) CarbonImmutable::now($company->timezone)->year;
        $types = LeaveType::orderBy('id')->get();

        return Inertia::render('me/TimeOff', [
            'year' => $year,
            'balances' => $types->map(fn (LeaveType $t) => [
                'id' => $t->id,
                'code' => $t->code,
                'name' => $t->name,
                'paid' => $t->paid,
                'color' => $t->color,
                'limited' => $t->isLimited(),
                'event_based' => $t->event_based,
                'half_day_allowed' => $t->half_day_allowed,
                'eligible_from' => $t->min_service_months ? $this->leave->eligibleFrom($employee, $t)->toDateString() : null,
                ...$this->leave->balance($employee, $t, $year),
            ])->values(),
            'requests' => LeaveRequest::with('type:id,code,name,color')->where('employee_id', $employee->id)->latest('start_date')->limit(20)->get()->map(fn (LeaveRequest $r) => [
                'id' => $r->id,
                'type' => $r->type->name,
                'code' => $r->type->code,
                'color' => $r->type->color,
                'start' => $r->start_date->toDateString(),
                'end' => $r->end_date->toDateString(),
                'original_end' => $r->original_end_date?->toDateString(),
                'half_day' => $r->half_day,
                'days' => (float) $r->days,
                'status' => $r->status,
                'reason' => $r->reason,
            ]),
            'holidays' => collect($this->calendar->holidays($company, CarbonImmutable::create($year, 1, 1), CarbonImmutable::create($year + 1, 12, 31)))
                ->map(fn ($name, $date) => ['date' => $date, 'name' => $name])->values(),
        ]);
    }

    /** Live working-day count while the form is filled in. */
    public function quote(Request $request): JsonResponse
    {
        $data = $request->validate(['start_date' => ['required', 'date'], 'end_date' => ['required', 'date', 'after_or_equal:start_date'], 'half_day' => ['nullable', 'in:none,am,pm']]);
        $employee = TodayController::employeeOf($request);
        $count = $this->leave->workingDays($employee, CarbonImmutable::parse($data['start_date']), CarbonImmutable::parse($data['end_date']), $data['half_day'] ?? 'none');

        return response()->json(['days' => $count['days'], 'by_year' => $count['by_year']]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'leave_type_id' => ['required', 'integer'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date'],
            'half_day' => ['required', 'in:none,am,pm'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);
        $employee = TodayController::employeeOf($request);
        $type = LeaveType::findOrFail($data['leave_type_id']);

        $leave = $this->leave->request($employee, $type, CarbonImmutable::parse($data['start_date']), CarbonImmutable::parse($data['end_date']), $data['half_day'], $data['reason'] ?? null);

        return back()->with('success', __(':type request for :days :unit sent for approval.', ['type' => $type->name, 'days' => rtrim(rtrim(number_format((float) $leave->days, 1), '0'), '.'), 'unit' => __((float) $leave->days == 1 ? 'day' : 'days')]));
    }

    public function destroy(Request $request, LeaveRequest $leave): RedirectResponse
    {
        $employee = TodayController::employeeOf($request);
        abort_unless($leave->employee_id === $employee->id, 404);
        $wasStarted = $leave->status === 'approved' && $leave->start_date->isPast() && ! $leave->start_date->isToday();
        $this->leave->cancel($leave);

        return back()->with('success', $wasStarted ? __('Leave shortened. Days after today were returned to your balance.') : __('Leave request cancelled.'));
    }
}
