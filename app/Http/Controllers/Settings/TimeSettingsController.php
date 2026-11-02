<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\WorkPattern;
use App\Support\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** HR maintains work patterns and leave types without a developer. */
class TimeSettingsController extends Controller
{
    public const COLORS = ['#2E9E8F', '#D9594C', '#7B61D9', '#D45FA0', '#8A9691', '#3E7FD6', '#D99A2B', '#4BA36B'];

    public function __construct(private CurrentCompany $company) {}

    public function index(Request $request): Response
    {
        $this->allow($request);
        $companyId = $this->company->id();
        $contracts = Contract::whereHas('employee', fn ($q) => $q->where('status', '!=', 'resigned'))->whereNotNull('work_pattern_id')->pluck('work_pattern_id');
        $staff = Employee::where('status', '!=', 'resigned')->count();
        $patterns = WorkPattern::orderByDesc('is_default')->orderBy('name')->get();

        return Inertia::render('settings/Time', [
            'tab' => $request->query('tab') === 'leave' ? 'leave' : 'patterns',
            'patterns' => $patterns->map(fn (WorkPattern $p) => [
                'id' => $p->id, 'name' => $p->name, 'start_time' => substr($p->start_time, 0, 5), 'end_time' => substr($p->end_time, 0, 5),
                'crosses_midnight' => $p->crosses_midnight, 'grace_min' => $p->grace_min, 'working_days' => $p->working_days, 'is_default' => $p->is_default,
                'people' => $p->is_default ? $staff - $contracts->count() : $contracts->filter(fn ($id) => $id === $p->id)->count(),
            ]),
            'leaveTypes' => LeaveType::orderBy('id')->get()->map(fn (LeaveType $t) => [
                'id' => $t->id, 'code' => $t->code, 'name' => $t->name, 'paid' => $t->paid, 'annual_days' => $t->annual_days, 'accrual' => $t->accrual,
                'event_based' => $t->event_based, 'min_service_months' => $t->min_service_months, 'half_day_allowed' => $t->half_day_allowed,
                'requires_second_approval' => $t->requires_second_approval, 'color' => $t->color,
                'requests' => LeaveRequest::where('leave_type_id', $t->id)->count(),
            ]),
            'colors' => self::COLORS,
            'companyId' => $companyId,
        ]);
    }

    public function savePattern(Request $request, ?WorkPattern $pattern = null): RedirectResponse
    {
        $this->allow($request);
        $times = $this->times();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60', Rule::unique('work_patterns')->where('company_id', $this->company->id())->ignore($pattern?->id)],
            'start_time' => ['required', Rule::in($times)],
            'end_time' => ['required', Rule::in($times), 'different:start_time'],
            'grace_min' => ['required', 'integer', 'min:0', 'max:60'],
            'working_days' => ['required', 'array', 'min:1'],
            'working_days.*' => ['integer', 'between:1,7', 'distinct'],
            'is_default' => ['boolean'],
        ], ['end_time.different' => 'A shift needs a different start and end time.', 'working_days.required' => 'Pick at least one working day.']);

        $data['working_days'] = collect($data['working_days'])->map(fn ($d) => (int) $d)->sort()->values()->all();
        $data['crosses_midnight'] = $data['end_time'] <= $data['start_time'];
        $hadDefault = $pattern->is_default ?? false;
        abort_if($hadDefault && empty($data['is_default']), 422, 'Make another pattern the default first: everyone without a pattern of their own uses it.');

        DB::transaction(function () use ($data, &$pattern) {
            if (! empty($data['is_default'])) {
                WorkPattern::where('is_default', true)->when($pattern, fn ($q) => $q->whereKeyNot($pattern->id))->update(['is_default' => false]);
            }
            $pattern ? $pattern->update($data) : $pattern = WorkPattern::create([...$data, 'company_id' => $this->company->id()]);
        });

        return redirect()->route('settings.time')->with('success', "Saved {$data['name']}. Clock-in and leave use it from now on; past attendance is unchanged.");
    }

    public function saveLeaveType(Request $request, ?LeaveType $type = null): RedirectResponse
    {
        $this->allow($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'paid' => ['boolean'],
            'annual_days' => ['nullable', 'numeric', 'min:0', 'max:365'],
            'accrual' => ['required', 'in:upfront,monthly'],
            'event_based' => ['boolean'],
            'min_service_months' => ['required', 'integer', 'min:0', 'max:60'],
            'half_day_allowed' => ['boolean'],
            'requires_second_approval' => ['boolean'],
            'color' => ['required', Rule::in(self::COLORS)],
        ]);
        if (! $type) {
            $code = Str::slug($data['name'], '_');
            abort_if(LeaveType::where('code', $code)->exists(), 422, 'There is already a leave type with this name.');
            $data['code'] = $code;
            $type = LeaveType::create([...$data, 'company_id' => $this->company->id()]);
        } else {
            $type->update($data);
        }

        return redirect()->route('settings.time', ['tab' => 'leave'])->with('success', "Saved {$type->name}. Balances recalculate from these rules; approved leave keeps its days.");
    }

    /** @return list<string> 15-minute steps, "00:00" … "23:45" */
    private function times(): array
    {
        $out = [];
        for ($m = 0; $m < 1440; $m += 15) {
            $out[] = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        }

        return $out;
    }

    private function allow(Request $request): void
    {
        abort_unless($request->user()->can(Permission::EmployeesManage->value), 403);
    }
}
