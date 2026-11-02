<?php

use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\AttendanceRecord;
use App\Models\Company;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Services\Time\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Models\Audit;

beforeEach(function () {
    extract($this->t = timeSetup());
    $this->head = Employee::factory()->for($company)->create(['hire_date' => '2019-01-07']);
    $this->lead = Employee::factory()->for($company)->create(['hire_date' => '2020-01-06', 'manager_id' => $this->head->id]);
    $this->dev = Employee::factory()->for($company)->create(['hire_date' => '2024-01-08', 'manager_id' => $this->lead->id]);
    $this->leadUser = memberOf($company, Role::Manager, $this->lead);
    $this->headUser = memberOf($company, Role::Manager, $this->head);
    $this->hr = memberOf($company, Role::HrAdmin);
    $this->travelTo(Carbon::parse('2026-11-02 09:00', 'Asia/Jakarta'));
    $this->book = fn (Employee $e, $type, string $from, string $to) => app(LeaveService::class)->request($e, $type, CarbonImmutable::parse($from), CarbonImmutable::parse($to), 'none', null);
});

afterEach(fn () => Carbon::setTestNow());

it('lets the manager approve from the inbox', function () {
    $leave = ($this->book)($this->dev, $this->t['annual'], '2026-11-23', '2026-11-24');

    signIn($this->leadUser)->get(route('inbox'))->assertInertia(fn ($p) => $p->component('time/Inbox')->has('items', 1)->where('items.0.title', 'Annual leave'));
    signIn($this->leadUser)->post(route('approvals.decide', ApprovalStep::sole()), ['decision' => 'approve'])->assertSessionHasNoErrors();

    expect($leave->fresh()->status)->toBe('approved');
});

it('requires a reason to reject', function () {
    $leave = ($this->book)($this->dev, $this->t['annual'], '2026-11-23', '2026-11-24');

    signIn($this->leadUser)->post(route('approvals.decide', ApprovalStep::sole()), ['decision' => 'reject'])->assertSessionHasErrors('comment');
    signIn($this->leadUser)->post(route('approvals.decide', ApprovalStep::sole()), ['decision' => 'reject', 'comment' => 'Release week'])->assertSessionHasNoErrors();
    expect($leave->fresh()->status)->toBe('rejected');
});

it('stops other managers from deciding', function () {
    ($this->book)($this->dev, $this->t['annual'], '2026-11-23', '2026-11-24');

    signIn($this->headUser)->post(route('approvals.decide', ApprovalStep::sole()), ['decision' => 'approve'])->assertForbidden();
});

it('runs a second HR approval when the leave type needs it', function () {
    $leave = ($this->book)($this->dev, $this->t['unpaid'], '2026-11-23', '2026-11-24');

    signIn($this->leadUser)->post(route('approvals.decide', ApprovalStep::where('step', 1)->sole()), ['decision' => 'approve']);
    expect($leave->fresh()->status)->toBe('pending');

    signIn($this->hr)->get(route('inbox'))->assertInertia(fn ($p) => $p->where('items.0.step', 2)->where('items.0.steps', 2)->where('items.0.hr_queue', true));
    signIn($this->hr)->post(route('approvals.decide', ApprovalStep::where('step', 2)->sole()), ['decision' => 'approve']);
    expect($leave->fresh()->status)->toBe('approved');
});

it('sends requests from people without a manager to the HR queue, but HR cannot approve their own', function () {
    ($this->book)($this->head, $this->t['annual'], '2026-11-23', '2026-11-24');
    expect(ApprovalStep::sole()->approver_id)->toBeNull();

    $hrEmployee = Employee::factory()->for($this->t['company'])->create(['hire_date' => '2020-01-06']);
    $this->hr->employee()->save($hrEmployee);
    ($this->book)($hrEmployee, $this->t['annual'], '2026-11-25', '2026-11-26');

    signIn($this->hr)->get(route('inbox'))->assertInertia(fn ($p) => $p->has('items', 1)); // only the head's request
});

it('routes to the manager\'s manager while the manager is on leave', function () {
    LeaveRequest::create(['company_id' => $this->t['company']->id, 'employee_id' => $this->lead->id, 'leave_type_id' => $this->t['annual']->id,
        'start_date' => '2026-11-02', 'end_date' => '2026-11-06', 'days' => 5, 'status' => 'approved']);

    ($this->book)($this->dev, $this->t['annual'], '2026-11-23', '2026-11-24');

    $step = ApprovalStep::sole();
    expect($step->approver_id)->toBe($this->head->id)->and($step->delegated_from_id)->toBe($this->lead->id);
    signIn($this->headUser)->get(route('inbox'))->assertInertia(fn ($p) => $p->where('items.0.delegated_from', $this->lead->full_name));
});

it('returns 404 for a step from another company', function () {
    ($this->book)($this->dev, $this->t['annual'], '2026-11-23', '2026-11-24');
    $outsider = memberOf(Company::factory()->create(), Role::HrAdmin);

    signIn($outsider)->post(route('approvals.decide', ApprovalStep::sole()), ['decision' => 'approve'])->assertNotFound();
});

it('applies an approved correction while keeping the original times', function () {
    $devUser = memberOf($this->t['company'], Role::Employee, $this->dev);
    $record = AttendanceRecord::create(['company_id' => $this->t['company']->id, 'employee_id' => $this->dev->id, 'work_date' => '2026-10-30',
        'clock_in_at' => Carbon::parse('2026-10-30 08:31', 'Asia/Jakarta'), 'status' => 'incomplete']);

    signIn($devUser)->post(route('me.attendance.correct', $record), ['in' => '08:31', 'out' => '17:42', 'reason' => 'Battery died'])->assertSessionHasNoErrors();
    signIn($this->leadUser)->post(route('approvals.decide', ApprovalStep::sole()), ['decision' => 'approve']);

    $record->refresh();
    $correction = $record->corrections()->first();
    expect($record->status)->toBe('corrected')
        ->and($record->clock_out_at->setTimezone('Asia/Jakarta')->format('H:i'))->toBe('17:42')
        ->and($correction->original_out)->toBeNull()
        ->and($correction->status)->toBe('approved')
        ->and(Audit::where('auditable_type', AttendanceRecord::class)->where('event', 'updated')->exists())->toBeTrue();
});

it('lets an employee request overtime that the manager approves', function () {
    $devUser = memberOf($this->t['company'], Role::Employee, $this->dev);

    signIn($devUser)->post(route('me.overtime'), ['work_date' => '2026-10-29', 'start_time' => '20:00', 'end_time' => '01:00', 'reason' => 'Migration'])->assertSessionHasNoErrors();
    expect(OvertimeRequest::sole()->minutes)->toBe(300);

    signIn($this->leadUser)->post(route('approvals.decide', ApprovalStep::sole()), ['decision' => 'approve']);
    expect(OvertimeRequest::sole()->status)->toBe('approved')->and(ApprovalRequest::sole()->status)->toBe('approved');
});

it('shows HR the whole company and a manager only the team on the attendance board', function () {
    signIn($this->hr)->get(route('attendance', ['date' => '2026-11-02']))->assertInertia(fn ($p) => $p->where('scope', 'company')->has('rows', 3));
    signIn($this->leadUser)->get(route('attendance', ['date' => '2026-11-02']))->assertInertia(fn ($p) => $p->where('scope', 'team')->has('rows', 1));
    signIn($this->leadUser)->get(route('leave-calendar', ['month' => '2026-11']))->assertOk();
});
