<?php

use App\Enums\Role;
use App\Models\Department;
use App\Models\PayrollItem;
use App\Models\Payslip;
use App\Services\Payroll\PayrollRuns;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->t = payrollSetup();
    $this->travelTo(Carbon::parse('2026-10-31 10:00', 'Asia/Jakarta'));
    $eng = Department::create(['company_id' => $this->t['company']->id, 'name' => 'Engineering']);
    $ops = Department::create(['company_id' => $this->t['company']->id, 'name' => 'Operations']);
    hire($this->t['company'], 10_000_000)->update(['department_id' => $eng->id]);
    hire($this->t['company'], 16_000_000, 'K/1')->update(['department_id' => $eng->id]);
    hire($this->t['company'], 6_000_000, 'K/3')->update(['department_id' => $ops->id]);

    $runs = app(PayrollRuns::class);
    $this->run = $runs->calculate($runs->create($this->t['company'], CarbonImmutable::create(2026, 10, 1), false, $this->t['officer']));
    $runs->approve($this->run->refresh(), $this->t['officer']);
    $runs->approve($this->run->refresh(), $this->t['hr']);
    $runs->lock($this->run->refresh(), $this->t['officer']);
    $runs->publish($this->run->refresh());
});

afterEach(fn () => Carbon::setTestNow());

it('adds up to exactly what the payslips say', function () {
    $slips = Payslip::with('item')->get();
    $sum = fn (string $col) => round($slips->sum(fn ($p) => (float) $p->item->{$col}), 2);

    signIn($this->t['officer'])->get(route('reports', ['report' => 'payroll']))
        ->assertInertia(fn ($p) => $p->where('period', '2026-10')->has('payroll.rows', 2)->where('payroll.rows.0.department', 'Engineering')->where('payroll.rows.0.headcount', 2)
            ->where('payroll.total.net', fn ($v) => $v == $sum('net'))->where('payroll.total.gross', fn ($v) => $v == $sum('gross'))
            ->where('payroll.total.tax', fn ($v) => $v == $sum('tax'))->where('payroll.total.employer_contributions', fn ($v) => $v == $sum('employer_contributions')));
});

it('lists what to pay each contribution programme, matching the breakdown lines', function () {
    $items = PayrollItem::all();
    $line = fn (string $code) => $items->sum(fn ($i) => abs(collect($i->breakdown)->firstWhere('code', $code)['amount'] ?? 0));

    signIn($this->t['officer'])->get(route('reports', ['report' => 'contributions', 'period' => '2026-10']))
        ->assertInertia(function ($p) use ($line, $items) {
            $p->where('contributions.rows.0.code', 'jht')->where('contributions.rows.0.employee', fn ($v) => $v == $line('jht_ee'))->where('contributions.rows.0.employer', fn ($v) => $v == $line('jht_er'));
            $p->where('contributions.rows.1.code', 'jp')->where('contributions.rows.1.people', 3);
            $ee = collect(['jht', 'jp', 'bpjs_kes'])->sum(fn ($c) => $line("{$c}_ee"));
            expect((float) $ee)->toEqual(round($items->sum(fn ($i) => (float) $i->employee_contributions), 2));
        });
});

it('exports Excel and CSV, and keeps payroll reports from people without payroll rights', function () {
    signIn($this->t['officer'])->get(route('reports.export', ['report' => 'payroll', 'period' => '2026-10', 'format' => 'xlsx']))
        ->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    $csv = signIn($this->t['officer'])->get(route('reports.export', ['report' => 'contributions', 'period' => '2026-10']))->streamedContent();
    expect($csv)->toContain('"BPJS JHT (employee)"');

    $manager = memberOf($this->t['company'], Role::Manager);
    signIn($manager)->get(route('reports', ['report' => 'payroll']))->assertForbidden();
    signIn($manager)->get(route('reports.export', ['report' => 'contributions']))->assertForbidden();
    signIn($this->t['officer'])->get(route('reports', ['report' => 'payroll', 'period' => '2026-09']))->assertNotFound();
});
