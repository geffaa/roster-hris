<?php

namespace App\Http\Controllers\Time;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\ApprovalStep;
use App\Models\AttendanceCorrection;
use App\Models\AttendanceRecord;
use App\Models\BankAccountChange;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Services\Time\ApprovalService;
use App\Services\Time\LeaveService;
use App\Support\CurrentCompany;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/** Every pending approval for the signed-in person, plus team records that need attention. */
class InboxController extends Controller
{
    public function __construct(private ApprovalService $approvals, private LeaveService $leave, private CurrentCompany $company) {}

    /** @return Collection<int, ApprovalStep> */
    public function pendingFor(Request $request): Collection
    {
        $user = $request->user();
        $mine = $user->employee()->value('id');
        $isHr = $user->can(Permission::EmployeesManage->value);

        return ApprovalStep::query()
            ->with(['request.requester:id,full_name,job_title,manager_id,bank_name,bank_account_last4', 'request.subject', 'delegatedFrom:id,full_name'])
            ->where('status', 'pending')
            ->whereHas('request', fn ($q) => $q->where('company_id', $this->company->id())->where('status', 'pending'))
            ->where(function ($q) use ($mine, $isHr) {
                $q->where('approver_id', $mine ?? 0);
                if ($isHr) {
                    $q->orWhereNull('approver_id');
                }
            })
            ->orderBy('created_at')
            ->get()
            ->filter(fn (ApprovalStep $s) => $this->approvals->canAct($request->user(), $s))
            ->values();
    }

    public function index(Request $request): Response
    {
        $tz = $this->company->get()->timezone;
        $items = $this->pendingFor($request)->map(fn (ApprovalStep $s) => $this->present($s, $tz));

        $mine = $request->user()->employee()->value('id');
        $teamIds = $request->user()->can(Permission::EmployeesViewAll->value)
            ? null
            : Employee::where('manager_id', $mine ?? 0)->pluck('id');
        $attention = AttendanceRecord::with('employee:id,full_name')
            ->where('status', 'incomplete')
            ->when($teamIds !== null, fn ($q) => $q->whereIn('employee_id', $teamIds))
            ->whereDoesntHave('corrections', fn ($q) => $q->where('status', 'pending'))
            ->orderByDesc('work_date')->limit(10)->get()
            ->map(fn (AttendanceRecord $r) => [
                'id' => $r->id,
                'employee' => $r->employee->full_name,
                'employee_id' => $r->employee_id,
                'date' => $r->work_date->toDateString(),
                'in' => $r->clock_in_at->setTimezone($tz)->format('H:i'),
            ]);

        return Inertia::render('time/Inbox', ['items' => $items, 'attention' => $attention]);
    }

    public function decide(Request $request, ApprovalStep $step): RedirectResponse
    {
        abort_unless($step->request()->withoutGlobalScopes()->value('company_id') === $this->company->id(), 404);
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'comment' => ['nullable', 'required_if:decision,reject', 'string', 'max:500']], [
            'comment.required_if' => 'Tell the employee why, so they can fix it or plan around it.',
        ]);

        try {
            $result = $this->approvals->decide($step, $request->user(), $data['decision'] === 'approve', $data['comment'] ?? null);
        } catch (RuntimeException) {
            abort(403);
        }

        $message = match (true) {
            $data['decision'] === 'reject' => 'Request rejected. The employee will see your reason.',
            $result->status === 'pending' => 'Approved. It now goes to HR for the second approval.',
            default => 'Approved.',
        };

        return back()->with('success', $message);
    }

    /** @return array<string, mixed> */
    public function present(ApprovalStep $step, string $tz): array
    {
        $request = $step->request;
        $subject = $request->subject;
        $base = [
            'step_id' => $step->id,
            'type' => $request->type,
            'step' => $step->step,
            'steps' => $request->steps()->count(),
            'hr_queue' => $step->approver_id === null,
            'delegated_from' => $step->delegatedFrom?->full_name,
            'requester' => ['id' => $request->requester->id, 'name' => $request->requester->full_name, 'title' => $request->requester->job_title],
            'submitted_at' => $request->created_at->toIso8601String(),
        ];

        return $base + match (true) {
            $subject instanceof LeaveRequest => $this->leaveDetail($subject),
            $subject instanceof BankAccountChange => [
                'title' => 'Bank account change',
                'summary' => sprintf('%s ···· %s → %s ···· %s', $request->requester->bank_name ?? 'None', $request->requester->bank_account_last4 ?? '—', $subject->bank_name, $subject->account_last4),
                'reason' => null,
                'facts' => ['Holder: '.$subject->holder_name, 'Applies from the next payroll calculation'],
            ],
            $subject instanceof AttendanceCorrection => [
                'title' => 'Attendance correction',
                'summary' => sprintf('%s · %s → %s–%s', CarbonImmutable::parse($subject->record()->withoutGlobalScopes()->value('work_date'))->format('D j M'),
                    $subject->original_out ? $subject->original_in?->setTimezone($tz)->format('H:i').'–'.$subject->original_out->setTimezone($tz)->format('H:i') : ($subject->original_in?->setTimezone($tz)->format('H:i').'–missing'),
                    $subject->new_in->setTimezone($tz)->format('H:i'), $subject->new_out->setTimezone($tz)->format('H:i')),
                'reason' => $subject->reason,
                'facts' => [],
            ],
            default => [
                'title' => 'Overtime',
                'summary' => sprintf('%s · %s–%s · %dh %02dm', $subject->work_date->format('D j M'), substr($subject->start_time, 0, 5), substr($subject->end_time, 0, 5), intdiv($subject->minutes, 60), $subject->minutes % 60),
                'reason' => $subject->reason,
                'facts' => [],
            ],
        };
    }

    /** @return array<string, mixed> */
    private function leaveDetail(LeaveRequest $leave): array
    {
        $employee = Employee::withoutGlobalScopes()->findOrFail($leave->employee_id);
        $type = $leave->type()->withoutGlobalScopes()->firstOrFail();
        $year = $leave->start_date->year;
        $balance = $this->leave->balance($employee, $type, $year);
        $overlaps = LeaveRequest::with('employee:id,full_name')
            ->where('status', 'approved')
            ->where('employee_id', '!=', $leave->employee_id)
            ->whereIn('employee_id', Employee::where('manager_id', $employee->manager_id)->pluck('id'))
            ->whereDate('start_date', '<=', $leave->end_date)->whereDate('end_date', '>=', $leave->start_date)
            ->get()->map(fn ($r) => $r->employee->full_name)->unique()->values();

        $range = $leave->start_date->isSameDay($leave->end_date)
            ? $leave->start_date->format('D j M').($leave->half_day !== 'none' ? ' ('.strtoupper($leave->half_day).')' : '')
            : $leave->start_date->format('j M').' – '.$leave->end_date->format('j M Y');

        return [
            'title' => $type->name,
            'summary' => sprintf('%s · %s %s', $range, rtrim(rtrim(number_format((float) $leave->days, 1), '0'), '.'), (float) $leave->days == 1 ? 'day' : 'days'),
            'reason' => $leave->reason,
            'color' => $type->color,
            'facts' => array_values(array_filter([
                $balance['available'] !== null ? sprintf('%s days left in %d after this request', rtrim(rtrim(number_format($balance['available'], 1), '0'), '.'), $year) : null,
                $overlaps->isNotEmpty() ? 'Also off: '.$overlaps->join(', ') : 'Nobody else in the team is off then',
            ])),
        ];
    }
}
