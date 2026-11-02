<?php

use App\Enums\Role;
use App\Models\LeaveRequest;
use App\Models\WorkPattern;
use App\Services\Payroll\PayrollRuns;
use App\Services\Time\LeaveService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->t = payrollSetup();
    $this->travelTo(Carbon::parse('2026-11-16 10:00', 'Asia/Jakarta'));
    $this->lead = hire($this->t['company'], 20_000_000);
    $this->dev = hire($this->t['company'], 10_000_000);
    $this->dev->update(['manager_id' => $this->lead->id]);
    $this->outsider = hire($this->t['company'], 9_000_000);
    $this->leadUser = memberOf($this->t['company'], Role::Manager, $this->lead);
});

afterEach(fn () => Carbon::setTestNow());

it('gives HR and payroll the company view with payroll, requests and attendance', function () {
    app(PayrollRuns::class)->calculate(app(PayrollRuns::class)->create($this->t['company'], CarbonImmutable::create(2026, 11, 1), false, $this->t['officer']));
    app(LeaveService::class)->request($this->outsider, $this->t['sick'], CarbonImmutable::parse('2026-11-18'), CarbonImmutable::parse('2026-11-18'), 'none', null);

    signIn($this->t['officer'])->get(route('dashboard'))->assertInertia(fn ($p) => $p->component('Dashboard')->where('scope', 'company')
        ->where('headcount', 3)->where('payroll.status', 'calculated')->where('payroll.approvals', 0)->has('departments')
        ->where('attendance.counts.not_in', 3));
    signIn($this->t['hr'])->get(route('dashboard'))->assertInertia(fn ($p) => $p->where('requests.count', 1)->where('requests.items.0.type', 'leave'));
});

it('scopes a manager to their team and hides payroll', function () {
    LeaveRequest::create(['company_id' => $this->t['company']->id, 'employee_id' => $this->dev->id, 'leave_type_id' => $this->t['sick']->id, 'start_date' => '2026-11-16', 'end_date' => '2026-11-16', 'days' => 1, 'status' => 'approved']);

    signIn($this->leadUser)->get(route('dashboard'))->assertInertia(fn ($p) => $p->where('scope', 'team')->where('headcount', 1)
        ->where('payroll', null)->where('departments', [])->has('team', 1)->where('attendance.counts.leave', 1));
});

it('sends employees without team access to the phone app', function () {
    $user = memberOf($this->t['company'], Role::Employee, $this->outsider);
    signIn($user)->get(route('dashboard'))->assertRedirect(route('me.today'));
});

it('says a night shift has not started yet instead of calling it missing', function () {
    $night = WorkPattern::create(['company_id' => $this->t['company']->id, 'name' => 'Night', 'start_time' => '22:00', 'end_time' => '06:00', 'crosses_midnight' => true, 'grace_min' => 10, 'working_days' => [1, 2, 3, 4, 5]]);
    $this->dev->contracts()->create(['type' => 'permanent', 'start_date' => '2024-01-08', 'work_pattern_id' => $night->id]);

    signIn($this->leadUser)->get(route('attendance'))->assertInertia(fn ($p) => $p->where('rows.0.state', 'later'));
});
