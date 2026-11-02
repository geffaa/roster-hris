<?php

use App\Enums\Role;
use App\Models\EmployeeComponent;
use App\Models\PolicyDocument;
use App\Models\SalaryComponent;
use App\Services\Payroll\PayrollRuns;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->t = payrollSetup();
    config(['services.anthropic.key' => null]);
    $this->travelTo(Carbon::parse('2026-10-31 10:00', 'Asia/Jakarta'));
});

afterEach(fn () => Carbon::setTestNow());

it('briefs the payroll officer on changes and flags from the run figures', function () {
    $e = hire($this->t['company'], 10_000_000);
    $runs = app(PayrollRuns::class);
    $sep = $runs->calculate($runs->create($this->t['company'], CarbonImmutable::create(2026, 9, 1), false, $this->t['officer']));
    $runs->approve($sep->refresh(), $this->t['officer']);
    $runs->approve($sep->refresh(), $this->t['hr']);
    $runs->lock($sep->refresh(), $this->t['officer']);
    $bonus = SalaryComponent::create(['company_id' => $this->t['company']->id, 'code' => 'bonus', 'name' => 'Bonus', 'fixed' => false, 'recurring' => false]);
    EmployeeComponent::create(['company_id' => $this->t['company']->id, 'employee_id' => $e->id, 'salary_component_id' => $bonus->id, 'amount' => 5_000_000, 'effective_from' => '2026-10-20', 'note' => 'Q3']);
    $oct = $runs->calculate($runs->create($this->t['company'], CarbonImmutable::create(2026, 10, 1), false, $this->t['officer']));

    $json = signIn($this->t['officer'])->postJson(route('payroll.briefing', $oct))->assertOk()->json();
    expect($json['mode'])->toBe('rules')->and(implode(' ', $json['points']))->toContain($e->full_name)->toContain('Bonus · Q3');

    signIn(memberOf($this->t['company'], Role::Manager))->postJson(route('payroll.briefing', $oct))->assertForbidden();
});

it('lets HR add a policy from text or a file and indexes it into passages', function () {
    signIn($this->t['hr'])->post(route('settings.policies.store'), ['title' => 'Travel', 'body' => "# Travel\n\nBook flights through the office.\n\n## Per diem\n\nRp300.000 a day."])->assertSessionHasNoErrors();
    signIn($this->t['hr'])->post(route('settings.policies.store'), ['title' => 'Dress code', 'file' => UploadedFile::fake()->createWithContent('dress.md', "# Dress\n\nSmart casual.")])->assertSessionHasNoErrors();

    $travel = PolicyDocument::where('title', 'Travel')->sole();
    expect($travel->chunks()->count())->toBe(2)->and($travel->chunks()->pluck('heading')->all())->toBe(['Travel', 'Per diem']);
    signIn(memberOf($this->t['company'], Role::Employee))->get(route('settings.policies'))->assertForbidden();
});
