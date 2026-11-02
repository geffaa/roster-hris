<?php

use App\Enums\Role;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use Illuminate\Support\Carbon;

beforeEach(function () {
    extract($this->t = timeSetup());
    $this->lead = Employee::factory()->for($company)->create(['hire_date' => '2020-01-06']);
    $this->dev = Employee::factory()->for($company)->create(['hire_date' => '2024-01-08', 'manager_id' => $this->lead->id, 'full_name' => '=HYPERLINK("x")']);
    $this->other = Employee::factory()->for($company)->create(['hire_date' => '2024-01-08']);
    $this->leadUser = memberOf($company, Role::Manager, $this->lead);
    $this->hr = memberOf($company, Role::HrAdmin);
    $this->travelTo(Carbon::parse('2026-11-09 10:00', 'Asia/Jakarta'));

    $rec = fn (string $date, string $status, int $late = 0) => AttendanceRecord::create(['company_id' => $company->id, 'employee_id' => $this->dev->id, 'work_date' => $date,
        'clock_in_at' => Carbon::parse("$date 08:30", 'Asia/Jakarta')->addMinutes($late), 'clock_out_at' => $status === 'incomplete' ? null : Carbon::parse("$date 17:30", 'Asia/Jakarta'), 'status' => $status, 'late_min' => $late]);
    $rec('2026-11-02', 'ok');
    $rec('2026-11-03', 'late', 25);
    $rec('2026-11-04', 'incomplete');
    $rec('2026-11-09', 'ok'); // today: not counted yet
    LeaveRequest::create(['company_id' => $company->id, 'employee_id' => $this->dev->id, 'leave_type_id' => $this->t['sick']->id, 'start_date' => '2026-11-05', 'end_date' => '2026-11-05', 'days' => 1, 'status' => 'approved']);
});

afterEach(fn () => Carbon::setTestNow());

it('summarises a month of attendance up to yesterday', function () {
    signIn($this->leadUser)->get(route('reports', ['period' => '2026-11']))
        ->assertInertia(fn ($p) => $p->component('insights/Reports')->where('scope', 'team')->has('attendance', 1)
            ->where('attendance.0.expected', 5)->where('attendance.0.present', 3)->where('attendance.0.late', 1)->where('attendance.0.late_min', 25)
            ->where('attendance.0.incomplete', 1)->where('attendance.0.leave', 1)->where('attendance.0.absent', 1)->where('attendance.0.rate', 75));
});

it('shows the whole company to HR and refuses future months', function () {
    signIn($this->hr)->get(route('reports', ['period' => '2026-11']))->assertInertia(fn ($p) => $p->where('scope', 'company')->has('attendance', 3));
    signIn($this->hr)->get(route('reports', ['period' => '2026-12']))->assertStatus(422);
});

it('lists yearly leave balances with pending days deducted', function () {
    LeaveRequest::create(['company_id' => $this->t['company']->id, 'employee_id' => $this->dev->id, 'leave_type_id' => $this->t['annual']->id, 'start_date' => '2026-11-23', 'end_date' => '2026-11-24', 'days' => 2, 'status' => 'pending']);

    signIn($this->leadUser)->get(route('reports', ['report' => 'leave', 'period' => '2026']))
        ->assertInertia(fn ($p) => $p->where('period', '2026')->has('leave.types', 3)->where('leave.types.0.name', 'Annual leave')->where('leave.types.1.limited', false)
            ->where("leave.rows.0.balances.{$this->t['sick']->id}.used", 1)
            ->where("leave.rows.0.balances.{$this->t['annual']->id}.pending", 2)->where("leave.rows.0.balances.{$this->t['annual']->id}.available", 10));
});

it('exports CSV without letting a cell run as a formula', function () {
    $csv = signIn($this->leadUser)->get(route('reports.export', ['report' => 'attendance', 'period' => '2026-11']))
        ->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8')->streamedContent();

    expect($csv)->toContain('Attendance rate %')->toContain("\"'=HYPERLINK(\"\"x\"\")\"")->not->toContain(',=HYPERLINK');
});

it('keeps reports away from employees without directory access', function () {
    $user = memberOf($this->t['company'], Role::Employee, $this->dev);
    signIn($user)->get(route('reports'))->assertForbidden();
    signIn($user)->get(route('reports.export'))->assertForbidden();
});
