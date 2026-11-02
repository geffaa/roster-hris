<?php

use App\Enums\Role;
use App\Models\Company;
use App\Models\EmployeeComponent;
use App\Models\LeaveRequest;
use App\Models\OvertimeRequest;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\Payslip;
use App\Models\SalaryComponent;
use App\Services\Payroll\PayrollRuns;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use OwenIt\Auditing\Models\Audit;

beforeEach(function () {
    $this->t = payrollSetup();
    $this->runs = app(PayrollRuns::class);
    $this->travelTo(Carbon::parse('2026-10-31 10:00', 'Asia/Jakarta'));
});

afterEach(fn () => Carbon::setTestNow());

/** Create, calculate, approve twice and lock a month. */
function closeMonth(int $month, int $year = 2026): PayrollRun
{
    $t = test()->t;
    $run = test()->runs->create($t['company'], CarbonImmutable::create($year, $month, 1), false, $t['officer']);
    test()->runs->calculate($run, $t['officer']);
    foreach ($run->items()->get() as $item) {
        if ($item->needsReview()) {
            test()->runs->review($item, $t['officer']);
        }
    }
    test()->runs->approve($run->refresh(), $t['officer']);
    test()->runs->approve($run->refresh(), $t['hr']);
    test()->runs->lock($run->refresh(), $t['officer']);

    return $run->refresh();
}

it('calculates a month from salaries, unpaid leave and approved overtime', function () {
    $plain = hire($this->t['company'], 10_000_000);
    $busy = hire($this->t['company'], 8_000_000);
    OvertimeRequest::create(['company_id' => $this->t['company']->id, 'employee_id' => $busy->id, 'work_date' => '2026-10-14', 'start_time' => '17:30', 'end_time' => '20:30', 'minutes' => 180, 'reason' => 'Deadline', 'status' => 'approved']);
    OvertimeRequest::create(['company_id' => $this->t['company']->id, 'employee_id' => $busy->id, 'work_date' => '2026-10-17', 'start_time' => '08:00', 'end_time' => '18:00', 'minutes' => 600, 'reason' => 'Expo', 'status' => 'approved']);
    OvertimeRequest::create(['company_id' => $this->t['company']->id, 'employee_id' => $busy->id, 'work_date' => '2026-10-20', 'start_time' => '17:30', 'end_time' => '19:30', 'minutes' => 120, 'reason' => 'Not approved yet', 'status' => 'pending']);
    $short = hire($this->t['company'], 6_250_000, 'TK/1');
    LeaveRequest::create(['company_id' => $this->t['company']->id, 'employee_id' => $short->id, 'leave_type_id' => $this->t['unpaid']->id, 'start_date' => '2026-10-20', 'end_date' => '2026-10-21', 'days' => 2, 'status' => 'approved']);

    $run = $this->runs->calculate($this->runs->create($this->t['company'], CarbonImmutable::create(2026, 10, 1), false, $this->t['officer']));
    $item = fn ($e) => PayrollItem::where('employee_id', $e->id)->sole();

    // Same figures as golden cases 1, 7 and 5.
    expect($run->status)->toBe('calculated')->and($run->rulePackVersion->version)->toBe('ID-2026.2')
        ->and((float) $item($plain)->net)->toBe(9_338_650.0)->and((float) $item($plain)->tax)->toBe(261_350.0)
        ->and((float) $item($busy)->net)->toBe(8_804_297.0)
        ->and((float) $item($short)->net)->toBe(5_387_077.0)->and($item($short)->context['unpaid_days'])->toBe(2);
});

it('uses the rule pack version in force for each month', function () {
    $e = hire($this->t['company'], 12_000_000);
    $feb = closeMonth(2);
    $mar = $this->runs->calculate($this->runs->create($this->t['company'], CarbonImmutable::create(2026, 3, 1), false, $this->t['officer']));
    $jp = fn (PayrollRun $r) => collect(PayrollItem::where('payroll_run_id', $r->id)->where('employee_id', $e->id)->sole()->breakdown)->firstWhere('code', 'jp_ee')['amount'];

    expect($feb->rulePackVersion->version)->toBe('ID-2026.1')->and($jp($feb))->toBe(-105_474)
        ->and($mar->rulePackVersion->version)->toBe('ID-2026.2')->and($jp($mar))->toBe(-110_863);
});

it('needs two different people to approve before the run can be locked', function () {
    hire($this->t['company'], 10_000_000);
    $run = $this->runs->calculate($this->runs->create($this->t['company'], CarbonImmutable::create(2026, 10, 1), false, $this->t['officer']));

    signIn($this->t['officer'])->post(route('payroll.lock', $run))->assertSessionHasErrors('run');
    signIn($this->t['officer'])->post(route('payroll.approve', $run))->assertSessionHasNoErrors();
    signIn($this->t['officer'])->post(route('payroll.approve', $run))->assertSessionHasErrors(['run' => 'You already approved. A second, different person must approve.']);
    signIn($this->t['officer'])->post(route('payroll.lock', $run))->assertSessionHasErrors('run');
    signIn($this->t['hr'])->post(route('payroll.approve', $run))->assertSessionHasNoErrors();
    signIn($this->t['officer'])->post(route('payroll.lock', $run))->assertSessionHasNoErrors();

    expect($run->refresh()->status)->toBe('locked')->and($run->approved_by_1)->not->toBe($run->approved_by_2);
});

it('makes reviewers tick large changes and blocks negative net pay', function () {
    $e = hire($this->t['company'], 10_000_000);
    closeMonth(9);
    $bonus = SalaryComponent::create(['company_id' => $this->t['company']->id, 'code' => 'bonus', 'name' => 'Bonus', 'fixed' => false, 'recurring' => false]);
    EmployeeComponent::create(['company_id' => $this->t['company']->id, 'employee_id' => $e->id, 'salary_component_id' => $bonus->id, 'amount' => 5_000_000, 'effective_from' => '2026-10-20', 'note' => 'Q3 bonus']);
    $run = $this->runs->calculate($this->runs->create($this->t['company'], CarbonImmutable::create(2026, 10, 1), false, $this->t['officer']));
    $item = PayrollItem::where('payroll_run_id', $run->id)->sole();

    expect($item->flags[0]['level'])->toBe('check')->and($item->flags[0]['message'])->toContain('higher than September (Bonus · Q3 bonus)');
    signIn($this->t['hr'])->post(route('payroll.approve', $run))->assertSessionHasErrors(['run' => 'Review the 1 flagged person before approving.']);
    signIn($this->t['officer'])->post(route('payroll.review', [$run, $item]))->assertSessionHasNoErrors();
    signIn($this->t['hr'])->post(route('payroll.approve', $run))->assertSessionHasNoErrors();

    $advance = SalaryComponent::create(['company_id' => $this->t['company']->id, 'code' => 'advance', 'name' => 'Advance', 'kind' => 'deduction', 'taxable' => false, 'fixed' => false, 'recurring' => false]);
    EmployeeComponent::create(['company_id' => $this->t['company']->id, 'employee_id' => $e->id, 'salary_component_id' => $advance->id, 'amount' => 20_000_000, 'effective_from' => '2026-10-25']);
    $this->runs->calculate($run->refresh());
    expect($run->refresh()->approvals())->toBe(0); // recalculating clears approvals
    signIn($this->t['hr'])->post(route('payroll.approve', $run))->assertSessionHasErrors(['run' => 'Fix the errors first: someone has negative net pay.']);
});

it('keeps locked numbers when salaries or rules change later', function () {
    $e = hire($this->t['company'], 10_000_000);
    $run = closeMonth(10);
    $e->compensations()->create(['effective_from' => '2026-10-01', 'base_salary' => 15_000_000, 'currency' => 'IDR', 'tax_status' => 'TK/0', 'reason' => 'Retro raise']);
    $v2 = $this->t['v2'];
    $config = $v2->config;
    $config['contributions'][0]['employee'] = 3;
    $v2->forceFill(['config' => $config])->save();

    signIn($this->t['officer'])->post(route('payroll.calculate', $run))->assertStatus(422);
    expect((float) PayrollItem::where('payroll_run_id', $run->id)->sole()->net)->toBe(9_338_650.0);
});

it('recalculates the year in December and withholds the difference', function () {
    hire($this->t['company'], 10_000_000);
    foreach (range(1, 11) as $m) {
        closeMonth($m);
    }
    $dec = $this->runs->calculate($this->runs->create($this->t['company'], CarbonImmutable::create(2026, 12, 1), false, $this->t['officer']));
    $item = PayrollItem::where('payroll_run_id', $dec->id)->sole();

    // Golden case 10: PKP 61,848,000 → 3,277,200 for the year, 2,874,850 already withheld.
    expect($item->tax_detail['method'])->toBe('true_up')->and($item->tax_detail['withheld'])->toBe(2_874_850)
        ->and((float) $item->tax)->toBe(402_350.0)->and((float) $item->net)->toBe(9_197_650.0);
});

it('treats the leaving month as the final month', function () {
    $e = hire($this->t['company'], 8_000_000);
    foreach (range(1, 5) as $m) {
        closeMonth($m);
    }
    $e->update(['end_date' => '2026-06-15']);
    $jun = $this->runs->calculate($this->runs->create($this->t['company'], CarbonImmutable::create(2026, 6, 1), false, $this->t['officer']));
    $item = PayrollItem::where('payroll_run_id', $jun->id)->sole();

    // Golden case 12: neto below PTKP → refund everything withheld January–May.
    expect($item->tax_detail['method'])->toBe('true_up')->and((float) $item->tax)->toBe(-627_240.0)->and((float) $item->net)->toBe(4_467_240.0);
});

it('runs months in order, one at a time', function () {
    hire($this->t['company'], 10_000_000);
    $this->runs->create($this->t['company'], CarbonImmutable::create(2026, 10, 1), false, $this->t['officer']);

    signIn($this->t['officer'])->post(route('payroll.store'), ['period' => '2026-11-01'])->assertSessionHasErrors(['period' => 'Finish RUN-2026-10 first: year-to-date tax depends on earlier months being locked.']);
    signIn($this->t['officer'])->post(route('payroll.store'), ['period' => '2026-10-01'])->assertSessionHasErrors('period');
});

it('publishes payslips that only their owner can open', function () {
    $mine = hire($this->t['company'], 10_000_000);
    $other = hire($this->t['company'], 9_000_000);
    $run = closeMonth(10);
    signIn($this->t['officer'])->post(route('payroll.publish', $run))->assertSessionHasNoErrors();
    $me = memberOf($this->t['company'], Role::Employee, $mine);
    $slip = Payslip::where('employee_id', $mine->id)->sole();
    $theirs = Payslip::where('employee_id', $other->id)->sole();

    signIn($me)->get(route('me.pay'))->assertInertia(fn ($p) => $p->component('me/Pay')->has('slips', 1)->where('slips.0.net', 9_338_650));
    signIn($me)->get(route('me.payslip', $slip))->assertInertia(fn ($p) => $p->where('slip.net', 9_338_650)->where('slip.version', 'ID-2026.2'));
    expect($slip->refresh()->first_viewed_at)->not->toBeNull();
    signIn($me)->get(route('me.payslip.pdf', $slip))->assertOk()->assertHeader('content-type', 'application/pdf');
    signIn($me)->get(route('me.payslip', $theirs))->assertNotFound();
    signIn($me)->get(route('me.payslip.pdf', $theirs))->assertNotFound();
});

it('exports the bank file only for locked runs, skipping people without an account, and logs it', function () {
    hire($this->t['company'], 10_000_000, bank: '5270099887');
    hire($this->t['company'], 5_000_000, bank: null);
    $this->runs->calculate($run = $this->runs->create($this->t['company'], CarbonImmutable::create(2026, 10, 1), false, $this->t['officer']));
    signIn($this->t['officer'])->get(route('payroll.bank', $run))->assertStatus(422);

    $this->runs->approve($run->refresh(), $this->t['officer']);
    $this->runs->approve($run->refresh(), $this->t['hr']);
    $this->runs->lock($run->refresh(), $this->t['officer']);
    $csv = signIn($this->t['officer'])->get(route('payroll.bank', $run))->assertOk()->streamedContent();

    expect(substr_count(trim($csv), "\n"))->toBe(1)->and($csv)->toContain('5270099887,9338650.00,IDR,"Salary Oct 2026"')
        ->and(Audit::where('event', 'bank_file_exported')->count())->toBe(1);
});

it('keeps payroll away from people without payroll rights and from other companies', function () {
    hire($this->t['company'], 10_000_000);
    $run = $this->runs->create($this->t['company'], CarbonImmutable::create(2026, 10, 1), false, $this->t['officer']);
    $manager = memberOf($this->t['company'], Role::Manager);
    $auditor = memberOf($this->t['company'], Role::Auditor);

    signIn($manager)->get(route('payroll.index'))->assertForbidden();
    signIn($auditor)->get(route('payroll.show', $run))->assertOk();
    signIn($auditor)->post(route('payroll.calculate', $run))->assertForbidden();
    signIn($this->t['hr'])->post(route('payroll.calculate', $run))->assertForbidden();

    $elsewhere = Company::factory()->create();
    $outsider = memberOf($elsewhere, Role::PayrollOfficer);
    signIn($outsider)->get(route('payroll.show', $run))->assertNotFound();
});
