<?php

use App\Enums\Role;
use App\Models\ApprovalRequest;
use App\Models\ApprovalStep;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Services\Time\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    extract($this->t = timeSetup());
    $this->lead = Employee::factory()->for($company)->create(['hire_date' => '2020-01-06']);
    $this->employee = Employee::factory()->for($company)->create(['hire_date' => '2024-01-08', 'manager_id' => $this->lead->id]);
    $this->user = memberOf($company, Role::Employee, $this->employee);
    $this->travelTo(Carbon::parse('2026-11-02 09:00', 'Asia/Jakarta'));
    $this->leave = app(LeaveService::class);
});

afterEach(fn () => Carbon::setTestNow());

function ask($test, array $data)
{
    return signIn($test->user)->post(route('me.time-off.store'), ['half_day' => 'none', 'reason' => null, ...$data]);
}

it('counts only working days, skipping weekends and public holidays', function () {
    // Fri 13 – Wed 18 Nov: Fri, Mon, (Tue holiday), Wed = 3 days
    $count = $this->leave->workingDays($this->employee, CarbonImmutable::parse('2026-11-13'), CarbonImmutable::parse('2026-11-18'));

    expect($count['days'])->toBe(3.0);
});

it('creates a pending request routed to the manager', function () {
    ask($this, ['leave_type_id' => $this->t['annual']->id, 'start_date' => '2026-11-23', 'end_date' => '2026-11-24'])->assertSessionHasNoErrors();

    $leave = LeaveRequest::sole();
    expect($leave->status)->toBe('pending')->and((float) $leave->days)->toBe(2.0);
    expect(ApprovalStep::sole()->approver_id)->toBe($this->lead->id);
});

it('rejects a request above the balance unless negative balance is allowed', function () {
    ask($this, ['leave_type_id' => $this->t['annual']->id, 'start_date' => '2026-11-30', 'end_date' => '2026-12-18'])
        ->assertSessionHasErrors(['end_date' => 'This needs 15 days from your 2026 balance, but only 12 are left.']);

    $this->t['annual']->update(['allow_negative' => true]);
    ask($this, ['leave_type_id' => $this->t['annual']->id, 'start_date' => '2026-11-30', 'end_date' => '2026-12-18'])->assertSessionHasNoErrors();
});

it('blocks annual leave before 12 months of service', function () {
    $new = Employee::factory()->for($this->t['company'])->create(['hire_date' => '2026-03-02', 'manager_id' => $this->lead->id]);
    $this->user = memberOf($this->t['company'], Role::Employee, $new);

    ask($this, ['leave_type_id' => $this->t['annual']->id, 'start_date' => '2026-11-23', 'end_date' => '2026-11-23'])
        ->assertSessionHasErrors(['start_date' => 'Annual leave becomes available on 2 Mar 2027, 12 months after you joined.']);
});

it('prorates entitlement for mid-year joiners and monthly accrual', function () {
    $joiner = Employee::factory()->for($this->t['company'])->create(['hire_date' => '2026-07-15']);
    $upfrontNoWait = LeaveType::create(['company_id' => $this->t['company']->id, 'code' => 'pto', 'name' => 'PTO', 'annual_days' => 25]);
    $monthly = LeaveType::create(['company_id' => $this->t['company']->id, 'code' => 'uk', 'name' => 'UK annual', 'annual_days' => 25, 'accrual' => 'monthly']);

    expect($this->leave->entitlement($joiner, $upfrontNoWait, 2026))->toBe(12.5) // Jul–Dec = 6/12 × 25
        ->and($this->leave->entitlement($joiner, $upfrontNoWait, 2027))->toBe(25.0)
        ->and($this->leave->entitlement($this->employee, $monthly, 2026))->toBe(25.0);
});

it('splits leave across years and checks each year\'s balance', function () {
    ask($this, ['leave_type_id' => $this->t['annual']->id, 'start_date' => '2026-12-28', 'end_date' => '2027-01-05'])->assertSessionHasNoErrors();

    // 28–31 Dec = 4 days in 2026; 1 Jan is a holiday, 4–5 Jan = 2 days in 2027
    expect((float) LeaveRequest::sole()->days)->toBe(6.0)
        ->and($this->leave->balance($this->employee, $this->t['annual'], 2026)['pending'])->toBe(4.0)
        ->and($this->leave->balance($this->employee, $this->t['annual'], 2027)['pending'])->toBe(2.0);
});

it('counts a half day as 0.5 and only for single days', function () {
    ask($this, ['leave_type_id' => $this->t['annual']->id, 'start_date' => '2026-11-23', 'end_date' => '2026-11-23', 'half_day' => 'pm'])->assertSessionHasNoErrors();
    expect((float) LeaveRequest::sole()->days)->toBe(0.5);

    ask($this, ['leave_type_id' => $this->t['annual']->id, 'start_date' => '2026-11-25', 'end_date' => '2026-11-26', 'half_day' => 'am'])->assertSessionHasErrors('half_day');
});

it('refuses overlapping requests', function () {
    ask($this, ['leave_type_id' => $this->t['sick']->id, 'start_date' => '2026-11-23', 'end_date' => '2026-11-25']);
    ask($this, ['leave_type_id' => $this->t['annual']->id, 'start_date' => '2026-11-25', 'end_date' => '2026-11-26'])->assertSessionHasErrors('start_date');
});

it('returns only the days not yet taken when approved leave in progress is ended early', function () {
    $leave = LeaveRequest::create(['company_id' => $this->t['company']->id, 'employee_id' => $this->employee->id, 'leave_type_id' => $this->t['annual']->id,
        'start_date' => '2026-10-28', 'end_date' => '2026-11-06', 'days' => 8, 'status' => 'approved']);
    expect($this->leave->balance($this->employee, $this->t['annual'], 2026)['available'])->toBe(4.0);

    signIn($this->user)->delete(route('me.time-off.destroy', $leave))->assertSessionHasNoErrors();

    $leave->refresh();
    // Kept: Wed 28 Oct – Mon 2 Nov (today) = 4 working days; 6 days back in the balance
    expect($leave->status)->toBe('approved')
        ->and($leave->end_date->toDateString())->toBe('2026-11-02')
        ->and($leave->original_end_date->toDateString())->toBe('2026-11-06')
        ->and((float) $leave->days)->toBe(4.0)
        ->and($this->leave->balance($this->employee, $this->t['annual'], 2026)['available'])->toBe(8.0);
});

it('cancels a pending request and its approval', function () {
    ask($this, ['leave_type_id' => $this->t['annual']->id, 'start_date' => '2026-11-23', 'end_date' => '2026-11-24']);
    $leave = LeaveRequest::sole();

    signIn($this->user)->delete(route('me.time-off.destroy', $leave));

    expect($leave->fresh()->status)->toBe('cancelled')->and(ApprovalRequest::sole()->status)->toBe('cancelled');
});
