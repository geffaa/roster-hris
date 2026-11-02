<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Http\Controllers\Time\InboxController;
use App\Models\ApprovalStep;
use App\Models\Company;
use App\Models\Contract;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\RulePackVersion;
use App\Services\Time\AttendanceBoard;
use App\Services\Time\WorkCalendar;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/** home for HR, payroll and managers: what needs attention today, scoped to what the person may see. */
class DashboardController extends Controller
{
    public function __construct(private CurrentCompany $company, private AttendanceBoard $board, private WorkCalendar $calendar) {}

    public function __invoke(Request $request, InboxController $inbox): Response|RedirectResponse
    {
        $user = $request->user();

        // Employees without team or company access live in the phone app.
        if (! $user->can('viewAny', Employee::class) && $user->employee()->exists()) {
            return redirect()->route('me.today');
        }
        $company = $this->company->get();
        $tz = $company->timezone;
        $now = CarbonImmutable::now($tz);
        $today = $now->startOfDay();
        $seesCompany = $user->can(Permission::EmployeesViewAll->value);
        $me = $user->employee;
        $teamIds = $seesCompany ? null : Employee::where('manager_id', $me->id ?? 0)->pluck('id');

        $steps = $inbox->pendingFor($request);
        $board = $user->can('viewAny', Employee::class) ? $this->board->day($user, $company, $today) : ['rows' => [], 'holiday' => null];
        $states = collect($board['rows'])->countBy('state');

        $run = $user->can(Permission::PayrollView->value) ? PayrollRun::whereNotIn('status', ['published'])->orderByDesc('period_start')->first() : null;

        return Inertia::render('Dashboard', [
            'now' => $now->toIso8601String(),
            'scope' => $seesCompany ? 'company' : 'team',
            'headcount' => Employee::where('status', '!=', 'resigned')->when($teamIds, fn ($q) => $q->whereIn('id', $teamIds))->count(),
            'requests' => [
                'count' => $steps->count(),
                'by_type' => $steps->countBy(fn (ApprovalStep $s) => $s->request->type),
                'items' => $steps->take(5)->map(fn (ApprovalStep $s) => $inbox->present($s, $tz))->values(),
            ],
            'attendance' => [
                'holiday' => $board['holiday'],
                'counts' => $states,
                'watch' => collect($board['rows'])->whereIn('state', ['not_in', 'late', 'incomplete'])->sortBy(fn ($r) => ['not_in' => 0, 'late' => 1, 'incomplete' => 2][$r['state']])->take(6)->values(),
            ],
            'payroll' => $run ? [
                'id' => $run->id, 'reference' => $run->reference, 'period' => $run->period_start->toDateString(), 'pay_date' => $run->pay_date->toDateString(),
                'status' => $run->status, 'approvals' => $run->approvals(), 'net' => $run->totals['net'] ?? null, 'headcount' => $run->headcount,
                'open_reviews' => $run->status === 'calculated' ? $run->items()->get()->filter->needsReview()->count() : 0,
                'currency' => $company->base_currency,
            ] : null,
            'contracts' => Contract::with('employee:id,full_name')->whereNotNull('end_date')
                ->whereBetween('end_date', [$today->toDateString(), $today->addDays(60)->toDateString()])
                ->when($teamIds, fn ($q) => $q->whereIn('employee_id', $teamIds))
                ->whereHas('employee', fn ($q) => $q->where('status', '!=', 'resigned'))
                ->orderBy('end_date')->get()
                ->map(fn ($c) => ['employee_id' => $c->employee_id, 'name' => $c->employee->full_name, 'date' => $c->end_date->toDateString()]),
            'upcoming' => $this->upcoming($today, $teamIds, $company),
            'departments' => $seesCompany ? Employee::where('status', '!=', 'resigned')->with('department:id,name')->get(['id', 'department_id'])
                ->countBy(fn ($e) => $e->department->name ?? 'No department')->sortDesc()->map(fn ($n, $name) => ['name' => $name, 'count' => $n])->values() : [],
            'team' => $seesCompany ? [] : Employee::whereIn('id', $teamIds ?? [])->orderBy('full_name')->get(['id', 'full_name', 'job_title']),
        ]);
    }

    /**
     * Holidays, team leave starting soon, and rule pack versions taking effect, in the next 45 days.
     *
     * @param  Collection<int, int>|null  $teamIds
     * @return list<array{date: string, title: string, detail: string, kind: string, link: ?string}>
     */
    private function upcoming(CarbonImmutable $today, $teamIds, Company $company): array
    {
        $until = $today->addDays(45);
        $out = [];
        foreach ($this->calendar->holidays($company, $today, $until) as $date => $name) {
            $out[] = ['date' => $date, 'title' => $name, 'detail' => 'Public holiday', 'kind' => 'holiday', 'link' => null];
        }
        $leave = LeaveRequest::with(['employee:id,full_name', 'type:id,name'])->where('status', 'approved')
            ->whereBetween('start_date', [$today->addDay()->toDateString(), $until->toDateString()])
            ->when($teamIds, fn ($q) => $q->whereIn('employee_id', $teamIds))->orderBy('start_date')->limit(6)->get();
        foreach ($leave as $l) {
            $out[] = ['date' => $l->start_date->toDateString(), 'title' => $l->employee->full_name, 'detail' => $l->type->name.' · '.(float) $l->days.' '.((float) $l->days === 1.0 ? 'day' : 'days'), 'kind' => 'leave', 'link' => route('leave-calendar', ['month' => $l->start_date->format('Y-m')])];
        }
        if ($company->rule_pack_id) {
            foreach (RulePackVersion::where('rule_pack_id', $company->rule_pack_id)->whereIn('status', ['approved', 'pending'])->whereBetween('effective_from', [$today->toDateString(), $until->addDays(30)->toDateString()])->get() as $v) {
                $out[] = ['date' => $v->effective_from->toDateString(), 'title' => "Rule pack {$v->version}", 'detail' => $v->status === 'pending' ? 'Takes effect if approved' : 'New rates take effect', 'kind' => $v->status, 'link' => route('rule-packs.show', $v->id)];
            }
        }
        usort($out, fn ($a, $b) => strcmp($a['date'], $b['date']));

        return array_slice($out, 0, 6);
    }
}
